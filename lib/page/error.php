<?php
class ErrorPage extends LTIPage {
	protected function main(){
		$main = <<< EOD
			<div class="justify-content-center row mt-5">
			<div class="col-2">
			<img alt="Coursebuilder logo" width="100%" src="{$this->webdir}/images/coursebuilder_icon_512.png">
			</div>
			</div>
			<main id="mainCBErrorContainer" role="main" class="container mt-2">
			<div class="row mt-3 mb-5 justify-content-center">
				<div class="p-3 shadow bg-white rounded col-md-10 col-lg-8 text-center">
					<h4>Starting Coursebuilder failed</h4>
					<noscript>
						For full functionality of this site it is necessary to enable JavaScript.
					</noscript>
					<p>Sorry, Coursebuilder has encountered an error loading this page. Try reloading the page or come back later.</p>
EOD;
		$main .= "<div class='alert alert-warning alert-dismissible pr-1' role='alert'>";
		$main .= "The following error detail was provided:";
		foreach($this->alerts as $alert){
			$main .= "<br><strong>{$alert->text}</strong>";
		}
		$main .= <<< EOD
					<button type="button" class="close pt-2 pr-3 pl-3" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
					</div>
					<h5>Using macOS or iOS?</h5>
					<p>Safari 13.1 contains an update that causes issues with loading content. We are currently working on a solution for this issue. Until then, you can avoid errors by <a data-toggle="collapse" href="#safari">disabling cross-site tracking prevention</a> in Safari and reloading the page.</p>
					<div class="collapse" id="safari">
						<div class="card card-body">
						<h6>macOS Catalina</h6>
							<ol>
							<li>In the Safari app on your Mac, choose <b>Safari</b> &gt; <b>Preferences</b>, then click <b>Privacy</b>.</li>
							<li><b>Untick</b> "Prevent cross-site tracking".</li>
							</ol>
							<hr>
						<h6 class="mt-2">iPhone/iPad</h6>
							<ol>
							<li>Open the <b>Settings</b> app on your device.</li>
							<li>Scroll down and select <b>Safari</b>.</li>
							<li>Scroll down and <b>turn off</b> "Prevent Cross-Site Tracking".</li>
							</ol>
					        </div>
					</div>
				</div>
			</div>
EOD;
		return $main;
	}
}
?>
