from   django.conf import settings
from   django.conf.urls.static import static
from   django.contrib import admin
from   django.urls import path, include
import lti.views

urlpatterns = [
    path('', lti.views.IndexView.as_view(), name='index'),
    path('admin/', admin.site.urls),
    path("accounts/", include("django.contrib.auth.urls")),
    path('lti/', include('lti.urls', namespace='lti')),
    path('material/', include('material.urls', namespace='material')),
]

#The tutorial is needlessly complex on how to change the admin site properties,
#and says 'you can override site_header' without any links on how to do so.
#This seems to be the cleanest way as per:
#https://stackoverflow.com/questions/4938491/how-to-change-site-title-site-header-and-index-title-in-django-admin
admin.site.site_header = 'Chirun LTI Administration'
admin.site.site_title = 'Chirun LTI Admin'
admin.site.index_title = "Chirun LTI Administration"

urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
