<?php

require_once('lib/init.php');
// Initialise session and database
$resource = NULL;
$db = NULL;
$ok = init($db, TRUE);

// Perform requested actions
if ($ok) $ok = do_action($db);

// If this is a valid LTI session, attempt to load the associated resource
if ($ok && isset($_SESSION['resource_pk'])) {
	$resource = new Resource($db, $_SESSION['resource_pk']);
}

// Load the correct type of page based on if this is Student/Staff
if ($ok && $_SESSION['isStudent']) {
	if (isset($resource->module)) {
		$page = new StudentPage($db, $resource);
	} else {
		$page = new ModuleNotSelectedPage($db);
	}
} else if ($ok && $_SESSION['isStaff']) {
	$page = new DashboardPage($db, $resource);
} else {
	$page = new ErrorPage($db);
}

// Handle error/alert messages
if (isset($_SESSION['error_message'])) {
	$page->alerts[] = new Alert($_SESSION['error_message'], "Error", 'danger');
}
if (isset($_SESSION['message'])) {
	$page->alerts[] = new Alert($_SESSION['message']);
}
$_SESSION['ack_messages'] = True;

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
