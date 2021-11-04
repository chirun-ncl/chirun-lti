<?php
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

class TwigLoader {
	private static $instance = null;
	protected $twig;
	protected $loader;

	private function __construct(){
		$this->loader = new FilesystemLoader(INSTALLDIR.'/lib/templates');
		$this->twig =  new Environment($this->loader, [
			'cache' => '/tmp/cb_site_cache/php',
			'auto_reload' => true,
		]);
	}

	public static function twig(){
		if (self::$instance == null){
			self::$instance = new TwigLoader();
		}
		return (self::$instance)->twig;
	}
}

class Alert {
	public $text;
	public $lead;
	public $level;
	public function __construct($text, $lead = "", $level = "primary"){
		$this->text = $text;
		$this->lead = $lead;
		$this->level = $level;
	}
}

trait ModuleInfo {
	protected $module = NULL;

	public function isModuleEmpty(){
		if (!isset($this->module)) return true;
		if (empty($this->module->code)) return true;
		return false;
	}

	public function isModuleStandalone(){
		if (!isset($this->module)) return true;
		// TODO: Replace this check so that it instead tests if there is only a single content item
		if ($this->module->selected_theme->title == "Standalone") return true;
		return false;
	}
}

class BasePage {
	protected $page;
	protected $db;
	public $template = "base.html";
	public $css = array();
	public $js = array();
	public $title = "NCL Coursebuilder";

	public function setDB($db){
		$this->db = $db;
	}

	public function render() {
		$page = TwigLoader::twig()->load($this->template);
		$context = get_object_vars($this);
		echo $page->render($context);
	}
}
?>
