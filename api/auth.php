<?php
require_once('common.php');

function unauthorized() {
	header('HTTP/1.0 401 Unauthorized');
	exit();
}
function notFound() {
	header('HTTP/1.0 404 Not Found');
	exit();
}
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
	$token = substr($_SERVER['HTTP_AUTHORIZATION'],7);
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
	$token = substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'],7);
} else {
	notFound();
}

if (!checkAuth($token)) unauthorized();