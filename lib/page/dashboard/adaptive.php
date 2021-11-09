<?php
class DashboardAdaptiveReleasePage extends BaseDashboardContent {
	public $title = "Adaptive Release";
	public $template = "dashboard/adaptive.html";
	public function setup($resource){
		parent::setup($resource);
		if($this->resource->isModuleEmpty()){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
	}
}
?>
