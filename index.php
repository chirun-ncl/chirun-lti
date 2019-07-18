<?php
/**
 * Source code based on an example LTI tool provider written by:
 *
 * @author  Stephen P Vickers <svickers@imsglobal.org>
 * @copyright  IMS Global Learning Consortium Inc
 * @date  2016
 * @version 2.0.0
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3.0
 */

use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

require_once('lib.php');
require_once('mime_type_lib.php');

// Initialise session and database
$db = NULL;
$ok = init($db, TRUE);
// Initialise parameters
$id = 0;
if ($ok) {
	$action = '';
	// Check for item id and action parameters
	if (isset($_REQUEST['id'])) {
		$id = intval($_REQUEST['id']);
	}
	if (isset($_REQUEST['do'])) {
		$action = $_REQUEST['do'];
	}

	if ($action == 'showpage' && isset($_SESSION['resource_pk']) && isset($_REQUEST['page_id'])) {
		$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
		if($selected_module){
			$selected_module->apply_content_overrides($db);
		} else {
			$ok = false;
		}
		$real_page_req = realpath(MODULEDIR.'/'.$_REQUEST['page_id']);
		if (strpos($real_page_req,MODULEDIR.'/'.$selected_module->code) === 0){
			$ok = getModuleContent($selected_module,$real_page_req);
		} else {
			$ok = false;
		}
		if (!$ok){
			$_SESSION['error_message'] = 'An error has occured.';
			echo "error";
			#header('Location: /lti/');
		}
		exit;
	}

	// Process add module action
	if ($action == 'add') {
		// Ensure all required fields have been provided
		if (isset($_REQUEST['id']) && isset($_REQUEST['module_path'])) {
			$ok = TRUE;
			$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
			$consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
			if (is_null($_SESSION['resource_pk'])) {
				$resource_link = ToolProvider\ResourceLink::fromConsumer($consumer, $_SESSION['resource_id']);
				$ok = $resource_link->save();
			} else {
				$resource_link = ToolProvider\ResourceLink::fromRecordId($_SESSION['resource_pk'], $data_connector);
			}
			if ($ok) {
				$_SESSION['resource_pk'] = $resource_link->getRecordId();
				$ok = selectModule($db, $_SESSION['resource_pk'], $_REQUEST['module_path']);
			}
			if ($ok) {
				$_SESSION['message'] = 'The module has been selected.';
				//if (!$_SESSION['isContentItem'] && ($update_module->visible != $was_visible)) {
				//  updateGradebook($db);
				//}
			} else {
				$_SESSION['error_message'] = 'Unable to select module; please try again';
			}
			header('Location: ./');
			exit;
		}

		// Process delete item action
	} else if ($action == 'delete') {
		if (deleteModule($db, $_SESSION['resource_pk'], $id)) {
			$_SESSION['message'] = 'The item has been deleted.';
		} else {
			$_SESSION['error_message'] = 'Unable to remove module; please try again.';
		}
		header('Location: ./');
		exit;

		// Process content-item save action
	} else if ($action == 'saveci') {
		// Pass on preference for overlay, popup, iframe, frame options in that order if any of these is offered
		$placement = NULL;
		$documentTarget = '';
		if (in_array('overlay', $_SESSION['document_targets'])) {
			$documentTarget = 'overlay';
		} else if (in_array('popup', $_SESSION['document_targets'])) {
			$documentTarget = 'popup';
		} else if (in_array('iframe', $_SESSION['document_targets'])) {
			$documentTarget = 'iframe';
		} else if (in_array('frame', $_SESSION['document_targets'])) {
			$documentTarget = 'frame';
		}
		if (!empty($documentTarget)) {
			$placement = new ToolProvider\ContentItemPlacement(NULL, NULL, $documentTarget, NULL);
		}
		$item = new ToolProvider\ContentItem('LtiLinkItem', $placement);
		$item->setMediaType(ToolProvider\ContentItem::LTI_LINK_MEDIA_TYPE);
		$item->setTitle($_SESSION['title']);
		$item->setText($_SESSION['text']);
		$item->icon = new ToolProvider\ContentItemImage(getAppUrl() . 'images/icon50.png', 50, 50);
		$item->custom = array('content_item_id' => $_SESSION['resource_id']);
		$form_params['content_items'] = ToolProvider\ContentItem::toJson($item);
		if (!is_null($_SESSION['data'])) {
			$form_params['data'] = $_SESSION['data'];
		}
		$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
		$consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
		$form_params = $consumer->signParameters($_SESSION['return_url'], 'ContentItemSelection', $_SESSION['lti_version'], $form_params);
		$page = ToolProvider\ToolProvider::sendForm($_SESSION['return_url'], $form_params);
		echo $page;
		exit;

		// Process content-item cancel action
	} else if ($action == 'cancelci') {

		deleteAllItems($db, $_SESSION['resource_pk']);

		$form_params = array();
		if (!is_null($_SESSION['data'])) {
			$form_params['data'] = $_SESSION['data'];
		}
		$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
		$consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
		$form_params = $consumer->signParameters($_SESSION['return_url'], 'ContentItemSelection', $_SESSION['lti_version'], $form_params);
		$page = ToolProvider\ToolProvider::sendForm($_SESSION['return_url'], $form_params);
		echo $page;
		exit;
	} else if ($action == 'content_saveall') {
		if (isset($_REQUEST['content']) and isset($_SESSION['resource_pk'])) {
			$ok = TRUE;
			$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
			if(!isset($selected_module)){
				$ok = FALSE;
			}
			foreach($_REQUEST['content'] as $path => $content_item){
				$start = empty($content_item['start_datetime'])?NULL:$content_item['start_datetime'];
				$end = empty($content_item['end_datetime'])?NULL:$content_item['end_datetime'];
				$hidden=0;
				if (isset($content_item['checked'])){
					$hidden = (strcmp('on',$content_item['checked'])==0)?1:0;
				} 
				if($ok) {
					$ok = updateContentOverrides($db, $selected_module->selected_id,
							$path, $start, $end, $hidden);
				}
			}
			if ($ok) {
				$_SESSION['message'] = 'Content successfully updated.';
			} else {
				$_SESSION['error_message'] = 'Unable to update content; please try again';
			}
			header('Location: ./');
			exit;
		}
	} else if ($action == 'content_clearall') {
		if (isset($_SESSION['resource_pk'])) {
			$ok = TRUE;
			$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
			if(!isset($selected_module)){
				$ok = FALSE;
			}
			$ok = deleteContentOverrides($db, $selected_module->selected_id);
			if ($ok) {
				$_SESSION['message'] = 'Adaptive release schedule successfully set back to its defaults.';
			} else {
				$_SESSION['error_message'] = 'Unable to update content; please try again';
			}
			header('Location: ./');
			exit;
		}
	}

	if (isset($_SESSION['resource_pk'])) {
		$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
		if(isset($selected_module)){
			$selected_module->apply_content_overrides($db);
		}
	}

	//if ($_SESSION['isStudent']) {
	//  // Fetch a list of ratings for items for the resource link for the student
	//  $user_rated = getUserRated($db, $_SESSION['resource_pk'], $_SESSION['user_pk']);
	//}

}


if (!$_SESSION['isStudent']) {
	// Page header
	$title = APP_NAME;
	$page = <<< EOD
<!DOCTYPE html>
<html>
<head>
<title>{$title}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
aside {
	background: #d62f1f;
	margin: 1em 0.5em;
	padding: 0.3em 1em;
	border-radius: 3px;
	color: #fff;
}
.button-warning {
	color: white;
	ext-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
	background: rgb(202, 60, 60);
}
.content {
	padding: 0.5em 1em 0;
}
</style>
</head>
<body>
<div class="pure-g">
<div class="content pure-u-1">
EOD;

	// Check for any messages to be displayed
	if (isset($_SESSION['error_message'])) {
		$page .= <<< EOD
<aside>
<p style="font-weight: bold;">ERROR: {$_SESSION['error_message']}</p>
</aside>
EOD;
		unset($_SESSION['error_message']);
	}

	if (isset($_SESSION['message'])) {
		$page .= <<< EOD
<aside>
<p style="font-weight: bold;">{$_SESSION['message']}</p>
</aside>
EOD;
		unset($_SESSION['message']);
	}

	// Display module if it already exists
	if ($ok) {
		if (!isset($selected_module)) {
			$page .= <<< EOD
<h2>Teacher Options</h2>
<h3>Select Coursebuilder notes</h3>
<table class="pure-table">
<thead>
<tr>
<th>Module Code</th>
<th>Module Title</th>
<th>Author</th>
<th>Year</th>
<th>Select</th>
</tr>
</thead>
<tbody>
EOD;
				foreach(getModules($db) as $module){
					$page .= "<tr><td>".$module->code."</td>";
					$page .= "<td>".$module->title."</td>";
					$page .= "<td>".$module->author."</td>";
					$page .= "<td>".$module->year."</td>";
					$page .= <<< EOD
<td><a class="pure-button pure-button-primary"
href="./index.php?do=add&id={$id}&module_path={$module->yaml_path}">Use</a></td>
EOD;
					$page .= "</tr>";
				}
				$page .= <<< EOD
</tbody>
</table>
EOD;
		} else {
			$page .= <<< EOD
<h2>Teacher Options</h2>
<h3>Selected Coursebuilder module:</h3>
<table class="pure-table">
<thead>
<tr>
<th>Module Code</th>
<th>Module Title</th>
<th>Author</th>
<th>Year</th>
<th>Options</th>
</tr>
</thead>
<tbody>
<tr>
<td>$selected_module->code</td>
<td><a href="#" target="_blank">{$selected_module->title}</a></td>
<td>$selected_module->author</td>
<td>$selected_module->year</td>
<td><a class="pure-button button-warning"
href="./index.php?do=delete&id={$selected_module->selected_id}">Remove</a></td>
</tr>
</tbody>
</table>
<h3>Adaptive Release:</h3>
<form action="index.php" method="POST">
<table class="pure-table">
<thead>
<tr>
<th></th>
<th>Page Title</th>
<th>Visibility</th>
<th>Start Date/Time</th>
<th>End Date/Time</th>
<th>Force Hidden</th>
</tr>
</thead>
<tbody>
EOD;
			foreach($selected_module->content as $module_content){
				$page .= $module_content->adaptive_release_tbl_row();
			}
			$page .= <<< EOD
</tbody>
</table>
<p>
<button class="pure-button pure-button-primary" name="do" value="content_saveall">Save All Changes</button>
<button class="pure-button pure-button-primary button-warning" name="do" value="content_clearall">Restore Defaults</button>
</p>
</form>
<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>flatpickr(".flatpickr",{enableTime: true,dateFormat: "Y-m-d H:i",time_24hr: true});</script>
<script>
$(document).ready(function() {
		$('.expand-indicator a').click(function(){
				var el = $(this).parents().next('.hide').toggle();
				if(el.is( ":hidden" )){
				$(this).first().html("&#x25B6;");
				} else {
				$(this).first().html("&#x25BC;");
				}
				});
	});
</script>
EOD;
		}
	}
	// Page footer
	$page .= <<< EOD
</div>
</div>
</body>
</html>
EOD;

	// Display page
	echo $page;
} else {
	if ($ok) {
		if (!isset($selected_module)) {
			echo "Module not yet selected. Contact your module leader.";
		} else {
			echo file_get_contents(MODULEDIR.$selected_module->default_theme_path.'/index.html');
		}
	}
}
?>
