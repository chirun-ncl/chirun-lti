<?php
class LTIPage extends BasePage {
	protected $authLevel = 0;
	protected $CBLTI = array(
		'api_path' => WEBDIR.'/api.php',
		'resource_pk' => NULL,
		'user_id' => NULL,
		'user_email' => NULL,
		'auth_method' => NULL,
		'auth_level' => 0
	);
	protected $authorisedContent = NULL;
	protected $webdir = WEBDIR;
	public $alerts = array();
	public $template = "base.html";
	public $title = "NCL Coursebuilder LTI Tool";
	public $css = array(
		'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
		'https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
		'css/styles.css',
	);
	public $js = array(
		'https://code.jquery.com/jquery-3.6.0.min.js',
		'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
	);

	public function __construct($db = NULL, $resource = NULL){
		$this->db = $db;
		$this->resource = $resource;

		// If an LTI launch and successful, setup the resource and auth information
		if(isset($resource)){
			$this->setResource($resource);
			$this->CBLTI['auth_method'] = 'LTI';
			if (isset($_SESSION['user_id'])){
				$this->CBLTI['user_id'] = $_SESSION['user_id'];
				$this->CBLTI['user_email'] = $_SESSION['user_email'];
			}
		}
	}

	public function setResource($resource){
		$this->resource = $resource;
		$this->CBLTI['resource_pk'] = $resource->resource_link_pk;

		// Use the module title for the title of the page
		if (!empty($resource->module->title)){
			$this->title = $resource->module->title;
		}
	}

	public function requestContent($contentPath, $authLevel = 0){
		/*
			Try to request access to a piece of content. The request is made first to the current
			resource's accociated module. Next the request is made to any modules/resources associated
			with valid sessions in the user's cookies. Finally a request is made to all modules marked
			as public.

			If the request is granted, and the module is not associated with the current resourse,
			the LTIPage's module property is updated to point to the module containing the requested
			content and the CBLTI object is updated to reflect the change in the active session.

			Returns true if successful, false otherwise.
		*/
		$success = false;
		$errorMsg = NULL;

		// Check if the requested content is part of the currently set module
		if(isset($this->resource) && $this->resource->isModuleEmpty()){
			$errorMsg = "No Coursebuilder content has been configured for this resource. Please check this page again later or contact your course instructor for advice.";
		} elseif(isset($this->resource)) {
			$success = $this->requestContentForModule($contentPath, $this->resource->module, $authLevel, $errorMsg);
		} else {
			$errorMsg = "You are not authorised to view this content.";
		}

		// Check the user's cookies for other valid sessions, the content may be part of some
		// other recently launched LTI content item.
		if (!$success && isset($_COOKIE['coursebuilder_session']) && isset($_COOKIE['coursebuilder_user_id'])){
			$ck_user_id = $_COOKIE['coursebuilder_user_id'];
			foreach ($_COOKIE['coursebuilder_session'] as $ck_resource_pk => $ck_token) {
				$session = Session::getUserSession($this->db, $ck_user_id, $ck_token);
				if(empty($session)) continue;
				$ck_resource = new Resource($this->db, $session['resource_link_pk']);
				if(isset($ck_resource->module)){
					if($success = $this->requestContentForModule($contentPath, $ck_resource->module, $authLevel)){
						$this->setResource($ck_resource);
						$this->CBLTI['auth_method'] = 'cookie';
						$this->CBLTI['user_id'] = $session['user_id'];
						$this->CBLTI['user_email'] = $session['user_email'];
						Session::setUserSession($session);
						break;
					}
				}
			}
		}

		// Check for module content marked public in the database.
		// Anyone is allowed to load content from those modules.
		if (!$success){
			foreach (Resource::getPublicModules($this->db) as $module){
				$pub_resource = new Resource($this->db, $module['resource_link_pk']);
				$moduleCode = $pub_resource->module->code;
				if(substr($contentPath, 0, strlen($moduleCode)) === $moduleCode){
					if($success = $this->requestContentForModule($contentPath, $pub_resource->module, $authLevel)){
						$this->setResource($pub_resource);
						$this->CBLTI['auth_method'] = 'anonymous';
						$this->CBLTI['user_id'] = NULL;
						$this->CBLTI['user_email'] = NULL;
						Session::addAnonymousUserSession($this->db, $pub_resource->resource_link_pk, getGuid());
						break;
					}
				}
			}
		}

		if(!$success){
			$this->alerts[] = new Alert($errorMsg, "Error", "danger");
		}

		return $success;
	}

	protected function requestContentForModule($contentPath, $module, $authLevel, &$errorMsg = NULL){
		/*
			Request access to a piece of content from a specfic module.

			For the request to be granted the content must be some subpath of the root
			content path of the module argument.

			The request is denied if authLevel = 0 and the requested content is hidden by
			adaptive release in the resource settings.

			Returns true if successful, false otherwise.
		*/
		$authorisedContent = NULL;
		$success = false;

		$moduleCode = $module->code;
		$fullContentPath = CONTENTDIR .'/'. $contentPath;
		
		// Check content path is a subpath of this module
		if(substr($contentPath, 0, strlen($moduleCode)) === $moduleCode){
			// Check if content path exists on disk
			if (is_file($fullContentPath)){
				$authorisedContent = $fullContentPath;
			}
			if (is_dir($fullContentPath)){
				if(is_file($fullContentPath.'/index.html')){
					$authorisedContent = $fullContentPath.'/index.html';
				} else if (is_file($fullContentPath.'/index.php')){
					$authorisedContent = $fullContentPath.'/index.php';
				}
			}
			if(isset($authorisedContent)){
				// Check to make sure the content is not hidden
				$matched_content = $module->get_content_for_path($fullContentPath);
				if($matched_content && $matched_content->hidden > 0 && $authLevel == 0){
					$errorMsg = "The requested content is not available.";
				} else {
					//All tests passed, grant access to the content.
					$this->authorisedContent = $authorisedContent;			
					$this->CBLTI['auth_level'] = $authLevel;
					$this->authLevel = $authLevel;
					$success = true;
				}
			} else $errorMsg = "The requested content item was not found.";
		} else $errorMsg = "You are not authorised to view this content.";

		return $success;
	}

	protected function filter_content($html){
		/* 
		   Applies filters to the provided HTML content.
		   TODO: Separate out into indiviual filters.
		*/
		$dom = new DomDocument;
		@$dom->loadHTML($html);

		// Inject the CBLTI JS object into the output, providing LTI authentication information
		// to the client in a form that can be used in JS.
		$head = $dom->getElementsByTagName('head');
		if (count($head) > 0){
			$script = $dom->createElement('script', 'var CBLTI = CBLTI || {}; CBLTI='.json_encode($this->CBLTI).';');
			$head->item(0)->appendChild($script);
		}

		/* Search for LTI hints in the html items and apply coursebuilder filters.
		   Hints such as 'lti-hint-item' are used to hide/remove content when required by
		   the resource's adaptive release settings.
		*/
		if ($this->authLevel == 0){
			foreach($this->resource->module->get_hidden() as $hidden_item){
				$xpath = new DomXPath($dom);
				$hidden_slug_ltrim = ltrim($hidden_item->slug_path,'/');

				if($hidden_item->type == 'introduction') {
					$nodes = $xpath->query("//*[contains(@class, 'lti-hint-introduction')]");
				} else if($hidden_item->type == 'part') {
					$nodes = $xpath->query("//*[contains(@class,'lti-hint-part') and .//a[contains(@href, '".$hidden_slug_ltrim."')]]");
				} else if($hidden_item->type == 'url') {
					$nodes = $xpath->query("//*[contains(@class,'lti-hint-item') and .//a[contains(@href, '".$hidden_item->source."')]]");
				} else {
					$nodes = $xpath->query("//*[contains(@class,'lti-hint-item') and .//a[contains(@href, '".$hidden_slug_ltrim."')]]");
				}

				foreach ($nodes as $node){
					$node->parentNode->removeChild($node);
				}
			}
			$xpath = new DomXPath($dom);
			$cleanup = $xpath->query("//*[contains(@class,'lti-hint-part') and not(.//ul/li)]");
			foreach ($cleanup as $node){
				$node->parentNode->removeChild($node);
			}
		}

		return html_entity_decode($dom->saveHTML());
	}

	protected function renderAuthorisedContent(){
		/* Render authorised content has been successfully requested. The content
		   is loaded from disk, then output to the client. PHP content is includ()-ed,
		   raw HTML content is first run through filters to check for CB LTI hints, and
		   any other content is output directly.

		   Returns true if authorised content is rendered, false otherwise.
		*/
		if(!isset($this->authorisedContent)) return false;
		$ext = pathinfo($this->authorisedContent, PATHINFO_EXTENSION);

		if ($ext === 'php'){
			include $this->authorisedContent;
			return true;
		} else if ($ext === 'html' or $ext === 'htm') {
			header('Content-Type: ' . get_file_mime_type($this->authorisedContent));
			header('Content-Disposition: inline; filename="'.basename($this->authorisedContent).'"');
			$file_content = file_get_contents($this->authorisedContent);
			$file_content = $this->filter_content($file_content);
			echo $file_content;
			return true;
		} else {
			header('Content-Type: ' . get_file_mime_type($this->authorisedContent));
			header('Content-Disposition: inline; filename="'.basename($this->authorisedContent).'"');
			$file_content = file_get_contents($this->authorisedContent);
			echo $file_content;
			return true;
		}

		return false;
	}

	public function render(){
		// If some content was requested successfully, render it.
		if(!$this->renderAuthorisedContent()){
			parent::render();
		}
	}
}
?>
