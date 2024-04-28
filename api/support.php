<?php

require_once('auth.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'gen';
switch ($action) {
case 'gen':
	$tok = genToken(array('role' => 'support', 'api' => APIKEY, 'exp' => strtotime('+5 hour')));
	localApiResponse(json_encode(array('token'=>$tok)));
	break;

default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}
