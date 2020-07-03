<?php
class DashboardViewAsStudentPage {
	use ModulePage;
	public $title = "View All";
        protected $resource_pk = NULL;
	protected $resource_options = NULL;
        protected $db = NULL;
        public function setup($module, $db, $res){
                $this->module = $module;
		$this->resource_pk = $res;
		if(!empty($this->resource_pk)){
			$this->resource_options = getResourceOptions($db, $this->resource_pk);
		}
                $this->db = $db;
        }
	public function main(){
		if (empty($this->module)){
			$main .= "<p>No content selected. Upload a document source to begin.</p>";
			$main .= "<p><a class='btn btn-primary' href='index.php?dashpage=upload' role='button'>Go to Upload page</a></p>";
		} else {
			$main .= <<< EOD
			<script>
			function resizeIframe(obj) {
				obj.style.height = (5+obj.contentWindow.document.body.clientHeight) + 'px';
			}
			</script>
			</main>
			<iframe width="100%" src="{$this->module->url()}?auth_level=0" frameborder="0" scrolling="no" onload="resizeIframe(this)" />
			<main>
EOD;
		}
		return $main;
	}
}
class DashboardViewAllPage {
	use ModulePage;
	public $title = "View All";
        protected $resource_pk = NULL;
	protected $resource_options = NULL;
        protected $db = NULL;
        public function setup($module, $db, $res){
                $this->module = $module;
		$this->resource_pk = $res;
		if(!empty($this->resource_pk)){
			$this->resource_options = getResourceOptions($db, $this->resource_pk);
		}
                $this->db = $db;
        }
	public function main(){
		if (empty($this->module)){
			$main .= "<p>No content selected. Upload a document source to begin.</p>";
			$main .= "<p><a class='btn btn-primary' href='index.php?dashpage=upload' role='button'>Go to Upload page</a></p>";
		} else {
			$main .= <<< EOD
			<script>
			function resizeIframe(obj) {
				obj.style.height = (5+obj.contentWindow.document.body.clientHeight) + 'px';
			}
			</script>
			</main>
			<iframe width="100%" src="{$this->module->url()}?auth_level=1" frameborder="0" scrolling="no" onload="resizeIframe(this)" />
			<main>
EOD;
		}
		return $main;
	}
}
?>
