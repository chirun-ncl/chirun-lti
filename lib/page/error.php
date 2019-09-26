<?php
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
?>
