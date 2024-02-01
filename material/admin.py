from typing import Any
from django.contrib import admin
from django.db.models import Max
from django.utils.translation import gettext_lazy as _

from django.utils import timezone
from datetime import timedelta

from .models import ChirunPackage

#ZZZZ To do: Need to add a field to chirun package listing any connected PackageLTIUse models

class LastCompiledListFilter(admin.SimpleListFilter):
    # Human-readable splitting of last compile times
    title = _("last compiled")
    parameter_name = "compile_threshold"

    def lookups(self, request, model_admin):
        return [
            ("na","never compiled"),
            ("<1d","within the last day"),
            ("<7d","within the last week"),
            ("<30d", "within the last month"),
            (">30d", "more than 30 days ago"),
            (">1y", "more than a year ago"),
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
            ("<30d", "within the last month"),
            (">30d", "more than 30 days ago"),
            (">1y", "more than a year ago"),
            (">3y", "more than a year ago"),
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



class ChirunPackageAdmin(admin.ModelAdmin):
    fieldsets = [(None,{"fields": ["title"]}),
                 ("UIDs",{"fields": ["uid","edit_uid"]}),
                 ("Status",{"fields":["last_compiled","last_launched"]}),
                 ("Git Connection",{"fields": ["git_url","git_username","git_status"],"classes": ["collapse"]})]
    list_display = ["title","uid","last_compiled","last_launched"] #("Status",{"list_display":["last_compiled"]})
    list_filter = [LastCompiledListFilter,LastLaunchedListFilter]
    readonly_fields = ["title","last_compiled","last_launched"]
    search_fields = ["uid","edit_uid","title"] #ZZZZ Searchable fields?


    def get_queryset(self,request):
        #add the sorting conditions for the last compiled and last launched functions.
        queryset = super().get_queryset(request)
        queryset = queryset.annotate(last_compiled_sort = Max("compilations__start_time"))
        queryset = queryset.annotate(last_launched_sort = Max("launches__launch_time"))
        return queryset
        


admin.site.register(ChirunPackage, ChirunPackageAdmin)



# Register your models here.
