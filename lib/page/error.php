<?php
class ErrorPage extends LTIPage {
	protected function errorTitle(){
		return "Starting Coursebuilder failed";
	}
	protected function errorContent(){
		$errorContent = <<< EOD
			<div id="errorMessage">
			<p>Sorry, Coursebuilder has encountered an error loading this page. Please try reloading the page or opening the page in a new tab.</p>
			<p><button onclick='window.open(window.location+"&do_sessid=true");' type='button' class='btn btn-primary'>Click here to open in a new tab</button></p>
			</div>
			<div class="alert alert-warning alert-dismissible pr-1" id="errorAlert" role="alert">
				The following error detail was provided:
EOD;
		foreach($this->alerts as $alert){
			$errorContent .= "<br><strong>{$alert->text}</strong>";
		}
		$errorContent .= <<< EOD
				<button type="button" class="close pt-2 pr-3 pl-3" data-dismiss="alert" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</div>
			<h5>Using macOS or iOS?</h5>
			<p>Safari 13.1 contains an update that causes issues with loading content. We are currently working on a solution for this issue. Until then, you can avoid this step by <a data-toggle="collapse" href="#safari">disabling cross-site tracking prevention</a> in Safari and reloading the page.</p>
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
			<script>
				//var cookiePromise = document.hasStorageAccess();
				cookiePromise.then(
				function (hasAccess) {
					console.log('document.hasStorageAccess: '+hasAccess);
					if(!hasAccess){
						document.getElementById("errorMessage").previousElementSibling.previousElementSibling.style.display = 'none';
						document.getElementById("errorMessage").style.display = 'none';
						document.getElementById("errorAlert").innerHTML =
							'<p>Due to your browser\'s cookie settings this content must be opened in a new tab.</p>'+
EOD;
		$errorContent .= "\"<button onclick='window.open(window.location+\\\"&do_sessid=true\\\");' type='button' class='btn btn-primary'>Click here to open in a new tab</button>\";";
		$errorContent .= <<< EOD
					}
				},
				function (reason) {
					// Promise was rejected.
					console.log('document.hasStorageAccess promise rejected: '+reason);
				});
			</script>
EOD;
		return $errorContent;
	}
	protected function errorBox(){
		$errTitle = $this->errorTitle();
		$errContent = $this->errorContent();
		$errBox = <<< EOD
			<div class="row mt-3 mb-5 justify-content-center">
				<div class="p-3 shadow bg-white rounded col-md-10 col-lg-8 text-center">
					<h4>{$errTitle}</h4>
					<noscript>
						For full functionality of this site it is necessary to enable JavaScript.
					</noscript>
					{$errContent}
				</div>
			</div>
EOD;
		return $errBox;

	}
	protected function main(){
		$errBox = $this->errorBox();
		$main = $this->coursebuilderIconHeader();
		$main .= <<< EOD
			<main id="mainCBErrorContainer" role="main" class="container mt-2">
				{$errBox}
			</main>
EOD;
		return $main;
	}
}
?>
