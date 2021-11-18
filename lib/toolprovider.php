<?php
/*
 * This page processes a launch request from an LTI tool consumer.
 */

use IMSGlobal\LTI\Profile;
use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\Service;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

require_once(__DIR__.'/init.php');


class CBLTIToolProvider extends ToolProvider\ToolProvider {
	function __construct($data_connector) {
		parent::__construct($data_connector);
		$this->baseUrl = getAppUrl();
		$this->vendor = new Profile\Item('ncl', 'NCL', 'Newcastle University', 'https://www.ncl.ac.uk/');
		$this->product = new Profile\Item('coursebuilder', 'Coursebuilder', 'Coursebuilder',
			'https://www.mas-coursebuild.ncl.ac.uk/', VERSION);
		$requiredMessages = array(new Profile\Message('basic-lti-launch-request', 'connect.php', array('User.id', 'Membership.role')));
		$optionalMessages = array(new Profile\Message('ContentItemSelectionRequest', 'connect.php', array('User.id', 'Membership.role')),
			new Profile\Message('DashboardRequest', 'connect.php', array('User.id'), array('a' => 'User.id'), array('b' => 'User.id')));
		$this->resourceHandlers[] = new Profile\ResourceHandler(
			new Profile\Item('coursebuilder', 'Coursebuilder', 'Produce flexible and accessible notes, in a variety of formats, using LaTeX or Markdown source'), 'lti/images/coursebuilder_icon_512.png',
			$requiredMessages, $optionalMessages);
		$this->requiredServices[] = new Profile\ServiceDefinition(array('application/vnd.ims.lti.v2.toolproxy+json'), array('POST'));
	}

	function onLaunch() {
		global $db;
		// Initialise the user session
		$_SESSION['consumer_pk'] = $this->consumer->getRecordId();
		$_SESSION['resource_pk'] = $this->resourceLink->getRecordId();
		$_SESSION['user_consumer_pk'] = $this->user->getResourceLink()->getConsumer()->getRecordId();
		$_SESSION['user_resource_pk'] = $this->user->getResourceLink()->getRecordId();
		$_SESSION['user_pk'] = $this->user->getRecordId();
		$_SESSION['user_id'] = $this->user->getId();
		$_SESSION['user_email'] = $this->user->email;
		$_SESSION['user_fullname'] = $this->user->fullname;
		$_SESSION['isStudent'] = !$this->user->isStaff();
		$_SESSION['isAdmin'] = $this->user->isAdmin();
		$_SESSION['isStaff'] = $this->user->isStaff();
		$_SESSION['isContentItem'] = FALSE;

		// Set a cookie in the user's browser for persistence
		$new_token = bin2hex(openssl_random_pseudo_bytes(32));
		Session::addUserSession($db, $_SESSION['resource_pk'], $new_token, $_SESSION);
		setcookie("coursebuilder_session[{$_SESSION['user_resource_pk']}]",
			$new_token, time() + 24*3600, "/");
		setcookie("coursebuilder_user_id", $_SESSION['user_id'], time() + 24*3600, "/");

		// Redirect the user to display the list of items for the resource link
		$this->redirectUrl = getAppUrl();
	}

	function onContentItem() {
		// Check that the Tool Consumer is allowing the return of an LTI link
		$this->ok = in_array(ToolProvider\ContentItem::LTI_LINK_MEDIA_TYPE, $this->mediaTypes) || in_array('*/*', $this->mediaTypes);
		if (!$this->ok) {
			$this->reason = 'Return of an LTI link not offered';
		} else {
			$this->ok = !in_array('none', $this->documentTargets) || (count($this->documentTargets) > 1);
			if (!$this->ok) {
				$this->reason = 'No visible document target offered';
			}
		}
		if ($this->ok) {
			// Initialise the user session
			$_SESSION['consumer_pk'] = $this->consumer->getRecordId();
			$_SESSION['resource_id'] = getGuid();
			$_SESSION['resource_pk'] = NULL;
			$_SESSION['user_consumer_pk'] = $_SESSION['consumer_pk'];
			$_SESSION['user_pk'] = NULL;
			$_SESSION['isStudent'] = FALSE;
			$_SESSION['isContentItem'] = TRUE;
			$_SESSION['lti_version'] = $_POST['lti_version'];
			$_SESSION['return_url'] = $this->returnUrl;
			$_SESSION['title'] = isset($_POST['title'])?$_POST['title']:NULL;
			$_SESSION['text'] = isset($_POST['text'])?$_POST['text']:NULL;
			$_SESSION['data'] = isset($_POST['data'])?$_POST['data']:NULL;
			$_SESSION['document_targets'] = $this->documentTargets;
			// Redirect the user to display the list of items for the resource link
			$this->redirectUrl = getAppUrl();
		}
	}

	function onRegister() {
		// Initialise the user session
		$_SESSION['consumer_pk'] = $this->consumer->getRecordId();
		$_SESSION['tc_profile_url'] = $_POST['tc_profile_url'];
		$_SESSION['tc_profile'] = $this->consumer->profile;
		$_SESSION['return_url'] = $_POST['launch_presentation_return_url'];

		// Redirect the user to process the registration
		$this->redirectUrl = getAppUrl() . 'register.php';
	}

	function onError() {
		$msg = $this->message;
		if ($this->debugMode && !empty($this->reason)) {
			$msg = 'LTI Error: ' . $this->reason;
		}
		$loader = new FilesystemLoader('lib/templates');
		$twig = new Environment($loader, [
			'cache' => '/tmp/cb_site_cache_lti/php',
			'auto_reload' => true,
		]);

		$page = $twig->load('error.html');
		$this->errorOutput = $page->render(['errorTitle' => $msg]);
	}

}

?>
