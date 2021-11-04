<?php

require_once(__DIR__.'/dashboard/base.php');
require_once(__DIR__.'/dashboard/directlink.php');
require_once(__DIR__.'/dashboard/adaptive.php');
require_once(__DIR__.'/dashboard/module.php');
require_once(__DIR__.'/dashboard/prebuilt.php');
require_once(__DIR__.'/dashboard/upload.php');
require_once(__DIR__.'/dashboard/logs.php');
require_once(__DIR__.'/dashboard/buildlog.php');
require_once(__DIR__.'/dashboard/publicaccess.php');

class DashboardPage extends LTIPage {
	public $template = "dashboard.html";
	public $title = "NCL Coursebuilder";
	protected $resource_pk = NULL;
	protected $dashPage = NULL;
	protected $resource_options = NULL;
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

	public function __construct(){
		$this->js = array_merge($this->js, array(
			"https://cdn.jsdelivr.net/npm/flatpickr",
			"https://cdn.datatables.net/v/bs5/dt-1.11.3/datatables.min.js",
		));
		$this->css = array_merge($this->css, array(
			"https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css",
			"https://cdn.datatables.net/v/bs5/dt-1.11.3/datatables.min.css",
		));

		if(in_array($_SESSION['user_id'],AUTH_USER_IDS) || in_array(strtolower($_SESSION['user_email']),AUTH_USER_EMAILS)){
			$this->pageStructure['content']['items']['prebuilt'] = array(
				'navTitle'  => 'Prebuilt Modules',
				'navIcon'   => 'fa-list'
			);
		}

	}

	public function setResource($resource_pk){
		$this->resource_pk = $resource_pk;
	}

	public function render(){
		$req = isset($_REQUEST['dashpage'])?$_REQUEST['dashpage']:'selected';
		if (array_key_exists($req,  $this->pageClass)){
			$this->dashPage = new $this->pageClass[$req];
		} else {
			$this->dashPage = new DashboardSelectedContentPage();
		}
		$this->dashPage->setup($this->module, $this->db, $this->resource_pk);		
		$this->template = $this->dashPage->template;
		$this->title = $this->dashPage->title . ' | ' . $this->title;
		parent::render();
	}
}
?>
