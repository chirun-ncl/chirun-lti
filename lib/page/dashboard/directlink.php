<?php
class DashboardDirectLinkPage extends BaseDashboardContent {
	public $title = "Direct Link";
	public $template = "dashboard/directlink.html";
	public $directLinkItem = NULL;
	public function setup($resource){
		parent::setup($resource);
		if($this->resource->isModuleEmpty()){
			$_SESSION['ack_messages'] = False;
			header("Location: index.php");
		}
		$this->directLinkedItem = $this->resource->module->get_direct_linked_item();
	}
}
?>
