<?php

require_once('auth.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'list';
switch ($action) {
case 'status':
	// get stats from api
	try {
		$ret = json_decode(noipApiRq(array(
			'a' => 'info'
		)), true);
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// return data
	localApiResponse(json_encode($ret));

	break;

case 'arbrq':
	// validate request
	$body = file_get_contents('php://input');
	if (!isset($_GET['r'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// post to api
	try {
		$apiJson = noipApiRq(array(
			'a' => $r
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

	// return response
	localApiResponse($apiJson);
	break;

case 'updatejs':
	
	try {
		$apiJson = noipApiRq(array(
			'a' => 'updatejs' 
		));
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	
	// check success
	$apiData = json_decode($apiJson, true);
	if (isset($apiData['error']) || !isset($apiData['data'])) {
		localApiResponse($apiJson, 'HTTP/1.0 500 Internal Server Error'); 
		return;
	} 

	// save file
	$r = file_put_contents('c.js', $apiData['data']);
	if ($r === false) {
		localApiResponse(json_encode(array('error' => 'failed to save file')), 'HTTP/1.0 500 Internal Server Error'); 
		return;
	}

	// return response
	localApiResponse(''); // ok
	break;


default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}
