<?php

class BaseDashboardContent {
	public $title = "";
	public $template = "dashboard/dashboard.html";
	protected $resource = NULL;
	protected $db = NULL;

	public function setup($resource){
		$this->resource = $resource;
	}
}

?>