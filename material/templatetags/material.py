from   django import template
import urllib.parse

register = template.Library()

def urljoin(url, prefix):
    print(prefix)
    print(url)
    return urllib.parse.urljoin(str(prefix)+'/', str(url))

register.filter('urljoin', urljoin)
