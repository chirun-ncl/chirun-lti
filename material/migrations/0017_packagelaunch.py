# Generated by Django 4.2.6 on 2024-01-23 14:14

from django.db import migrations, models
import django.db.models.deletion
import django.utils.timezone


class Migration(migrations.Migration):

    dependencies = [
        ('lti', '0003_resourcelink'),
        ('material', '0016_build_log_files'),
    ]

    operations = [
        migrations.CreateModel(
            name='PackageLaunch',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('launch_time', models.DateTimeField(default=django.utils.timezone.now)),
                ('item', models.CharField(max_length=500)),
                ('theme', models.CharField(max_length=500)),
                ('link', models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, to='lti.resourcelink')),
                ('package', models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, to='material.chirunpackage')),
            ],
        ),
    ]
