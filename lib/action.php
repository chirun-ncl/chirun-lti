<?php
use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

function do_action($db){
	$ok = True;
	$action = '';
	$resource = NULL;
	
	// Check for item id and action parameters
	if (isset($_REQUEST['req_id'])) {
		$req_id = intval($_REQUEST['req_id']);
	}
	if (isset($_REQUEST['do'])) {
		$action = $_REQUEST['do'];
	}

	// Setup the resource session link
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
		$resource = new Resource($db, $_SESSION['resource_pk']);
	} else {
		$_SESSION['error_message'] = 'Unable to setup session. Please ensure you are launching this content item via your VLE';
	}


	if ($ok && $action == 'add' && isset($_REQUEST['module_path']) && isset($_REQUEST['theme_id']) && $_SESSION['isStaff']) {
		$ok = $resource->selectModule($_REQUEST['module_path'], $_REQUEST['theme_id'][$_REQUEST['module_path']]);
		$ok = $ok && $resource->updateOptions(array('user_uploaded'=>0));
		if ($ok) {
			$_SESSION['message'] = 'The module has been selected.';
		} else {
			$_SESSION['error_message'] = 'Unable to select module; please try again';
		}
		header('Location: ./');
		exit;
	} else if ($action == 'delete' && !empty($req_id) && $_SESSION['isStaff']) {
		if ($resource->module->id == $req_id && $resource->deselectModule()) {
			$_SESSION['message'] = 'The content for this resource has been successfully removed.';
		} else {
			$_SESSION['error_message'] = 'Unable to remove resource content; please try again.';
		}
		header('Location: ./');
		exit;
	} else if ($action == 'adaptive_save' && isset($_REQUEST['content']) && $_SESSION['isStaff']) {
		if ($ok = isset($resource->module)) {
			foreach($_REQUEST['content'] as $path => $content_item){
				$start = empty($content_item['start_datetime'])?NULL:$content_item['start_datetime'];
				$end = empty($content_item['end_datetime'])?NULL:$content_item['end_datetime'];
				$hidden = 0;
				if (isset($content_item['checked'])){
					$hidden = (strcmp('on',$content_item['checked'])==0)?1:0;
				}
				if($ok) {
					$ok = $resource->updateAdaptiveRelease($path, $start, $end, $hidden);
				}
			}
		}
		if ($ok) {
			$_SESSION['message'] = 'Adaptive release schedule successfully updated.';
		} else {
			$_SESSION['error_message'] = 'Unable to update adaptive release schedule; please try again';
		}
		header('Location: ./?dashpage=adaptive');
		exit;
	} else if ($action == 'adaptive_clear' && $_SESSION['isStaff']) {
			if ($ok = isset($resource->module)) {
				$ok = $resource->deleteResourceAdaptiveRelease();
			}
			if ($ok) {
				$_SESSION['message'] = 'Adaptive release schedule successfully set back to its defaults.';
			} else {
				$_SESSION['error_message'] = 'Unable to update adaptive release schedule; please try again';
			}
			header('Location: ./?dashpage=adaptive');
			exit;
	} else if($action == 'set_direct_link' && $_SESSION['isStaff']) {
		$new_opt = $resource->options;
		$new_opt['direct_link_slug'] = $_REQUEST["slug"];
		if ($ok = $resource->updateOptions($new_opt)) {
			$_SESSION['message'] = 'Successfuly set content as directly linked!';
		} else {
			$_SESSION['error_message'] = 'Unable to link to content; please try again';
		}
		header('Location: ./?dashpage=directlink');
		exit;
	} else if ($action == 'set_theme' && isset($_REQUEST["theme_id"]) && $_SESSION['isStaff']){
		if ($ok = $resource->selectTheme($_POST["theme_id"])) {
			$_SESSION['message'] = 'Theme successfully updated!';
		} else {
			$_SESSION['error_message'] = 'Unable to update selected theme; please try again';
		}
		$redirect_location = isset($_POST["redirect_loc"])?$_POST["redirect_loc"]:'selected';
		header("Location: ./?dashpage={$redirect_location}");
		exit;
	} else if ($action == 'options_save' && $_SESSION['isStaff']){
		$new_opt = $resource->options;
		foreach ($resource->options as $key => $value){
			if(isset($_POST["opt"][$key])){
				$new_opt[$key] = $_POST["opt"][$key];
			}
		}
		if ($ok = $resource->updateOptions($new_opt)) {
			$_SESSION['message'] = 'Options successfully updated!';
		} else {
			$_SESSION['error_message'] = 'Unable to update options; please try again';
		}
		$redirect_location = isset($_POST["redirect_loc"])?$_POST["redirect_loc"]:'selected';
		header("Location: ./?dashpage={$redirect_location}");
		exit;
	} else if ($action == 'processUpload' && $_SESSION['isStaff']){
		$guid = getGuid();
		$upload_dir = UPLOADDIR.'/'.$guid;
		$ok = mkdir($upload_dir);

		if(!$ok){
			$_SESSION['error_message'] = 'Unable to create new upload directory!';
		} else {
			foreach ($_FILES["docUpload"]["error"] as $key => $error) {
				if ($error == UPLOAD_ERR_OK) {
					$tmp_name = $_FILES["docUpload"]["tmp_name"][$key];
					$name = basename($_FILES["docUpload"]["name"][$key]);
					move_uploaded_file($tmp_name, $upload_dir.'/'.$name);
					$uploadedFilePathInfo = pathinfo($upload_dir.'/'.$name);
					if($uploadedFilePathInfo['extension'] === 'zip'){
						$ok = unzip($upload_dir.'/'.$name);
					}
				} else {
					$ok = false;
					$_SESSION['error_message'] = "Problem uploading files!";
				}
			}
		}

		if(!$ok){
			$_SESSION['error_message'] = 'Unable to save uploaded files!';
		} else {
			$ok = $resource->selectModule('/'.$guid.'/MANIFEST.yml');
		}

		if(!$ok){
			$_SESSION['error_message'] = 'Unable to select module!';
		} else {
			$content_files = glob($upload_dir.'/'."*.{tex,md,yml}",GLOB_BRACE);
			$config_files = glob($upload_dir.'/'."config.yml");
			if(count($content_files) == 1 || count($config_files) == 1){
				$ok = Process::fromSourceFile($db, $guid, basename($content_files[0]));
			}
		}

		if(!$ok){
			$_SESSION['error_message'] = 'Upload processing failed: '. basename($content_files[0]);
		} else {
			$ok = $resource->updateOptions(array('user_uploaded'=>1));
		}
		header('Location: ./?dashpage=upload&upload=processed');
		exit;
	} else if ($action == 'processBuild' && isset($_REQUEST["source_main"]) && $_SESSION['isStaff']){
		$ok = isset($resource->module);

		if(!$ok){
			$_SESSION['error_message'] = 'Failed to process build, no module found for this resource';
		} else {
			$source_main = $_POST['source_main'];
			$guid = basename(dirname($resource->module->yaml_path));
			$ok = Process::fromSourceFile($db, $guid, $source_main);
		}

		if(!$ok){
			$_SESSION['error_message'] = 'Unable to select source file!';
		} else {
			$ok = $resource->updateOptions(array('user_uploaded'=>1));
		}

		if($ok){
			$_SESSION['message'] = "Selected main content file: {$source_main}";
		} else {
			$_SESSION['error_message'] = 'Upload process failed!';
		}
		header('Location: ./?dashpage=upload');
		exit;
	} else if ($action == 'processGuidSelect' && $_SESSION['isStaff'] && isset($_POST['guidSelect'])){
		$guid = $_POST['guidSelect'];
		$content_dir = UPLOADDIR.'/'.$guid;
		$ok = preg_match('/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/i', $guid);

		if($ok){
			$ok = is_dir($content_dir);
		} else {
			$_SESSION['error_message'] = 'Invalid GUID provided';
		}

		if($ok){
			$ok = (Process::getUploadUser($db, $guid) === $_SESSION['user_id']);
		} else {
			$_SESSION['error_message'] = 'Content for provided GUID not found.';
		}

		if($ok){
			$ok = $resource->selectModule('/'.$guid.'/MANIFEST.yml', $_REQUEST['theme_id'][$_REQUEST['module_path']]);
		} else {
			$_SESSION['error_message'] = 'You do not have permission to access this GUID.';
		}

		if($ok){
			$_SESSION['message'] = "Existing GUID successfuly selected for use.";
		} else {
			$_SESSION['error_message'] = 'Error setting up content resource!';
		}
		header('Location: ./');
		exit;
	}
	return $ok;
}

?>
