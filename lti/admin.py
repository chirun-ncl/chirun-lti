from django.contrib import admin

from material.models import PackageLTIUse
from .models import Context, ResourceLink

# Register your models here.

class PackageLTIUseInline(admin.TabularInline):
    model = PackageLTIUse
    fields = ["package", "package_title"]
    readonly_fields = ["package", "package_title"]
    extra = 0
    def package_title(self,instance):
        return instance.package.name or instance.package.uid

class ResourceLinkInline(admin.TabularInline):
    model = ResourceLink
    fields = ["title","tool","resource_link_id"]
    readonly_fields = ["title","tool","resource_link_id"]
    extra = 0

class ContextAdmin(admin.ModelAdmin):
    fieldsets = [(None,{"fields": ["title","tool","context_id"]})]
    readonly_fields = ["title","tool","context_id"]

    list_display = ["title","tool","context_id"] 
    inlines = [PackageLTIUseInline,ResourceLinkInline]
    

admin.site.register(Context, ContextAdmin)