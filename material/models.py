from   channels.layers import get_channel_layer
from   django.conf import settings
from   django.db import models
from   django.urls import reverse
import functools
import json
from   lti.models import Context
from   pathlib import Path, PurePath
from   pylti1p3.contrib.django.lti1p3_tool_config.models import LtiTool
import uuid
import yaml

class ChirunPackage(models.Model):
    name = models.CharField(max_length=500)
    uid = models.UUIDField(default = uuid.uuid4, primary_key = True)
    edit_uid = models.UUIDField(default = uuid.uuid4, unique = True)

    created = models.DateTimeField(auto_now_add = True)

    lti_tool = models.ForeignKey(LtiTool, null=True, blank=True, related_name='chirun_packages', on_delete = models.SET_NULL)
    lti_context = models.ForeignKey(Context, null=True, blank=True, related_name='chirun_packages', on_delete = models.SET_NULL)

    def __str__(self):
        return f'{self.name} ({self.uid})'

    class Meta:
        ordering = (models.functions.Lower('name'), '-created', 'uid')

    @staticmethod
    def channel_group_name_for_package(uid):
        return f'package_{uid}'

    def get_channel_group_name(self):
        return ChirunPackage.channel_group_name_for_package(self.uid)

    @property
    def title(self):
        return self.manifest.get('title', self.name if self.name else "Unnamed package")

    @property 
    def relative_extracted_path(self):
        if self.uid is None:
            raise Exception("This object doesn't have an ID yet.")
        path = Path('chirun-packages') / 'source' / str(self.uid)
        return path

    @property
    def absolute_extracted_path(self):
        path = Path() / settings.MEDIA_ROOT / self.relative_extracted_path
        path.mkdir(exist_ok=True, parents=True)
        return path.resolve()

    @property 
    def relative_output_path(self):
        if self.uid is None:
            raise Exception("This object doesn't have an ID yet.")
        path = Path('chirun-packages') / 'output' / str(self.uid)
        return path

    @property
    def absolute_output_path(self):
        path = Path() / settings.MEDIA_ROOT / self.relative_output_path
        path.mkdir(exist_ok=True, parents=True)
        return path.resolve()

    def build(self):
        compilation = Compilation.objects.create(package = self)

        from . import tasks
        print(f"Building {self}")
        tasks.build_package(compilation)

        return compilation

    def get_absolute_url(self):
        return reverse('material:view', args=(self.edit_uid,))

    def get_output_url(self):
        return PurePath(settings.MEDIA_URL) / self.relative_output_path

    def get_index_url(self):
        return self.get_output_url() / 'index.html'

    def build_status(self):
        last_build = self.compilations.first()
        if last_build:
            return last_build.status
        else:
            return 'error'

    def config_yaml(self):
        try:
            with open(self.absolute_extracted_path / 'config.yml') as f:
                return f.read()
        except FileNotFoundError:
            return ''

    def get_config(self):
        try: 
            with open(self.absolute_extracted_path / 'config.yml') as f:
                return yaml.load(f, Loader=yaml.CLoader)
        except FileNotFoundError:
            pass

    def save_config(self, config):
        with open(self.absolute_extracted_path / 'config.yml', 'w') as f:
            f.write(yaml.dump(config))

    @property
    def manifest(self):
        try:
            with open(self.absolute_output_path / 'MANIFEST.json') as f:
                data = json.load(f)
            return data
        except FileNotFoundError:
            return {}

    @property
    def structure(self):
        return self.manifest.get('structure')

    def all_items(self):
        """
            A generator for all items in the package's structure, in depth-first order.
        """

        def visit(item):
            yield item
            for subitem in item.get('content',[]):
                yield from visit(subitem)

        for item in self.structure:
            yield from visit(item)

    def get_item_by_url(self, item_url):
        for item in self.all_items():
            if item.get('url') == item_url:
                return item

    def themes(self):
        return self.manifest.get('themes', [])

    def all_source_files(self):
        root = self.absolute_extracted_path

        def visit(d):
            dirs = []
            files = []

            for i in sorted(d.iterdir()):
                if i.is_dir():
                    dirs.append(visit(i))
                else:
                    files.append(i.name)

            return {
                'path': d.relative_to(root).name,
                'dirs': dirs,
                'files': files,
            }

        return visit(root)

BUILD_STATUSES = [
    ("building", "Building"),
    ("built", "Built"),
    ("error", "Error during building"),
]

class Compilation(models.Model):
    package = models.ForeignKey(ChirunPackage, related_name='compilations', on_delete=models.CASCADE)
    status = models.CharField(max_length=10, choices=BUILD_STATUSES, default='building')
    output = models.TextField(default='', blank=True)

    start_time = models.DateTimeField(auto_now_add = True)
    end_time = models.DateTimeField(null = True)

    class Meta:
        ordering = ('-start_time',)

    def __str__(self):
        return f'Compilation {self.pk} of {self.package}'

    def get_absolute_url(self):
        return reverse('material:build_progress', kwargs = {
            'package_pk': self.package.edit_uid,
            'pk': self.pk,
        })

    def get_cache_key(self):
        return f'chirun_lti:build:{self.pk}'

    def get_channel_group_name(self):
        return Compilation.channel_group_name_for_compilation(self.pk)

    @staticmethod
    def channel_group_name_for_compilation(pk):
        return f'build_{pk}'

    async def send_status_change(self):
        channel_layer = get_channel_layer()

        package = self.package

        message = {
            "type": "status_change",
            'compilation': self.pk,
            "status": self.status,
            'start_time': self.start_time.isoformat(),
        }
        if self.end_time is not None:
            message.update({
                'end_time': self.end_time.isoformat(),
                'time_taken': (self.end_time - self.start_time).total_seconds(),
            })

        await channel_layer.group_send(self.get_channel_group_name(), message)
        await channel_layer.group_send(package.get_channel_group_name(), {'type': 'build_status', 'package': str(package.uid), 'message': message})
