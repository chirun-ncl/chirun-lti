<?php
class StudentPage extends ErrorPage {
	protected $reqContentError = NULL;
	protected function errorTitle(){
		return "Failed to load page";
	}
	public function render(){
		$ok = false;
		if(isset($this->requestedContent)){
			$ok = $this->renderRequestedContent();
		}
		if (!$ok){
			$path = '';
			$direct_link = $this->module->get_direct_linked_item();
			if(!empty($direct_link) && $direct_link->type != 'introduction'){
				$path = $direct_link->slug_path.'/';
				$path = ltrim($path,'/');
			}
			$indexContent = str_replace('{base}/','',$this->module->root_url);
			$indexContent = str_replace('{code}',$this->module->code,$indexContent);
			$indexContent = str_replace('{year}',$this->module->year,$indexContent);
			$indexContent = str_replace('{theme}',$this->module->selected_theme->path,$indexContent);
			$indexContent .= $path;
			$indexContent = ltrim($indexContent,'/');
			$this->reqContentError = $this->requestContentForModule($indexContent);
			if(empty($this->reqContentError)){
				header("Location: ".WEBCONTENTDIR."/".$indexContent);
			} else {
				$this->addAlert($this->reqContentError, "danger");
				$ok = false;
				parent::render();
				$this->main();
			}
		}
	}
}
?>
