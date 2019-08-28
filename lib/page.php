<?php
class BasePage {
	public $page = "";
	public function render() {
        $this->page .= $this->header();
        $this->page .= $this->main();
        $this->page .= $this->footer();
        echo $this->page;
    }
	protected function header() {
		return "";
	}
	protected function main() {
		return "";
	}
	protected function footer() {
		return "";
	}
}

class LTIPage extends BasePage {
	use ModulePage;
	public $title = "Coursebuilder LTI Tool";
	protected $webdir = WEBDIR;
	protected $alerts = array();
	public function addAlert($alertText, $alertLevel="primary"){
		$alert = new Alert($alertText);
		$alert->setLevel($alertLevel);
		$this->alerts[] = $alert;
	}

	protected function main(){
		$main = '<main role="main" class="container mt-2">';
		$main .= '<div class="row">';
		$main .= '<div class="col">';
		foreach($this->alerts as $alert){
			$main .= $alert->html();
		}
		$main .= '</div>';
		$main .= '</div>';
		return $main;
	}
	protected function css() {
		$css = '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">';
		$css .= '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">';
		$css .= '<link rel="stylesheet" href="css/styles.css">';
		return $css;
	}
	protected function js(){
		$js = <<< EOD
			<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
			<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
			<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

EOD;
		return $js;
	}
	protected function header() {
		$header = <<< EOD
			<!doctype html>
			<html lang="en">
			<head>
			<title>{$this->title}</title>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
			{$this->css()}
			{$this->js()}
        	</head>
			<body>

EOD;
		return $header;
	}
	protected function footer(){
		$footer = <<< EOD
			</main>
			<hr>
			<footer class="text-muted">
				<div class="container">
					<p>Coursebuilder LTI Tool<br>&copy; E-Learning Unit, School of Mathematics,
						Statistics &amp; Physics, Newcastle University</p>
				</div>
			</footer>
			</body>
			</html>	
EOD;
		return $footer;
	}
}

class LandingPage extends LTIPage {
	protected function main(){
		$main = <<< EOD
			<main role="main" class="container mt-2">
			<div class="jumbotron">
			<div class="row">
			<div class="col-md-2">
			<img width="100%" src="{$this->webdir}/images/coursebuilder_icon_512.png">
			</div>
			</div>
			<div class="row">
			<div class="col-md-12">
			<h1>Coursebuilder</h1>
			<p class="lead">Coursebuilder produces flexible and accessible notes, in a variety of formats, using LaTeX or Markdown source.</p>
			<p class="lead">Coursebuilder is under active development, and currently in a closed testing stage. Access is currently restricted to developers and authorised test users only.</p>
			<p>Contact Information: <a href="mailto:christopher.graham@newcastle.ac.uk">E-Learning Unit</a>, School of Mathematics, Statistics &amp; Physics.</p>
			</div>
			</div>
			</div>
EOD;
		return $main;
	}
}

class ModuleNotSelectedPage extends LTIPage {
	protected function main(){
		$main = <<< EOD
			<main role="main" class="container mt-2">
			<div class="jumbotron alert-info">
			<div class="row">
			<div class="col-md-12">
				<h1>Module content has not yet been configured!</h1>
				<p class="lead">Please contact your module leader.</p>
			</div>
			</div>
			</div>
EOD;
		return $main;
	}
}

class ErrorPage extends LTIPage {
	protected function main(){
		$main = <<< EOD
			<main role="main" class="container mt-2">
			<div class="jumbotron alert-danger">
			<div class="row">
			<div class="col-md-12">
				<h1>Sorry, Coursebuilder has encountered an error.</h1>
EOD;
		$main .= "<p class='lead'>The following error detail was provided:";
		foreach($this->alerts as $alert){
           	$main .= "<br><strong>{$alert->text}</strong>";
		}
		$main .= <<< EOD
			</p><p class='lead'>Please include the above details in any error reports.</p>
			</div>
			</div>
			</div>
EOD;
		return $main;
	}
}

class StudentPage extends LTIPage {
	public function render(){
		if(isset($this->requestedContent)){
			$ok = $this->renderRequestedContent();
		} else {
			$indexContent = $this->module->code . '/' . $this->module->year . '/';
			$this->requestContent($indexContent);
			$ok = $this->renderRequestedContent();
		}
		if (!$ok) parent::render();
	}
}

class DashboardPage extends LTIPage {
	public function render(){
		if(isset($this->requestedContent)){
			$ok = $this->renderRequestedContent();
			if($ok) return;
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
		$header .= <<< EOD
			<nav class="navbar navbar-expand-lg navbar-light bg-light">
            	<a class="navbar-brand" href="#"><img src="{$this->webdir}/images/coursebuilder_icon_128.png" alt="Coursebuilder Logo" style="width:40px;"> {$this->title}</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="navbarSupportedContent">
					<ul class="navbar-nav mr-auto">
      					<li class="nav-item active">
        					<a class="nav-link" href="#"><i class="fa fa-dashboard" aria-hidden="true"></i> Dashboard</a>
      					</li>
    				</ul>
EOD;
		if($this->module){
			$header .= <<< EOD
					<ul class="navbar-nav">
      					<li class="nav-item">
        					<a class="nav-link" href="{$this->module->url()}"><i class="fa fa-book" aria-hidden="true"></i> View Content</a>
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
	protected function main(){
		$main = parent::main();
		if($this->isModuleEmpty()){
			$main .= <<< EOD
				<h2>Instructor Options</h2>
				<h3>Select Coursebuilder notes</h3>
				<form action="index.php" method="POST">
				<input type="hidden" name="do" value="add">
				<table class="table">
				<thead class="thead-light">
				<tr>
				<th>Module Code</th>
				<th>Module Title</th>
				<th>Author</th>
				<th>Year</th>
				<th>Theme</th>
				<th>Select</th>
				</tr>
				</thead>
				<tbody>
EOD;
			foreach(getModules() as $module){
				$main .= "<tr><td>".$module->code."</td>";
				$main .= "<td>".$module->title."</td>";
				$main .= "<td>".$module->author."</td>";
				$main .= "<td>".$module->year."</td>";
				$main .= "<td><select name='theme_id'>";
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
			$main .= <<< EOD
				</tbody>
				</table>
EOD;
		} else {
		$main .= <<< EOD
			<div class="row">
				<div class="col">
					<h2>Instructor Dashboard</h2>
					<h4 class="mt-4">Selected Module</h4>
					<p><strong>Module:</strong> {$this->module->title}</p>
					<p><strong>Theme:</strong> {$this->module->selected_theme->title}</p>
					<h4 class="mt-4">Adaptive Release</h4>
					<p>Use the below controls to manage when content will be visible to students on a part or chapter basis.</p>
					<form action="index.php" method="POST">
					<table class="table">
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
		}
		return $main;
	}
}

trait ModulePage {
	protected $module = NULL;
	protected $requestedContent = NULL;
	public function setModule($module){
		$this->module = $module;
		if (!empty($this->module->title)){
			$this->title = $module->title;
		}
	}
	public function isModuleEmpty(){
		if (!isset($this->module)) return true;
		if (empty($this->module->title)) return true;
		return false;
	}
	public function requestContent($contentPath){
		if (!isset($this->module)) return "Module has not yet been configured";
		$authModule = $this->module->code . '/' . $this->module->year;
		$fullContentPath = MODULEDIR .'/'. $contentPath;
		$this->requestedContent = -1;
		
		if(substr($contentPath, 0, strlen($authModule)) === $authModule){
			if (is_file($fullContentPath)){
				$requestedContent = $fullContentPath;
			}
			if (is_dir($fullContentPath)){
				if(is_file($fullContentPath.'/index.html')){
					$requestedContent = $fullContentPath.'/index.html';
				} else if (is_file($fullContentPath.'/index.php')){
					$requestedContent = $fullContentPath.'/index.php';
				}
			}
			if(!isset($requestedContent)) return "The requested content item was not found.";
			$matched_content = $this->module->get_content_for_path($fullContentPath);
			if($matched_content && $matched_content->hidden>0){
				return "The requested content is not available.";
			}

			//All tests passed, We're good to print the requested content
			$this->requestedContent = $requestedContent;			
			return 0;
		}
		return "You are not authorised to view this content.";
	}

	protected function filter_content($html){
		//TODO: Make it so that this hack is no longer required.
		//		It searches the DOM for content that is supposed to be hidden and removes it.
		//		The XPath queries strongly depend on the module's theme, this is far from ideal.
		$dom = new DomDocument;
		@$dom->loadHTML($html);
		foreach($this->module->get_hidden() as $hidden_slug){
			$xpath = new DomXPath($dom);
			$content_nodes = $xpath->query("//main/div[contains(concat(' ', @class, ' '), ' album ')]/div/div/div[contains(concat(' ', @class, ' '), ' col-md-4 ')]//ul/li[./a[contains(@href, '".$hidden_slug."')]]");
			foreach ($content_nodes as $node){
				$node->parentNode->removeChild($node);
			}
			$part_nodes = $xpath->query("//main/div[contains(concat(' ', @class, ' '), ' album ')]/div/div/div[contains(concat(' ', @class, ' '), ' col-md-4 ') and .//div[contains(concat(' ', @class, ' '), ' card-header ')]//a[contains(@href, '".$hidden_slug."')]]");
			foreach ($part_nodes as $node){
				$node->parentNode->removeChild($node);
			}
			$heading_nodes = $xpath->query("//li[contains(concat(' ', @class, ' '), ' nav-item ') and ./a[contains(@href, '".$hidden_slug."')]]");
			foreach ($heading_nodes as $node){
				$node->parentNode->removeChild($node);
			}
			$cleanup = $xpath->query("//main/div[contains(concat(' ', @class, ' '), ' album ')]/div/div/div[contains(concat(' ', @class, ' '), ' col-md-4 ') and not(.//ul/li)]");
			foreach ($cleanup as $node){
				$node->parentNode->removeChild($node);
			}

		}
		return $dom->saveHTML();
	}

	protected function renderRequestedContent(){
		if(!isset($this->requestedContent)) return false;
		if($this->requestedContent<0) return false;
		$ext = pathinfo($this->requestedContent, PATHINFO_EXTENSION);
		if ($ext === 'php'){
			include $this->requestedContent;
			return true;
		} else if ($ext === 'html' or $ext === 'htm') {
			header('Content-Type: ' . get_file_mime_type($this->requestedContent));
			header('Content-Disposition: inline; filename="'.basename($this->requestedContent).'"');
			$file_content = file_get_contents($this->requestedContent);
			$file_content = $this->filter_content($file_content);
			echo $file_content;
			return true;
		} else {
			header('Content-Type: ' . get_file_mime_type($this->requestedContent));
			header('Content-Disposition: inline; filename="'.basename($this->requestedContent).'"');
			$file_content = file_get_contents($this->requestedContent);
			echo $file_content;
			return true;
		}
		return false;
	}
}

class Alert {
	public $text = "";
	public $lead = "";
	public $level = "primary";
	public function __construct($alertText){
		$this->text = $alertText;
	}
	public function setLevel($alertLevel){
		$this->level = $alertLevel;
		if($this->level == 'danger'){
			$this->lead = "<strong>Error:</strong>";
		}
	}
	public function html(){
		$html = <<< EOD
			<div class="alert alert-{$this->level}" role="alert">
			{$this->lead} {$this->text}
			</div>
EOD;
		return $html;
	}
}

?>
