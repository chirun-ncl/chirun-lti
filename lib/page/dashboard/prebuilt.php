<?php
class DashboardPrebuiltModuleSelectPage extends BaseDashboardContent {
	public $title = "Prebuilt Modules";
	public $template = "dashboard/prebuilt.html";

	public function setup($resource){
		parent::setup($resource);
		if(!empty($this->resource->module)){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
		$this->allModules = getModules();
	}
}
?>
