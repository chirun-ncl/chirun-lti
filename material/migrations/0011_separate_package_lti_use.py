# Generated by Django 4.2.4 on 2023-08-02 12:12

from django.db import migrations, models
import django.db.models.deletion

def separate_package_lti_use(apps, schema_editor):
    ChirunPackage = apps.get_model('material', 'ChirunPackage')
    PackageLTIUse = apps.get_model('material', 'PackageLTIUse')

    for p in ChirunPackage.objects.exclude(lti_context=None):
        PackageLTIUse.objects.create(package = p, lti_context = p.lti_context)



class Migration(migrations.Migration):

    dependencies = [
        ('lti1p3_tool_config', '0002_alter_ltitool_id_alter_ltitoolkey_id'),
        ('lti', '0003_resourcelink'),
        ('material', '0010_auto_20230511_0909'),
    ]

    operations = [
        migrations.CreateModel(
            name='PackageLTIUse',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('lti_context', models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, related_name='chirun_packages', to='lti.context')),
                ('package', models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, related_name='lti_uses', to='material.chirunpackage')),
            ],
        ),
        migrations.RunPython(separate_package_lti_use, migrations.RunPython.noop),
        migrations.RemoveField(
            model_name='chirunpackage',
            name='lti_context',
        ),
        migrations.RemoveField(
            model_name='chirunpackage',
            name='lti_tool',
        ),
    ]
