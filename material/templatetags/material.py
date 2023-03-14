from   django import template
import urllib.parse

register = template.Library()

def urljoin(prefix, url):
    return urllib.parse.urljoin(str(prefix)+'/', str(url))

register.filter('urljoin', urljoin)
