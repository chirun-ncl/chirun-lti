from .models import ChirunPackage
from django import forms
from django.utils.translation import gettext as _
from multiupload.fields import MultiFileField, MultiMediaField, MultiImageField

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
