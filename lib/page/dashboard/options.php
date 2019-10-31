<?php
class DashboardOptionsPage {
	public $title = "Options";
	protected $resource_pk = NULL;
	protected $db = NULL;
	protected $module = NULL;
	public function setup($module, $db, $res){
		$this->module = $module;
		$this->resource_pk = $res;
		$this->db = $db;
	}
	public function main(){
		$options = getResourceOptions($this->db, $this->resource_pk);
		$hide_by_default = $options['hide_by_default']?'checked':'';
		$main = <<< EOD
			<div class="row">
				<div class="col">
					<h2>Instructor Dashboard</h2>
					<h4 class="mt-4">Options</h4>
					<p>Use the below controls to change the behaviour of the Coursebuilder LTI tool.</p>
					<form action="index.php" method="POST">
					<input type="hidden" name="dashpage" value="opt">
					<input type="checkbox" name="opt[hide_by_default]" value="checked" {$hide_by_default}> Hide previously unseen items by default<br><br>
					<button class="btn btn-primary" name="do" value="options_save">Save Changes</button>
					</form>
				</div>
			</div>
EOD;
		return $main;
	}
}
?>
