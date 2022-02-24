<?php

require_once('lib/init.php');

// Initialise session and database
$db = NULL;
$ok = init($db, TRUE);

class Response {
	public $code = 200;
	public $status = 'success';
	public $data = array();

	public function respond() {
		http_response_code($this->code);
		echo json_encode([
			'status' => $this->status,
			'data' => $this->data
		]);
		exit;
	}
}

class Request {
	public $method;
	public $data;
	public $response;

	public function __construct() {
		$this->response = new Response();
		$this->method = $_SERVER['REQUEST_METHOD'];
		$this->format = 'url';
	}

	public function parse() {
		$data = array();
		//GET vars
		if (isset($_SERVER['QUERY_STRING'])) {
			parse_str($_SERVER['QUERY_STRING'], $data);
		}
		// PUT/POST bodies
		$body = file_get_contents("php://input");
		$content_type = false;
		if(isset($_SERVER['CONTENT_TYPE'])) {
			$content_type = $_SERVER['CONTENT_TYPE'];
		}
		switch($content_type) {
			case "application/json":
			$body_params = json_decode($body, true);
			if($body_params) {
				foreach($body_params as $param_name => $param_value) {
					$data[$param_name] = $param_value;
				}
				$this->format = "json";
			} else {
				$this->response->code = 500;
				$this->response->status = 'error';
				$this->response->data[] = ['message' => 'JSON failed to decode in request.'];
			}
			break;
			case "application/x-www-form-urlencoded":
			parse_str($body, $postvars);
			foreach($postvars as $field => $value) {
				$data[$field] = $value;
			}
			break;
			default:
			break;
		}
		$this->data = $data;
		return true;
	}
}

function getKV($db, $resource_pk, $user, $data_key){
	return getDataKVStore($db, $resource_pk, $user, $data_key);
}


$request = new Request();
$resource = NULL;
$ok = $request->parse();

if (!$ok || !isset($request->data['resource_pk']) || !isset($request->data['action'])){
	$request->response->code = 500;
	$request->response->status = 'error';
	$request->response->data[] = ['message' => 'Missing data in request.'];
	$ok = false;
}

// Find and verify an active LTI session
if($ok){
	$ok = false;
	$session = NULL;
	if (isset($_COOKIE['chirun_user_id']) && isset($_COOKIE['chirun_session'][$request->data['resource_pk']])) {
		$ck_token = $_COOKIE['chirun_session'][$request->data['resource_pk']];
		$ck_user_id = $_COOKIE['chirun_user_id'];
		$ck_session = Session::getUserSession($db, $ck_user_id, $ck_token);
		if(!empty($ck_session)){
			$session = $ck_session;
			$session['resource_pk'] = $ck_session['resource_link_pk'];
			$ok = true;
		}
	}
	if(isset($_SESSION['resource_pk']) && $_SESSION['resource_pk'] == $request->data['resource_pk']){
		$session = $_SESSION;
		$ok = true;
	}

	$resource = new Resource($db, $session['resource_pk']);

	if (!$ok){
		$request->response->code = 500;
		$request->response->status = 'error';
		$request->response->data[] = ['message' => 'Invalid LTI session requested.'];
	}
}

if($ok){
	$ok = false;
	switch($request->data['action']){
		case 'set':
		if (!isset($request->data['key']) || !isset($request->data['value'])){
			$request->response->code = 500;
			$request->response->status = 'error';
			$request->response->data[] = ['message' => 'Missing data in request.'];
			break;
		}
		$resource->addDataKVStore($session['user_id'], $request->data['key'], $request->data['value']);
		$ok = true;
		break;
		case 'get':
		if (!isset($request->data['key'])){
			$request->response->code = 500;
			$request->response->status = 'error';
			$request->response->data[] = ['message' => 'Missing data in request.'];
			break;
		}
		$val = $resource->getDataKVStore($session['user_id'], $request->data['key']);
		if(is_null($val)){
			$request->response->data[] = json_encode(array());
		} else {
			$request->response->data[] = $val;
		}
		$ok = true;
		break;
		case 'get_resource_build_log':
		$module = $resource->module;
		if (empty($module)){
			$request->response->code = 500;
			$request->response->status = 'error';
			$request->response->data[] = ['message' => 'Invalid request.'];
			break;
		}
		$fullLogPath = PROCESSDIR .'/logs'. dirname($module->yaml_path).'.log';
		if (!file_exists($fullLogPath)){
			$request->response->code = 404;
			$request->response->status = 'error';
			$request->response->data[] = ['message' => 'Build log not found.'];
			break;
		}
		$request->response->data[] = file_get_contents($fullLogPath);
		$ok = true;
		break;
	}
}

if(!$ok){
	$request->response->code = 500;
	$request->response->status = 'error';
	$request->response->data[] = ['message' => 'An error occured actioning your request.'];
}
$request->response->respond();
exit;
?>
