<?php

require_once('auth.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'list';
switch ($action) {
case 'get':
	// load user config from sqlite
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	$res = $ldb->querySingle('SELECT enableIntercom FROM config', true);
	$lastError = $ldb->lastErrorMsg();
	$ldb->close();

	// check success
	if (!$res) {
		$r = array('error' => $lastError);
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// return result
	localApiResponse(json_encode($res));

	break;

case 'setKey':
	// validate request
	if (!isset($_GET['key']) || !isset($_GET['val'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// store user config to sqlite
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	// update status
	$ok = $ldb->exec("UPDATE config SET ".
		SQLite3::escapeString($_GET['key'])."='".SQLite3::escapeString($_GET['val'])."'"
	);
	$lastError = $ldb->lastErrorMsg();
	$ldb->close();

	// check success
	if (!$ok) {
		$r = array('error' => $lastError);
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// return result
	localApiResponse(''); // ok

	break;

default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}
