<?php
define('CLIENT_VERSION','1.8.2');
define('API_DOMAIN','api.noipfraud.com');

define('API_VERSION','1.8');
define('API_PATH','/1.8/');

//constants
define('IP_REAL',0);
define('IP_SHARE',1);
define('IP_PROXY',2);

//variables
define('DEF_DYN_VARS','country,city,region,rnd,utc,devicetype,browser,platform,subid');

//memcached
define('APC_EXPIRY', 1800); //number of seconds

//curl
$curl_config = Array(
	CURLOPT_HEADER=>0,
	CURLOPT_RETURNTRANSFER=>1,
	CURLOPT_CONNECTTIMEOUT=>2,
	CURLOPT_TIMEOUT=>10,
	CURLOPT_DNS_CACHE_TIMEOUT=>120, //seconds
	CURLOPT_FORBID_REUSE=>0,
	CURLOPT_FRESH_CONNECT=>0
);

//check for extra.php to define platform specific variable CUSTOM_INCL_PATH
//usage - create extra.php file with this content: <?php define('CUSTOM_INCL_PATH','/var/www/user/');
if (file_exists('extra.php')) {
	include('extra.php');
}
