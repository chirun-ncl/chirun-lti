from typing import Any
from django.contrib import admin
from django.db.models import Max, OuterRef, Subquery
from django.utils.translation import gettext_lazy as _

from django.utils import timezone
from datetime import timedelta

from .models import ChirunPackage, PackageLTIUse, Compilation

class LastCompiledListFilter(admin.SimpleListFilter):
    # Human-readable splitting of last compile times
    title = _("last compiled")
    parameter_name = "compile_threshold"

    def lookups(self, request, model_admin):
        return [
            ("na", _("never built")),
            ("<1d", _("within the last day")),
            ("<7d", _("within the last week")),
            ("<30d", _("within the last month")),
            (">30d", _("more than 30 days ago")),
            (">1y", _("more than a year ago")),
        ]

    def queryset(self, request, queryset):
        if self.value() == "na":
            return queryset.filter(
                last_compiled_sort__isnull = True
                )
        if self.value() == "<1d":
            return queryset.filter(
                last_compiled_sort__gte = timezone.now() - timedelta(days = 1)
                )
        if self.value() == "<7d":
            return queryset.filter(
                last_compiled_sort__gte = timezone.now() - timedelta(days = 7)
                )
        if self.value() == "<30d":
            return queryset.filter(
                last_compiled_sort__gte = timezone.now() - timedelta(days = 30)
                )
        if self.value() == ">30d":
            return queryset.filter(
                last_compiled_sort__lte = timezone.now() - timedelta(days = 30)
                )
        if self.value() == ">1y":
            return queryset.filter(
                last_compiled_sort__lte = timezone.now() - timedelta(days = 365)
                )

class LastLaunchedListFilter(admin.SimpleListFilter):
    # Human-readable splitting of last compile times
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
    def queryset(self, request, queryset):
        if self.value() == "na":
            return queryset.filter(
                last_launched_sort__isnull = True
                )
        if self.value() == "<7d":
            return queryset.filter(
                last_launched_sort__gte = timezone.now() - timedelta(days = 7)
                )
        if self.value() == "<30d":
            return queryset.filter(
                last_launched_sort__gte = timezone.now() - timedelta(days = 30)
                )
        if self.value() == ">30d":
            return queryset.filter(
                last_launched_sort__lte = timezone.now() - timedelta(days = 30)
                )
        if self.value() == ">1y":
            return queryset.filter(
                last_launched_sort__lte = timezone.now() - timedelta(days = 365)
                )
        if self.value() == ">3y":
            return queryset.filter(
                last_launched_sort__lte = timezone.now() - timedelta(days = 1096)
                )

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
        
class LastBuildStatusListFilter(admin.SimpleListFilter):
    title = _("last build status")
    parameter_name = "built"
    def lookups(self,request,model_admin):
        return [
            ("building",_("building")),
            ("built",_("built")),
            ("error",_("error")),
            ("not",_("not built")),
        ]
    def queryset(self,request,queryset):
        if self.value() == "building":
            return queryset.filter(last_build_status = "building")
        if self.value() == "built":
            return queryset.filter(last_build_status = "built")
        if self.value() == "error":
            return queryset.filter(last_build_status = "error")
        if self.value() == "not":
            return queryset.filter(last_build_status = "not_built") | queryset.filter(last_compiled_sort__isnull = True)

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
    list_filter = [LastCompiledListFilter,LastLaunchedListFilter,GitExistsListFilter,LastBuildStatusListFilter]
    list_display_links = ["name","uid"]
    readonly_fields = ["name","created","author","last_compiled","build_status","last_launched"]
    search_fields = ["uid","edit_uid","name","author"]
    inlines = [PackageLTIUseInline]

    def get_queryset(self,request):
        last_compilations = Compilation.objects.filter(package = OuterRef("uid")).exclude(end_time=None).order_by("-end_time")
        #add the sorting conditions for the last compiled and last launched functions.
        queryset = super().get_queryset(request) \
            .annotate(last_compiled_sort = Max("compilations__start_time")) \
            .annotate(last_launched_sort = Max("launches__launch_time")) \
            .annotate(last_build_status = Subquery(last_compilations.values("status")[:1]))
        return queryset
        


admin.site.register(ChirunPackage, ChirunPackageAdmin)
