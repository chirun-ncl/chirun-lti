<?php
class DashboardSelectedContentPage {
	use ModulePage;
	public $title = "Selected Content";
        protected $resource_pk = NULL;
        protected $db = NULL;
        public function setup($module, $db, $res){
                $this->module = $module;
                $this->resource_pk = $res;
                $this->db = $db;
        }
	public function main(){
		$main = "<h2>Selected Content Information</h2>";
		if(empty($this->module)){
			header('Location: index.php?dashpage=upload');
		} else if($this->isModuleEmpty()){
			$main .= <<< EOD
			<p><strong>A module has been uploaded but has not yet been successfully built</strong><br/>
			Visit the "Upload Document" page to continue building this module.</p>
			<h3 class="mt-4">Delete content</h3>
			<p>To select different content, first <i>remove</i> the content asssociated with this item using the button below.</p>
			<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Delete Coursebuilder Content</a>
EOD;
		} else if($this->isModuleStandalone()){
			$main .= <<< EOD
			<p><strong>Document source:</strong> {$this->module->content[0]->source}<br/>
			<strong>Theme:</strong> {$this->module->selected_theme->title}</br>
			<strong>GUID:</strong> {$this->module->code}</p>
			<h3 class="mt-4">Delete content</h3>
			<p>To select different content, first <i>remove</i> the content asssociated with this item using the button below.</p>
			<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Remove Content</a>
EOD;
		} else {
			$main .= <<< EOD
			<h3 class="mt-4">Selected Module</h3>
			<p><strong>Module:</strong> {$this->module->title}</p>
			<form class="form-inline mb-2" action="index.php" method="post">
			<div class="input-group">
			<label for="defThemeSelect" class="mr-2"><b>Theme:</b></label>
			<select class="form-control custom-select custom-select-sm mr-2" name="theme_id" id="defThemeSelect">
EOD;
			foreach ($this->module->themes as $idx => $theme){
				$selected = ($this->module->selected_theme->path == $theme->path)?'selected':'';
				$main .= <<< EOD
				<option {$selected} value="{$idx}">{$theme->title}</option>
EOD;
			}
			$main .= <<< EOD
			</select>
			<div class="input-group-addon input-group-button">
			<button type="submit" class="btn btn-sm btn-primary mb-2">Set Default Theme</button>
			</div>
			</div>
			<input type="hidden" name="do" value="change_theme">
			</form>
			<p><strong>GUID:</strong> {$this->module->code}</p>
EOD;
			$options = getResourceOptions($this->db, $this->resource_pk);
			$hide_by_default = $options['hide_by_default']?'checked':'';
			$main .= <<< EOD
			<h3 class="mt-4">Further Options</h3>
			<form action="index.php" method="POST">
			<input type="hidden" name="dashpage" value="opt">
			<input type='hidden' value='0' name="opt[hide_by_default]">
			<input type="checkbox" name="opt[hide_by_default]" value="checked" {$hide_by_default}> Hide in adaptive release by default.<br><br>
			<button class="btn btn-sm btn-primary" name="do" value="options_save">Save Changes</button>
			</form>
			<h3 class="mt-4">Deselect Module</h3>
			<p>To select a different module, first <i>remove</i> the current module using the button below.</p>
			<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Deselect Module</a>
EOD;
		}
		return $main;
	}
}
?>
