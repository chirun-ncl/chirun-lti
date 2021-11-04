<?php
class DashboardAdaptiveReleasePage extends BaseDashboardContent {
	public $title = "Adaptive Release";
	public $template = "dashboard_adaptive.html";
	public function setup($module, $db, $res){
		parent::setup($module, $db, $res);
		if($this->isModuleEmpty()){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
	}
}
?>
