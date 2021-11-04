<?php
class DashboardPublicAccessPage extends BaseDashboardContent {
	public $title = "Public Access";
	public $template = "dashboard_publicaccess.html";
	public function setup($module, $db, $res){
		parent::setup($module, $db, $res);
		if(empty($this->module)){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
	}
}
?>
