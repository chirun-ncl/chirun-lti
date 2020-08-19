<?php
class DashboardBuildLogPage {
	use ModulePage;
	public $title = "BuildLog";
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
		$main = "<h2>Build Log</h2>";
		if (empty($this->module)){
			$main .= "<p>No build log was found. Upload a document source to begin building.</p>";
			$main .= "<p><a class='btn btn-primary' href='index.php?dashpage=upload' role='button'>Go to Upload page</a></p>";
		} else {
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
					$(document).ready(function () {
						var log = document.getElementById("console-log");
						log.scrollIntoView(false);
						setTimeout(function(){
							if(!$('#console-log').text().includes('Finished!') && !$('#console-log').text().includes('Your document failed to build')){
								location.reload(true);
							}
						}, 5000);       
					});
					</script>
EOD;
			} else {
				$main .= "<p>No build log was found. Note that prebuilt modules have no build log.</p>";
			}
		}
		return $main;
	}
}
?>
