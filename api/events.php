<?php

require_once('auth.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'list';
switch ($action) {
case 'log':
	// get events from api
	try {
		$apiData = json_decode(noipApiRq(array(
			'a' => 'eventlog'
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

	// return data
	localApiResponse(json_encode($apiData));

	break;
    
case 'list':
	// get events from api
	try {
		$apiData = json_decode(noipApiRq(array(
			'a' => 'listevents'
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

	// return data
	localApiResponse(json_encode($apiData));

	break;

case 'create':
	// validate request
	$body = file_get_contents('php://input');
	$ev = json_decode($body, true);
	if (!$ev || empty($ev)) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// post to api
	try {
		$apiJson = noipApiRq(array(
			'a' => 'createevent'
		), $body);
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	$apiData = json_decode($apiJson, true);
	if (isset($apiData['error'])) {
		localApiResponse($apiJson, 'HTTP/1.0 500 Internal Server Error'); 
		return;
	} 

	// return created id
	localApiResponse($apiJson);
	break;

case 'delete':
	// validate request
	if (!isset($_GET['id'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// delete to api
	try {
		$apiJson = noipApiRq(array(
			'a' => 'deleteevent',
			'id' => $_GET['id']
		));
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	$apiData = json_decode($apiJson, true);
	if (isset($apiData['error'])) {
		localApiResponse($apiJson, 'HTTP/1.0 500 Internal Server Error'); 
		return;
	} 

	// return result
	localApiResponse(''); // ok

	break;
    
case 'deleteclid':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// delete to api
	try {
		$apiJson = noipApiRq(array(
			'a' => 'deleteeventclid',
			'clid' => $_GET['clid']
		));
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	$apiData = json_decode($apiJson, true);
	if (isset($apiData['error'])) {
		localApiResponse($apiJson, 'HTTP/1.0 500 Internal Server Error'); 
		return;
	} 

	// return result
	localApiResponse(''); // ok

	break;

case 'update':
	// validate request
	$body = file_get_contents('php://input');
	$ev = json_decode($body, true);
	if (!$ev || empty($ev)) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// post to api
	try {
		$apiJson = noipApiRq(array(
			'a' => 'updateevent'
		), $body);
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	$apiData = json_decode($apiJson, true);
	if (isset($apiData['error'])) {
		localApiResponse($apiJson, 'HTTP/1.0 500 Internal Server Error'); 
		return;
	} 

	// return created id
	localApiResponse($apiJson);
	break;

case 'testwebhook':
	// validate request
	$body = file_get_contents('php://input');
	$ev = json_decode($body, true);
	if (!$ev || empty($ev) || empty($_GET['testclid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// post to api
	try {
		$apiJson = noipApiRq(array(
			'a' => 'testwebhook',
			'testclid' => $_GET['testclid']
		), $body);
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// check success
	$apiData = json_decode($apiJson, true);
	if (isset($apiData['error'])) {
		localApiResponse($apiJson, 'HTTP/1.0 500 Internal Server Error'); 
		return;
	} 

	// return created id
	localApiResponse($apiJson);
	break;

default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}
