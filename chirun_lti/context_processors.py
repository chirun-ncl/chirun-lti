from . import version
from django.conf import settings

def globals(request):
    return {
        'CHIRUN_LTI_VERSION': version,
        'ALLOW_DYNAMIC_REGISTRATION': getattr(settings, 'ALLOW_DYNAMIC_REGISTRATION', False),
        'HELP_URL': settings.HELP_URL,
    }
