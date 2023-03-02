from .models import ChirunPackage
from django import forms
from django.utils.translation import gettext as _
from multiupload.fields import MultiFileField, MultiMediaField, MultiImageField

class UploadPackageForm(forms.ModelForm):
    files = MultiFileField(min_num=1)

    class Meta:
        model = ChirunPackage
        fields = []

class DeepLinkForm(forms.Form):
    package = forms.ModelChoiceField(queryset=ChirunPackage.objects.all())
    item = forms.CharField(required=False)
    title = forms.CharField()

class PackageFileForm(forms.ModelForm):
    content = forms.CharField(widget=forms.Textarea)

    class Meta:
        model = ChirunPackage
        fields = []

class DeleteFileForm(forms.ModelForm):
    class Meta:
        model = ChirunPackage
        fields = []

class What(forms.JSONField):
    def to_python(self, value):
        print(value)
        return super().to_python(value)

class ConfigForm(forms.ModelForm):
    config = What(widget=forms.HiddenInput)

    class Meta:

        model = ChirunPackage
        fields = []
