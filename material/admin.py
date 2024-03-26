from typing import Any
from django.contrib import admin
from django.db.models import Max, OuterRef, Subquery, Q
from django.utils.translation import gettext_lazy as _

from django.utils import timezone
from datetime import timedelta

from .models import ChirunPackage, PackageLTIUse, Compilation

class DateThresholdFilter(admin.SimpleListFilter):
    def queryset(self, request, queryset):
        interval_str = self.value()

        if interval_str is None:
            return
        
        if interval_str == "na":
            return queryset.filter(**{f'{self.field_name}__isnull': True})

        comparison = 'gte' if interval_str[0] == '<' else 'lte'
        n = int(interval_str[1:-1])
        unit = {'d': 1, 'y': 365}[interval_str[-1]]

        interval = timedelta(days=unit * n)
        threshold = timezone.now() - interval

        return queryset.filter(**{f'{self.field_name}__{comparison}': threshold})


class LastCompiledListFilter(DateThresholdFilter):
    # Filter on when the package was last compiled
    title = _("last compiled")
    parameter_name = "compile_threshold"
    field_name = 'last_compiled'

    def lookups(self, request, model_admin):
        return [
            ("na", _("never built")),
            ("<1d", _("within the last day")),
            ("<7d", _("within the last week")),
            ("<30d", _("within the last month")),
            (">30d", _("more than 30 days ago")),
            (">1y", _("more than a year ago")),
        ]

class LastLaunchedListFilter(DateThresholdFilter):
    # Filter on when the package was las launched via LTI
    title = _("last launched")
    parameter_name = "launch_threshold"

    def lookups(self, request, model_admin):
        return [
            ("na","never launched"),
            ("<7d","within the last week"),
            ("<30d", "within the last 30 days"),
            (">30d", "more than 30 days ago"),
            (">1y", "more than a year ago"),
            (">3y", "more than three years ago"),
        ]

class BuildStatusFilter(admin.SimpleListFilter):
    title = _("build status")
    parameter_name = 'last_build_status'

    def lookups(self, request, model_admin):
        return Compilation.status.field.choices

    def queryset(self, request, queryset):
        status = self.value()

        if status == 'not_built':
            return queryset.filter(Q(last_build_status__isnull=True) | Q(last_build_status='not_built'))

        return queryset.filter(last_build_status = self.value())

class GitExistsListFilter(admin.SimpleListFilter):
    title = _("git connection")
    parameter_name = "git_linked"
    def lookups(self,request,model_admin):
        return [
            ("false","not connected"),
            ("true","connected")
        ]
    def queryset(self,request,queryset):
        if self.value() == "false":
            return queryset.filter(git_url = "")
        if self.value() == "true":
            return queryset.exclude(git_url = "")

class PackageLTIUseInline(admin.TabularInline):
    model = PackageLTIUse
    fields = ["lti_context","context_title"]
    readonly_fields = ["lti_context","context_title"]
    extra = 0
    def context_title(self,instance):
        return instance.lti_context

class ChirunPackageAdmin(admin.ModelAdmin):
    fieldsets = [(None,{"fields": ["name","author"]}),
                 ("UIDs",{"fields": ["uid","edit_uid"]}),
                 ("Status",{"fields":["created","last_compiled","build_status","last_launched"]}),
                 ("Git Connection",{"fields": ["git_url","git_username","git_status"],"classes": ["collapse"]})]
    list_display = ["name","author","uid","created","last_compiled","build_status","last_launched"]
    list_filter = [LastCompiledListFilter,BuildStatusFilter,LastLaunchedListFilter,GitExistsListFilter]
    list_display_links = ["name","uid"]
    readonly_fields = ["name","created","author","last_compiled","build_status","last_launched"]
    search_fields = ["uid","edit_uid","name","author"]
    inlines = [PackageLTIUseInline]

    @admin.display(description=_("Last compiled"))
    def last_compiled(self, package):
        return package.last_compiled

    @admin.display(description=_("Build status"))
    def build_status(self, package):
        return Compilation(package=package, status=package.last_build_status).get_status_display()

    @admin.display(description=_("Last launched"))
    def last_launched(self, package):
        return package.last_launched


admin.site.register(ChirunPackage, ChirunPackageAdmin)
