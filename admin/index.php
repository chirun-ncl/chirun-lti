<?php

require_once('../lib/init.php');

use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$loader = new FilesystemLoader('../lib/templates');
$twig = new Environment($loader, [
	'cache' => '/tmp/cb_site_cache/php',
	'auto_reload' => true,
]);

// Initialise session and database
$db = NULL;
$ok = init($db, FALSE);
// Initialise parameters
$id = NULL;

if ($ok) {
	// Create LTI Tool Provider instance
	$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
	$tool = new ToolProvider\ToolProvider($data_connector);
	// Check for consumer id and action parameters
	$action = '';
	if (isset($_REQUEST['id'])) {
		$id = intval($_REQUEST['id']);
	}
	if (isset($_REQUEST['do'])) {
		$action = $_REQUEST['do'];
	}

	// Process add consumer action
	if (($action == 'add') && (!empty($id) || !empty($_REQUEST['key']))) {
		if (empty($id)) {
			$update_consumer = new ToolProvider\ToolConsumer($_REQUEST['key'], $data_connector);
			$update_consumer->ltiVersion = ToolProvider\ToolProvider::LTI_VERSION1;
		} else {
			$update_consumer = ToolProvider\ToolConsumer::fromRecordId($id, $data_connector);
		}
		$update_consumer->name = $_POST['name'];
		if (isset($_POST['secret'])) {
			$update_consumer->secret = $_POST['secret'];
		}
		$update_consumer->enabled = isset($_POST['enabled']);
		$date = $_POST['enable_from'];
		if (empty($date)) {
			$update_consumer->enableFrom = NULL;
		} else {
			$update_consumer->enableFrom = strtotime($date);
		}
		$date = $_POST['enable_until'];
		if (empty($date)) {
			$update_consumer->enableUntil = NULL;
		} else {
			$update_consumer->enableUntil = strtotime($date);
		}
		$update_consumer->protected = isset($_POST['protected']);
		// Ensure all required fields have been provided
		if ($update_consumer->save()) {
			$_SESSION['message'] = 'The consumer has been saved.';
		} else {
			$_SESSION['error_message'] = 'Unable to save the consumer; please check the data and try again.';
		}
		header('Location: ./');
		exit;

		// Process delete consumer action
	} else if ($action == 'delete') {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$ok = true;
			foreach ($_POST['ids'] as $id) {
				$consumer = ToolProvider\ToolConsumer::fromRecordId($id, $data_connector);
				$ok = $ok && $consumer->delete();
			}
			if ($ok) {
				$_SESSION['message'] = 'The selected consumers have been deleted.';
			} else {
				$_SESSION['error_message'] = 'Unable to delete at least one of the selected consumers; please try again.';
			}
		} else {
			$consumer = ToolProvider\ToolConsumer::fromRecordId($id, $data_connector);
			if ($consumer->delete()) {
				$_SESSION['message'] = 'The consumer has been deleted.';
			} else {
				$_SESSION['error_message'] = 'Unable to delete the consumer; please try again.';
			}
		}
		header('Location: ./');
		exit;

	} else if (!empty($id)) {
		$update_consumer = ToolProvider\ToolConsumer::fromRecordId($id, $data_connector);
	} else {
		// Initialise an empty tool consumer instance
		$update_consumer = new ToolProvider\ToolConsumer(NULL, $data_connector);
	}

	// Fetch a list of existing tool consumer records
	$consumers = $tool->getConsumers();

	// Set launch and xml URL for information
	$launchUrl = getAppUrl() . 'connect.php';
	$xmlUrl = getAppUrl() . 'xml/';
}

$css = array(
	'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
	'https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
);

$js = array(
	'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
);

$page = $twig->load('admin.html');
echo $page->render([
	'launchUrl' => $launchUrl,
	'xmlUrl' => $xmlUrl,
	'id' => $id,
	'css' => $css,
	'js' => $js,
	'consumers' => $consumers,
	'update_consumer' => $update_consumer,
]);

?>
