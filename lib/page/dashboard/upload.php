<?php

function tex_array_filter($fn){
	$ret = false;
	if (str_replace('.tex', '', $fn) != $fn) {
		$ret = true;
	}
	return $ret;
}

$arr = array_filter($arr, 'url_array_filter');
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
			<div class="form-group">
			<input type="checkbox" id="split_chapters" name="split_chapters"> <label for="split_chapters">Split document into separate chapters</label> 
			</div>
			<input type="hidden" name="do" value="processUpload">
			<div class="form-group">
			<button type="submit" onclick="$('#uploadSpinner').show(); setTimeout(function(){ $('#uploadButton').prop('disabled', true);}, 0);" id="uploadButton" class="btn btn-primary">
				<span id="uploadSpinner" style="display:none;" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Upload
			</button>
			</div>
			</form>
			<hr>
			<h4 class="mt-4">Existing Document</h3>
			<p>Enter an existing <i>GUID</i> to reuse existing content previously uploaded to Cousebuilder. The GUID can be found on the <b>Selected Content</b> dashboard page once content has been successfuly uploaded.</p>
			<p><i>Existing content must have been uploaded by the same user currently logged in.</i></p>
			<form action="index.php" method="POST" enctype="multipart/form-data">
			<div class="form-row">
			<div class="form-group col-sm-7 col-md-6 col-lg-4">
			<label for="guidSelect" class="small"><b>GUID:</b></label>
			<input id="guidSelect" name="guidSelect" class="form-control" type="text" placeholder="00000000-0000-0000-000000000000">
			</div>
			</div>
			<input type="hidden" name="do" value="processGuidSelect">
			<button type="submit" id="guidButton" class="btn btn-sm btn-primary">Submit</button>
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
			$uploadFiles = array_filter($uploadFiles, 'tex_array_filter');
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
				<div class="form-group">
				<input type="checkbox" id="split_chapters" name="split_chapters"> <label for="split_chapters">Split document into separate chapters</label> 
				</div>
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
