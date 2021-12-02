<?php
class DashboardSelectedContentPage extends BaseDashboardContent {
	public $title = "Selected Content";
	public $template = "dashboard/module.html";

	public function setup($resource){
		parent::setup($resource);
		if(empty($_REQUEST['req_content']) && empty($this->resource->module)){
			$_SESSION['ack_messages'] = False;
			header('Location: index.php?dashpage=upload');
			return;
		}
	}
}
?>
