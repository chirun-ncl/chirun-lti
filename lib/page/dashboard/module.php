<?php
class DashboardSelectedContentPage extends BaseDashboardContent {
	public $title = "Selected Content";
	public $template = "dashboard_module.html";

	public function setup($module, $db, $res){
		parent::setup($module, $db, $res);
		if(empty($this->module)){
			$_SESSION['ack_messages'] = False;
			header('Location: index.php?dashpage=upload');
			return;
		}
	}
}
?>