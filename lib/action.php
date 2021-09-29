<?php
use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

function do_action($db){
	$ok = true;
	$action = '';
        // Check for item id and action parameters
        if (isset($_REQUEST['req_id'])) {
                $req_id = intval($_REQUEST['req_id']);
        }
        if (isset($_REQUEST['do'])) {
                $action = $_REQUEST['do'];
        }

        if ($action == 'add' && isset($_REQUEST['module_path']) && isset($_REQUEST['theme_id']) && $_SESSION['isStaff']) {
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
                        $ok = selectModule($db, $_SESSION['resource_pk'], $_REQUEST['module_path'], $_REQUEST['theme_id'][$_REQUEST['module_path']]);
                }
                if ($ok) {
                        $ok = updateResourceOptions($db, $_SESSION['resource_pk'], array('user_uploaded'=>0));
                        $_SESSION['message'] = 'The module has been selected.';
                } else {
                        $_SESSION['error_message'] = 'Unable to select module; please try again';
                }
                header('Location: ./');
                exit;
        } else if ($action == 'delete' && !empty($req_id) && $_SESSION['isStaff']) {
                if (deleteModule($db, $_SESSION['resource_pk'], $req_id)) {
                        $_SESSION['message'] = 'The module has been successfully deselected.';
                } else {
                        $_SESSION['error_message'] = 'Unable to remove module; please try again.';
                }
                header('Location: ./');
                exit;
        } else if ($action == 'content_saveall' && $_SESSION['isStaff']) {
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
                                        $ok = updateContentOverrides($db, $_SESSION['resource_pk'], $path, $start, $end, $hidden);
                                }
                        }
                        if ($ok) {
                                $_SESSION['message'] = 'Content successfully updated.';
                        } else {
                                $_SESSION['error_message'] = 'Unable to update content; please try again';
                        }
                        header('Location: ./?dashpage=adaptive');
                        exit;
                }
        } else if ($action == 'content_clearall' && $_SESSION['isStaff']) {
                if (isset($_SESSION['resource_pk'])) {
                        $ok = TRUE;
                        $selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
                        if(!isset($selected_module)){
                                $ok = FALSE;
                        }
                        $ok = deleteContentOverrides($db, $_SESSION['resource_pk']);
                        if ($ok) {
                                $_SESSION['message'] = 'Adaptive release schedule successfully set back to its defaults.';
                        } else {
                                $_SESSION['error_message'] = 'Unable to update content; please try again';
                        }
                        header('Location: ./?dashpage=adaptive');
                        exit;
                }
	} else if ($action == 'change_theme' && $_SESSION['isStaff'] && isset($_SESSION['resource_pk']) && isset($_POST["theme_id"]) ){
		$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
		$ok = TRUE;
		if(!isset($selected_module)){
			$ok = FALSE;
		}
		$ok = selectTheme($db, $_SESSION['resource_pk'], $_POST["theme_id"]);
		if ($ok) {
			$_SESSION['message'] = 'Default theme updated!';
		} else {
			$_SESSION['error_message'] = 'Unable to update default theme; please try again';
		}
		header('Location: ./');
		exit;
	} else  if($action == 'set_direct_link' && isset($_REQUEST["slug"])) {
		$options = getResourceOptions($db, $_SESSION['resource_pk']);
		$new_opt = $options;
		$new_opt['direct_link_slug'] = $_REQUEST["slug"];
		$ok = updateResourceOptions($db, $_SESSION['resource_pk'], $new_opt);
		if ($ok) {
			$_SESSION['message'] = 'Successfuly set content as directly linked!';
		} else {
			$_SESSION['error_message'] = 'Unable to link to content; please try again';
		}
		header('Location: ./?dashpage=directlink');
		exit;

        } else if ($action == 'options_save' && $_SESSION['isStaff'] && isset($_SESSION['resource_pk'])){
		$options = getResourceOptions($db, $_SESSION['resource_pk']);
		$new_opt = $options;
		foreach ($options as $key => $value){
			if(isset($_POST["opt"][$key])){
				$new_opt[$key] = $_POST["opt"][$key];
			}
		}
		$ok = updateResourceOptions($db, $_SESSION['resource_pk'], $new_opt);
		if ($ok) {
			$_SESSION['message'] = 'Options updated!';
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
		if ($ok) {
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
					$_SESSION['error_message'] = "Problem uploading files!";
					$ok = false;
				}
			}
		} else {
			$_SESSION['error_message'] = 'Unable to create new upload directory!';
		}

		if($ok){
			$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
			$consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
			if (is_null($_SESSION['resource_pk'])) {
				$resource_link = ToolProvider\ResourceLink::fromConsumer($consumer, $_SESSION['resource_id']);
				$ok = $resource_link->save();
			} else {
				$resource_link = ToolProvider\ResourceLink::fromRecordId($_SESSION['resource_pk'], $data_connector);
			}
			$_SESSION['resource_pk'] = $resource_link->getRecordId();
			$ok = selectModule($db, $_SESSION['resource_pk'], '/'.$guid.'/MANIFEST.yml');
		} else {
			$_SESSION['error_message'] = 'Unable to save uploaded files!';
		}
		
		if($ok){
			$content_files = glob($upload_dir.'/'."*.{tex,md,yml}",GLOB_BRACE);
			$config_files = glob($upload_dir.'/'."config.yml");
			$template_name = isset($_POST['split_chapters'])?'split':'standalone';
			if(count($content_files) == 1 || count($config_files) == 1){
				$ok = processWithSourceFile($db, $_SESSION['resource_pk'], basename($content_files[0]),$template_name);
				if($ok){
					updateResourceOptions($db, $_SESSION['resource_pk'], array('user_uploaded'=>1));
				} else {
					$_SESSION['error_message'] = 'Upload processing failed: '. basename($content_files[0]);
				}
			}
		} else {
			$_SESSION['error_message'] = 'Error setting up content resource!';
		}
		header('Location: ./?dashpage=upload&upload=processed');
		exit;
	} else if ($action == 'processBuild' && $_SESSION['isStaff'] && isset($_SESSION['resource_pk'])){
		$selected_module = getSelectedModule($db, $_SESSION['resource_pk']);
		if(!isset($selected_module)){
			$ok = false;
		}
		$source_main = $_POST['source_main'];
		$template_name = isset($_POST['split_chapters'])?'split':'standalone';
		if($ok && !empty($source_main)){
			$ok = processWithSourceFile($db, $_SESSION['resource_pk'], $source_main, $template_name);
		} else {
			$ok = false;
			$_SESSION['error_message'] = 'Unable to select source file!';
		}
		if($ok){
			updateResourceOptions($db, $_SESSION['resource_pk'], array('user_uploaded'=>1));
			$_SESSION['message'] = "Selected main content file: {$source_main}";
		} else {
			$_SESSION['error_message'] = 'Upload process failed!';
		}
		header('Location: ./?dashpage=upload');
		exit;
	} else if ($action == 'processGuidSelect' && $_SESSION['isStaff'] && isset($_POST['guidSelect'])){
		$guid = $_POST['guidSelect'];
		$content_dir = UPLOADDIR.'/'.$guid;
		$ok = true;
		$ok = preg_match('/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/i', $guid);
		if($ok){
			$ok = is_dir($content_dir);
		} else {
			$_SESSION['error_message'] = 'Invalid GUID provided';
			header('Location: ./?dashpage=upload');
			exit;
		}

		if($ok){
			$ok = (getUploadUser($db, $guid) === $_SESSION['user_id']);
		} else {
			$_SESSION['error_message'] = 'Content for provided GUID not found.';
			header('Location: ./?dashpage=upload');
			exit;
		}

		if($ok){
			$data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
			$consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
			if (is_null($_SESSION['resource_pk'])) {
				$resource_link = ToolProvider\ResourceLink::fromConsumer($consumer, $_SESSION['resource_id']);
				$ok = $resource_link->save();
			} else {
				$resource_link = ToolProvider\ResourceLink::fromRecordId($_SESSION['resource_pk'], $data_connector);
			}
			$_SESSION['resource_pk'] = $resource_link->getRecordId();
			$ok = selectModule($db, $_SESSION['resource_pk'], '/'.$guid.'/MANIFEST.yml');
		} else {
			$_SESSION['error_message'] = 'You do not have permission to access this GUID.';
			header('Location: ./?dashpage=upload');
			exit;
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
