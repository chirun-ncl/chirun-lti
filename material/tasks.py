from   .models import Compilation
from   asgiref.sync import async_to_sync, sync_to_async
import asyncio
from   channels.layers import get_channel_layer
from   chirun_lti.cache import get_cache
from   datetime import datetime, timedelta
from   django.conf import settings
from   django.utils.timezone import now
import functools
from   huey.contrib.djhuey import task
import shutil
import subprocess
import tempfile

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

@async_task()
async def build_package(compilation):
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

        async def read(pipe, pipe_name):
            out = b''
            part = b''
            count = 0
            while True:
                t = datetime.now()
                buf = b''
                while datetime.now() - t < timedelta(seconds=0.2):
                    bit = await pipe.read(100)
                    if not bit:
                        break
                    buf += bit
                if not buf:
                    break

                part += buf
                if b'\n' in buf:
                    out += part
                    await cache.set(cache_key+'_pipe_name', out)

                    count += 1

                    await channel_layer.group_send(
                        channel_group_name, {"type": f'{pipe_name}_bytes', "bytes": part, "count": count,}
                    )

                    part = b''

            return out

        process = await asyncio.create_subprocess_exec(
            *cmd,
            cwd = working_directory,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )

        stdout_bytes, stderr_bytes = await asyncio.gather(
            read(process.stdout, 'stdout'),
            read(process.stderr, 'stderr')
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

        if final_output_path.exists():
            shutil.rmtree(final_output_path)

        shutil.copytree(output_path, final_output_path, dirs_exist_ok=True)

        final_output_path.chmod(0o755)

        await process.communicate()

    stdout = stdout_bytes.decode('utf-8')
    stderr = stderr_bytes.decode('utf-8')

    await cache.delete(stdout_cache_key)
    await cache.delete(stderr_cache_key)

    compilation.output = stdout+'\n\n'+stderr
    if process.returncode == 0:
        compilation.status = 'built'
    else:
        compilation.status = 'error'

    compilation.end_time = now()

    await compilation.send_status_change()

    print(f"Finished building {package}: {compilation}")
    await sync_to_async(compilation.save)()

@task()
def delete_package_files(package):
    shutil.rmtree(package.absolute_extracted_path)
    shutil.rmtree(package.absolute_output_path)
