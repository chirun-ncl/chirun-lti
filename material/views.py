from   . import forms
from   .models import ChirunPackage, Compilation, PackageLTIUse
from   chirun_lti.mixins import BackPageMixin, HelpPageMixin
from   dataclasses import dataclass
from   django.conf import settings
from   django.contrib import messages
from   django.contrib.auth.mixins import UserPassesTestMixin
from   django.core.exceptions import PermissionDenied
from   django.db.models import Q
from   django.http import HttpResponse, HttpResponseRedirect, Http404, HttpResponseBadRequest
from   django.views import generic
from   django.shortcuts import render, redirect
from   django.urls import reverse, reverse_lazy
from   django.utils.crypto import constant_time_compare
from   django.utils.translation import gettext as _
import hmac
import json
from   lti.views import CachedLTIView
import mimetypes
from   pathlib import Path
from   pylti1p3.deep_link_resource import DeepLinkResource
import shutil
import zipfile

class DeepLinkView(generic.View):
    def dispatch(self, request, *args, **kwargs):
        def respond(view):
            return view(request, *args, **kwargs)

        if request.method.lower() == 'post' and 'url' not in self.request.POST:
                return respond(DeepLinkConfirmView.as_view())

        if 'package' not in self.request.GET:
            return respond(DeepLinkPickPackageView.as_view())

        package = ChirunPackage.objects.get(uid=self.request.GET['package'])

        if package.build_status() != 'built':
            return redirect(reverse('material:deep_link_build', kwargs={'launch_id': kwargs['launch_id'], 'pk': package.uid}))

        if 'theme' not in self.request.GET:
            themes = package.themes()
            if len(themes) == 1:
                return redirect(reverse('material:deep_link', args=(kwargs['launch_id'],))+f'''?package={package.uid}&theme={themes[0]['path']}''')
            else:
                return respond(DeepLinkPickThemeView.as_view())
        if 'item' not in self.request.GET:
            return respond(DeepLinkPickItemView.as_view())

        return respond(DeepLinkConfirmView.as_view())


class AbstractDeepLinkView:
    def dispatch(self, request, *args, **kwargs):
        package_uid = self.request.GET.get('package', self.kwargs.get('pk'))

        if package_uid is not None:
            self.package = ChirunPackage.objects.get(uid=package_uid)

        if not self.get_message_launch().is_deep_link_launch():
            return HttpResponseForbidden('Must be a deep link!')

        return super().dispatch(request, *args, **kwargs)

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        if hasattr(self, 'package'):
            context['package'] = self.package

        return context


class DeepLinkPickPackageView(AbstractDeepLinkView, CachedLTIView, generic.FormView):
    """
        During a deep link launch, pick the package to use.
        The next step is to pick a theme, if there's more than one, or to pick an item in the package.
    """
    template_name = 'package/deep_link/pick_package.html'
    context_object_name = 'packages'

    form_class = forms.DeepLinkImportForm

    def get_launch_id(self):
        return self.kwargs['launch_id']

    def form_valid(self, form):

        package = form.cleaned_data['package']

        PackageLTIUse.objects.get_or_create(package = package, lti_context = self.get_lti_context())

        return redirect(reverse('material:deep_link', kwargs={'launch_id': self.get_launch_id()})+'?package='+str(package.uid))

    def get_context_data(self, *args, **kwargs):
        context = super().get_context_data(*args, **kwargs)

        queryset = ChirunPackage.objects.all()

        same_context = queryset.filter(lti_uses__lti_context=self.get_lti_context()).distinct()

        context['same_context'] = same_context

        return context

    def get_queryset(self):
        return ChirunPackage.objects.all()

class DeepLinkBuildPackageView(AbstractDeepLinkView, BackPageMixin, CachedLTIView, generic.TemplateView):
    """
        During a deep link, after picking a package that hasn't been built or is in the middle of being built, show a holding message.
    """

    template_name = 'package/deep_link/build_package.html'

    def get_back_url(self):
        return reverse_lazy('material:deep_link', kwargs={'launch_id': self.kwargs['launch_id']})


class DeepLinkPickThemeView(AbstractDeepLinkView, BackPageMixin, CachedLTIView, generic.TemplateView):
    """
        During a deep link launch, pick the theme to use in the chosen package.
    """
    template_name = 'package/deep_link/pick_theme.html'

    def get_back_url(self):
        return reverse_lazy('material:deep_link', kwargs=self.kwargs)

class DeepLinkPickItemView(DeepLinkPickThemeView):
    """
        During a deep link launch, pick the item within the previously-chosen package to use.
    """
    template_name = 'package/deep_link/pick_item.html'

    def get_back_url(self):
        url = reverse_lazy('material:deep_link', kwargs=self.kwargs)

        if len(self.package.themes()) > 1:
            url += f'''?package={self.package.uid}'''
        
        return url

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        theme_path = self.request.GET['theme']
        context['theme'] = next(t for t in self.package.themes() if t['path'] == theme_path)

        return context

class DeepLinkConfirmView(AbstractDeepLinkView, BackPageMixin, CachedLTIView, generic.FormView):
    form_class = forms.DeepLinkForm
    template_name = 'package/deep_link/confirm.html'

    def dispatch(self, request, *args, **kwargs):
        self.package = ChirunPackage.objects.get(uid=self.request.GET['package'])

        return super().dispatch(request, *args, **kwargs)

    def get_back_url(self):
        theme = self.request.GET['theme']
        return reverse_lazy('material:deep_link', kwargs=self.kwargs)+f'''?package={self.package.uid}&theme={theme}'''

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        context['package'] = self.package
        theme_path = self.request.GET['theme']
        context['theme'] = next(t for t in self.package.themes() if t['path'] == theme_path)

        item_url = context['item_url'] = self.request.GET['item']
        item = context['item'] = self.package.get_item_by_url(item_url)

        context['formats'] = [f for f in item.get('formats',[]) if f['filetype'] == 'html']

        return context

    def form_valid(self, form):
        launch_url = self.request.build_absolute_uri(reverse('lti:launch'))

        package = form.cleaned_data.get('package')
        item_url = form.cleaned_data.get('item')
        theme = self.request.GET.get('theme')
        item_format = form.cleaned_data.get('item_format', 'default')

        item = package.get_item_by_url(item_url)

        if item_format is not None:
            try:
                format_manifest = next(f for f in item['formats'] if f['format'] == item_format)
            except StopIteration:
                raise Exception(f"This item isn't available in the format \"{item_format}\"")

            item_url = format_manifest['url']

        resource = DeepLinkResource()\
            .set_url(launch_url)\
            .set_custom_params({
                'package': str(package.uid),
                'item': item_url,
                'theme': theme,
                'item_format': item_format,
            })\
            .set_title(item.get('title',_('Untitled item')))

        html = self.message_launch.get_deep_link().output_response_form([resource])
        return HttpResponse(html)

class PackageEditView(UserPassesTestMixin):
    """
        A mixin for any view whose object is a ChirunPackage and which requires edit access.
    """
    model = ChirunPackage
    context_object_name = 'package'

    def get_object(self, queryset=None):
        if queryset is None:
            queryset = self.get_queryset()

        queryset = queryset.filter(edit_uid = self.kwargs.get(self.pk_url_kwarg))

        try:
            obj = queryset.get()
        except queryset.model.DoesNotExist:
            raise Http404(
                _("No %(verbose_name)s found matching the query")
                % {"verbose_name": queryset.model._meta.verbose_name}
            )
        return obj
    
    def test_func(self):
        if self.request.user.is_superuser:
            return True

        return True

    def get_back_url(self):
        return reverse_lazy('material:view', args=(str(self.get_object().edit_uid),))

class DeleteView(PackageEditView, generic.DeleteView):
    model = ChirunPackage
    template_name = 'package/delete.html'

    def get_success_url(self):
        return reverse('index')

class PackageUploadView(PackageEditView):
    form_class = forms.UploadPackageForm

    def form_valid(self, form):
        package = self.object = form.save()
        self.uploaded_files = []

        editing_file = self.request.POST.get('editing_file')
        editing_path = Path(editing_file).parent if editing_file is not None else Path()

        for file in form.cleaned_data.get('files'):
            root_relative_path = editing_path / file.name
            path = package.absolute_extracted_path / root_relative_path

            if zipfile.is_zipfile(file):
                z = zipfile.ZipFile(file,'r')
                z.extractall(package.absolute_extracted_path)

                root = package.absolute_extracted_path
                root_items = list(root.iterdir())
                if len(root_items) == 1:
                    root_dir = root_items[0]
                    for f in root_dir.iterdir():
                        shutil.move(f,root)
                    root_dir.rmdir()
            else:
                path.parent.mkdir(exist_ok=True,parents=True)
                with open(path, 'wb+') as destination:
                    for chunk in file.chunks():
                        destination.write(chunk)
                self.uploaded_files.append(root_relative_path)

        return redirect(self.get_success_url())

    def get_success_url(self):
        return self.object.get_absolute_url()

class CreatePackageView(BackPageMixin, CachedLTIView, PackageUploadView, generic.CreateView):
    get_message_launch_on_dispatch = False
    template_name = 'package/create.html'
    back_url = reverse_lazy('index')

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        context['launch_id'] = self.request.GET.get('launch_id')
        context['git_form'] = forms.CreatePackageFromGitForm()

        return context

    def form_valid(self, form):
        super().form_valid(form)
        
        package = self.object

        if package.get_config() is None:
            messages.info(self.request, _("Your package didn't contain a config file, so we've created one. Please review the configuration."))
            package.create_initial_config()
        else:
            messages.info(self.request, _("A new package has been created."))

        return redirect(self.get_success_url())

    def is_deep_link_launch(self):
        if 'launch_id' not in self.request.GET:
            return False

        self.get_lti_data()
        return self.message_launch.is_deep_link_launch()

    def get_success_url(self):
        if self.is_deep_link_launch():
            return self.get_deep_link_success_url()
        else:
            return reverse('material:configure', args=(self.object.edit_uid,))
  
    def get_deep_link_success_url(self):
        package = self.object

        PackageLTIUse.objects.get_or_create(package = package, lti_context = self.lti_context)

        launch_id = self.message_launch.get_launch_id()

        return reverse('material:deep_link', args=(launch_id,)) + f'''?package={package.uid}'''

class CreatePackageFromGitView(BackPageMixin, PackageEditView, generic.CreateView):
    template_name = 'package/git/create.html'
    back_url = reverse_lazy('index')
    help_url = 'package/git.html'
    form_class = forms.CreatePackageFromGitForm

    def form_valid(self, form):
        package = form.save()

        package.clone_from_git()

        return super().form_valid(form)
    
    def get_success_url(self):
        return reverse('material:view', args=(self.object.edit_uid,))

class UpdateFromGitView(PackageEditView, generic.UpdateView):
    template_name = 'package/git/update.html'
    help_url = 'package/git.html'

    def post(self, request, *args, **kwargs):
        package = self.get_object()

        package.update_from_git()

        return redirect(package.get_absolute_url())

class ConfigureGitView(BackPageMixin, HelpPageMixin, PackageEditView, generic.UpdateView):
    template_name = 'package/git/configure.html'
    form_class = forms.ConfigureGitForm
    help_url = 'package/git.html'

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        package = self.get_object()

        context['webhook_url'] = self.request.build_absolute_uri(reverse('material:git_webhook', args=(package.edit_uid,)))

        return context

    def get_back_url(self):
        return reverse_lazy('material:view', args=(str(self.get_object().edit_uid),))

    def get_form_kwargs(self):
        kwargs = super().get_form_kwargs()

        package = self.get_object()

        kwargs['initial'].update({
            'ref': package.git_current_branch()
        })

        return kwargs

    def get_form(self, **kwargs):
        form = super().get_form(**kwargs)

        package = self.get_object()

        ref = form.fields['ref']
        branches = package.git_branches()
        if branches is not None:
            ref.choices = [(a, a) for a in branches]

        return form

    def form_valid(self, form):
        package = form.save()

        package.update_from_git(ref=form.cleaned_data.get('ref'))

        return super().form_valid(form)
    
    def get_success_url(self):
        return reverse('material:view', args=(self.object.edit_uid,))

class UploadFilesView(PackageUploadView, BackPageMixin, generic.UpdateView):
    template_name = 'package/upload.html'

    def get_success_url(self):
        editing_file = self.request.POST.get('editing_file')

        if editing_file is not None:
            if len(self.uploaded_files) == 1:
                path = self.uploaded_files[0]
                return reverse('material:file', args=(self.object.edit_uid, path))
            else:
                return reverse('material:file', args=(self.object.edit_uid, editing_file))

        return super().get_success_url()

class ViewPackageView(BackPageMixin, PackageEditView, generic.DetailView):
    template_name = 'package/detail.html'
    back_url = reverse_lazy('index')

class BuildView(PackageEditView, generic.UpdateView):
    template_name = 'package/detail.html'

    def post(self, request, *args, **kwargs):
        package = self.get_object()
        compilation = package.build()

        return redirect(compilation.get_absolute_url())

class BuildProgressView(BackPageMixin, generic.DetailView):
    template_name = 'package/build_progress.html'
    context_object_name = 'compilation'
    model = Compilation
    
    def get_object(self, queryset=None):
        if queryset is None:
            queryset = self.get_queryset()

        package_edit_uid = self.kwargs.get('package_pk')
        compilation_pk = self.kwargs.get('pk')
        queryset = queryset.filter(package__edit_uid = package_edit_uid, pk = compilation_pk)

        try:
            obj = queryset.get()
        except queryset.model.DoesNotExist:
            raise Http404(
                _("No %(verbose_name)s found matching the query")
                % {"verbose_name": queryset.model._meta.verbose_name}
            )
        return obj


    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        compilation = self.get_object()
        context['package'] = compilation.package

        if compilation.end_time is not None:
            context['time_taken'] = compilation.end_time - compilation.start_time

        context['status_json'] = {
            'status': compilation.status,
            'output': compilation.output,
        }

        return context

    def get_back_url(self):
        compilation = self.get_object()
        return reverse_lazy('material:view', args=(compilation.package.edit_uid,))

class FileView(PackageEditView, BackPageMixin, generic.UpdateView):
    form_class = forms.PackageFileForm
    template_name = 'package/package_file.html'

    mime_type = None
    is_binary = False
    is_image = False

    def get_path(self):
        package = self.object = self.get_object()
        path = package.absolute_extracted_path / self.kwargs.get('path','')
        extra = self.request.GET.get('newfile')
        if extra:
            path = path.parent if not path.is_dir() else path
            path = path / extra

        self.mime_type, _ = mimetypes.guess_type(str(path))
        self.is_image = self.mime_type is not None and self.mime_type.split('/')[0] == 'image'

        if path.is_relative_to(package.absolute_extracted_path):
            return path
        else:
            raise PermissionDenied

    def get_relative_path(self):
        root = self.object.absolute_extracted_path
        return self.get_path().relative_to(root)

    def get_initial(self):
        initial = super().get_initial()

        path = self.get_path()

        initial['path'] = self.get_relative_path()

        try:
            if path.exists():
                initial['content'] = path.read_text()
            else:
                if path.suffix == '.tex':
                    initial['content'] = getattr(settings,'TEX_FILE_INITIAL_CONTENT','')
        except UnicodeDecodeError:
            self.is_binary = True
        except (FileNotFoundError, IsADirectoryError):
            pass

        return initial

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        path = context['path'] = self.get_path()
        root = self.object.absolute_extracted_path
        directory = path if path.is_dir() else path.parent
        context['directory'] = directory.relative_to(root)
        context['directory_relative_path'] = path.relative_to(directory)
        root_relative_path = context['root_relative_path'] = path.relative_to(root)
        context['mime_type'] = self.mime_type
        context['is_binary'] = self.is_binary
        context['is_image'] = self.is_image
        context['file_url'] = settings.MEDIA_URL + str(self.object.relative_extracted_path / root_relative_path)
        try:
            siblings = [(p.relative_to(root), p.is_dir()) for p in directory.iterdir() if not p.name.startswith('.')]
        except FileNotFoundError:
            siblings = []

        if not path.exists():
            siblings.append((path.relative_to(root), False))

        context['siblings'] = sorted(siblings, key=lambda x: x[0].name)

        context['package'] = self.object

        context['upload_form'] = forms.UploadPackageForm()
        return context

    def form_valid(self, form):
        package = form.instance

        existing_path = self.get_path()
        path = form.cleaned_data.get('path', self.get_relative_path())
        self.saved_path = path

        path = package.absolute_extracted_path / path

        path.parent.mkdir(exist_ok=True,parents=True)

        if path != existing_path:
            if existing_path.exists():
                if self.is_binary or self.is_image:
                    existing_path.rename(path)
                else:
                    existing_path.unlink()

        replace_file = form.cleaned_data.get('replace_file')
        content = form.cleaned_data.get('content')
        if replace_file:
            path.write_bytes(replace_file.read())
        elif content is not None and not (self.is_binary or self.is_image):
            path.write_text(content)

        return redirect(self.get_success_url())

    def get_success_url(self):
        package = self.object
        path = self.saved_path

        return reverse('material:file', args=(package.edit_uid, path))

class DeleteFileView(FileView):
    form_class = forms.DeleteFileForm

    def form_valid(self, form):
        path = self.get_path()

        if path.is_dir():
            path.rmdir()
        else:
            path.unlink()

        return redirect(self.get_success_url())

    def get_success_url(self):
        package = self.object
        path = self.get_path()
        return reverse('material:file', args=(package.edit_uid, path.relative_to(self.get_object().absolute_extracted_path).parent))

class ConfigView(BackPageMixin, HelpPageMixin, PackageEditView, CachedLTIView, generic.UpdateView):
    form_class = forms.ConfigForm
    help_url = 'package/configure.html'
    get_message_launch_on_dispatch = False

    def get_back_url(self):
        return reverse_lazy('material:view', args=(str(self.get_object().edit_uid),))

    template_name = 'package/config.html'

    def get_context_data(self, **kwargs):
        package = self.get_object()
        context = super().get_context_data(**kwargs)

        context['config'] = package.get_config()
        context['files'] = package.all_source_files()
        context['media_root'] = settings.MEDIA_URL + str(package.relative_extracted_path) + '/'

        return context

    def get_initial(self):
        initial = super().get_initial()

        package = self.get_object()

        config = package.get_config()
        if config is not None:
            for key in ('author', 'institution', 'code', 'year', 'build_pdf', 'num_pdf_runs', 'mathjax_url'):
                if key in config:
                    initial[key] = config[key]

        return initial

    def form_valid(self, form):
        package = self.get_object()

        config = form.cleaned_data.get('config')

        messages.info(self.request, _("The package configuration has been saved and the package is being rebuilt."))

        package.save_config(config)

        self.compilation = package.build()

        return HttpResponseRedirect(self.get_success_url())

    def get_success_url(self):
        return reverse_lazy('material:view', args=(str(self.get_object().edit_uid),))

class DownloadOutputView(PackageEditView, generic.DetailView):
    def get(self, request, *args, **kwargs):
        package = self.get_object()
        response = HttpResponse(content_type='application/zip')
        response['Content-Disposition'] = f'attachment; filename="{package.edit_uid}.zip"'
        zf = zipfile.ZipFile(response,'w')
        for fname in package.all_output_files():
            zf.write(str(Path(package.absolute_output_path) / fname), fname)
        return response

class ZipDownloadView(PackageEditView, generic.DetailView):
    def get_all_files(self):
        """
            A generator giving the absolute source paths, and paths in the .zip package, of all files to include in the download.

            Yields tuples (source_path, zip_path)
        """
        raise NotImplementedError

    def get_zip_filename(self):
        """
            Get the stem of the name of the .zip file to produce.
        """
        raise NotImplementedError

    def get(self, request, *args, **kwargs):
        response = HttpResponse(content_type='application/zip')
        response['Content-Disposition'] = f'attachment; filename="{self.get_zip_filename()}.zip"'
        zf = zipfile.ZipFile(response,'w')
        for path, fname in self.get_all_files():
            zf.write(path, fname)
        return response


class DownloadOutputView(ZipDownloadView):
    def get_all_files(self):
        package = self.get_object()
        for fname in package.all_output_files():
            yield (str(Path(package.absolute_output_path) / fname), fname)

    def get_zip_filename(self):
        package = self.get_object()
        return package.edit_uid

class DownloadSourceView(ZipDownloadView):
    def get_all_files(self):
        package = self.get_object()
        for fname in package.all_source_files_list():
            yield (str(Path(package.absolute_extracted_path) / fname), fname)

    def get_zip_filename(self):
        package = self.get_object()
        return f'{package.edit_uid}-source'


class WebhookValidationException(Exception):
    pass


class GitWebhookHandler:
    http_method_names = ['post',]

    event_map = {
        'github': {
            'push': 'push',
        },
        'gitlab': {
            'Push Hook': 'push',
        },
    }
    
    def post(self, request, *args, **kwargs):
        try:
            self.validate_payload()
            return self.handle_webhook_event()
        except WebhookValidationException as e:
            return HttpResponseBadRequest()

    def get_secret(self):
        """
            Return the secret string that is used to sign the payload.
        """
        return None

    def get_webhook_kind(self):
        if 'X-GitHub-Event' in self.request.headers:
            return 'github'

        if 'X-Gitlab-Event' in self.request.headers:
            return 'gitlab'

        raise WebhookValidationException("Can't work out what format of webhook this is.")

    def validate_payload(self):
        secret = self.get_secret()

        if secret is None:
            return

        payload = self.request.body
        received_signature = self.request.headers.get('X-Hub-Signature-256','')
            
        h = hmac.new(secret.encode('utf-8'), payload, digestmod='sha256')
        
        expected_signature = 'sha256=' + h.hexdigest()

        if not constant_time_compare(expected_signature, received_signature):
            raise WebhookValidationException("The payload signature does not match the expected signature.")

    def handle_webhook_event(self):
        event = self.request.headers.get('X-GitHub-Event') or self.request.headers.get('X-GitLab-Event')

        if event is None:
            raise WebhookValidationException("Couldn't find the event type header.")

        webhook_kind = self.webhook_kind = self.get_webhook_kind()
        event = self.event_map[webhook_kind].get(event)

        payload = self.payload = json.loads(self.request.body.decode('utf-8'))

        handler = getattr(self, f'handle_event_{event}')
        return handler(payload)

    def get_repository_info(self):
        if self.webhook_kind == 'github':
            return self.payload['repository']

        if self.webhook_kind == 'gitlab':
            return self.payload['project']

class GitWebhookView(GitWebhookHandler, PackageEditView, generic.DetailView):
    http_method_names = ['post',]

    def check_git_repo(self, payload):
        package = self.object

        repo_info = self.get_repository_info()
        keys = ['html_url', 'git_url', 'clone_url', 'ssh_url'] if self.webhook_kind == 'github' else ['web_url', 'git_ssh_url', 'git_http_url', 'url', 'ssh_url', 'http_url']
        for key in keys:
            if repo_info.get(key) == package.git_url:
                return True

        raise WebhookValidationException(f"This event is not for the package's github repo. Expected {package.git_url}.")
        
    def handle_event_push(self, payload):
        package = self.object = self.get_object()

        if package.source_type != 'git':
            return HttpResponseBadRequest()

        self.check_git_repo(payload)

        package.update_from_git()

        return HttpResponse("ok")
