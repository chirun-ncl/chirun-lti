<?php

###
### Class representing some coursebuilder module content; e.g. a chapter
###
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
	
	public function __construct($content_item, $parent = NULL) {
		$this->type = $content_item['type'];
		if(strcmp($this->type,'introduction')==0){
			$this->title = "Introduction";
		} else {
			$this->title = $content_item['title'];
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
		if($this->hidden) $hidden[] = $this->slug_path;
		return $hidden;
	}

	public function adaptive_release_tbl_row(){
		$html = '<tr>';
		$html .= '<td></td>';
		$html .= '<td>'.$this->title.'</td>';
		$html .= '<td>'.$this->hidden_reason.'</td>';
		$html .= '<td>'.($this->start_datetime?
			'<input class="flatpickr" name="content['.$this->slug_path.'][start_datetime]"
				type="text" value="'.$this->start_datetime.'">':
			'<input class="flatpickr" name="content['.$this->slug_path.'][start_datetime]"
				type="text">').'</td>';
		$html .= '<td>'.($this->end_datetime?
			'<input class="flatpickr" name="content['.$this->slug_path.'][end_datetime]"
				type="text" value="'.$this->end_datetime.'">':
			'<input class="flatpickr" name="content['.$this->slug_path.'][end_datetime]"
				type="text">').'</td>';
		$html .= '<td class="text-center"><input type="checkbox" name="content['.$this->slug_path.'][checked]"';
		$html .= ($this->hidden==1?' checked ':'').'></td>';
		$html .= '</tr>';
		return $html;
	}

	public function slugify(){
		$slug = '';
		if(isset($this->parent_slug)){
			$slug .= $this->parent_slug;
		}
		$slug .= '/'.strtolower(preg_replace("/[^A-Za-z0-9]/", '_', $this->title));
		return $slug;
	}

	public function calculate_hidden(){
		if($this->hidden) return; //Content is already known to be hidden
		if(isset($this->parent) && $this->parent->hidden) {//Content is hidden because parent is hidden
			$this->hidden = 3;
			$this->hidden_reason='Hidden (via parent)';
			return;
		}
		//Check if startdate hasn't passed yet
		$now = strtotime(date("Y-m-d H:i:s"));
		if(isset($this->start_datetime)){
			if($now < strtotime($this->start_datetime)){
				$this->hidden = 2;
				$this->hidden_reason='Hidden (Too early)';
			}
		}
		if(isset($this->end_datetime)){
			if($now > strtotime($this->end_datetime)){
				$this->hidden = 2;
				$this->hidden_reason='Hidden (Too late)';
			}
		}
		if($this->hidden) return;

		foreach ($this->children as $child_content){
			if($child_content->hidden){
				$this->hidden = 0;
				$this->hidden_reason='Partially Hidden';
			}
		}
	}
	public function apply_overrides($content_overrides){
		foreach ($content_overrides as $override){
			if(strcmp($override['slug_path'],$this->slug_path)==0){
				$this->start_datetime = $override['start_datetime'];
				$this->end_datetime = $override['end_datetime'];
				if($override['hidden']==1){
					$this->hidden = TRUE; 
					$this->hidden_reason='Hidden (Always)';
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

	public function __construct($content_item) {
		$this->title = $content_item['title'];
		$this->slug_path = $this->slugify();

		foreach ($content_item['content'] as $child_content){
			$this->children[] = new Content($child_content, $this);
		}

		$this->calculate_hidden();
	}
	
	public function get_hidden(){
		$hidden = array();
		if($this->hidden) $hidden[] = $this->slug_path;
		foreach ($this->children as $child_content){
			$hidden = array_merge($hidden,$child_content->get_hidden());
		}
		return $hidden;
	}

	public function adaptive_release_tbl_row(){
		$html = "<tbody><tr class='table-primary'>";
		$html .= "<td class='clickable collapsed' data-toggle='collapse' data-target='#table-part-{$this->slug_path}' aria-expanded='false' aria-controls='table-part-{$this->slug_path}'>";

		$html .= '<i class="fa fa-3 fa-chevron-down"></i></td>';
		$html .= '<td>'.$this->title.'</td>';
		$html .= '<td>'.$this->hidden_reason.'</td>';
		$html .= '<td>'.($this->start_datetime?
			'<input class="flatpickr" name="content['.$this->slug_path.'][start_datetime]"
				type="text" value="'.$this->start_datetime.'">':
			'<input class="flatpickr" name="content['.$this->slug_path.'][start_datetime]"
				type="text">').'</td>';
		$html .= '<td>'.($this->end_datetime?
			'<input class="flatpickr" name="content['.$this->slug_path.'][end_datetime]"
				type="text" value="'.$this->end_datetime.'">':
			'<input class="flatpickr" name="content['.$this->slug_path.'][end_datetime]"
				type="text">').'</td>';
		$html .= '<td class="text-center"><input type="checkbox" name="content['.$this->slug_path.'][checked]"';
		$html .= ($this->hidden==1?' checked ':'').'></td>';
		$html .= '</tr></tbody>';
		$html .= "<tbody id='table-part-{$this->slug_path}' class='collapse'>";
		foreach ($this->children as $child_content){
			$html .= $child_content->adaptive_release_tbl_row();
		}
		$html .= '</tbody>';
		return $html;
	}
}


###
###  Class representing a set of coursebuilder notes for a module
###
class Module {
	public $title = NULL;
	public $year = NULL;
	public $author = NULL;
	public $code = NULL;
	public $yaml_path = NULL;
	public $default_theme_path = NULL;
	public $selected_id = NULL;
	public $yaml = array();
	public $content = array();

	public function __construct($yaml_path = "", $module_selected_id = NULL) {
		if(empty($yaml_path)) return;
		$this->selected_id = $module_selected_id;
		$this->yaml_path = $yaml_path;
		$this->parse_yaml();
		$this->parse_content();
		$this->default_theme_path = dirname($yaml_path).'/'.$this->yaml['themes'][0]['path'];
	}
	
	public function get_hidden(){
		$hidden = array();
		foreach ($this->content as $content){
			$hidden = array_merge($hidden,$content->get_hidden());
		}
		return $hidden;
	}

	public function url(){
		if($this->default_theme_path){
			return LTI_CONTENTDIR.$this->default_theme_path.'/';
		}
		return NULL;
	}

	public function parse_yaml() {
		$this->yaml = @yaml_parse_file(MODULEDIR.$this->yaml_path);
		if(!$this->yaml) return NULL;
		$this->title = $this->yaml['title'];
		$this->year = $this->yaml['year'];
		$this->author = $this->yaml['author'];
		$this->code = $this->yaml['code'];
	}

	public function parse_content() {
		if(!$this->yaml) return NULL;
		$content_overrides = NULL;
		foreach ($this->yaml['structure'] as $content_item){
			if (strcmp($content_item['type'],"part")==0){
				$this->content[] = new Part($content_item);
			} else {
				$this->content[] = new Content($content_item);
			}
		}
	}
	
	public function get_content_for_path($path){
		$path_noext = substr($path, 0, strrpos($path, ".")); 
		foreach ($this->content as $content_item){
			if (substr($path_noext, -strlen($content_item->slug_path)) === $content_item->slug_path){
				return $content_item;
			}
			foreach ($content_item->children as $content_child){
				if (substr($path_noext, -strlen($content_child->slug_path)) === $content_child->slug_path){
					return $content_child;
				}
			}
		}
		return false;
	}
	
	public function apply_content_overrides($db){
		if($this->selected_id){
			$content_overrides = getContentOverrides($db, $this->selected_id);
			foreach ($this->content as $content_item){
				$content_item->apply_overrides($content_overrides);
			}
		}
	}
}

?>
