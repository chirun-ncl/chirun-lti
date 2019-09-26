<?php
class StudentPage extends LTIPage {
	public function render(){
		if(isset($this->requestedContent)){
			$ok = $this->renderRequestedContent();
		} else {
			$indexContent = $this->module->code . '/' . $this->module->year . '/';
			$this->requestContentForModule($indexContent);
			$ok = $this->renderRequestedContent();
		}
		if (!$ok) parent::render();
	}
}
?>
