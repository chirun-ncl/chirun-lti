<?php
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
			</main>
EOD;
		return $main;
	}
}
?>
