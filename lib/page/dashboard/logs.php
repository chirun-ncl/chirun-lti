<?php
class DashboardLogsPage extends BaseDashboardContent {
	public $title = "Access Logs";
	public $template = "dashboard/accesslog.html";
	public $userSessions = NULL;
	public function setup($resource){
		parent::setup($resource);
		$this->userSessions = $resource->getAllUserSessions();
	}
}
?>
