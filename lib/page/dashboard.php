<?php
require_once(__DIR__.'/dashboard/options.php');
require_once(__DIR__.'/dashboard/adaptive.php');
require_once(__DIR__.'/dashboard/module.php');
require_once(__DIR__.'/dashboard/upload.php');
require_once(__DIR__.'/dashboard/logs.php');

class DashboardPage extends LTIPage {
	protected $resource_pk = NULL;
	protected $activePage = NULL;
	public $pageClass = array(
		'mod' => 'DashboardModuleSelectPage',
		'upload' => 'DashboardUploadPage',
		'adapt' => 'DashboardAdaptiveReleasePage',
		'log' => 'DashboardLogsPage',
		'opt' => 'DashboardOptionsPage');
	public $pageDesc = array(
		'mod' => 'Content',
		'upload' => 'Upload Document',
		'adapt' => 'Adaptive Release',
		'log' => 'Logs',
		'opt' => 'Options');
	public $pageNavIcon = array(
		'mod' => 'fa-book',
		'upload' => 'fa-upload',
		'adapt' => 'fa-clock-o',
		'log' => 'fa-bar-chart',
		'opt' => 'fa-wrench');

	public function getTitle(){
		$title = $this->title;
		if (isset($this->activePage)){
			$title = $this->activePage->title . " | " . $this->title; 
		}
		return $title;
	}

	public function setResource($resource_pk){
		$this->resource_pk = $resource_pk;
	}

	public function render(){
		$req = isset($_REQUEST['dashpage'])?$_REQUEST['dashpage']:'mod';
		if (array_key_exists($req,$this->pageClass)){
			$this->activePage = new $this->pageClass[$req];
		//} else if($this->isModuleEmpty()){
		//	$this->activePage = new DashboardNoModulePage();
		} else {
			$this->activePage = new DashboardModuleSelectPage();
		}
		parent::render();
	}

	protected function css(){
		$css = parent::css();
		$css .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
		$css .= '<link rel="stylesheet" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css">';
		return $css;
	}
	protected function js(){
		$js = parent::js();
		$js .= '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>';
		$js .= '<script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>';
		return $js;
	}
	protected function header(){
		$header = parent::header();
		$header .= <<< EOD
			<nav class="navbar navbar-expand-lg navbar-light bg-light">
				<a class="navbar-brand" href="index.php"><img src="{$this->webdir}/images/coursebuilder_icon_128.png" alt="Coursebuilder Logo" style="width:40px;"> Coursebuilder Dashboard</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="navbarSupportedContent">
					<ul class="navbar-nav mr-auto">
EOD;
		foreach($this->pageClass as $pageKey => $pageClass){
			$isActive = ($pageClass == get_class($this->activePage))?'active':'';
			$header .= <<< EOD
				<li class="nav-item {$isActive}">
					<a class="nav-link" href="index.php?dashpage={$pageKey}"><i class="fa {$this->pageNavIcon[$pageKey]}" aria-hidden="true"></i> {$this->pageDesc[$pageKey]}</a>
				</li>
EOD;
		}

		if(!$this->isModuleEmpty()){
			$header .= <<< EOD
				</ul>
				<ul class="navbar-nav">
					<li class="nav-item">
					<a class="nav-link" target="_blank" href="{$this->module->url()}?auth_level=1"><i class="fa fa-book" aria-hidden="true"></i> View All Content</a>
					</li>
					<li class="nav-item">
					<a class="nav-link" href="{$this->module->url()}?auth_level=0"><i class="fa fa-user-circle" aria-hidden="true"></i> Student View</a>
					</li>
				</ul>
EOD;
		}
		$header .= <<< EOD
				</div>
			</nav>

EOD;
		return $header;
	}

	protected function main(){
		$main = '<main role="main" class="mt-2 ml-3 mr-3">';
		$main .= '<div class="row">';
		$main .= '<div class="col">';
		foreach($this->alerts as $alert){
			$main .= $alert->html();
		}
		$main .= '</div>';
		$main .= '</div>';
		$this->activePage->setup($this->module,$this->db,$this->resource_pk);
		$main .= $this->activePage->main();
		$main .= '</main>';
		return $main;
	}
}
?>
