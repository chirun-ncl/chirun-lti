<?php
class DashboardBuildLogPage extends BaseDashboardContent {
	public $title = "BuildLog";
	public $template = "dashboard/buildlog.html";
	public $logContents = NULL;
	public $moduleSelected = False;
	public $logExists = False;

	public function setup($resource){
		parent::setup($resource);
		if(!empty($this->resource->module)){
			$this->moduleSelected = True;
			$fullLogPath = INSTALLDIR .'/process/logs'. dirname($this->resource->module->yaml_path).'.log';
			$this->logExists = file_exists($fullLogPath);
			if ($this->logExists){
				$this->logContents = file_get_contents($fullLogPath);
			}
		}
	}
}
?>
