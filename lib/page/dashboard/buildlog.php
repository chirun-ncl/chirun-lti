<?php
class DashboardBuildLogPage extends BaseDashboardContent {
	public $title = "BuildLog";
	public $template = "dashboard_buildlog.html";
	public $logContents = NULL;
	public $moduleSelected = False;
	public $logExists = False;

	public function setup($module, $db, $res){
		parent::setup($module, $db, $res);
		if(!empty($this->module)){
			$this->moduleSelected = True;
			$fullLogPath = INSTALLDIR .'/process/logs'. dirname($this->module->yaml_path).'.log';
			$this->logExists = file_exists($fullLogPath);
			if ($this->logExists){
				$this->logContents = file_get_contents($fullLogPath);
			}
		}
	}
}
?>
