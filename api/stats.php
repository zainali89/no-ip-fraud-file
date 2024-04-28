<?php

// set UTC
date_default_timezone_set('UTC');

require_once('auth.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'load';
switch ($action) {
case 'loadcl':
	// validate request
	if (!isset($_GET['clid']) || !isset($_GET['group']) || 
	  !isset($_GET['from']) || !isset($_GET['to'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load job into api
	try {
		$curl_config[CURLOPT_TIMEOUT] = 30;
		$apiData = json_decode(noipApiRq(array(
			'a' => 'loadstatsjob',
			'clid' => $_GET['clid'],
			'group' => $_GET['group'],
			'from' => $_GET['from'],
			'to' => $_GET['to']
		)), true);
		if (isset($apiData['data']) && !isset($apiData['data']['id'])) {
			throw new Exception('Unable to load job, please try again later');
		}

	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	if (!$apiData || isset($apiData['error']) || !isset($apiData['data'])) {
		$r = array('error' => isset($apiData['error']) ? $apiData['error'] : 'unknown error');
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// return result
	localApiResponse(json_encode($apiData));

	break;

case 'getcl':
	// validate request
	if (!isset($_GET['jobid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}
	$pageToken = isset($_GET['pagetoken']) ? $_GET['pagetoken'] : '';

	// load job into api
	try {
		$curl_config[CURLOPT_TIMEOUT] = 30;
		$apiData = json_decode(noipApiRq(array(
			'a' => 'getstatsjob',
			'jobid' => $_GET['jobid'],
			'pagetoken' => $pageToken
		)), true);

	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	if (!$apiData || isset($apiData['error']) || !isset($apiData['data'])) {
		$r = array('error' => isset($apiData['error']) ? $apiData['error'] : 'unknown error');
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// return result
	localApiResponse(json_encode($apiData));

	break;

case 'daily':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// set timeframe
	$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', time());
	$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d', time());

	// request from api
	try {
		$curl_config[CURLOPT_TIMEOUT] = 30;
		$apiData = json_decode(noipApiRq(array(
			'a' => 'dailystats',
			'clid' => $_GET['clid'],
			'from' => $from,
			'to' => $to
		)), true);

	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	if (!$apiData || isset($apiData['error']) || !isset($apiData['data'])) {
		$r = array('error' => isset($apiData['error']) ? $apiData['error'] : 'unknown error');
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// return result
	localApiResponse(json_encode($apiData));

	break;

case 'params':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// set timeframe
	$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', time());
	$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d', time());

	// request from api
	try {
		$curl_config[CURLOPT_TIMEOUT] = 30;
		$apiData = json_decode(noipApiRq(array(
			'a' => 'paramstats',
			'clid' => $_GET['clid'],
			'from' => $from,
			'to' => $to
		)), true);

	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	
	// check success
	if (!$apiData || isset($apiData['error']) || !isset($apiData['data'])) {
		$r = array('error' => isset($apiData['error']) ? $apiData['error'] : 'unknown error');
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// return result
	localApiResponse(json_encode($apiData));

	break;

case 'events':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load events form sqlite
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	$c = array();
	$res = $ldb->query('SELECT * FROM events WHERE clid=\''.SQLite3::escapeString($_GET['clid']).'\' ORDER BY time DESC');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		array_push($c, $row);
	}
	$ldb->close();

	// return result
	localApiResponse(json_encode($c)); 

	break;

default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}
