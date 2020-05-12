<?php
class ErrorPage extends LTIPage {
	protected function main(){
		$main = <<< EOD
			<main id="mainCBErrorContainer" role="main" class="container mt-2">
			<noscript>
			For full functionality of this site it is necessary to enable JavaScript.
			</noscript>
			<div  class="jumbotron alert-danger">
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
			</main>
			<script>
			var promise = document.hasStorageAccess();
			promise.then(
			  function (hasAccess) {
				console.log('document.hasStorageAccess: '+hasAccess);
				if(!hasAccess){
					document.getElementById("CBBody").innerHTML =
						'<div class="row">'+
						'<div class="col-md-12">'+
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
