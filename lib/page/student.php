<?php
class StudentPage extends LTIPage {
	protected $reqContentError = NULL;
	public function render(){
		/*
			Render the page for students launching the Chirun LTI item
			First, check if there is specific requested content and render it if request granted.

			If the requested content was denied, or no direct path was requested, load the default
			page. This will either be the module introduction or the item set as "Direct linked" in
			the resource options for this item.
		*/
		if (!$this->renderAuthorisedContent()){
			// Don't report an error, just load the root of the module associated with
			// this resource instead
			$this->alerts = array();

			// Get the indexContent for the module
			$indexContent = $this->resource->module->indexContent(true);

			// Request the default item, this should succeed immediately if our LTI launch was successful
			// We force students to have authLevel = 0
			if($this->requestContentForModule($indexContent, $this->resource->module, 0, $this->reqContentError)){
				// Request granted, redirect to the default content to ensure relative paths work
				header("Location: ".WEBCONTENTDIR."/".$indexContent);
			} else {
				// Something still went wrong, show an error page
				$this->alerts[] = new Alert($this->reqContentError, "Error", "danger");
				$this->template = "error.html";
				$this->title = "Launch Error | Chirun";
				$this->errorTitle = "Launching Chirun Failed";
				$this->errorTemplate = "launch_failed.html";
				parent::render();
			}
		}
	}
}
?>
