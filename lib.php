<?php
/**
 * rating - Rating: an example LTI tool provider
 *
 * @author  Stephen P Vickers <svickers@imsglobal.org>
 * @copyright  IMS Global Learning Consortium Inc
 * @date  2016
 * @version 2.0.0
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3.0
 */

/*
 * This page provides general functions to support the application.
 */

use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

error_reporting(0);
@ini_set('display_errors', false);

###  Uncomment the next line to enable error messages
error_reporting(E_ALL);

require_once('db.php');
require_once('module.php');

###
###  Initialise application session and database connection
###
function init(&$db, $checkSession = NULL) {

	$ok = TRUE;

	// Set timezone
	if (!ini_get('date.timezone')) {
		date_default_timezone_set('UTC');
	}

	// Set session cookie path
	ini_set('session.cookie_path', getAppPath());

	// Open session
	session_name(SESSION_NAME);
	session_start();

	if (!is_null($checkSession) && $checkSession) {
		$ok = isset($_SESSION['consumer_pk']) && (isset($_SESSION['resource_pk']) || is_null($_SESSION['resource_pk'])) &&
			isset($_SESSION['user_consumer_pk']) && (isset($_SESSION['user_pk']) || is_null($_SESSION['user_pk'])) && isset($_SESSION['isStudent']);
	}

	if (!$ok) {
		$_SESSION['error_message'] = 'Unable to open session.';
	} else {
		// Open database connection
		$db = open_db(!$checkSession);
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
### Write out module content to HTTP response, unless it's hidden!
###

function getModuleContent($module, $real_page_req){
	$final_page_req = false;
	
	if (is_file($real_page_req)){
		$final_page_req = $real_page_req;
	}

	if (is_dir($real_page_req)){
		if(is_file($real_page_req.'/index.html')){
			$final_page_req = $real_page_req.'/index.html';
		} else if (is_file($real_page_req.'/index.php')){
			$final_page_req = $real_page_req.'/index.php';
		}
	}

	if ($final_page_req){
		$matched_content = $module->get_content_for_path($final_page_req);
		if($matched_content && $matched_content->hidden>0){
			echo "HIDDEN";
			exit;
		}
		$ext = pathinfo($final_page_req, PATHINFO_EXTENSION);
		if ($ext === 'php'){
			include $final_page_req;
			return true;
		} else {
			header('Content-Type: ' . get_file_mime_type($final_page_req));
			header('Content-Disposition: inline; filename="'.basename($final_page_req).'"');
			readfile($final_page_req);
			return true;
		}
	}
	return false;
}

###
###  Return the module for a specified resource link
###
function getSelectedModule($db, $resource_pk) {

	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
SELECT module_selected_id, module_yaml_path
FROM {$prefix}module_selected
WHERE (resource_link_pk = :resource_pk)
EOD;

	$query = $db->prepare($sql);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	$query->execute();

	$row = $query->fetch(PDO::FETCH_ASSOC);
	if (isset($row['module_yaml_path'])){
		$module = new Module($row['module_yaml_path'], $row['module_selected_id']);
		return $module;
	} else {
		return NULL;
	}
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
function selectModule($db, $resource_pk, $module_path) {
	$prefix = DB_TABLENAME_PREFIX;
	$sql = <<< EOD
INSERT INTO {$prefix}module_selected (resource_link_pk, module_yaml_path)
VALUES (:resource_pk, :module_path)
EOD;
	$query = $db->prepare($sql);
	$query->bindValue('module_path', $module_path, PDO::PARAM_STR);
	$query->bindValue('resource_pk', $resource_pk, PDO::PARAM_INT);
	return $query->execute();
}

###
###  Deselect a specified module including any related page settings
###
function deleteModule($db, $resource_pk, $module_selected_id) {

	// Delete any pages
	//deleteRatings($db, $item_pk);

	// Delete the item
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
###  Get the web path to the application
###
function getAppPath() {

	$root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
	if (substr($root, -1) === '/') {  // remove any trailing / which should not be there
		$root = substr($root, 0, -1);
	}
	$dir = str_replace('\\', '/', dirname(__FILE__));

	$path = str_replace($root, '', $dir) . '/';

	return $path;

}

###
###  Find all modules on the filesystem 
###
function getModules() {
	$configpaths = recursiveSearchScan(MODULEDIR,'.yml');
	$modules = array();
	foreach($configpaths as $config){
		$yaml_path = str_replace(MODULEDIR,'',$config);
		$module = new Module($yaml_path);
		if (isset($module->code)){
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
