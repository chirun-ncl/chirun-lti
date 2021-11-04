<?php
class DashboardDirectLinkPage extends BaseDashboardContent {
	public $title = "Direct Link";
	public $template = "dashboard_directlink.html";
	public $directLinkItem = NULL;
	public function setup($module, $db, $res){
		parent::setup($module, $db, $res);
		if($this->isModuleEmpty()){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
		$this->directLinkedItem = $this->module->get_direct_linked_item();
	}
}
?>
