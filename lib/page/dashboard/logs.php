<?php
class DashboardLogsPage extends BaseDashboardContent {
	public $title = "Access Logs";
	public $template = "dashboard_accesslog.html";
	public $userSessions = NULL;
	public function setup($module, $db, $res){
		parent::setup($module, $db, $res);
		$this->userSessions = getAllUserSessions($this->db, $this->resource_pk);
	}
}
?>
