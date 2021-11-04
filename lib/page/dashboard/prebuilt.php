<?php
class DashboardPrebuiltModuleSelectPage extends BaseDashboardContent {
	public $title = "Prebuilt Modules";
	public $template = "dashboard_prebuilt.html";

	public function setup($module, $db, $res){
		parent::setup($module, $db, $res);
		if(!empty($this->module)){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
		$this->allModules = getModules();
	}
}
?>
