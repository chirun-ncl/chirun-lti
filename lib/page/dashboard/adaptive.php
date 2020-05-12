<?php
class DashboardAdaptiveReleasePage {
	use ModulePage;
	public $title = "Adaptive Release Settings";
	protected $resource_pk = NULL;
	protected $db = NULL;
	public function setup($module, $db, $res){
		$this->module = $module;
		$this->resource_pk = $res;
		$this->db = $db;
	}
	public function main(){
		if($this->isModuleEmpty()){
			$main = "<h2>Adaptive Release</h2>";
			$main .= "<p><b>No module selected!</b></p>";
			$main .= "<p>To configure adaptive release a module must be selected first.</p>";
		} else {
			$main = <<< EOD
					<h2>Adaptive Release</h2>
					<p>Use the below controls to manage when content will be visible to students on a part or chapter basis.</p>
					<form action="index.php" method="POST">
					<button class="btn btn-primary" name="do" value="content_saveall">Save All Changes</button>
					<button class="btn btn-secondary" name="do" value="content_clearall">Restore Defaults</button>
					<table class="table mt-2">
					<thead class="thead-light">
					<tr>
					<th></th>
					<th>Content Title</th>
					<th>Current Visibility</th>
					<th>Start Date &amp; Time</th>
					<th>End Date &amp; Time</th>
					<th class="text-center">Force Hidden</th>
					</tr>
					</thead>
EOD;
			foreach($this->module->content as $module_content){
				$main .= $module_content->adaptive_release_tbl_row();
			}
			$main .= <<< EOD
					</table>
					<input type="hidden" name="dashpage" value="adapt"></input>
					<button class="btn btn-primary" name="do" value="content_saveall">Save All Changes</button>
					<button class="btn btn-secondary" name="do" value="content_clearall">Restore Defaults</button>
					</form>
			<script>flatpickr(".flatpickr",{enableTime: true,dateFormat: "Y-m-d H:i",time_24hr: true});</script>
EOD;
		}
		return $main;
	}
}

?>