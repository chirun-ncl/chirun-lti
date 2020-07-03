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
			<strong>Theme:</strong> {$this->module->selected_theme->title}</p>
			<h3 class="mt-4">Delete content</h3>
			<p>To select different content, first <i>remove</i> the content asssociated with this item using the button below.</p>
			<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Delete Courebuilder Content</a>
EOD;
		} else {
			$main .= <<< EOD
				<h3 class="mt-4">Selected Module</h3>
				<p><strong>Module:</strong> {$this->module->title}</p>
				<p><strong>Theme:</strong> {$this->module->selected_theme->title}</p>

				<h3 class="mt-4">Deselect Module</h3>
				<p>To select a different module, first <i>remove</i> the current module using the button below.</p>
				<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Deselect Module</a>
EOD;
		}
		return $main;
	}
}
?>
