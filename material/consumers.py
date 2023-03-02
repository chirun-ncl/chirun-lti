from asgiref.sync import async_to_sync
import base64
from channels.generic.websocket import AsyncJsonWebsocketConsumer
from chirun_lti.cache import get_cache
import json
from material.models import Compilation

class CompilationConsumer(AsyncJsonWebsocketConsumer):
    async def connect(self):
        self.build_pk = self.scope["url_route"]["kwargs"]["build_pk"]
        self.group_name = f"build_{self.build_pk}"

        await self.channel_layer.group_add(
            self.group_name, self.channel_name
        )

        await self.accept()

        cache = get_cache()
        compilation = await Compilation.objects.aget(pk=self.build_pk)
        cached_stdout = await cache.get(compilation.get_cache_key())
        if cached_stdout is not None:
            await self.send_json(content={'type': 'cached_stdout', 'cached_stdout': cached_stdout.decode('utf-8')})

    async def disconnect(self, close_code):
        await self.channel_layer.group_discard(
            self.group_name, self.channel_name
        )

    async def stdout_bytes(self, event):
        stdout_bytes = event["bytes"]

        encoded = base64.b64encode(stdout_bytes).decode('ascii')

        # Send message to WebSocket
        await self.send_json(content={'type': 'stdout_bytes', 'bytes': encoded, 'count': event['count']})

    async def stderr_bytes(self, event):
        stderr_bytes = event["bytes"]

        encoded = base64.b64encode(stderr_bytes).decode('ascii')

        # Send message to WebSocket
        await self.send_json(content={'type': 'stderr_bytes', 'bytes': encoded, 'count': event['count']})

    async def finished(self, event):
        await self.send_json(content = {
            'type': 'finished',
            'status': event['status'],
            'end_time': event['end_time'],
            'time_taken': event['time_taken'],
        })
