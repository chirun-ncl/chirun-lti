<?php
class DashboardPublicAccessPage {
	public $title = "Public Access";
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
				<h2>Public Access</h2>
				<p>By default content uploaded to the Coursebuilder LTI tool is protected by LTI authentication and must be accessed through the VLE (e.g. Canvas)
				using an account registered on the course. Sharing a direct link to the content will not work.</p>
				<p>If you would like to allow learners to share direct links to this content, turn on the setting below. When enabled, the LTI authentication is
				not enforced and the content is publicly accessible to the world.</p>
EOD;
		$options = getResourceOptions($this->db, $this->resource_pk);
		$public_access = $options['public_access']?'checked':'';
		if(empty($this->module)){
			header('Location: index.php');
		} else {
			$main .= <<< EOD
				<form action="index.php" method="POST">
				<div class="form-group">
				<input type='hidden' value='0' name="opt[public_access]">
				<input type="checkbox" ${public_access} id="public_access" value="checked" {$public_access} name="opt[public_access]">
				<label for="public_access">Enable public access to content</label> 
				</div>
				<input type="hidden" name="do" value="options_save">
				<input type="hidden" name="redirect_loc" value="publicaccess">
				<div class="form-group">
					<button type="submit" id="publicAccessButton" class="btn btn-primary">Update</button>
				</div>
				</form>
			</div>
			</div>
EOD;
		}
		return $main;
	}
}
?>
