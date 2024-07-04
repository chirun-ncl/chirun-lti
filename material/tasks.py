from   .models import Compilation, GitException
from   asgiref.sync import async_to_sync, sync_to_async
import asyncio
from   channels.layers import get_channel_layer
from   chirun_lti.cache import get_cache
from   datetime import datetime, timedelta
from   django.conf import settings
from   django.utils.timezone import now
import functools
from   huey import crontab
from   huey.contrib.djhuey import task, db_task, db_periodic_task
import os
import shutil
import subprocess
import tempfile

COMPILATION_TIMEOUT = getattr(settings, 'COMPILATION_TIMEOUT', 60 * 5)

def async_task(*args, **kwargs):
    """
        Decorator for async tasks.
        Applies huey.task, and then decorates an async function.
    """

    def decorator(fn):
        @task(*args,**kwargs)
        @functools.wraps(fn)
        def wrapper(*args, **kwargs):
            try:
                loop = asyncio.get_event_loop()
            except RuntimeError:
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)

            loop.run_until_complete(fn(*args,**kwargs))

        return wrapper

    return decorator

def async_db_task(*args, **kwargs):
    """
        Decorator for async tasks which use the database.
        Applies huey.db_task, and then decorates an async function.
    """

    def decorator(fn):
        @db_task(*args,**kwargs)
        @functools.wraps(fn)
        def wrapper(*args, **kwargs):
            try:
                loop = asyncio.get_event_loop()
            except RuntimeError:
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)

            loop.run_until_complete(fn(*args,**kwargs))

        return wrapper

    return decorator

@async_db_task()
async def build_package(compilation):
    try:
        await do_build_package(compilation)

    except Exception as e:
        compilation.status = 'error'
        print(f"Error building {compilation.package}: {e}")

    finally:
        compilation.end_time = now()

        await compilation.send_status_change()

        print(f"Finished building {compilation.package}: {compilation}")

        await sync_to_async(compilation.save)()

class BuildException(Exception):
    pass

async def do_build_package(compilation):
    """
        Build a package, and record the results in the given Compilation object.

        Plan:
        * Create temporary directories to copy the source files to, and to store the output in.
        * Run chirun, either as a local command or through Docker, on the source directory.
        * Feed STDERR and STDOUT from the command to corresponding channel groups, to be passed through to websockets, as well as saving the whole output in the cache so in-progress logs can be restored on page reload.
        * Wait until the build has finished.
        * Copy the output to the package's permanent output directory.
        * Save the STDERR and STDOUT logs to the Compilation object.
    """
    package = compilation.package

    print(f"Task to build {package}")

    with tempfile.TemporaryDirectory() as source_path, tempfile.TemporaryDirectory() as output_path:
        cache = get_cache()
        await compilation.send_status_change()

        channel_layer = get_channel_layer()

        shutil.copytree(package.absolute_extracted_path, source_path, dirs_exist_ok=True)

        use_docker = hasattr(settings,'CHIRUN_DOCKER_IMAGE')

        chirun_output_path = '/opt/chirun-output' if use_docker else output_path
        final_output_path = package.absolute_output_path
        working_directory = source_path

        cmd = [ 
            'chirun',
            '-vv',
            '--hash-salt',
            str(package.edit_uid),
            '-o',
            chirun_output_path,
        ]

        if use_docker:
            cmd = [
                'docker',
                'run',
                '--rm',
                '-v',
                source_path + ':/opt/chirun-source',
                '-v',
                output_path + ':/opt/chirun-output',
                '-w',
                '/opt/chirun-source',
                settings.CHIRUN_DOCKER_IMAGE,
            ] + cmd

        cache_key = compilation.get_cache_key()
        stdout_cache_key = cache_key + '_stdout'
        stderr_cache_key = cache_key + '_stderr'
        channel_group_name = compilation.get_channel_group_name()

        async def read(pipe, pipe_name, log_name):
            with open(log_name, 'wb') as log_file:
                out = b''
                part = b''
                count = 0
                while True:
                    t = datetime.now()
                    buf = b''
                    while datetime.now() - t < timedelta(seconds=0.2):
                        bit = await pipe.readline()
                        if not bit:
                            break
                        buf += bit
                    log_file.write(buf)
                    if not buf:
                        break

                    part += buf
                    if b'\n' in part:
                        out += part
                        await cache.set(cache_key+'_pipe_name', out)

                        count += 1

                        await channel_layer.group_send(
                            channel_group_name, {"type": f'{pipe_name}_bytes', "bytes": part, "count": count,}
                        )

                        part = b''

                return out

        env = os.environ.copy()
        env.update({'PYTHONUNBUFFERED': '1'})
        process = await asyncio.create_subprocess_exec(
            *cmd,
            cwd = working_directory,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
            env = env
        )

        log_dir = compilation.get_build_log_path()

        async def run_command():
            stdout_bytes, stderr_bytes = await asyncio.gather(
                read(process.stdout, 'stdout', log_name=log_dir / 'stdout.txt'),
                read(process.stderr, 'stderr', log_name=log_dir / 'stderr.txt')
            )

            if use_docker:
                subprocess.run([
                    'docker',
                    'run',
                    '--rm',
                    '-v',
                    output_path+':/opt/chirun-output',
                    '-v',
                    '/etc/passwd:/etc/passwd:ro',
                    'coursebuilder/chirun-docker:dev',
                    'chown',
                    '-R',
                    final_output_path.parent.owner() + ':' + final_output_path.parent.group(),
                    '/opt/chirun-output'
                ])

            await process.communicate()

            return stdout_bytes, stderr_bytes

        try:
            stdout_bytes, stderr_bytes = await asyncio.wait_for(run_command(), timeout=COMPILATION_TIMEOUT)
            stdout = stdout_bytes.decode('utf-8')
            stderr = stderr_bytes.decode('utf-8')

        except asyncio.TimeoutError:
            process.kill()
            stderr = "The build process took too long and was stopped."
            await channel_layer.group_send(
                channel_group_name, {"type": f'stdout_bytes', "bytes": stderr.encode('utf-8'), "count": len(stderr),}
            )
            with open(log_dir / 'stdout.txt', 'a') as f:
                f.write('\n'+stderr)

        if process.returncode == 0:
            if final_output_path.exists():
                shutil.rmtree(final_output_path)

            shutil.copytree(output_path, final_output_path, dirs_exist_ok=True)

            final_output_path.chmod(0o755)

    await cache.delete(stdout_cache_key)
    await cache.delete(stderr_cache_key)

    if process.returncode != 0:
        raise BuildException("There was an error during the build process.")

    compilation.status = 'built'
    try:
        package.name = package.manifest.get('title', package.name)
        package.author = package.manifest.get('author', package.author)
        await sync_to_async(package.save)(update_fields=['name','author'])
    except Exception as e:

        print(" OH NO ")

        with open(compilation.get_build_log_path() / 'stderr.txt', 'a') as f:
            f.write('\n')
            f.write("There was en error completing the build: ")
            f.write(str(e))
        raise e

@task()
def delete_package_files(package):
    shutil.rmtree(package.absolute_extracted_path)
    shutil.rmtree(package.absolute_output_path)

@db_task()
def clone_from_git(package, ref=None):
    print(f"Clone from git package {package}: {package.git_remote_url}")
    shutil.rmtree(package.absolute_extracted_path)

    try:
        package.run_git_command(['git', 'clone', package.git_remote_url, package.absolute_extracted_path], save_interaction=True)
        if ref:
            package.run_git_command(['git', 'checkout', ref], save_interaction=True)

        package.git_status = 'ready'

        package.build()

    except GitException:
        package.git_status = 'error'

    finally:
        package.save(update_fields=('git_status',))


@db_task()
def update_from_git(package, ref=None):
    print(f"Update from git package {package}")
    if not (package.absolute_extracted_path / '.git').exists():
        return clone_from_git(package, ref)

    try:
        package.run_git_command(['git', 'reset', '--hard'], save_interaction=True)
        package.run_git_command(['git', 'fetch'], save_interaction=True)

        if ref:
            package.run_git_command(['git', 'checkout', ref], save_interaction=True)

        package.run_git_command(['git', 'pull'], save_interaction=True)

        package.git_status = 'ready'

        package.build()

    except GitException:
        package.git_status = 'error'

    finally:
        package.save(update_fields=('git_status',))

@db_periodic_task(crontab(minute='*'),priority=0)
def find_failed_compilations():
    t = now() - timedelta(seconds = COMPILATION_TIMEOUT * 2)
    for c in Compilation.objects.filter(start_time__lt=t, status__in=('building', 'not_built')):
        c.status = 'error'
        c.end_time = t
        c.save(update_fields=('status',))
