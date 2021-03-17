<?php

require_once(__DIR__.'/dashboard/directlink.php');
require_once(__DIR__.'/dashboard/adaptive.php');
require_once(__DIR__.'/dashboard/module.php');
require_once(__DIR__.'/dashboard/prebuilt.php');
require_once(__DIR__.'/dashboard/upload.php');
require_once(__DIR__.'/dashboard/logs.php');
require_once(__DIR__.'/dashboard/buildlog.php');
require_once(__DIR__.'/dashboard/publicaccess.php');

class DashboardPage extends LTIPage {
	protected $resource_pk = NULL;
	protected $activePage = NULL;
	protected $pageStructure = array(
		'content' => array(
			'navTitle' => 'Content',
			'navIcon'  => 'fa-file-text-o',
			'items'    => array(
				'selected' => array(
					'navTitle'  => 'Selected Content',
					'navIcon'   => 'fa-file-text-o'
				),
				'upload'   => array(
					'navTitle'  => 'Upload Content',
					'navIcon'   => 'fa-cloud-upload'
				)
			)
		),
		'access' => array(
			'navTitle' => 'Access Control',
			'navIcon'  => 'fa-unlock-alt',
			'items'    => array(
				'adaptive' => array(
					'navTitle'  => 'Adaptive Release',
					'navIcon'   => 'fa-clock-o'
				),
				'directlink'   => array(
					'navTitle'  => 'Direct Link',
					'navIcon'   => 'fa-link',
				),
				'publicaccess'   => array(
					'navTitle'  => 'Public Access',
					'navIcon'   => 'fa-globe',
				)
			)
		),
		'logs' => array(
			'navTitle' => 'Logs',
			'navIcon'  => 'fa-list-alt',
			'items'    => array(
				'accesslog' => array(
					'navTitle'  => 'Access Log',
					'navIcon'   => 'fa-bar-chart'
				),
				'buildlog'  => array(
					'navTitle'  => 'Build Log',
					'navIcon'   => 'fa-code'
				)
			)
		),
		'view' => array(
			'navTitle' => 'View Content',
			'navIcon'  => 'fa-eye',
			'items'    => array(
				'viewall' => array(
					'navTitle' => 'View All Content',
					'navIcon'  => 'fa-file-text-o'
				),
				'view'    => array(
					'navTitle'  => 'View as Student',
					'navIcon'   => 'fa-user'
				)
			)
		)
	);
	private $pageClass = array(
		'selected'     => 'DashboardSelectedContentPage',
		'prebuilt'     => 'DashboardPrebuiltModuleSelectPage',
		'upload'       => 'DashboardUploadPage',
		'adaptive'     => 'DashboardAdaptiveReleasePage',
		'accesslog'    => 'DashboardLogsPage',
		'buildlog'     => 'DashboardBuildLogPage',
		'directlink'   => 'DashboardDirectLinkPage',
		'publicaccess' => 'DashboardPublicAccessPage'
	);

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
		$req = isset($_REQUEST['dashpage'])?$_REQUEST['dashpage']:'selected';
		if (array_key_exists($req,$this->pageClass)){
			$this->activePage = new $this->pageClass[$req];
		} else {
			$this->activePage = new DashboardSelectedContentPage();
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
		if(in_array($_SESSION['user_id'],AUTH_USER_IDS) || in_array(strtolower($_SESSION['user_email']),AUTH_USER_EMAILS)){
			$this->pageStructure['content']['items']['prebuilt'] = array(
				'navTitle'  => 'Prebuilt Modules',
				'navIcon'   => 'fa-list'
			);
		}
		$header .= <<< EOD
			<nav class="navbar navbar-expand-lg navbar-light bg-light">
				<a class="navbar-brand" href="index.php"><img src="{$this->webdir}/images/coursebuilder_icon_128.png" alt="Coursebuilder Logo" style="width:40px;"> Coursebuilder Dashboard</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="navbarSupportedContent">
					<ul class="navbar-nav mr-auto">
EOD;
		foreach($this->pageStructure as $navHeading){
			$header .= <<< EOD
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="fa {$navHeading['navIcon']}" aria-hidden="true"></i> {$navHeading['navTitle']}
					</a>
					<div class="dropdown-menu" aria-labelledby="navbarDropdown">
EOD;
			foreach($navHeading['items'] as $navItemKey => $navItem){
				$href = "index.php?dashpage={$navItemKey}";
				$target = "";
				if ($navItemKey == 'viewall' && !empty($this->module)){
					$href = "{$this->module->url(false)}?auth_level=1";
					$target = 'target="_blank"';
				} else if ($navItemKey == 'view' && !empty($this->module)){
					$href = "{$this->module->url()}?auth_level=0";
					$target = 'target="_blank"';
				}
				$header .= <<< EOD
					<a class="dropdown-item" href="{$href}" {$target}>
						<i class="fa {$navItem['navIcon']}" aria-hidden="true"></i> {$navItem['navTitle']}
					</a>
EOD;
			}
			$header .= <<< EOD
				</li>
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
