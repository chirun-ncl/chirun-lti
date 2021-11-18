<?php
/*
 * This page provides general functions to support the application.
 */

use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

error_reporting(0);
@ini_set('display_errors', false);

// Uncomment the next line to enable error messages
error_reporting(E_ALL);

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/db.php');
require_once(__DIR__.'/resource.php');
require_once(__DIR__.'/session.php');
require_once(__DIR__.'/process.php');
require_once(__DIR__.'/action.php');
require_once(__DIR__.'/content.php');
require_once(__DIR__.'/mime_type.php');
require_once(__DIR__.'/page.php');

// Initialise application session and database connection
function init(&$db, $checkSession = NULL) {
	$ok = TRUE;

	// Open database connection
	$db = open_db(!$checkSession);
	if (!$db) {
		return;
	}

	// Set timezone
	if (!ini_get('date.timezone')) {
		date_default_timezone_set('UTC');
	}

	// Set session cookie path
	ini_set('session.cookie_path', getAppPath());

	if(array_key_exists('do_sessid', $_GET) && !is_null($_GET['do_sessid']) &&
		array_key_exists(SESSION_NAME.'_sessid', $_GET) && !is_null($_GET[SESSION_NAME.'_sessid'])){
		session_id($_GET[SESSION_NAME.'_sessid']);
	}

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
		} else {
			// Create database tables (if needed)
			$ok = init_db($db);
			if (!$ok) {
				$_SESSION['error_message'] = 'Unable to initialise database.';
			}
		}
	}
	return $ok;
}

// Get the web path to the application
function getAppPath() {
	return WEBDIR.'/';
}

// Find all modules on the filesystem 
function getModules() {
	$configpaths = recursiveSearchScan(CONTENTDIR,'.yml');
	$modules = array();
	foreach($configpaths as $config){
		$yaml_path = str_replace(CONTENTDIR,'',$config);
		$module = new Module($yaml_path);
		if (isset($module->code) && preg_match('/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4,5}-[a-z0-9]{12}/i', $module->code)===0 ){
			$modules[] = $module;
		}
	}
	return $modules;
}

// Search a directory tree recursively, optionally matching a string
function recursiveSearchScan($dir, $sstr='', &$results = array()) {
	$tree = glob(rtrim($dir, '/') . '/*');
	if (is_array($tree)) {
		foreach($tree as $file) {
			if (is_dir($file)) {
				recursiveSearchScan($file,$sstr,$results);
			} elseif (is_file($file)) {
				if(strpos($file, $sstr) !== false){
					$results[] = $file;
					return $results;
				}
			}
		}
	}
	return $results;
}

// Delete a directory recursively from disk
function delTree($dir) { 
	$files = array_diff(scandir($dir), array('.','..')); 
	foreach ($files as $file) { 
		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
	} 
	return rmdir($dir); 
} 

// Get the application domain URL
function getHost() {
	$scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on")
	? 'http'
	: 'https';
	$url = $scheme . '://' . $_SERVER['HTTP_HOST'];

	return $url;
}

// Get the URL to the application
function getAppUrl() {
	$url = getHost() . getAppPath();
	return $url;
}

// Return a string representation of a float value
function floatToStr($num) {
	$str = sprintf('%f', $num);
	$str = preg_replace('/0*$/', '', $str);
	if (substr($str, -1) == '.') {
		$str = substr($str, 0, -1);
	}
	return $str;
}

// Unzip a file in current directory
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

/*
	Returns a string representation of a version 4 GUID, which uses random
	numbers.There are 6 reserved bits, and the GUIDs have this format:
		xxxxxxxx-xxxx-4xxx-[8|9|a|b]xxx-xxxxxxxxxxxx
	where 'x' is a hexadecimal digit, 0-9a-f.
	See http://tools.ietf.org/html/rfc4122 for more information.
	Source: https://github.com/Azure/azure-sdk-for-php/issues/591
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
