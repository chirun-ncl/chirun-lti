<?php
/*
 * This page provides general functions to support the application.
 */

use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

error_reporting(0);
@ini_set('display_errors', false);

###  Uncomment the next line to enable error messages
error_reporting(E_ALL);

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/db.php');
require_once(__DIR__.'/action.php');
require_once(__DIR__.'/module.php');
require_once(__DIR__.'/mime_type.php');
require_once(__DIR__.'/page.php');

###
###  Initialise application session and database connection
###
function init(&$db, $checkSession = NULL) {

	$ok = TRUE;

	// Open database connection
	$db = open_db(!$checkSession);

	// Set timezone
	if (!ini_get('date.timezone')) {
		date_default_timezone_set('UTC');
	}

	// Set session cookie path
	ini_set('session.cookie_path', getAppPath());

	// Open session
	session_name(SESSION_NAME);

	// Set session samesite to None
	session_set_cookie_params(0, '/;SameSite=None; Secure; HttpOnly');

	session_start();

	if (!is_null($checkSession) && $checkSession) {
		$ok = isset($_SESSION['consumer_pk']) && (isset($_SESSION['resource_pk']) || is_null($_SESSION['resource_pk'])) &&
			isset($_SESSION['user_consumer_pk']) && (isset($_SESSION['user_pk']) || is_null($_SESSION['user_pk'])) && isset($_SESSION['isStudent']);
	}

	if (!$ok) {
		$_SESSION['error_message'] = 'Unable to open session. Ensure that you are loading this page through your VLE (e.g. via a module in Blackboard or Canvas).';
	} else {
		$ok = $db !== FALSE;
		if (!$ok) {
			if (!is_null($checkSession) && $checkSession) {
				// Display a more user-friendly error message to LTI users
				$_SESSION['error_message'] = 'Unable to open database.';
			}
		} else {//if (!is_null($checkSession) && !$checkSession) {
			// Create database tables (if needed)
			$ok = init_db($db);  // assumes a MySQL/SQLite database is being used
			if (!$ok) {
				$_SESSION['error_message'] = 'Unable to initialise database.';
			}
		}
	}

	return $ok;

}


###
###  Return the module for a specified resource link
###
function getSelectedModule($db, $resource_pk) {

	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
SELECT module_selected_id, module_yaml_path, module_theme_id
FROM {$prefix}module_selected
WHERE (resource_link_pk = :resource_pk)
EOD;

	$query = $db->prepare($sql);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	$query->execute();

	$row = $query->fetch(PDO::FETCH_ASSOC);
	if (isset($row['module_yaml_path'])){
		$module = new Module($row['module_yaml_path'], $row['module_selected_id']);
		$module->select_theme($row['module_theme_id']);
		return $module;
	} else {
		return NULL;
	}
}

###
###  Return the user session for a specified token and username
###
function getUserSession($db, $user_id, $token) {

	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
SELECT *
FROM {$prefix}user_session
WHERE user_id = :user_id
AND user_session_token = :token
AND expiry > NOW()
EOD;

	$query = $db->prepare($sql);
	$query->bindValue('user_id', $user_id, PDO::PARAM_STR);
	$query->bindValue('token', $token, PDO::PARAM_STR);
	$query->execute();

	$row = $query->fetch(PDO::FETCH_ASSOC);
	if (isset($row['resource_link_pk'])){
		return $row;
	} else {
		return NULL;
	}
}

###
###  Set current $_SESSION variables to match given user session
###

function setUserSession($session) {
        $_SESSION['user_id'] = $session['user_id'];
        $_SESSION['user_email'] = $session['user_email'];
        $_SESSION['user_fullname'] = $session['user_fullname'];
        $_SESSION['isStudent'] = $session['isStudent'];
        $_SESSION['isStaff'] = $session['isStaff'];
	$_SESSION['isAdmin'] = false;
}

###
###  Return every historial user session for a specified resource
###
function getAllUserSessions($db, $resource_pk) {

	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
SELECT user_email, user_id, user_fullname, isStudent, isStaff, timestamp, expiry
FROM {$prefix}user_session
WHERE (resource_link_pk = :resource_pk)
EOD;

	$query = $db->prepare($sql);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	$query->execute();

	$rows = $query->fetchAll(PDO::FETCH_ASSOC);
	$user_sessions=array();
	foreach($rows as $row){
		 $user_sessions[] = $row;
	}
	return $user_sessions;
}

###
###  Set upload username for a new upload
###

function setUploadUser($db, $guid, $username){
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
INSERT INTO {$prefix}uploaded_content (guid, username)
VALUES (:guid, :username)
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('guid', $guid, PDO::PARAM_STR);
	$query->bindValue('username', $username, PDO::PARAM_STR);
	return $query->execute();
}

###
###  Get upload username for an uploaded GUID
function getUploadUser($db, $guid){
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
SELECT username
FROM {$prefix}uploaded_content
WHERE (guid = :guid)
LIMIT 1
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('guid', $guid, PDO::PARAM_STR);
	$query->execute();
	$row = $query->fetch(PDO::FETCH_ASSOC);
	return $row['username'];
}


###
###  Return any set options for a specified resource
###
function getResourceOptions($db, $resource_pk) {

	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
SELECT *
FROM {$prefix}resource_options
WHERE (resource_link_pk = :resource_pk)
LIMIT 1
EOD;

	$query = $db->prepare($sql);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	$query->execute();
	$row = $query->fetch(PDO::FETCH_ASSOC);
	return $row;
}

###
###  Set options for a specified resource
###

function updateResourceOptions($db, $resource_pk, $opt){
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
REPLACE INTO {$prefix}resource_options (resource_link_pk, hide_by_default, user_uploaded, direct_link_slug)
VALUES (:resource_pk, :hide_by_default, :user_uploaded, :direct_link_slug)
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	$query->bindValue('hide_by_default', !empty($opt['hide_by_default']), PDO::PARAM_INT);
	$query->bindValue('user_uploaded', !empty($opt['user_uploaded']), PDO::PARAM_INT);
	$query->bindValue('direct_link_slug', array_key_exists('direct_link_slug',$opt)?$opt['direct_link_slug']:'/', PDO::PARAM_STR);
	return $query->execute();
}

###
###  Return any content overrides for a specified module selection
###
function getContentOverrides($db, $module_selected_id) {

	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
SELECT module_selected_id,slug_path,start_datetime,end_datetime,hidden
FROM {$prefix}module_content_overrides
WHERE (module_selected_id = :selected_id)
EOD;

	$query = $db->prepare($sql);
	$query->bindValue('selected_id', $module_selected_id, PDO::PARAM_INT);
	$query->execute();

	$rows = $query->fetchAll(PDO::FETCH_ASSOC);
	$content_overrides=array();
	foreach($rows as $row){
		 $content_overrides[] = $row;
	}
	return $content_overrides;
}

###
###  Update content overrides for a specified module selection
###
function updateContentOverrides($db, $module_selected_id, $slug_path, $start_datetime, $end_datetime, $hidden) {
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
REPLACE INTO {$prefix}module_content_overrides (module_selected_id,slug_path,start_datetime,end_datetime,hidden)
VALUES (:module_selected_id, :slug_path, :start_datetime, :end_datetime, :hidden)
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('module_selected_id', $module_selected_id, PDO::PARAM_INT);
	$query->bindValue('slug_path', $slug_path, PDO::PARAM_STR);
	$query->bindValue('start_datetime', $start_datetime, PDO::PARAM_STR);
	$query->bindValue('end_datetime', $end_datetime, PDO::PARAM_STR);
	$query->bindValue('hidden', $hidden, PDO::PARAM_INT);
	return $query->execute();
}

###
###  Delete content overrides for a specified module selection
###
function deleteContentOverrides($db, $module_selected_id) {
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
DELETE FROM {$prefix}module_content_overrides
WHERE (module_selected_id = :module_selected_id)
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('module_selected_id', $module_selected_id, PDO::PARAM_INT);
	return $query->execute();
}

###
###  Select a module to display for a specified resource link
###
function selectTheme($db, $resource_pk, $theme_id = 0) {
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
UPDATE {$prefix}module_selected SET module_theme_id = :theme_id
WHERE resource_link_pk = :resource_pk
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('theme_id', $theme_id, PDO::PARAM_INT);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	return $query->execute();
}
###
###  Select a module to display for a specified resource link
###

function selectModule($db, $resource_pk, $module_path, $theme_id = 0) {
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
REPLACE INTO {$prefix}module_selected (resource_link_pk, module_yaml_path, module_theme_id)
VALUES (:resource_pk, :module_path, :theme_id)
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('module_path', $module_path, PDO::PARAM_STR);
	$query->bindValue('theme_id', $theme_id, PDO::PARAM_INT);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	return $query->execute();
}

###
###  Deselect a specified module including any related page settings
###
function deleteModule($db, $resource_pk, $module_selected_id) {

	$selected_module = getSelectedModule($db, $resource_pk);
	$options = getResourceOptions($db, $resource_pk);
	$module_realdir = $selected_module->real_path();
	$ok = true;
	// Delete module content overrides
	deleteContentOverrides($db, $module_selected_id);
	// Delete the item from the disk if user uploaded
	if($options['user_uploaded']){
		updateResourceOptions($db, $resource_pk, array('user_uploaded'=>0));
		$ok = false;
		if(!empty($module_realdir) && is_dir($module_realdir)){
			$ok = delTree($module_realdir);
		}
	}
	// Delete the item from the DB
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
DELETE FROM {$prefix}module_selected
WHERE (module_selected_id = :module_selected_id) AND (resource_link_pk = :resource_pk)
EOD;
	
	$query = $db->prepare($sql);
	$query->bindValue('module_selected_id', $module_selected_id, PDO::PARAM_INT);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_STR);
	$ok = $query->execute();
	return $ok;
}

###
###  Add a user session to the databse
###
function addUserSession($db, $resource_pk, $token, $session) {
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
INSERT INTO {$prefix}user_session (user_session_token, resource_link_pk, 
	user_id, user_email, user_fullname, isStudent, isStaff, timestamp, expiry)
VALUES (:user_session_token, :resource_link_pk, :user_id, :user_email, 
	:user_fullname, :isStudent, :isStaff, NOW(), NOW() + INTERVAL 1 DAY)
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('user_session_token', $token, PDO::PARAM_STR);
	$query->bindValue('resource_link_pk', $resource_pk, PDO::PARAM_INT);
	$query->bindValue('user_id', $session['user_id'], PDO::PARAM_STR);
	$query->bindValue('user_email', $session['user_email'], PDO::PARAM_STR);
	$query->bindValue('user_fullname', $session['user_fullname'], PDO::PARAM_STR);
	$query->bindValue('isStudent', $session['isStudent'], PDO::PARAM_INT);
	$query->bindValue('isStaff', $session['isStaff'], PDO::PARAM_INT);
	return $query->execute();
}

function processWithSourceFile($db, $resource_pk, $source_main, $template_name="standalone"){
	$selected_module = getSelectedModule($db, $resource_pk);
	if(!isset($selected_module)) return false;
	$guid = basename(dirname($selected_module->yaml_path));
	if(!file_exists(UPLOADDIR.'/'.$guid.'/'.$source_main)) return false;
	$webbase = WEBCONTENTDIR;
	$logloc = PROCESSDIR.'/logs/'.$guid.'.log';
	$escaped_guid = escapeshellarg($guid);
	$escaped_source = escapeshellarg($source_main);
	$escaped_webbase = escapeshellarg($webbase);
	$escaped_logloc = escapeshellarg($logloc);
	$script_dir = PROCESSDIR;
	$script_owner = PROCESSUSER;
	$template = $template_name;
	exec("cd {$script_dir} && sudo -u {$script_owner} ./process.sh -g {$escaped_guid} -d {$escaped_source} -b {$escaped_webbase} -t {$template}  > {$escaped_logloc} 2>&1 &");
	setUploadUser($db, $guid, $_SESSION['user_id']);
	return true;
}

###
###  Get the web path to the application
###
function getAppPath() {
       return WEBDIR.'/';
}

###
###  Find all modules on the filesystem 
###
function getModules() {
	$configpaths = recursiveSearchScan(CONTENTDIR,'.yml');
	$modules = array();
	foreach($configpaths as $config){
		$yaml_path = str_replace(CONTENTDIR,'',$config);
		$module = new Module($yaml_path);
		if (isset($module->code) && preg_match('/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/i', $module->code)===0 ){
			$modules[] = $module;
		}
	}
	return $modules;
}

###
###  Search a directory tree recursively, optionally matching a string
###
function recursiveSearchScan($dir, $sstr='', &$results = array()) {
	$tree = glob(rtrim($dir, '/') . '/*');
	if (is_array($tree)) {
		foreach($tree as $file) {
			if (is_dir($file)) {
				recursiveSearchScan($file,$sstr,$results);
			} elseif (is_file($file)) {
				if(strpos($file, $sstr) !== false){
					$results[] = $file;
				}
			}
		}
	}
	return $results;
}

###
###  Get the application domain URL
###
function getHost() {

	$scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on")
		? 'http'
		: 'https';
	$url = $scheme . '://' . $_SERVER['HTTP_HOST'];

	return $url;

}


###
###  Get the URL to the application
###
function getAppUrl() {

	$url = getHost() . getAppPath();
	return $url;

}


###
###  Return a string representation of a float value
###
function floatToStr($num) {

	$str = sprintf('%f', $num);
	$str = preg_replace('/0*$/', '', $str);
	if (substr($str, -1) == '.') {
		$str = substr($str, 0, -1);
	}

	return $str;

}


###
###  Return the value of a POST parameter
###
function postValue($name, $defaultValue = NULL) {

	$value = $defaultValue;
	if (isset($_POST[$name])) {
		$value = $_POST[$name];
	}

	return $value;

}

###
### Delete a directory recursively from disk
###
function delTree($dir) { 
	$files = array_diff(scandir($dir), array('.','..')); 
	foreach ($files as $file) { 
		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
	} 
	return rmdir($dir); 
} 


###
### Unzip a file in current directory
###

function unzip($file){
	if(!($zipRealPath = realpath($file))) return false;
	$zip = new ZipArchive;
	if ($zip->open($zipRealPath) === TRUE) {
		$zip->extractTo(dirname($zipRealPath));
		$zip->close();
		return true;
	}
	return false;
}

/**
 * Returns a string representation of a version 4 GUID, which uses random
 * numbers.There are 6 reserved bits, and the GUIDs have this format:
 *     xxxxxxxx-xxxx-4xxx-[8|9|a|b]xxx-xxxxxxxxxxxx
 * where 'x' is a hexadecimal digit, 0-9a-f.
 *
 * See http://tools.ietf.org/html/rfc4122 for more information.
 *
 * Note: This function is available on all platforms, while the
 * com_create_guid() is only available for Windows.
 *
 * Source: https://github.com/Azure/azure-sdk-for-php/issues/591
 *
 * @return string A new GUID.
 */
function getGuid() {

	return sprintf('%04x%04x-%04x-%04x-%02x%02x-%04x%04x%04x',
			mt_rand(0, 65535),
			mt_rand(0, 65535),        // 32 bits for "time_low"
			mt_rand(0, 65535),        // 16 bits for "time_mid"
			mt_rand(0, 4096) + 16384, // 16 bits for "time_hi_and_version", with
			// the most significant 4 bits being 0100
			// to indicate randomly generated version
			mt_rand(0, 64) + 128,     // 8 bits  for "clock_seq_hi", with
			// the most significant 2 bits being 10,
			// required by version 4 GUIDs.
			mt_rand(0, 256),          // 8 bits  for "clock_seq_low"
			mt_rand(0, 65535),        // 16 bits for "node 0" and "node 1"
			mt_rand(0, 65535),        // 16 bits for "node 2" and "node 3"
			mt_rand(0, 65535)         // 16 bits for "node 4" and "node 5"
			);

}

?>
