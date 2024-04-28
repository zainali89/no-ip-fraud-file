<?php

if (empty($_GET['subid'])) exit();

include('../api/constants.php');
include('../api/config.php');

$utc = time();
$sig = hash_hmac('sha256', $utc.APIKEY, APISECRET);
$auth2 = http_build_query(array(
	'auth'=>2,
	'key'=>APIKEY,
	'utc'=>$utc,
	'sig'=>$sig
));
$rq = '&'.http_build_query(array(
	'a'=>'conversion',
    'subid'=>$_GET['subid']
));

$url = 'http://'.API_DOMAIN.API_PATH.'api.php?'.$auth2.$rq;
$curl_config[CURLOPT_URL] = $url;
$ch = curl_init();
curl_setopt_array($ch, $curl_config);
curl_exec($ch);
curl_close($ch);