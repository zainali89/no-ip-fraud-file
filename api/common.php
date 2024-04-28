<?php
require_once('constants.php');
require_once('config.php');

function localApiResponse($json, $header = 'HTTP/1.1 200 OK', $contentType = "application/json") {
	if ($header !== NULL) {
		header($header);
	}
	header("Content-Type: $contentType");
	echo $json;
}

function getEventFromStatus($status) {
	$action = 'unknown';
	switch ($status) {
		case '-1':
			$action = 'paused';
			break;
		case '0':
			$action = 'under-review';
			break;
		case '1':
			$action = 'active';
			break;
		case '2':
			$action = 'allow-all';
			break;
		case '3':
			$action = 'scheduled';
			break;
	}
	return $action;
}

if (!function_exists('getLocalDb')) {
	function getLocalDb() {
		// get db path
		$dbpath = DB_FILE;
		if (strpos('/',DB_FILE) === false) {
			$dbpath = __DIR__.'/db/'.$dbpath;
		}

		// open db for read/write
		$ldb = new SQLite3($dbpath, SQLITE3_OPEN_READWRITE, DB_KEY);
		$ldb->busyTimeout(60000); 
		return $ldb;
	}
}

function genToken($payload) {
	$payload = json_encode($payload);
	$sig = hash_hmac('sha256', $payload, APISECRET);
	return base64_encode($payload).'.'.base64_encode($sig);
}

function checkAuth($jwt) {
	@list($payload, $sig) = explode('.', $jwt);
	if (!$payload || !$sig) return false;

	$payload_json = base64_decode($payload);
	$payload = json_decode($payload_json,true);
	$sig = base64_decode($sig);
	if (!$payload || !$payload_json || !$sig) return false;

	if ($payload['role'] !== 'api') return false;
	if (time() > $payload['exp']) return false;

	$thisSig = hash_hmac('sha256', $payload_json, APISECRET);
	if ($sig !== $thisSig) return false;

	return $payload;
}

function noipApiRq($params, $post = NULL, $retry = false) {
	global $curl_config;

	// build url
	$utc = time();
	$auth = array(
		'auth' => 2,
		'key' => APIKEY,
		'utc' => $utc,
		'sig' => hash_hmac('sha256', $utc.APIKEY, APISECRET),
		'iid' => md5(DB_KEY)
	);
	$q = http_build_query(array_merge($auth, $params));
	$url = 'http://'.API_DOMAIN.API_PATH.'api.php?'.$q;

	// execute request
	$ch = curl_init();
	$curl_config[CURLOPT_URL] = $url;
	if ($post) {
		$curl_config[CURLOPT_POST] = 1;
		$curl_config[CURLOPT_POSTFIELDS] = $post;
	}
	curl_setopt_array($ch, $curl_config);
	$data = curl_exec($ch);
	$c_info = curl_getinfo($ch);
	$c_errno = curl_errno($ch);
	$c_err = curl_error($ch);
	curl_close($ch);

	// check success
	if ($c_errno !== 0) {
		if (!$retry) {
			return noipApiRq($params, $post, true); // retry 
		} else {
			throw new Exception('Error communicating with API: ('.$c_errno.') '.$c_err);
		}
	}
 
	return $data;
}
