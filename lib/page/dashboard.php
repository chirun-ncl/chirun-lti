<?php
class DashboardPage extends LTIPage {

	protected $resource_pk = NULL;

	public function setResource($resource_pk){
		$this->resource_pk = $resource_pk;
	}

	public function render(){
		if(isset($this->requestedContent)){
			$ok = $this->renderRequestedContent();
			if($ok) return;
		}
		if (isset($_REQUEST['dashpage'])){
			$this->pageID = $_REQUEST['dashpage'];
		} else {
			$this->pageID = "dash";
		}
		parent::render();
	}

	protected function css(){
		$css = parent::css();
		$css .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
		return $css;
	}
	protected function js(){
		$js = parent::js();
		$js .= '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>';
		return $js;
	}
	protected function header(){
		$header = parent::header();
		$dashActive = $this->pageID=="dash"?'active':'';
		$optActive = $this->pageID=="opt"?'active':'';
		$header .= <<< EOD
			<nav class="navbar navbar-expand-lg navbar-light bg-light">
				<a class="navbar-brand" href="index.php"><img src="{$this->webdir}/images/coursebuilder_icon_128.png" alt="Coursebuilder Logo" style="width:40px;"> {$this->title}</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="navbarSupportedContent">
					<ul class="navbar-nav mr-auto">
						<li class="nav-item {$dashActive}">
							<a class="nav-link" href="index.php"><i class="fa fa-dashboard" aria-hidden="true"></i> Dashboard</a>
						</li>
						<li class="nav-item {$optActive}">
							<a class="nav-link" href="index.php?dashpage=opt"><i class="fa fa-cog" aria-hidden="true"></i> Options</a>
						</li>
EOD;
		if($this->module){
			$header .= <<< EOD
				</ul>
				<ul class="navbar-nav">
					<li class="nav-item">
					<a class="nav-link" target="_blank" href="{$this->module->url()}?auth_level=1"><i class="fa fa-book" aria-hidden="true"></i> View All Content</a>
					</li>
					<li class="nav-item">
					<a class="nav-link" target="_blank" href="{$this->module->url()}?auth_level=0"><i class="fa fa-user-circle" aria-hidden="true"></i> Student View</a>
					</li>
				</ul>
EOD;
		}
		$header .= <<< EOD
				</div>
			</nav>

EOD;
		return $header;
	}
	protected function mainModuleEmpty(){
		$main = <<< EOD
				<h2>Instructor Options</h2>
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
		return $main;
	}

	protected function mainDashboard(){
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

	protected function mainDashboardOpt(){
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

	protected function main(){
		$main = parent::main();
		switch($this->pageID){
		case "opt":
			$main .= $this->mainDashboardOpt();
			break;
		default:
			if($this->isModuleEmpty()){
				$main .= $this->mainModuleEmpty();
			} else {
				$main .= $this->mainDashboard();
			}
		}
		return $main;
	}
}
?>
