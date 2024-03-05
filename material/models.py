from   channels.layers import get_channel_layer
import configparser
from   django.conf import settings
from   django.contrib import admin
from   django.db import models
from   django.db.models import F
from   django.urls import reverse
from   django.utils.translation import gettext as _
from   django.utils import timezone
import functools
import json
from   lti.models import Context, ResourceLink
import os
from   pathlib import Path, PurePath
from   pylti1p3.contrib.django.lti1p3_tool_config.models import LtiTool
import subprocess
import urllib.parse
import uuid
import yaml



def all_files_relative_to(top):
    for d,dirs,files in os.walk(str(top)):
        rd = Path(d).relative_to(top)
        for f in sorted(files, key=str):
            yield str(rd / f)

GIT_STATUSES = [
    ("cloning", _("Cloning from the source repository.")),
    ("updating", _("Updating from the source repository.")),
    ("ready", _("Ready to use")),
    ("error", _("There was an error fetching from the source repository."))
]

class GitException(Exception):
    pass

class ChirunPackage(models.Model):
    name = models.CharField(max_length=500)
    author = models.CharField(max_length=200)
    uid = models.UUIDField(default = uuid.uuid4, primary_key = True)
    edit_uid = models.UUIDField(default = uuid.uuid4, unique = True)

    created = models.DateTimeField(auto_now_add = True)

    git_url = models.CharField(max_length=2000, default='', blank=True, verbose_name=_('Git URL'))
    git_username = models.CharField(max_length=200, default='', blank=True, verbose_name=_('Username'))
    git_status = models.CharField(max_length=10, default='cloning', choices=GIT_STATUSES)

    def __str__(self):
        return f'{self.name} ({self.uid})'

    class Meta:
        ordering = (models.functions.Lower('name'), '-created', 'uid')


    @property
    def source_type(self):
        if self.git_url:
            return 'git'

        return 'local'

    @staticmethod
    def channel_group_name_for_package(uid):
        return f'package_{uid}'

    def get_channel_group_name(self):
        return ChirunPackage.channel_group_name_for_package(self.uid)

    @property
    def title(self):
        return self.manifest.get('title', self.name if self.name else _("Unnamed package"))

    @property 
    def relative_extracted_path(self):
        if self.uid is None:
            raise Exception(_("This object doesn't have an ID yet."))
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
            raise Exception(_("This object doesn't have an ID yet."))
        path = Path('chirun-packages') / 'output' / str(self.uid)
        return path

    @property
    def absolute_output_path(self):
        path = Path() / settings.MEDIA_ROOT / self.relative_output_path
        path.mkdir(exist_ok=True, parents=True)
        return path.resolve()

    def has_output(self):
        return (self.absolute_output_path / 'MANIFEST.json').exists()

    def build(self):
        compilation = Compilation.objects.create(package = self)

        from . import tasks
        tasks.build_package(compilation)

        return compilation

    @admin.display(description = "Last build",
                   boolean = False,
                   ordering = F('last_compiled_sort').desc(nulls_last=True))
    def last_compiled(self):
        last_build = self.compilations.first()
        if last_build:
            return last_build.start_time
        else:
            return 'Never Built'
        
    @admin.display(description = "Last launch",
                   boolean = False,
                   ordering = F('last_launched_sort').desc(nulls_last=True))
    def last_launched(self):
        last_launch = self.launches.first()
        if last_launch:
            return last_launch.launch_time
        else:
            return 'Never Launched'

    def run_git_command(self, cmd, save_interaction=False):
        cmd = [str(x) for x in cmd]
        if save_interaction:
            git_interaction = GitInteraction.objects.create(package = self, command = ' '.join(cmd))
        result = subprocess.run(cmd, cwd=self.absolute_extracted_path, env={'GIT_CEILING_DIRECTORIES': str(self.absolute_extracted_path.parent)}, capture_output=True, encoding='utf-8')

        if save_interaction:
            try:
                result.check_returncode()
                git_interaction.status = 'success'
            except subprocess.CalledProcessError:
                git_interaction.status = 'error'

            git_interaction.output = result.stdout + ('\n' if result.stdout and result.stderr else '') + result.stderr

            git_interaction.save(update_fields=('status', 'output'))

        try:
            result.check_returncode()
        except subprocess.CalledProcessError as e:
            raise GitException(e)

        return result

    def clone_from_git(self, ref=None):
        self.git_status = 'cloning'
        self.save(update_fields=('git_status',))

        from . import tasks
        tasks.clone_from_git(self, ref=ref)

    @property
    def git_remote_url(self):
        pr = urllib.parse.urlparse(self.git_url)
        scheme, netloc, path, params, query, fragment = pr
        netloc = f'{self.git_username}@{pr.hostname}' if self.git_username else pr.netloc
        return urllib.parse.urlunparse((scheme, netloc, path, params, query, fragment))

    def update_from_git(self, ref=None):
        from . import tasks

        self.git_status = 'updating'
        self.save(update_fields=('git_status',))

        git_config = self.absolute_extracted_path / '.git' / 'config'
        if not git_config.exists():
            self.clone_from_git(ref=ref)

        cp = configparser.ConfigParser()
        cp.read(git_config)
        try:
            remote_url = cp.get('remote "origin"', 'url')
        except configparser.NoSectionError:
            remote_url = None
        if self.git_remote_url != remote_url:
            self.clone_from_git()

        tasks.update_from_git(self, ref=ref)

    def git_current_branch(self):
        if self.git_status != 'ready':
            return

        try:
            with open(self.absolute_extracted_path / '.git' / 'HEAD') as f:
                ref = f.read().strip()
        except FileNotFoundError:
            return

        if ref[:5] != 'ref: ':
            return

        return PurePath(ref[5:]).name

    def git_last_commit(self):
        if self.git_status != 'ready':
            return


        try:
            result = self.run_git_command(['git','log','--format=format:%h%x09%s','-n','1']).stdout.strip()
            i = result.index('\t')
        except (GitException, ValueError):
            return
        commit_hash = result[:i]
        commit_message = result[i+1:]
        return commit_hash, commit_message

    def git_branches(self):
        if self.git_status != 'ready':
            return

        try:
            result = self.run_git_command(['git','branch','-a', '--no-color'])
        except GitException:
            return

        branches = set()
        current = None
        for line in result.stdout.split('\n'):
            if not line:
                continue

            current = line[0] == '*'

            ref = line[2:].split(' ')[0]
            if '(' in ref or 'HEAD' in ref:
                continue

            ref = PurePath(ref)
            branches.add(ref.name)

        return sorted(branches)

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
            return 'not_built'

    def get_config(self):
        try: 
            with open(self.absolute_extracted_path / 'config.yml') as f:
                return yaml.load(f, Loader=yaml.CLoader)
        except FileNotFoundError:
            pass

    def save_config(self, config):
        with open(self.absolute_extracted_path / 'config.yml', 'w') as f:
            f.write(yaml.dump(config))

    def create_initial_config(self):
        structure = []

        config = {
            'structure': structure
        }

        root = self.absolute_extracted_path

        source_files = [p for p in root.rglob('*') if p.suffix in ('.tex', '.md')]

        is_standalone = len(source_files) == 1

        for p in source_files:
            if p.suffix not in ('.tex','.md'):
                continue

            structure.append({
                'source': str(p.relative_to(root)),
                'title': p.name,
                'type': 'standalone' if is_standalone else 'chapter',
            })

        self.save_config(config)
        return config

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
            if item.get('url') == item_url or item.get('slides_url') == item_url:
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

    def all_source_files_list(self):
        yield from all_files_relative_to(Path(self.absolute_extracted_path))

    def all_output_files(self):
        yield from all_files_relative_to(Path(self.absolute_output_path))

class PackageLTIUse(models.Model):
    """
        Recording that a package has been used with a certain context in an LTI tool.
    """

    package = models.ForeignKey(ChirunPackage, related_name='lti_uses', on_delete = models.CASCADE)
    lti_context = models.ForeignKey(Context, related_name='chirun_packages', on_delete = models.CASCADE)

   
class PackageLaunch(models.Model):
    """
        Records a single instance of package launch by a student
    """
    link = models.ForeignKey(ResourceLink, related_name='launches', on_delete=models.CASCADE)
    package = models.ForeignKey(ChirunPackage, related_name='launches',on_delete=models.CASCADE)
    launch_time = models.DateTimeField(default=timezone.now)
    item = models.CharField(max_length=500) 
    theme = models.CharField(max_length=500)
    def __str__(self):
        return f"{str(self.package)} - {str(self.launch_time)}" 


BUILD_STATUSES = [
    ("building", _("Building")),
    ("built", _("Built")),
    ("error", _("Error during building")),
    ("not_built", _("Not built")),
]

class Compilation(models.Model):
    package = models.ForeignKey(ChirunPackage, related_name='compilations', on_delete=models.CASCADE)
    status = models.CharField(max_length=10, choices=BUILD_STATUSES, default='building')

    start_time = models.DateTimeField(auto_now_add = True)
    end_time = models.DateTimeField(null = True)

    class Meta:
        ordering = ('-start_time',)

    def __str__(self):
        return _('Compilation {pk} of {package}').format(pk=self.pk, package=self.package)

    def get_absolute_url(self):
        return reverse('material:build_progress', kwargs = {
            'package_pk': self.package.edit_uid,
            'pk': self.pk,
        })

    def get_cache_key(self):
        return f'chirun_lti:build:{self.pk}'

    def get_channel_group_name(self):
        return Compilation.channel_group_name_for_compilation(self.pk)

    def get_build_log_path(self):
        path = Path() / settings.MEDIA_ROOT / 'chirun-packages' / 'build-logs' / str(self.package.uid) / str(self.pk)
        path.mkdir(exist_ok=True, parents=True)
        return path.resolve()

    def get_build_log(self, name):
        d = self.get_build_log_path()
        p = (d / name).with_suffix('.txt')
        if not p.exists():
            return ''
        with open(p) as f:
            return f.read()

    @property
    def stdout(self):
        return self.get_build_log('stdout')

    @property
    def stderr(self):
        return self.get_build_log('stderr')

    @property
    def output(self):
        logs = [self.stdout, self.stderr]
        return '\n\n'.join(x for x in logs if x)

    @staticmethod
    def channel_group_name_for_compilation(pk):
        return f'build_{pk}'

    def is_latest_compilation(self):
        return self.pk == self.package.compilations.first().pk

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

GIT_INTERACTION_STATUSES =[
    ('running', _('Running')),
    ('success', _('Finished')),
    ('error', _('Ended with an error')),
]

class GitInteraction(models.Model):
    package = models.ForeignKey(ChirunPackage, related_name='git_interactions', on_delete=models.CASCADE)
    command = models.TextField()
    status = models.CharField(max_length=10, choices=GIT_INTERACTION_STATUSES, default='running')
    output = models.TextField(default='', blank=True)

    time = models.DateTimeField(auto_now_add = True)

    def censor_string(self, string):
        """
            Remove information that shouldn't be displayed to the user from a string.
        """

        path = str(self.package.absolute_extracted_path)

        return string.replace(path, '<LOCAL_SOURCE>')

    @property
    def short_command(self):
        return ' '.join(self.censor_string(self.command).split(' ')[:2])

    @property
    def censored_command(self):
        return self.censor_string(self.command)

    @property
    def censored_output(self):
        return self.censor_string(self.output)

    class Meta:
        ordering = ('-time',)
