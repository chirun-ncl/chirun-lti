<?php
/*
 * This page displays a UI for registering the tool with a tool consumer.
 */

require_once('lib/vendor/autoload.php');
require_once('lib/toolprovider.php');

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

// Initialise session and database
$error_msg = '';
$db = NULL;
$loader = new FilesystemLoader('lib/templates');
$twig = new Environment($loader, [
	'cache' => '/tmp/cb_site_cache/php',
	'auto_reload' => true,
]);

if (init($db)) {
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {		
		$url = $_SESSION['return_url'];
		if (strpos($url, '?') === FALSE) {
			$sep = '?';
		} else {
			$sep = '&';
		}
		$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
		$tool = new RatingToolProvider($data_connector);
		$tool->consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
		$do = $_POST['do'];
		if ($do == 'Register') {
			$ok = $tool->doToolProxyService($_SESSION['tc_profile_url']);
			if ($ok) {
				$guid = $tool->consumer->getKey();
				header("Location: {$url}{$sep}lti_msg=The%20tool%20has%20been%20registered&status=success&tool_proxy_guid={$guid}");
				exit;
			} else {
				$error_msg = 'Error setting tool proxy';
			}
		} else if ($do == 'Cancel') {
			$tool->consumer->delete();
			header("Location: {$url}{$sep}lti_msg=The%20tool%20registration%20has%20been%20cancelled&status=failure");
			exit;
		}
	}
}

$page = $twig->load('register.html');
echo $page->render(['error' => $error_msg]);

?>
