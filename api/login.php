<?php

require_once('common.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'auth';
switch ($action) {
case 'auth':
	// validate request
	$body = file_get_contents('php://input');
	$auth = json_decode($body, true);
	if (!$auth || empty($auth)) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load local db
	try {
		$ldb = getLocalDb();
		$cfg = $ldb->querySingle('SELECT username, password FROM config', true);
		if( !$cfg || sizeof($cfg) == 0 ) { throw new Exception($ldb->lastErrorMsg()); }
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	$ldb->close();

	// validate auth
	if ( strtolower($auth['username']) !== strtolower($cfg['username']) || 
	(crypt($auth['password'], $cfg['password']) !== $cfg['password']) ) {
		localApiResponse('', 'HTTP/1.0 401 Authorization Required');
		sleep(2);
		exit();
	}

	$token = genToken(array('role' => 'api', 'username' => $auth['username'], 'exp' => strtotime('+3 hour')));
	localApiResponse(json_encode(array('token' => $token)));
	break;

default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}
