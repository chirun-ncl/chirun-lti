from django.core.management.base import BaseCommand
from material.models import ChirunPackage

class Command(BaseCommand):
    help = 'Show all materials'

    def handle(self, *args, **options):
        for p in ChirunPackage.objects.all():
            print(f'{p.title} - http://localhost:9002{p.get_absolute_url()}')
