<?php
class BasePage {
	public $page = "";
	public function render() {
		$this->page .= $this->header();
		$this->page .= $this->main();
		$this->page .= $this->footer();
		echo $this->page;
	}
	protected function header() {
		return "";
	}
	protected function main() {
		return "";
	}
	protected function footer() {
		return "";
	}
}

trait ModulePage {
	protected $module = NULL;
	protected $authLevel = 0;
	protected $requestedContent = NULL;
	public function setModule($module){
		$this->module = $module;
		if (!empty($this->module->title)){
			$this->title = $module->title;
		}
	}
	public function isModuleEmpty(){
		if (!isset($this->module)) return true;
		if (empty($this->module->title)) return true;
		return false;
	}
	public function requestContent($db, $contentPath, $authLevel = 0){
		$error = NULL;
		
		if (isset($this->module)){
			$error = $this->requestContentForModule($contentPath, $authLevel);
		} else {
			$error = "Module has not yet been configured";
		}

		if (!empty($error) && isset($_COOKIE['coursebuilder_session'])){
			//Check the user's cookies for other sessions
			foreach ($_COOKIE['coursebuilder_session'] as $ck_resource_pk => $ck_token) {
				$session = getUserSession($db, $_SESSION['user_id'], $ck_token);
				if(empty($session)) continue;

				$ck_module = getSelectedModule($db, $ck_resource_pk);
				if(isset($ck_module)){
					$ck_module->apply_content_overrides($db);
					$this->setModule($ck_module);
					setUserSession($session);
					$error = $this->requestContentForModule($contentPath, $authLevel);
					if(empty($error)) break;
				}
			}
		}

		return $error;
	}

	protected function requestContentForModule($contentPath, $authLevel = 0){
		$authModule = $this->module->code . '/' . $this->module->year;
		$fullContentPath = CONTENTDIR .'/'. $contentPath;
		$this->requestedContent = -1;
		$this->authLevel = $authLevel;
		
		if(substr($contentPath, 0, strlen($authModule)) === $authModule){
			if (is_file($fullContentPath)){
				$requestedContent = $fullContentPath;
			}
			if (is_dir($fullContentPath)){
				if(is_file($fullContentPath.'/index.html')){
					$requestedContent = $fullContentPath.'/index.html';
				} else if (is_file($fullContentPath.'/index.php')){
					$requestedContent = $fullContentPath.'/index.php';
				}
			}
			if(!isset($requestedContent)) return "The requested content item was not found.";
			$matched_content = $this->module->get_content_for_path($fullContentPath);
			if($matched_content && $matched_content->hidden>0 && $this->authLevel == 0){
				return "The requested content is not available.";
			}

			//All tests passed, We're good to print the requested content
			$this->requestedContent = $requestedContent;			
			return NULL;
		}
		return "You are not authorised to view this content.";
	}

	protected function filter_content($html){
		//
		// This function searches the DOM for content that is supposed to be hidden and removes it.
		//
		$dom = new DomDocument;
		@$dom->loadHTML($html);
		foreach($this->module->get_hidden() as $hidden_item){
			$xpath = new DomXPath($dom);

			if($hidden_item->type == 'introduction') {
				$nodes = $xpath->query("//*[contains(@class, 'lti-hint-introduction')]");
			} else if($hidden_item->type == 'part') {
				$nodes = $xpath->query("//*[contains(@class,'lti-hint-part') and .//a[contains(@href, '".$hidden_item->slug_path."')]]");
			} else {
				$nodes = $xpath->query("//*[contains(@class,'lti-hint-item') and .//a[contains(@href, '".$hidden_item->slug_path."')]]");
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
		return html_entity_decode($dom->saveHTML());
	}

	protected function renderRequestedContent(){
		if(!isset($this->requestedContent)) return false;
		if($this->requestedContent<0) return false;
		$ext = pathinfo($this->requestedContent, PATHINFO_EXTENSION);
		if ($ext === 'php'){
			include $this->requestedContent;
			return true;
		} else if ($ext === 'html' or $ext === 'htm') {
			header('Content-Type: ' . get_file_mime_type($this->requestedContent));
			header('Content-Disposition: inline; filename="'.basename($this->requestedContent).'"');
			$file_content = file_get_contents($this->requestedContent);
			if ($this->authLevel == 0){
				$file_content = $this->filter_content($file_content);
			}
			echo $file_content;
			return true;
		} else {
			header('Content-Type: ' . get_file_mime_type($this->requestedContent));
			header('Content-Disposition: inline; filename="'.basename($this->requestedContent).'"');
			$file_content = file_get_contents($this->requestedContent);
			echo $file_content;
			return true;
		}
		return false;
	}
}

class Alert {
	public $text = "";
	public $lead = "";
	public $level = "primary";
	public function __construct($alertText){
		$this->text = $alertText;
	}
	public function setLevel($alertLevel){
		$this->level = $alertLevel;
		if($this->level == 'danger'){
			$this->lead = "<strong>Error:</strong>";
		}
	}
	public function html(){
		$html = <<< EOD
			<div class="alert alert-{$this->level}" role="alert">
			{$this->lead} {$this->text}
			</div>
EOD;
		return $html;
	}
}
?>
