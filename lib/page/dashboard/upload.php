<?php

function tex_array_filter($fn){
	$ret = false;
	if (str_replace('.tex', '', $fn) != $fn || str_replace('.md', '', $fn) ) {
		$ret = true;
	}
	return $ret;
}

class DashboardUploadPage extends BaseDashboardContent {
	public $title = "Upload Document";
	public $template = "dashboard/upload.html";
	public $uploaded = NULL;
	public $uploadFileOptions = NULL;
	public function setup($resource){
		parent::setup($resource);

		if(empty($this->resource->module)){
			// Nothing at all has been uploaded
			$this->uploaded = False;
		} else if($this->resource->isModuleEmpty() && $this->resource->options['user_uploaded']==1){
			/*
				Something has been uploaded but it has not yet started processing
				Assume there is no config.yml and we don't yet know which file to process
				in standalone mode.
			*/
			$this->uploaded = True;
			$fullUploadPath = INSTALLDIR.'/upload'.dirname($this->resource->module->yaml_path);
			$this->uploadFileOptions = array_diff(scandir($fullUploadPath),array('..','.'));
			$this->uploadFileOptions = array_filter($this->uploadFileOptions, 'tex_array_filter');
		} else {
			// All done, redirect to the build log
			$_SESSION['ack_messages'] = False;
			header('Location: index.php?dashpage=buildlog');
		}
	}
}
?>
