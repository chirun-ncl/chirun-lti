<?php

require_once('lib/init.php');
// Initialise session and database
$db = NULL;
$ok = init($db, TRUE);

// Perform requested actions
if ($ok) $ok = do_action($db);

// Show debug output
/*
if(isset($_GET['debug'])){
	print_r($_SERVER);
	print_r($_SESSION);
	print_r($_COOKIE);
}
*/

// If this is a valid LTI session, attempt to load the associated module
if ($ok && isset($_SESSION['resource_pk'])) {
	$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
	if(isset($selected_module)){
		$selected_module->apply_content_overrides($db, $_SESSION['resource_pk']);
	}
}

// Load the correct type of page based on if this is Student/Staff
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
	$page = new ErrorPage();
}

// Handle error/alert messages
if (isset($_SESSION['error_message'])) {
	$page->alerts[] = new Alert($_SESSION['error_message'], "Error", 'danger');
}
if (isset($_SESSION['message'])) {
	$page->alerts[] = new Alert($_SESSION['message']);
}
$_SESSION['ack_messages'] = True;

// Load the db and module (if selected) objects into the page
$page->setDB($db);
if(isset($selected_module)){
	$page->setModule($selected_module, $_SESSION['resource_pk']);
}

// If loading a specific page of content, load that content into the page
// Use auth_level for staff looking at content restricted by adaptive release
if(isset($_REQUEST['req_content'])){
	$authLevel = 0;
	if($ok && $_SESSION['isStaff'] && isset($_COOKIE["auth_level"])){
		$authLevel = $_COOKIE["auth_level"];
	}
	if($ok && $_SESSION['isStaff'] && isset($_REQUEST['auth_level'])){
		setcookie("auth_level", $_REQUEST['auth_level'], time()+3600, "/", ".mas-coursebuild.ncl.ac.uk");
		$authLevel = $_REQUEST['auth_level'];
	}
	$page->requestContent($_REQUEST['req_content'], $authLevel);
}

// Render the page
$page->render();
if ($_SESSION['ack_messages']){
	unset($_SESSION['message']);
	unset($_SESSION['error_message']);
}
exit;
?>
