<?php
class DashboardUploadPage {
	use ModulePage;
	public $title = "Upload Document";
        protected $resource_pk = NULL;
	protected $resource_options = NULL;
        protected $db = NULL;
        public function setup($module, $db, $res){
                $this->module = $module;
		$this->resource_pk = $res;
		if(!empty($this->resource_pk)){
			$this->resource_options = getResourceOptions($db, $this->resource_pk);
		}
                $this->db = $db;
        }
	public function main(){
		$main = "<h2>Upload Document</h2>";
		if(empty($this->module)){
			//Nothing uploaded, no record of module content
			$main .= <<< EOD
			<h4 class="mt-4">New Document</h3>
			<p>Upload a <em>Coursebuilder package</em>, <em>LaTeX</em> document or <em>Markdown</em> document for automatic conversion by the Coursebuilder LTI tool. The following file formats are acceptable: <tt>.tex</tt>, <tt>.md</tt>, <tt>.zip</tt>. If your document contains several source files, select	them all or combine them into a <tt>.zip</tt> file for upload.</p>
			<form action="index.php" method="POST" enctype="multipart/form-data">
			<div class="form-group">
			<input id="docUpload" name="docUpload[]" type="file" class="file" multiple data-show-upload="true" data-show-caption="true">
			</div>
			<input type="hidden" name="do" value="processUpload">
			<div class="form-group">
			<button type="submit" onclick="$('#uploadSpinner').show(); setTimeout(function(){ $('#uploadButton').prop('disabled', true);}, 0);" id="uploadButton" class="btn btn-primary">
				<span id="uploadSpinner" style="display:none;" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Upload
			</button>
			</div>
			</form>
EOD;
		} else if($this->isModuleEmpty() && $this->resource_options['user_uploaded']==0){
			//Uploaded but unprocessed content
			$main .= <<< EOD
				<h4 class="mt-4">Select Document Source</h3>
				<p>Select the main source document to begin building:</p>
EOD;
			$fullUploadPath = INSTALLDIR.'/upload'.dirname($this->module->yaml_path);
			$uploadFiles = array_diff(scandir($fullUploadPath),array('..','.'));
			$main .= <<< EOD
				<form action="index.php" method="POST">
EOD;
			foreach ($uploadFiles as $key => $f){
				$main .= <<< EOD
				<div class="form-check">
					<input class="form-check-input" type="radio" id="source_main_{$key}" name="source_main" value="{$f}">
					<label class="form-check-label" for="source_main_{$key}">{$f}</label>
				</div>
EOD;
			}
			$main .= <<< EOD
				<div class="form-group mt-2">
				<input type="hidden" name="do" value="processBuild">
				<button type="submit" class="btn btn-primary">Build</button>
				</div>
				</form>
EOD;
		} else {
			header('Location: index.php?dashpage=buildlog');
		}
		return $main;
	}
}
?>
