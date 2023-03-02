from django.db import models
from pylti1p3.contrib.django.lti1p3_tool_config.models import LtiTool

class Context(models.Model):
    tool = models.ForeignKey(LtiTool, related_name='contexts', on_delete=models.CASCADE)
    context_id = models.CharField(max_length=200)
    title = models.CharField(max_length=500)

    def __str__(self):
        return f'{self.tool.title} - {self.title} ({self.context_id})'

class ResourceLink(models.Model):
    tool = models.ForeignKey(LtiTool, related_name='resource_links', on_delete=models.CASCADE)
    context = models.ForeignKey(Context, related_name='resource_links', on_delete=models.CASCADE)
    resource_link_id = models.CharField(max_length=200)
    title = models.CharField(max_length=500)

    def __str__(self):
        return f'{self.tool.title} - {self.title} ({self.resource_link_id})'
