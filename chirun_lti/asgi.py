"""
ASGI config for chirun_lti project.

It exposes the ASGI callable as a module-level variable named ``application``.

For more information on this file, see
https://docs.djangoproject.com/en/4.1/howto/deployment/asgi/
"""

from   channels.auth import AuthMiddlewareStack
from   channels.routing import ProtocolTypeRouter, URLRouter
from   channels.security.websocket import AllowedHostsOriginValidator
from   django.core.asgi import get_asgi_application
import os

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'chirun_lti.settings')

# Initialize Django ASGI application early to ensure the AppRegistry
# is populated before importing code that may import ORM models.
django_asgi_app = get_asgi_application()

import material.routing

application = ProtocolTypeRouter({
    "http": django_asgi_app,
    "websocket": AllowedHostsOriginValidator(
        AuthMiddlewareStack(URLRouter(material.routing.websocket_urlpatterns))
    ),
})
