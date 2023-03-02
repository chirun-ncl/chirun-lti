from django.apps import AppConfig
from django.db.models.signals import post_delete


class MaterialConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'material'

    def ready(self):
        from . import models
        from . import signals
        post_delete.connect(signals.delete_package_files, sender=models.ChirunPackage)
