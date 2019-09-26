<?php
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
?>
