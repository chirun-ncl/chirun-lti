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
			<button type="submit" class="btn btn-primary">Upload</button>
			</div>
			</form>
EOD;
		} else {
			//Test for uploaded but unprocessed content
			if($this->isModuleEmpty() && $this->resource_options['user_uploaded']==0){
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
			}
			$main .=  '<h4 class="mt-4">Build Log</h3>';
			$fullLogPath = INSTALLDIR .'/process/logs'. dirname($this->module->yaml_path).'.log';
			if (file_exists($fullLogPath)){
				$main .= <<< EOD
				<p>The build output from Coursebuilder is shown below. Use this to debug any errors in the build process.</p>
				<div class="card">
				<div class="card-header p-1">
				Coursebuilder output log
				<button onClick="window.location.reload();" class="btn btn-sm btn-primary float-right">Refresh</button>
				</div>
				<div class="card-body p-1 bg-dark">
				<pre class="text-light pre-scrollable"><small><code id='console-log'>
EOD;
				$main .= "...";
				$main .= file_get_contents($fullLogPath);
				$main .= <<< EOD
					</code></small></pre>
					</div>
					</div>
					<script>
					var log = document.getElementById("console-log");
					log.scrollIntoView(false);
					</script>
EOD;
			} else {
				$main .= "<p>No build log found. Select a document source to begin building.</p>";
			}
			$main .= <<< EOD
			<h3 class="mt-4">Delete content</h3>
			<p>To select different content, first <i>remove</i> the content asssociated with this item using the button below.</p>
			<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Delete Content</a>
EOD;
		}
		return $main;
	}
}
?>
