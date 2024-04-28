<?php
if (!file_exists('api/config.php')) {
	echo "It looks like you have not completed the install. Please check the install guide you were sent when you joined.";
	exit();
}
reset($_GET);
$clid = key($_GET);
$js = false;
if ((substr($clid, -3) == '_js') || (substr($clid, -3) == '.js')) {
	$clid = substr($clid, 0, -3);
	$js = true;
}
$_GET['clid'] = $clid;
include_once('api/go.php');
noIpFraud($js);
