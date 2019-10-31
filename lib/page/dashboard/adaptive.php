<?php
class DashboardAdaptiveReleasePage extends LTIPage {
	public $title = "Adaptive Release Settings";
	protected $resource_pk = NULL;
	protected $db = NULL;
	protected $module = NULL;
	public function setup($module, $db, $res){
		$this->module = $module;
		$this->resource_pk = $res;
		$this->db = $db;
	}
	public function main(){
		$main = <<< EOD
			<div class="row">
				<div class="col">
					<h2>Instructor Dashboard</h2>
					<h4 class="mt-4">Selected Module</h4>
					<p><strong>Module:</strong> {$this->module->title}</p>
					<p><strong>Theme:</strong> {$this->module->selected_theme->title}</p>
					<h4 class="mt-4">Adaptive Release</h4>
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
					<button class="btn btn-primary" name="do" value="content_saveall">Save All Changes</button>
					<button class="btn btn-secondary" name="do" value="content_clearall">Restore Defaults</button>
					</form>
					<h4 class="mt-4">Manage Content</h4>
					<p>The controls below are used to manage the module content as a whole.</p>
					<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Deselect Module</a>
				</div>
			</div>
			<script>flatpickr(".flatpickr",{enableTime: true,dateFormat: "Y-m-d H:i",time_24hr: true});</script>
EOD;
		return $main;
	}
}

?>
