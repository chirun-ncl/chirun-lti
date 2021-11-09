<?php
class DashboardPublicAccessPage extends BaseDashboardContent {
	public $title = "Public Access";
	public $template = "dashboard/publicaccess.html";
	public function setup($resource){
		parent::setup($resource);
		if(empty($this->resource->module)){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
	}
}
?>
