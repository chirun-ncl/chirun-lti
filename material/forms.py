from   .models import ChirunPackage
from   django import forms
from   django.core.exceptions import ValidationError
from   django.urls import resolve, Resolver404
from   django.utils.translation import gettext as _
from   multiupload.fields import MultiFileField, MultiMediaField, MultiImageField
from   urllib.parse import urlparse

class UploadPackageForm(forms.ModelForm):
    files = MultiFileField(min_num=1, required=False)

    class Meta:
        model = ChirunPackage
        fields = []

class DeepLinkForm(forms.Form):
    package = forms.ModelChoiceField(queryset=ChirunPackage.objects.all())
    item = forms.CharField(required=False)
    theme = forms.CharField(required=False)
    item_format = forms.CharField(required=False)

class DeepLinkImportForm(forms.Form):
    url = forms.CharField(required=True)

    def clean(self):
        cleaned_data = super().clean()

        if 'url' not in cleaned_data:
            return

        url = cleaned_data['url']

        path = urlparse(url).path

        try:
            m = resolve(path)
        except Resolver404:
            raise ValidationError(_("This URL is not valid."))
        if not ('material' in m.namespaces and 'pk' in m.kwargs):
            raise ValidationError(_("This is not a package URL."))

        pk = m.kwargs['pk']

        try:
            self.cleaned_data['package'] = ChirunPackage.objects.get(edit_uid = pk)
        except ChirunPackage.DoesNotExist:
            raise ValidationError(_("This is not a valid package URL."))

class PackageFileForm(forms.ModelForm):
    content = forms.CharField(widget=forms.Textarea, required=False)
    replace_file = forms.FileField(required = False)
    path = forms.CharField()

    class Meta:
        model = ChirunPackage
        fields = []

class DeleteFileForm(forms.ModelForm):
    class Meta:
        model = ChirunPackage
        fields = []

class ConfigForm(forms.ModelForm):
    config = forms.JSONField(widget=forms.HiddenInput)

    class Meta:

        model = ChirunPackage
        fields = []
