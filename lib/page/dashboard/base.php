<?php

class BaseDashboardContent {
	use ModuleInfo;
	public $title = "";
	public $template = "dashboard.html";
	public $resource_options = NULL;
	protected $resource_pk = NULL;
	protected $db = NULL;

	public function setup($module, $db, $res){
		$this->module = $module;
		$this->resource_pk = $res;
		$this->db = $db;
		if(!empty($this->resource_pk)){
			$this->resource_options = getResourceOptions($this->db, $this->resource_pk);
		}
	}
}

?>