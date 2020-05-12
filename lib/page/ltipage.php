<?php
class LTIPage extends BasePage {
	use ModulePage;
	public $title = "Coursebuilder LTI Tool";
	protected $webdir = WEBDIR;
	protected $alerts = array();

	public function getTitle(){
		return $this->title;
	}

	public function render(){
		if(isset($this->requestedContent)){
			$error = $this->renderRequestedContent();
			if($error) return;
		}
		parent::render();
	}

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
			<title>{$this->getTitle()}</title>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
			{$this->css()}
			{$this->js()}
			</head>
			<body id="CBBody">

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
?>
