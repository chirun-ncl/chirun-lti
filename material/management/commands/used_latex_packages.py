from collections import defaultdict
from django.core.management.base import BaseCommand
from django.db.models import OuterRef, Subquery
from material.models import ChirunPackage, Compilation
import os
from pathlib import Path
import re
import subprocess

from datetime import datetime

re_usepackage = re.compile(r'^[^%]*?\\usepackage[^{]*?\{(?P<names>[^}]+)\}', flags=re.M)

def packages_used_in_file(f):
    seen_names = set()

    with open(f) as fp:
        try:
            tex = fp.read()
        except UnicodeDecodeError as e:
            print(f)
            raise e

    try:
        tex = tex[:tex.index('\\begin{document}')]
    except ValueError:
        pass

    tex = re.sub(r'\\ifplastex(?:((?:.|\n)*?)\\else)?((?:.|\n)*?)\n\\fi\s*$', r'\1', tex, flags=re.M | re.I)

    for m in re_usepackage.findall(tex):
        names = re.split(r'\s*,\s*', m)
        seen_names.update(names)

    return seen_names

class Command(BaseCommand):
    help = 'Show all materials'

    def add_arguments(self, parser):
        parser.add_argument('--only-lti-uses', action='store_true', help="Only include packages which are used in an LTI link")

    def handle(self, *args, **options):
        last_build = Compilation.objects.filter(package=OuterRef('pk')).order_by('-end_time')

        packages = ChirunPackage.objects.annotate(last_build_status=Subquery(last_build.values('status')[:1])).filter(last_build_status='built')

        if options['only_lti_uses']:
            packages = packages.exclude(lti_uses=None)

        all_seen_names = set()

        package_uses = defaultdict(list)

        for p in packages:
            seen_names = set()
            for root, dirs, files in os.walk(p.absolute_extracted_path):
                for fname in files:
                    f = Path(root) / fname
                    if f.suffix != '.tex' or f.name.startswith('.'):
                        continue

                    seen_names.update(packages_used_in_file(f))

            print(p)
            print(seen_names)
            all_seen_names.update(seen_names)
            for name in seen_names:
                package_uses[name].append(p)

        print('\n---------\n')
        print(all_seen_names)

        for name, ps in sorted(package_uses.items()):
            print(name)
            for p in ps:
                print('   ','https://lti.chirun.org.uk'+p.get_absolute_url())
