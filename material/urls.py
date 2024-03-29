from . import views
from django.urls import path, include

app_name = 'material'

urlpatterns = [
    path(r'new/',
        views.CreatePackageView.as_view(),
        name='new'
    ),

    path(r'git-clone/',
        views.CreatePackageFromGitView.as_view(),
        name='git_clone'
    ),

    path(r'package/<uuid:pk>/',
        views.ViewPackageView.as_view(),
        name='view'
    ),

    path(r'package/<uuid:pk>.zip',
        views.DownloadOutputView.as_view(),
        name='download_output'
    ),

    path(r'package/<uuid:pk>-source.zip',
        views.DownloadSourceView.as_view(),
        name='download_source'
    ),

    path(r'package/<uuid:pk>/delete',
        views.DeleteView.as_view(),
        name='delete'
    ),

    path(r'package/<uuid:pk>/upload',
        views.UploadFilesView.as_view(),
        name='upload'
    ),

    path(r'package/<uuid:pk>/file/',
        views.FileView.as_view(),
        name='file_root'
    ),

    path(r'package/<uuid:pk>/file/<path:path>',
        views.FileView.as_view(),
        name='file'
    ),

    path(r'package/<uuid:pk>/delete-file/<path:path>',
        views.DeleteFileView.as_view(),
        name='delete_file'
    ),

    path(r'package/<uuid:pk>/configure',
        views.ConfigView.as_view(),
        name='configure'
    ),

    path(r'package/<uuid:pk>/git/update',
        views.UpdateFromGitView.as_view(),
        name='git_update'
    ),

    path(r'package/<uuid:pk>/git/configure',
        views.ConfigureGitView.as_view(),
        name='git_configure'
    ),

    path(r'package/<uuid:pk>/git/webhook',
        views.GitWebhookView.as_view(),
        name='git_webhook'
    ),

    path(r'package/<uuid:pk>/build',
        views.BuildView.as_view(),
        name='build'
    ),

    path(r'package/<uuid:package_pk>/build/<int:pk>',
        views.BuildProgressView.as_view(),
        name='build_progress'
    ),

    path(r'deep-link/<str:launch_id>/',
        views.DeepLinkView.as_view(),
        name='deep_link'
    ),

    path(r'deep-link/<str:launch_id>/package/<uuid:pk>/build',
        views.DeepLinkBuildPackageView.as_view(),
        name='deep_link_build'
    ),
]
