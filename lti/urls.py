from . import views
from django.urls import path, include

app_name = 'lti'

urlpatterns = [
    path(r'login/', views.LoginView.as_view(), name='login'),
    path(r'register', views.RegisterView.as_view(), name='register'),
    path(r'register/dynamic', views.register, name='dynamic_registration'),
    path(r'launch/', views.LaunchView.as_view(), name='launch'),
    path(r'jwks/', views.JWKSView.as_view(), name='jwks'),
    path(r'launch/teacher/<str:launch_id>', views.TeacherLaunchView.as_view(), name='teacher_launch'),
    path(r'launch/student/<str:launch_id>', views.StudentLaunchView.as_view(), name='student_launch'),
]
