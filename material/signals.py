def delete_package_files(sender, instance, **kwargs):
    from . import tasks
    tasks.delete_package_files(instance)
