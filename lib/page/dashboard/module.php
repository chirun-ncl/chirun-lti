<?php
class DashboardModuleSelectPage {
	use ModulePage;
	public $title = "Content Selection";
        protected $resource_pk = NULL;
        protected $db = NULL;
        public function setup($module, $db, $res){
                $this->module = $module;
                $this->resource_pk = $res;
                $this->db = $db;
        }
	public function main(){
		$main = "<h2>Content Selection</h2>";
		if(empty($this->module)){
			$main .= <<< EOD
				<h3>Select Coursebuilder notes</h3>
				<form action="index.php" method="POST">
				<input type="hidden" name="do" value="add">
				<table class="table">
				<thead class="thead-light">
				<tr>
				<th></th>
				<th>Module Code</th>
				<th>Module Title</th>
				<th>Author</th>
				<th>Year</th>
				<th>Theme</th>
				<th>Select</th>
				</tr>
				</thead>
EOD;
			$modules = getModules();
			$module_years = array_unique(array_column($modules, 'year'));
			sort($module_years);
			foreach($module_years as $module_year){
				if(empty($module_year)) continue;
				$module_keys = array_keys(array_column($modules,'year'), $module_year);
				$main .= <<< EOD
					<tbody>
						<tr class="table-primary">
EOD;
				if (end($module_years) == $module_year){
					$main .= <<< EOD
							<td class="clickable" data-toggle="collapse" data-target="#table-part-{$module_year}" aria-expanded="true" aria-controls="table-part-{$module_year}">
							<i class="fa fa-3 fa-chevron-down"></i>
							</td>
EOD;
				} else {
					$main .= <<< EOD
						<td class="clickable collapsed" data-toggle="collapse" data-target="#table-part-{$module_year}" aria-expanded="false" aria-controls="table-part-{$module_year}">
						<i class="fa fa-3 fa-chevron-down"></i>
						</td>
EOD;
				}
				$main .= <<< EOD
						<td>{$module_year}</td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						</tr>
						</tbody>
EOD;
				if (end($module_years) == $module_year){
					$main .= '<tbody id="table-part-'.$module_year.'" class="collapse show">';
				} else {
					$main .= '<tbody id="table-part-'.$module_year.'" class="collapse">';
				}
				foreach($module_keys as $module_key){
					$module = $modules[$module_key];
					$main .= "<tr><td></td>";
					$main .= "<td>".$module->code."</td>";
					$main .= "<td>".$module->title."</td>";
					$main .= "<td>".$module->author."</td>";
					$main .= "<td>".$module->year."</td>";
					$main .= "<td><select name='theme_id[".$module->yaml_path."]'>";
					foreach ($module->themes as $idx=>$theme){
						$main .= <<< EOD
							<option value="{$idx}">{$theme->title}</option>
EOD;
					}
					$main .="</select></td>";
					$main .= <<< EOD
						<td>
						<button class="btn btn-primary" name="module_path" value="{$module->yaml_path}">Use</button>
						</td>
EOD;
					$main .= "</tr>";
				}
				$main .= '</tbody>';
			}
			$main .= <<< EOD
				</tbody>
				</table>
EOD;
		} else if($this->isModuleEmpty()){
			$main .= <<< EOD
			<p><strong>A module has been uploaded but has not yet been successfully built</strong><br/>
			Visit the "Upload Document" page to continue building this module.</p>
			<h3 class="mt-4">Delete content</h3>
			<p>To select different content, first <i>remove</i> the content asssociated with this item using the button below.</p>
			<a class="btn btn-danger" href="./index.php?do=delete&req_id={$this->module->selected_id}">Delete Courebuilder Content</a>
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
