from   . import forms
from   .models import ChirunPackage, Compilation
from   chirun_lti.mixins import BackPageMixin, HelpPageMixin
from   dataclasses import dataclass
from   django.conf import settings
from   django.contrib.auth.mixins import UserPassesTestMixin
from   django.core.exceptions import PermissionDenied
from   django.db.models import Q
from   django.http import HttpResponse, HttpResponseRedirect, Http404
from   django.views import generic
from   django.shortcuts import render, redirect
from   django.urls import reverse, reverse_lazy
from   django.utils.translation import gettext as _
from   lti.views import CachedLTIView
import mimetypes
from   pathlib import Path
from   pylti1p3.deep_link_resource import DeepLinkResource
import shutil
import zipfile

class IndexView(BackPageMixin, generic.ListView):
    model = ChirunPackage
    template_name = 'package/index.html'
    back_url = reverse_lazy('index')

def deep_link(request, *args, **kwargs):
    if 'package' in request.GET:
        view = DeepLinkPickItemView.as_view()
    else:
        view = DeepLinkPickPackageView.as_view()

    return view(request, *args, **kwargs)

class DeepLinkView:
    def dispatch(self, request, *args, **kwargs):
        if not self.get_message_launch().is_deep_link_launch():
            return HttpResponseForbidden('Must be a deep link!')

        return super().dispatch(request, *args, **kwargs)

class DeepLinkPickPackageView(DeepLinkView, CachedLTIView, generic.ListView):
    """
        Change the configuration of the tool, completing a deep link launch.
    """
    template_name = 'package/deep_link/pick_package.html'
    context_object_name = 'packages'

    def get_context_data(self, *args, **kwargs):
        context = super().get_context_data(*args, **kwargs)

        queryset = ChirunPackage.objects.all()

        same_context = queryset.filter(lti_context=self.get_lti_context())

        context['same_context'] = same_context

        queryset = queryset.exclude(uid__in=same_context)

        same_tool = queryset.filter(lti_tool = self.get_lti_tool())
        
        context['same_tool'] = same_tool

        queryset = queryset.exclude(uid__in=same_tool)

        other_built = queryset.filter(compilations__status='built').distinct()

        context['other_built'] = other_built

        return context

    def get_queryset(self):
        return ChirunPackage.objects.all()

class DeepLinkPickItemView(DeepLinkView, BackPageMixin, CachedLTIView, generic.FormView):
    """
        Change the configuration of the tool, completing a deep link launch.
    """
    form_class = forms.DeepLinkForm
    template_name = 'package/deep_link/pick_item.html'

    def get_back_url(self):
        return reverse_lazy('material:deep_link', kwargs=self.kwargs)

    def dispatch(self, request, *args, **kwargs):
        self.package = ChirunPackage.objects.get(uid=self.request.GET['package'])

        return super().dispatch(request, *args, **kwargs)

    def get_form_kwargs(self):
        kwargs = super().get_form_kwargs()

        if self.package:
            kwargs['themes'] = self.package.themes()

        return kwargs

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        context['package'] = self.package
        return context

    def form_valid(self, form):
        launch_url = self.request.build_absolute_uri(reverse('lti:launch'))

        package = form.cleaned_data.get('package')
        item_url = form.cleaned_data.get('item')
        theme = form.cleaned_data.get('theme')

        item = package.get_item_by_url(item_url)

        resource = DeepLinkResource()\
            .set_url(launch_url)\
            .set_custom_params({
                'package': str(package.uid),
                'item': item_url,
                'theme': theme,
            })\
            .set_title(item.get('title','Untitled item'))

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
        return reverse('material:index')

class PackageUploadView(PackageEditView):
    form_class = forms.UploadPackageForm

    def form_valid(self, form):
        package = self.object = form.save()
        for file in form.cleaned_data.get('files'):
            path = package.absolute_extracted_path / file.name

            if zipfile.is_zipfile(file):
                print(f"Extracting {file.name} to {package.absolute_extracted_path}")
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
                print(f"writing {path}")
                with open(path, 'wb+') as destination:
                    for chunk in file.chunks():
                        destination.write(chunk)

        return redirect(self.get_success_url())

    def get_success_url(self):
        return self.object.get_absolute_url()

class CreatePackageView(BackPageMixin, CachedLTIView, PackageUploadView, generic.CreateView):
    get_message_launch_on_dispatch = False
    template_name = 'package/create.html'
    back_url = reverse_lazy('material:index')

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        context['launch_id'] = self.request.GET.get('launch_id')

        return context

    def get_success_url(self):
        if 'launch_id' in self.request.GET:
            self.get_lti_data()
            if self.message_launch.is_deep_link_launch():
                self.object.lti_tool = self.lti_tool
                self.object.lti_context = self.lti_context
                self.object.save(update_fields=('lti_tool', 'lti_context',))

                launch_id = self.message_launch.get_launch_id()

                if self.object.get_config() is None:
                    return reverse(
                        'material:deep_link_configure', 
                        kwargs = {
                            'pk': self.object.uid,
                            'launch_id': launch_id,
                        }
                    )

                return reverse('material:deep_link', args=(launch_id,))

        return reverse('material:configure', args=(self.object.edit_uid,))
        

class UploadFilesView(PackageUploadView, BackPageMixin, generic.UpdateView):
    template_name = 'package/upload.html'

class ViewPackageView(BackPageMixin, PackageEditView, generic.DetailView):
    template_name = 'package/detail.html'
    back_url = reverse_lazy('material:index')

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
            initial['content'] = path.read_text()
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
            siblings = [p.relative_to(root) for p in directory.iterdir() if not p.name.startswith('.')]
        except FileNotFoundError:
            siblings = []

        if not path.exists():
            siblings.append(path.relative_to(root))

        context['siblings'] = sorted(siblings, key=lambda x: x.name)

        context['package'] = self.object
        return context

    def form_valid(self, form):
        package = form.instance

        existing_path = self.get_path()
        path = form.cleaned_data.get('path', self.get_relative_path())
        self.saved_path = path

        path = package.absolute_extracted_path / path

        if path != existing_path:
            if existing_path.exists():
                existing_path.unlink()

        path.parent.mkdir(exist_ok=True,parents=True)

        replace_file = form.cleaned_data.get('replace_file')
        if replace_file:
            path.write_bytes(replace_file.read())
        else:
            content = form.cleaned_data.get('content')
            path.write_text(content)
        return redirect(self.get_success_url())

    def get_success_url(self):
        package = self.object
        path = self.saved_path

        return reverse('material:file', args=(package.edit_uid, path))

class DeleteFileView(FileView):
    form_class = forms.DeleteFileForm

    def form_invalid(self, form):
        print(form.errors)

    def form_valid(self, form):
        path = self.get_path()

        print("Deleting",path)

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

    def is_deep_link_launch(self):
        return 'launch_id' in self.kwargs

    def get_back_url(self):
        if self.is_deep_link_launch():
            return reverse_lazy('material:deep_link', args=(self.get_launch_id(),))

        return reverse_lazy('material:view', args=(str(self.get_object().edit_uid),))

    template_name = 'package/config.html'

    def get_context_data(self, **kwargs):
        package = self.get_object()
        context = super().get_context_data(**kwargs)

        context['config'] = package.get_config()
        context['files'] = package.all_source_files()

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
        print(config)

        package.save_config(config)

        self.compilation = package.build()

        return HttpResponseRedirect(self.get_success_url())

    def get_success_url(self):
        if self.is_deep_link_launch():
            return reverse_lazy('material:deep_link', args=(self.get_launch_id(),))
        else:
            return self.compilation.get_absolute_url()

class DownloadView(PackageEditView, generic.DetailView):
    def get(self, request, *args, **kwargs):
        package = self.get_object()
        response = HttpResponse(content_type='application/zip')
        response['Content-Disposition'] = f'attachment; filename="{package.edit_uid}.zip"'
        zf = zipfile.ZipFile(response,'w')
        for fname in package.all_output_files():
            zf.write(str(Path(package.absolute_output_path) / fname), fname)
        return response
