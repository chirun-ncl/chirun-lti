from .views import LoginView, LaunchView, JWKSView, register, TeacherLaunchView
from django.urls import path, include

app_name = 'lti'

urlpatterns = [
    path(r'login/', LoginView.as_view(), name='login'),
    path(r'register/', register, name='register'),
    path(r'launch/', LaunchView.as_view(), name='launch'),
    path(r'jwks/', JWKSView.as_view(), name='jwks'),
    path(r'launch/teacher/<str:launch_id>', TeacherLaunchView.as_view(), name='teacher_launch'),
]
