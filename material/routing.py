from django.urls import path

from . import consumers

websocket_urlpatterns = [
    path(r"ws/material/package/<uuid:pk>/build/<int:build_pk>/progress", consumers.CompilationConsumer.as_asgi()),
    path(r"ws/material/deep-link-websocket", consumers.DeepLinkConsumer.as_asgi()),
]
