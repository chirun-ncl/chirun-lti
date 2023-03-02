from django.conf import settings

class BackPageMixin:
    back_url = None

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        context['back_url'] = self.get_back_url()

        return context

    def get_back_url(self):
        return self.back_url

class HelpPageMixin:
    help_url = None

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        context['help_url'] = settings.HELP_URL + self.help_url

        return context
