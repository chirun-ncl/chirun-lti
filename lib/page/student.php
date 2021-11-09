<?php
class StudentPage extends LTIPage {
	protected $reqContentError = NULL;
	public function render(){
		/*
			Render the page for students launching the Coursebuilder LTI item
			First, check if there is specific requested content and render it if request granted.

			If the requested content was denied, or no direct path was requested, load the default
			page. This will either be the module introduction or the item set as "Direct linked" in
			the resource options for this item.
		*/
		if (!$this->renderAuthorisedContent()){
			// Don't report an error, just load the root of the module associated with
			// this resource instead
			$this->alerts = array();
			$path = '';

			// However, find the direct linked item if it exists for this resource,
			// and set the path accordingly if so
			$direct_link = $this->resource->module->get_direct_linked_item();
			if(!empty($direct_link) && $direct_link->type != 'introduction'){
				if(strcmp($direct_link->type,'html')==0){
					$path = $direct_link->slug_path;
				} else {
					$path = $direct_link->slug_path.'/';
				}
				$path = ltrim($path,'/');
			}

			if($this->resource->isModuleStandalone()){
				$this->resource->isModuleStandalone($path);
			}

			// Get the full path to the item, including the path to the module
			// associated with this resource
			$indexContent = str_replace('{base}/','',$this->resource->module->root_url);
			$indexContent = str_replace('{code}',$this->resource->module->code, $indexContent);
			$indexContent = str_replace('{year}',$this->resource->module->year, $indexContent);
			$indexContent = str_replace('{theme}',$this->resource->module->selected_theme->path, $indexContent);
			$indexContent .= $path;
			$indexContent = ltrim($indexContent,'/');

			// Request the default item, this should succeed immediately if our LTI launch was successful
			// We force students to have authLevel = 0
			if($this->requestContentForModule($indexContent, $this->resource->module, 0, $this->reqContentError)){
				// Request granted, redirect to the default content to ensure relative paths work
				header("Location: ".WEBCONTENTDIR."/".$indexContent);
			} else {
				// Something still went wrong, show an error page
				$this->alerts[] = new Alert($this->reqContentError, "Error", "danger");
				$this->template = "error.html";
				$this->title = "Launch Error | NCL Coursebuilder";
				$this->errorTitle = "Launching NCL Coursebuilder Failed";
				$this->errorTemplate = "launch_failed.html";
				parent::render();
			}
		}
	}
}
?>
