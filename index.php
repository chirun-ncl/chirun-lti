<?php

require_once('lib/init.php');
// Initialise session and database
$db = NULL;
$ok = init($db, TRUE);


// Initialise parameters
$req_id = 0;

// Perform requested actions
if ($ok) $ok = do_action($db);


// Show debug output
if(isset($_GET['debug'])){
	#print_r($_SERVER);
	#print_r($_SESSION);
	#print_r($_COOKIE);
}

if ($ok && isset($_SESSION['resource_pk'])) {
	$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
	if(isset($selected_module)){
		$selected_module->apply_content_overrides($db, $_SESSION['resource_pk']);
	}
}

if ($ok && $_SESSION['isStudent']) {
	if (isset($selected_module)) {
		$page = new StudentPage();
	} else {
		$page = new ModuleNotSelectedPage();
	}
} else if ($ok && $_SESSION['isStaff']) {
	$page = new DashboardPage();
	$page->setResource($_SESSION['resource_pk']);
} else {
	$page = new NoLTIPage();
}

// Handle messages
if (isset($_SESSION['error_message'])) {
	$page->addAlert($_SESSION['error_message'], 'danger');
	unset($_SESSION['error_message']);
}
if (isset($_SESSION['message'])) {
	$page->addAlert($_SESSION['message']);
	unset($_SESSION['message']);
}


$page->setDB($db);
if(isset($selected_module)){
	$page->setModule($selected_module, $_SESSION['resource_pk']);
}


if(isset($_REQUEST['req_content'])){
	$authLevel = 0;
	if($ok && $_SESSION['isStaff'] && isset($_COOKIE["auth_level"])){
		$authLevel = $_COOKIE["auth_level"];
	}
	if($ok && $_SESSION['isStaff'] && isset($_REQUEST['auth_level'])){
		setcookie("auth_level", $_REQUEST['auth_level'], time()+3600, "/", ".mas-coursebuild.ncl.ac.uk");
		$authLevel = $_REQUEST['auth_level'];
	}
	$requestReplyError = $page->requestContent($_REQUEST['req_content'], $authLevel);
	if(!empty($requestReplyError)) $page->addAlert($requestReplyError, 'danger');
}

$page->render();
exit;
?>
