from asgiref.sync import async_to_sync
import base64
from channels.generic.websocket import AsyncJsonWebsocketConsumer
from chirun_lti.cache import get_cache
import json
from material.models import Compilation, ChirunPackage

class CompilationConsumer(AsyncJsonWebsocketConsumer):
    async def connect(self):
        self.build_pk = self.scope["url_route"]["kwargs"]["build_pk"]
        self.compilation = await Compilation.objects.aget(pk=self.build_pk)
        self.group_name = self.compilation.get_channel_group_name()

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

    async def status_change(self, event):
        await self.send_json(content = {
            'type': 'status_change',
            'status': event['status'],
            'start_time': event['start_time'],
            'end_time': event.get('end_time'),
            'time_taken': event.get('time_taken'),
        })

class DeepLinkConsumer(AsyncJsonWebsocketConsumer):
    async def connect(self):
        print(self.channel_name)
        await self.channel_layer.group_add('hey',self.channel_name)
        await self.accept()

    async def subscribe_to_packages(self, content):
        packages = ChirunPackage.objects.filter(uid__in=content.get('packages', []))

        async for package in packages:
            group_name = package.get_channel_group_name()
            print("Joining",group_name)
            await self.channel_layer.group_add(
                group_name, self.channel_name
            )

    async def receive_json(self, content):
        print("Received", content)

        message_type = content.get('type')

        if message_type == 'subscribe-to-packages':
            return await self.subscribe_to_packages(content)

        await self.send_json({'type': 'error', 'message': f'Unknown message type {message_type}'})

    async def build_status(self, event):
        print("Build status", event)
        await self.send_json(content = {
            'type': 'build_status',
            'message': event['message'],
            'package': event['package'],
        })
