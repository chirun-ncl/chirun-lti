<?php
class DashboardDirectLinkPage {
	public $title = "Direct Link";
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
				<h2>Direct Link</h2>
				<p>Use the below controls to setup this item to directly link to a piece of course content. For standalone uploads there is only a single course item and so this setting can be safely ignored.</p>
EOD;
		$options = getResourceOptions($this->db, $this->resource_pk);
		$hide_by_default = $options['hide_by_default']?'checked':'';
		if(empty($this->module)){
			header('Location: index.php');
		} else {
			$direct_link = $this->module->get_direct_linked_item();
			if(!empty($direct_link)){
				$main .= <<< EOD
				<p><b>Currently selected item:</b> <a target="_blank" href="{$this->module->url()}?auth_level=1">{$direct_link->title}</a></p>		
EOD;
			}
			$main .= <<< EOD
				<table class="table mt-2">
				<thead class="thead-light">
				<tr>
				<th></th>
				<th>Content Title</th>
				<th class="text-center">Direct Link</th>
				</tr>
				</thead>
EOD;
			foreach($this->module->content as $module_content){
				$main .= $module_content->direct_link_tbl_row();
			}
			$main .= <<< EOD
					</table>
				</div>
			</div>
EOD;
		}
		return $main;
	}
}
?>
