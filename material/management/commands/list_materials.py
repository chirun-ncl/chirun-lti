from django.core.management.base import BaseCommand
from material.models import ChirunPackage

from datetime import datetime

class Command(BaseCommand):
    help = 'Show all materials'

    def handle(self, *args, **options):
        packages = list(ChirunPackage.objects.all())
        for p in packages:
            p.time = datetime.fromtimestamp(p.absolute_extracted_path.stat().st_mtime)

        for p in sorted(packages, key=lambda p:p.time):
            print(f'{p.time} {p.title} - http://localhost:9002{p.get_absolute_url()}')
