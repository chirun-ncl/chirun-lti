from .models import Context, ResourceLink
from django.core.exceptions import SuspiciousOperation
from django.http import HttpResponse, HttpResponseForbidden, HttpResponseBadRequest, JsonResponse
from django.shortcuts import render, redirect
from django.template.loader import get_template
from django.templatetags.static import static
from django.views import View
from django.views.generic import TemplateView, FormView, RedirectView
from django.views.decorators.http import require_POST
from django.urls import reverse, reverse_lazy
from material.models import ChirunPackage, PackageLaunch
from pathlib import PurePath
from pylti1p3.contrib.django import DjangoOIDCLogin, DjangoMessageLaunch, DjangoCacheDataStorage
from pylti1p3.contrib.django.lti1p3_tool_config import DjangoDbToolConf
from pylti1p3.contrib.django.lti1p3_tool_config.dynamic_registration import DjangoDynamicRegistration
from pylti1p3.exception import LtiException
import urllib.parse

PAGE_TITLE = 'Chirun'
PAGE_DESCRIPTION = 'The Chirun tool for creating accessible online course material.'


class IndexView(TemplateView):
    template_name = 'lti/index.html'

class RegisterView(TemplateView):
    template_name = 'registration/begin.html'

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        context['register_url'] = self.request.build_absolute_uri(reverse('lti:dynamic_registration'))

        return context

class LTIView:
    """
        A view mixin which adds a message_launch object to the view object.
        The message launch data is loaded from POST parameters, so this .

        For views which need access to the launch data after the launch/login flow, use CachedLTIView.
    """

    tool_conf = DjangoDbToolConf()
    launch_data_storage = DjangoCacheDataStorage()

    message_launch_cls = DjangoMessageLaunch

    """
        Should this view try to get the message launch at the start of the dispatch method?
        Return False if this view should sometimes work without an LTI launch.
    """
    get_message_launch_on_dispatch = True

    def dispatch(self, request, *args, **kwargs):
        if self.get_message_launch_on_dispatch:
            try:
                self.get_lti_data()
            except LtiException as e:
                message = str(e)
                error = self.request.POST.get('error')
                if error is not None:
                    message = self.request.POST.get('error_description', f"There was an error getting LTI data: {error}")
                    template = get_template('lti/launch_error.html')
                    return HttpResponseBadRequest(template.render({'message': message}))
                raise SuspiciousOperation(message)
        return super().dispatch(request, *args, **kwargs)

    def get_lti_data(self):
        self.message_launch = self.get_message_launch()
        self.lti_tool = self.get_lti_tool()
        self.lti_context = self.get_lti_context()
        self.lti_resource_link = self.get_lti_resource_link()

    def get_message_launch(self):
        message_launch = self.message_launch_cls(self.request, self.tool_conf, launch_data_storage = self.launch_data_storage)
        return message_launch

    def get_lti_tool(self):
        iss = self.message_launch.get_iss()
        client_id = self.message_launch.get_client_id()
        tool = self.message_launch.get_tool_conf().get_lti_tool(iss, client_id)
        return tool

    def get_lti_context(self):
        message_launch_data = self.message_launch.get_launch_data()

        context_claim = message_launch_data.get('https://purl.imsglobal.org/spec/lti/claim/context',{})
        context_id = str(context_claim.get('id',''))
        context_title = context_claim.get('title','')

        context, created = Context.objects.update_or_create(
            tool = self.lti_tool,
            context_id = context_id,
            defaults={'title': context_title,}
        )

        return context

    def get_lti_resource_link(self):
        message_launch_data = self.message_launch.get_launch_data()

        resource_link_claim = message_launch_data.get('https://purl.imsglobal.org/spec/lti/claim/resource_link')
        if resource_link_claim is None:
            # Resource link might not exist yet if this is a deep linking launch.
            return

        resource_link_id = str(resource_link_claim.get('id',''))
        resource_link_title = resource_link_claim.get('title','')

        resource_link, created = ResourceLink.objects.update_or_create(
            tool = self.lti_tool,
            context = self.lti_context,
            resource_link_id = resource_link_id,
            defaults={'title': resource_link_title,}
        )

        return resource_link

    def get_custom_param(self, param_name):
        message_launch_data = self.message_launch.get_launch_data()

        return message_launch_data.get('https://purl.imsglobal.org/spec/lti/claim/custom', {})\
            .get(param_name)

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)

        if hasattr(self, 'message_launch'):
            context['message_launch'] = self.message_launch

        return context
    

class CachedLTIView(LTIView):
    """
        A view mixin which adds a message_launch object to the view object.
        The message launch data is loaded from cache storage.

        The ID of the launch must be provided either as a POST parameter or in the query part of the URL under the key defined by the launch_id_param property.
    """

    launch_id_param = 'launch_id'

    def get_launch_id(self):
        post_param = self.request.POST.get(self.launch_id_param, self.request.GET.get(self.launch_id_param))
        if post_param:
            return post_param
        
        return self.kwargs.get(self.launch_id_param)

    def get_message_launch(self):
        launch_id = self.get_launch_id()

        if launch_id is None:
            raise SuspiciousOperation

        message_launch = self.message_launch_cls.from_cache(launch_id, self.request, self.tool_conf, launch_data_storage = self.launch_data_storage)

        return message_launch

class LoginView(LTIView, View):
    """
        LTI login: verify the credentials, and redirect to the target link URI given in the launch parameters.
        The OIDCLogin object handles checking that cookies can be set.
    """
    http_method_names = ['post', 'get']

    def get_launch_url(self):
        """
            Get the intended launch URL during a login request.
        """
        target_link_uri = self.request.POST.get('target_link_uri', self.request.GET.get('target_link_uri'))
        if not target_link_uri:
            raise Exception('Missing "target_link_uri" param')
        return target_link_uri

    def dispatch(self, request, *args, **kwargs):
        
        oidc_login = DjangoOIDCLogin(request, self.tool_conf, launch_data_storage = self.launch_data_storage)
        target_link_uri = self.get_launch_url()
        return oidc_login\
            .enable_check_cookies()\
            .redirect(target_link_uri)

class LaunchView(LTIView, TemplateView):
    """
        Handle a launch activity.

        There are several kinds of launch; the kind of launch is given by the message_launch object.
    """
    http_method_names = ['post']


    def post(self, request, *args, **kwargs):
        launch_id = self.message_launch.get_launch_id()
        
        if self.message_launch.check_teacher_access() or self.message_launch.check_teaching_assistant_access() or self.message_launch.check_staff_access():
            if self.message_launch.is_deep_link_launch():
                return redirect(reverse('material:deep_link', args=(launch_id,)))
            elif self.message_launch.is_resource_launch():
                return redirect(reverse('lti:teacher_launch', args=(launch_id,)))
            else:
                return HttpResponseBadRequest("This launch type is not recognised.")
        elif self.message_launch.check_student_access():
            if self.message_launch.is_resource_launch():
                return redirect(reverse('lti:student_launch', args=(launch_id,)))
            else:
                return HttpResponseBadRequest("This launch type is not recognised.")
        else:
            return HttpResponseBadRequest(f"You have an unknown role.")
    

class JWKSView(View):
    """
        Return the tool's JSON Web Key Set.
    """
    tool_conf = DjangoDbToolConf()
    def get(self, request, *args, **kwargs):
        return JsonResponse(self.tool_conf.get_jwks(), safe=False)

class DynamicRegistration(DjangoDynamicRegistration):
    """
        Dynamic registration handler.
    """
    client_name = PAGE_TITLE
    description = PAGE_DESCRIPTION
    logo_file = 'chirun_icon_512.png'

    initiate_login_url = reverse_lazy('lti:login')
    jwks_url = reverse_lazy('lti:jwks')
    launch_url = reverse_lazy('lti:launch')

    def get_claims(self):
        return ['iss', 'sub', 'name']

    def get_scopes(self):
        return [
            'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly',
        ]

    def get_messages(self):
        return [{
            'type': 'LtiDeepLinkingRequest',
            'target_link_uri': self.request.build_absolute_uri(reverse('lti:launch')),
            'label': 'New tool link',
        }]

def register(request):
    """
        Dynamic registration view.
        Triggers the dynamic registration handler, which creates an LtiTool entry in the database.
        Returns a page which does a JavaScript postMessage call to the platform to tell it that registration is complete.
    """

    registration = DynamicRegistration(request)

    lti_tool = registration.register()

    return HttpResponse(registration.complete_html())

def canvas_config_json(request):
    icon_url = request.build_absolute_uri(static("chirun_icon_512.png"))

    iss = request.GET.get('iss','https://canvas.instructure.com')
    iss = urllib.parse.quote_plus(iss)

    config = {
        "title": "Chirun",
        "description": "Chirun accessible course notes",
        "oidc_initiation_url": request.build_absolute_uri(reverse('lti:login')),
        "target_link_uri": request.build_absolute_uri(reverse('lti:launch')),
        "custom_fields": {},
        "scopes": [
            "https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly"
        ],
        "extensions": [
            {
                "domain": request.get_host(),
                "tool_id": "chirun",
                "platform": "canvas.instructure.com",
                "settings": {
                    "text": "Chirun",
                    "icon_url": icon_url,
                    "platform": "canvas.instructure.com",
                    "placements":[
                        {
                            "text": "Chirun material",
                            "enabled": True,
                            "icon_url": icon_url,
                            "placement": "editor_button",
                            "message_type": "LtiDeepLinkingRequest",
                            "target_link_uri": request.build_absolute_uri(reverse('lti:launch')),
                            "canvas_icon_class": "icon-lti"
                        },
                        {
                            "text": "Embed Chirun material",
                            "enabled": True,
                            "icon_url": icon_url,
                            "placement": "link_selection",
                            "message_type": "LtiDeepLinkingRequest",
                            "target_link_uri": request.build_absolute_uri(reverse('lti:launch')),
                            "canvas_icon_class": "icon-lti"
                        }
                    ]
                }
            }
        ],
        "public_jwk_url": request.build_absolute_uri(reverse('lti:jwks'))+"?iss="+iss,
        "public_jwk": {},
    }
    return JsonResponse(config)

class TeacherLaunchView(CachedLTIView, TemplateView):
    template_name = 'lti/teacher_launch.html'

    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        
        package_uid = self.get_custom_param('package')
        item = self.get_custom_param('item')
        theme = self.get_custom_param('theme')
        package = ChirunPackage.objects.get(uid = package_uid)

        context['package'] = package
        context['item'] = item
        context['theme'] = theme
        context['resource_link'] = self.get_lti_resource_link()

        context['view_url'] = PurePath(package.get_output_url()) / theme / item

        return context

class StudentLaunchView(CachedLTIView, RedirectView):


    def record_package_launch(self, package, item, theme):
        launch_record = PackageLaunch.objects.create(
            package=package,
            link=self.lti_resource_link,
            item = item,
            theme = theme        
        ) 
        return launch_record

    def get_redirect_url(self, *args, **kwargs):
        
        package_uid = self.get_custom_param('package')
        item = self.get_custom_param('item')
        theme = self.get_custom_param('theme')
        package = ChirunPackage.objects.get(uid = package_uid)
        launch_record = self.record_package_launch(package = package, item = item, theme = theme)

        url = PurePath(package.get_output_url()) / theme / item
        return str(url)
