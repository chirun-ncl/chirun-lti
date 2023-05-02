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

urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
