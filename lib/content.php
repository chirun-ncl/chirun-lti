<?php

class Content {
	public $slug_path = '';
	public $start_datetime = NULL;
	public $end_datetime = NULL;
	public $hidden = 0;
	public $hidden_reason = 'Visible';
	public $type = NULL;
	public $title = '';
	public $parent_slug = NULL;
	public $children = array();
	public $owner_module = NULL;
	
	public function __construct($content_item, $owner_module, $parent = NULL) {
		$this->type = $content_item['type'];
		$this->source = 'null';
		if (isset($content_item['source'])){
			$this->source = $content_item['source'];
		}
		$this->slug = array_key_exists('slug',$content_item)?$content_item['slug']:NULL;
		$this->owner_module = $owner_module;
		if(strcmp($this->type,'introduction')==0){
			$this->title = "Introduction";
		} else {
			$this->title = empty($content_item['title'])?$content_item['source']:$content_item['title'];
		}
		if(strcmp($this->type,'html')==0){
			$this->slug = $this->source;
		}
		if(isset($parent)){
			$this->parent = $parent;
			$this->parent_slug = $parent->slug_path;
		}
		$this->slug_path = $this->slugify();
		$this->calculate_hidden();
	}

	public function get_hidden(){
		$hidden = array();
		if($this->hidden > 0) $hidden[] = $this;
		return $hidden;
	}

	public function slugify(){
		$slug = '';
		if(isset($this->parent_slug)){
			$slug .= $this->parent_slug;
		}
		if(empty($this->slug)){
			$slug .= '/'.strtolower(preg_replace("/[^A-z0-9_\/]/", '', preg_replace("/\s+/", '_', $this->title)));
		} else {
			$slug .= '/'.$this->slug;
		}
		return $slug;
	}

	public function calculate_hidden(){
		// Check to see if this item's hidden status already been calculated
		// or has been forced.
		if($this->hidden > 0) return;

		//Content is hidden because the parent item is hidden
		if(isset($this->parent) && $this->parent->hidden) {
			$this->hidden = 3;
			$this->hidden_reason='Hidden (via parent)';
			return;
		}

		//Check if startdate hasn't passed yet
		$now = new DateTime();
		if(isset($this->start_datetime)){
			$start = new DateTime($this->start_datetime);
			if($now < $start){
				$this->hidden = 2;
				$this->hidden_reason='Hidden (Too early)';
				return;
			}
		}

		//Check if enddate has already passed
		if(isset($this->end_datetime)){
			$end = new DateTime($this->end_datetime);
			if($now > $end){
				$this->hidden = 2;
				$this->hidden_reason='Hidden (Too late)';
				return;
			}
		}

		// Check if this item will be hidden because items are hidden by default
		if(isset($this->owner_module->resource->options)){
			if(!isset($this->end_datetime) && !isset($this->start_datetime) && $this->owner_module->resource->options['hide_by_default']){
				$this->hidden = 4;
				$this->hidden_reason='Hidden (by default)';
			}
		}
		if($this->hidden > 0) return;

		foreach ($this->children as $child_content){
			if($child_content->hidden){
				$this->hidden_reason='Partially Hidden';
			}
		}
	}
	public function apply_overrides($content_overrides){
		foreach ($content_overrides as $override){
			if(strcmp($override['slug_path'], $this->slug_path)==0){
				$this->start_datetime = $override['start_datetime'];
				$this->end_datetime = $override['end_datetime'];
				if($override['hidden']==1){
					$this->hidden = 1;
					$this->hidden_reason='Hidden (Forced)';
				} else {
					$this->hidden = 0;
				}
			}
		}
		foreach($this->children as $child_content){
			$child_content->apply_overrides($content_overrides);
		}
		$this->calculate_hidden();
	}
}

class Part extends Content {
	public $type = 'part';

	public function __construct($content_item, $owner_module) {
		$this->title = $content_item['title'];
		$this->slug = array_key_exists('slug',$content_item)?$content_item['slug']:NULL;
		$this->slug_path = $this->slugify();
		$this->owner_module = $owner_module;

		if(isset($content_item['content'])){
			foreach ($content_item['content'] as $child_content){
				$this->children[] = new Content($child_content, $this->owner_module, $this);
			}
		}

		$this->calculate_hidden();
	}
	
	public function get_hidden(){
		$hidden = array();
		if($this->hidden) $hidden[] = $this;
		foreach ($this->children as $child_content){
			$hidden = array_merge($hidden,$child_content->get_hidden());
		}
		return $hidden;
	}
}

class Standalone extends Part {
	public $type = 'standalone';
	public function __construct($content_item, $owner_module) {
		$this->title = isset($content_item['title'])?$content_item['title']:"";
		$this->slug = '';
		$this->slug_path = '/';
		$this->owner_module = $owner_module;

		if(isset($content_item['content'])){
			foreach ($content_item['content'] as $child_content){
				$this->children[] = new Content($child_content, $this->owner_module, $this);
			}
		}

		$this->calculate_hidden();
	}
}

class ModuleTheme {
	public $path = 'default';
	public $title = 'Default';
	public $module = NULL;
	public function __construct($module, $theme_info=NULL) {
		$this->module = $module;
		if(empty($theme_info)) return;
		$this->path = $theme_info['path'];
		$this->title = $theme_info['title'];
	}
}

class Module {
	public $title = NULL;
	public $year = NULL;
	public $author = NULL;
	public $code = NULL;
	public $yaml_path = NULL;
	public $root_url =  NULL;
	public $id = NULL;
	public $selected_theme = NULL;
	public $resource = NULL;
	public $yaml = array();
	public $content = array();
	public $themes = array();

	public function __construct($yaml_path = "", $module_id = NULL, $resource = NULL) {
		if(empty($yaml_path)) return;

		$this->id = $module_id;
		$this->yaml_path = $yaml_path;
		$this->parse_yaml();
		$this->parse_content();
		$this->parse_themes();

		if(isset($module_id) && isset($resource)){
			$this->resource = $resource;
			foreach ($this->content as $content_item){
				$content_item->apply_overrides($resource->getAdaptiveRelease());
			}
		}
	}
	
	public function get_hidden(){
		$hidden = array();
		foreach ($this->content as $content){
			$hidden = array_merge($hidden,$content->get_hidden());
		}
		return $hidden;
	}

	public function get_direct_linked_item(){
		if(array_key_exists('direct_link_slug',$this->resource->options)){
			foreach ($this->content as $content){
				if ($content->slug_path == $this->resource->options['direct_link_slug']){
					return $content;
				}
				foreach ($content->children as $item){
					if ($item->slug_path == $this->resource->options['direct_link_slug']){
						return $item;
					}
				}
			}
		}
		return null;
	}

	public function real_path(){
		return CONTENTDIR.dirname($this->yaml_path);
	}

	public function indexContent($student_view = true){
		$path = '';

		// Find the direct linked item if it exists for this resource,
		// and set the path accordingly if so
		if($this->get_direct_linked_item() && $student_view){
			if($this->get_direct_linked_item()->type != 'introduction' && $this->get_direct_linked_item()->type != 'standalone'){
				if(strcmp($this->get_direct_linked_item()->type,'html')==0){
					$path = $this->get_direct_linked_item()->slug_path;
				} else {
					$path = $this->get_direct_linked_item()->slug_path.'/';
				}
				$path = ltrim($path,'/');
			}
		}

		// If this module is standalone, ignore direct link and get the path of the standalone item instead
		$noIntroContent = array_filter($this->content, function($item) { return $item->type != 'introduction'; });
		if (count($noIntroContent) == 1 && $student_view){
			$path = $noIntroContent[0]->slug_path.'/';
		}
		$path = ltrim($path,'/');

		// Get the full path to the item, using the root_url property
		$indexContent = str_replace('{base}/','',$this->root_url);
		$indexContent = str_replace('{code}',$this->code, $indexContent);
		$indexContent = str_replace('{year}',$this->year, $indexContent);
		$indexContent = str_replace('{theme}',empty($this->selected_theme)?'':$this->selected_theme->path, $indexContent);
		$indexContent .= $path;
		return ltrim($indexContent,'/');
	}

	public function url($student_view = true){
		return WEBCONTENTDIR.'/'.$this->indexContent($student_view);
	}

	public function select_theme($theme_id){
		if(count($this->themes) > 0) $this->selected_theme = $this->themes[$theme_id];
	}

	public function parse_yaml() {
		$this->yaml = @yaml_parse_file(CONTENTDIR.$this->yaml_path);
		if(!$this->yaml) return NULL;
		$this->title = empty($this->yaml['title'])?NULL:$this->yaml['title'];
		$this->year = empty($this->yaml['year'])?NULL:$this->yaml['year'];
		$this->author = empty($this->yaml['author'])?NULL:$this->yaml['author'];
		$this->code = $this->yaml['code'];
		$this->root_url = empty($this->yaml['root_url'])?'/{base}/{code}/{year}/{theme}/':$this->yaml['root_url'];
	}

	public function parse_content() {
		if(!$this->yaml) return NULL;
		$content_overrides = NULL;
		foreach ($this->yaml['structure'] as $content_item){
			if (strcmp($content_item['type'],"standalone")==0){
				$this->content[] = new Standalone($content_item, $this);
			} else if (strcmp($content_item['type'],"part")==0 || strcmp($content_item['type'],"document")==0){
				$this->content[] = new Part($content_item, $this);
			} else {
				$this->content[] = new Content($content_item, $this);
			}
		}
	}

	public function parse_themes() {
		if(!$this->yaml) return NULL;
		foreach ($this->yaml['themes'] as $theme_info){
			$this->themes[] = new ModuleTheme($this,$theme_info);
		}
	}
	
	public function get_content_for_path($path){
		foreach ($this->content as $content_item){
			foreach ($content_item->children as $content_child){
				if (strpos($path, $content_child->slug_path) !== false){
					return $content_child;
				}
			}
			if (strpos($path, $content_item->slug_path) !== false){
				return $content_item;
			}
		}
		return false;
	}
}

?>
