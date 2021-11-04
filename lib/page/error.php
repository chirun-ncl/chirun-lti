<?php
class ErrorPage extends LTIPage {
	public $template = "error.html";
	public $title = "Launch Error | NCL Coursebuilder";
	public $errorTitle = "Launching NCL Coursebuilder Failed";
	public $errorTemplate = "launch_failed.html";
}

class ModuleNotSelectedPage extends ErrorPage {
	public $errorTitle = "Coursebuilder content has not been configured.";
	public $errorTemplate = "no_module.html";
}
?>
