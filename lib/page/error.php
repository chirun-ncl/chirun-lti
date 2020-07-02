<?php
class ErrorPage extends LTIPage {
	protected function main(){
		$main = <<< EOD
			<div class="justify-content-center row mt-5">
			<div class="col-2">
			<img width="100%" src="{$this->webdir}/images/coursebuilder_icon_512.png">
			</div>
			</div>
			<main id="mainCBErrorContainer" role="main" class="container mt-2">
			<noscript>
			For full functionality of this site it is necessary to enable JavaScript.
			</noscript>
			<div class="row mt-3 mb-5 justify-content-center">
				<div class="p-3 shadow bg-white rounded col-md-10 col-lg-8 text-center">
					<h4>Starting Coursebuilder failed</h4>
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
			</main>
			<script>
			var promise = document.hasStorageAccess();
			promise.then(
			  function (hasAccess) {
				console.log('document.hasStorageAccess: '+hasAccess);
				if(!hasAccess){
					document.getElementById("mainCBErrorContainer").innerHTML =
						'<div class="text-center justify-content-center row mb-5">'+
						'<div class="col-8">'+
						'<p>This content is provided as a Coursebuilder Item.</p>'+
						'<button onclick="makeRequestWithUserGesture()">Click here to load the content item.</button>'+
						'</div>'+
						'</div>';
				}
			  },
			  function (reason) {
				// Promise was rejected.
				console.log('document.hasStorageAccess promise rejected: '+reason);
			  }
			);
			var promise = document.requestStorageAccess();
			var helpShown = false;
			promise.then(
				function () {
					//Access granted
				},
				function () {
					// Promise was rejected for some reason.
					console.log('requestStorageAssess promise rejected');
				});
			function makeRequestWithUserGesture() {
				var promise = document.requestStorageAccess();
				promise.then(
					function () {
						//Access granted
						location.reload();
					},
					function () {
						// Promise was rejected for some reason.
						console.log('requestStorageAssess promise rejected');
						if(!helpShown){document.getElementById("CBBody").innerHTML =
							document.getElementById("CBBody").innerHTML +
							'<div class="row">'+
							'<div class="mt-3 col-md-12">'+
							'<p>Please click "Allow" to grant access to cookies and website data. We require this data so that we are able to confirm who you are and that you are authorised to see the course content.</p>'+
							'</div>'+
							'</div>';
							helpShown = true;
						}
					}
				);
			}
			</script>
EOD;
		return $main;
	}
}
?>
