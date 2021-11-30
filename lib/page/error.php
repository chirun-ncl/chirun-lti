<?php
class ErrorPage extends LTIPage {
	public $template = "error.html";
	public $title = "Launch Error | Chirun";
	public $errorTitle = "Launching Chirun Failed";
	public $errorTemplate = "launch_failed.html";
}

class ModuleNotSelectedPage extends ErrorPage {
	public $errorTitle = "Chirun content has not been configured.";
	public $errorTemplate = "no_module.html";
}
?>
