<?php

class Resource {
	public $db = NULL;
	public $resource_link_pk = NULL;
	public $module = NULL;
	public $options = NULL;
	public $adaptive_release = NULL;

	public static function getPublicModules($db) {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT module_selected_id, module_yaml_path, {$prefix}module_selected.resource_link_pk as resource_link_pk
				FROM {$prefix}module_selected
				JOIN {$prefix}resource_options
				ON {$prefix}module_selected.resource_link_pk = {$prefix}resource_options.resource_link_pk
				WHERE {$prefix}resource_options.public_access = 1";
		$query = $db->prepare($sql);
		$query->execute();
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		$modules=array();
		foreach($rows as $row){
			$modules[] = $row;
		}
		return $modules;
	}

	public function __construct($db, $resource_link_pk) {
		$this->db = $db;
		$this->resource_link_pk = $resource_link_pk;
		$this->getSelectedModule();
		$this->getResourceOptions();
	}

	public function isModuleEmpty(){
		if (!isset($this->module)) return true;
		if (empty($this->module->code)) return true;
		return false;
	}

	public function isModuleStandalone(){
		if (!isset($this->module)) return false;
		// Check to see if there's only one content item. If there is, return true
		$noIntroContent = array_filter($this->module->content, function($item) { return $item->type != 'introduction'; });
		if (count($noIntroContent) == 1 && isset($noIntroContent[0]->slug_path)){
			return true;
		}
		return false;
	}

	protected function getSelectedModule() {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT module_selected_id, module_yaml_path, module_theme_id
				FROM {$prefix}module_selected
				WHERE (resource_link_pk = :resource_pk)";

		$query = $this->db->prepare($sql);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->execute();

		$row = $query->fetch(PDO::FETCH_ASSOC);
		if (isset($row['module_yaml_path'])){
			$this->module = new Module($row['module_yaml_path'], $row['module_selected_id'], $this);
			$this->module->select_theme($row['module_theme_id']);
			return $this->module;
		} else {
			return NULL;
		}
	}

	protected function getResourceOptions() {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT *
				FROM {$prefix}resource_options
				WHERE (resource_link_pk = :resource_pk)
				LIMIT 1";
		$query = $this->db->prepare($sql);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->execute();
		$this->options = $query->fetch(PDO::FETCH_ASSOC);
		return $this->options;
	}

	public function deselectModule() {
		if (!isset($this->module)) return;
		$module_realdir = $this->module->real_path();
		$ok = true;

		// Delete the item from the disk if user uploaded
		if($this->options['user_uploaded']){
			$this->updateOptions(array('user_uploaded'=>0));
			$ok = false;
			if(!empty($module_realdir) && is_dir($module_realdir)){
				$ok = delTree($module_realdir);
			}
		}

		// Delete the item from the DB
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "DELETE FROM {$prefix}module_selected
				WHERE (module_selected_id = :module_selected_id) AND (resource_link_pk = :resource_pk)";
	
		$query = $this->db->prepare($sql);
		$query->bindValue('module_selected_id', $this->module->id, PDO::PARAM_INT);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_STR);
		$ok = $query->execute();
		return $ok;
	}

	public function selectModule($module_path, $theme_id = 0) {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "REPLACE INTO {$prefix}module_selected (resource_link_pk, module_yaml_path, module_theme_id)
				VALUES (:resource_pk, :module_path, :theme_id)";
		$query = $this->db->prepare($sql);
		$query->bindValue('module_path', $module_path, PDO::PARAM_STR);
		$query->bindValue('theme_id', $theme_id, PDO::PARAM_INT);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		return $query->execute();
	}

	public function updateOptions($opt){
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "REPLACE INTO {$prefix}resource_options (resource_link_pk, hide_by_default, user_uploaded, public_access, direct_link_slug)
				VALUES (:resource_pk, :hide_by_default, :user_uploaded, :public_access ,:direct_link_slug)";
		$query = $this->db->prepare($sql);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->bindValue('hide_by_default', !empty($opt['hide_by_default']), PDO::PARAM_INT);
		$query->bindValue('user_uploaded', !empty($opt['user_uploaded']), PDO::PARAM_INT);
		$query->bindValue('public_access', !empty($opt['public_access']), PDO::PARAM_INT);
		$query->bindValue('direct_link_slug', array_key_exists('direct_link_slug',$opt)?$opt['direct_link_slug']:'/', PDO::PARAM_STR);
		return $query->execute();
	}

	public function selectTheme($theme_id = 0) {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "UPDATE {$prefix}module_selected SET module_theme_id = :theme_id
				WHERE resource_link_pk = :resource_pk";
		$query = $this->db->prepare($sql);
		$query->bindValue('theme_id', $theme_id, PDO::PARAM_INT);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		return $query->execute();
	}

	public function updateAdaptiveRelease($slug_path, $start_datetime, $end_datetime, $hidden) {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "REPLACE INTO {$prefix}resource_adaptive_release (resource_link_pk,slug_path,start_datetime,end_datetime,hidden)
				VALUES (:resource_link_pk, :slug_path, :start_datetime, :end_datetime, :hidden)";
		$query = $this->db->prepare($sql);
		$query->bindValue('resource_link_pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->bindValue('slug_path', $slug_path, PDO::PARAM_STR);
		$query->bindValue('start_datetime', $start_datetime, PDO::PARAM_STR);
		$query->bindValue('end_datetime', $end_datetime, PDO::PARAM_STR);
		$query->bindValue('hidden', $hidden, PDO::PARAM_INT);
		return $query->execute();
	}

	public function getAdaptiveRelease() {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT resource_link_pk,slug_path,start_datetime,end_datetime,hidden
				FROM {$prefix}resource_adaptive_release
				WHERE (resource_link_pk = :pk)";

		$query = $this->db->prepare($sql);
		$query->bindValue('pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->execute();

		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		$adaptive_release = array();
		foreach($rows as $row){
			$adaptive_release[] = $row;
		}
		return $adaptive_release;
	}

	public function deleteResourceAdaptiveRelease() {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "DELETE FROM {$prefix}resource_adaptive_release
				WHERE (resource_link_pk = :resource_link_pk)";
		$query = $this->db->prepare($sql);
		$query->bindValue('resource_link_pk', $this->resource_link_pk, PDO::PARAM_INT);
		return $query->execute();
	}

	public function getAllUserSessions() {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT user_email, user_id, user_fullname, isStudent, isStaff, timestamp, expiry
				FROM {$prefix}user_session
				WHERE (resource_link_pk = :resource_pk)";
		$query = $this->db->prepare($sql);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->execute();
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		$user_sessions=array();
		foreach($rows as $row){
			$user_sessions[] = $row;
		}
		return $user_sessions;
	}

	public function addDataKVStore($user, $data_key, $data_value){
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "REPLACE INTO {$prefix}key_value_store (resource_link_pk, username, data_key, data_value)
				VALUES (:resource_pk, :user, :data_key, :data_value)";
		$query = $this->db->prepare($sql);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->bindValue('user', $user, PDO::PARAM_STR);
		$query->bindValue('data_key', $data_key, PDO::PARAM_STR);
		$query->bindValue('data_value', $data_value, PDO::PARAM_STR);
		return $query->execute();
	}

	public function getDataKVStore($user, $data_key) {
		$prefix = DB_TABLENAME_PREFIX;
		$sql = "SELECT data_value
				FROM {$prefix}key_value_store
				WHERE
					resource_link_pk = :resource_pk
					AND username = :user
					AND data_key = :data_key";
		$query = $this->db->prepare($sql);
		$query->bindValue('resource_pk', $this->resource_link_pk, PDO::PARAM_INT);
		$query->bindValue('user', $user, PDO::PARAM_STR);
		$query->bindValue('data_key', $data_key, PDO::PARAM_STR);
		$query->execute();

		$row = $query->fetch(PDO::FETCH_ASSOC);
		if (isset($row['data_value'])){
			return $row['data_value'];
		}
		return NULL;
	}
}

?>
