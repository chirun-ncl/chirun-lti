<?php
class ModuleNotSelectedPage extends ErrorPage {
	protected function errorTitle(){
		return "Coursebuilder content has not been configured.";
	}
	protected function errorContent(){
		return "Please check this page again later or contact your course instructor for advice.";
	}
}
?>
