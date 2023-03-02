from   .models import Compilation
from   asgiref.sync import async_to_sync
import asyncio
from   channels.layers import get_channel_layer
from   chirun.cli import Chirun, arg_parser
from   chirun_lti.cache import get_cache
from   django.utils.timezone import now
from   huey.contrib.djhuey import task
import shutil
import subprocess

@task()
def build_package(compilation):
    """
        Build a package, and record the results in the given Compilation object.
    """
    package = compilation.package

    print(f"Task to build {package}")

    async def do_build():

        cache = get_cache()

        channel_layer = get_channel_layer()

        cmd = ['chirun', '-vv', '-o', package.absolute_output_path]

        cache_key = compilation.get_cache_key()
        stdout_cache_key = cache_key + '_stdout'
        stderr_cache_key = cache_key + '_stderr'
        channel_group_name = compilation.get_channel_group_name()

        async def read(pipe, pipe_name):
            out = b''
            part = b''
            count = 0
            while True:
                buf = await pipe.read(10)
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
            cwd = package.absolute_extracted_path,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )

        stdout_bytes, stderr_bytes = await asyncio.gather(
            read(process.stdout, 'stdout'),
            read(process.stderr, 'stderr')
        )

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

        time_taken = compilation.end_time - compilation.start_time

        await channel_layer.group_send(
                channel_group_name, {"type": "finished", "status": compilation.status, 'end_time': compilation.end_time.isoformat(), 'time_taken': time_taken.total_seconds()}
        )

    async_to_sync(do_build)()


    print(f"Finished building {package}: {compilation}")
    compilation.save()

@task()
def delete_package_files(package):
    shutil.rmtree(package.absolute_extracted_path)
    shutil.rmtree(package.absolute_output_path)
