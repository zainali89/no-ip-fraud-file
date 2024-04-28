<?php

require_once('constants.php');

function localApiResponse($json, $header = 'HTTP/1.1 200 OK', $contentType = "application/json") {
	if ($header !== NULL) {
		header($header);
	}
	header("Content-Type: $contentType");
	echo $json;
}

$action = isset($_GET['a']) ? $_GET['a'] : 'check';
switch ($action) {
case 'check':
	// check api installed
	if (!file_exists('config.php')) {
		localApiResponse(json_encode(array('result' => false)));
		exit();
	}

	// check for upgrade requirement
	$f = file('config.php');
	$thisVerStr = "//DB_VER=".CLIENT_VERSION."\n";
	$cmpVer = '';
	foreach ($f as &$l) {
		if (strpos($l, "?>") !== false) {
			$l = '';
			continue;
		}
		if (strpos($l, "//DB_VER=") !== false) {
			$v = explode('.', trim(substr($l, strlen("//DB_VER="))));
			if (count($v) == 3) {
				$cmpVer = $v[0].str_pad($v[1], 2, '0', STR_PAD_LEFT).str_pad($v[2], 2, '0', STR_PAD_LEFT);
				$l = $thisVerStr;
				break;
			}
		}
	}
	// no version found, add to file
	if (empty($cmpVer)) {
		array_push($f, $thisVerStr);
	}

	// upgrade 1.6.0 -> 1.7.0
	if (empty($cmpVer) || intval($cmpVer) <= 10600) {

		// load local db
		include('config.php');
		try {
			$dbpath = DB_FILE;
			if (strpos('/',DB_FILE) === false) {
				$dbpath = __DIR__.'/db/'.$dbpath;
			}

			// backup db
			@copy($dbpath, $dbpath.'_'.time().'.bak');

			// open db for read/write
			$ldb = new SQLite3($dbpath, SQLITE3_OPEN_READWRITE, DB_KEY);
			$ldb->busyTimeout(60000); 
		} catch (Exception $e) {
			$r = array('error' => $e->getMessage());
			localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
			exit();
		}

		// add lastgoodapiaddr column
		$ok = @$ldb->exec("ALTER TABLE config ADD COLUMN lastgoodapiaddr TEXT");

		// add schedule column
		$ok = @$ldb->exec("ALTER TABLE campaigns ADD COLUMN schedule TEXT");

		// add events table
		$ok = @$ldb->exec('CREATE TABLE events(
			clid TEXT,
			type TEXT,
			time INTEGER
		);');

		// clear urlkeyword column
		$ok = @$ldb->exec("UPDATE campaigns SET urlkeyword = ''");

		// add version column
		$ok = @$ldb->exec("ALTER TABLE config ADD COLUMN version TEXT");

		// add user options column
		$ok = @$ldb->exec("ALTER TABLE config ADD COLUMN useropt TEXT");

		// update DB version
		$ok = @$ldb->exec("UPDATE config SET version = '".CLIENT_VERSION."'");

		// close db
		$ldb->close();

		// rewrite config.php file
		@file_put_contents('config.php', implode('', $f));
	}
	
	// upgrade 1.7.0 -> 1.8.0
	if (empty($cmpVer) || intval($cmpVer) <= 10700) {
		// load local db
		include('config.php');
		try {
			$dbpath = DB_FILE;
			if (strpos('/',DB_FILE) === false) {
				$dbpath = __DIR__.'/db/'.$dbpath;
			}

			// backup db
			@copy($dbpath, $dbpath.'_'.time().'.bak');

			// open db for read/write
			$ldb = new SQLite3($dbpath, SQLITE3_OPEN_READWRITE, DB_KEY);
			$ldb->busyTimeout(60000); 
		} catch (Exception $e) {
			$r = array('error' => $e->getMessage());
			localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
			exit();
		}

		// add urlfilter column
		$ok = @$ldb->exec("ALTER TABLE campaigns ADD COLUMN urlfilter TEXT");
		
		// add pagelock column
		$ok = @$ldb->exec("ALTER TABLE campaigns ADD COLUMN pagelock TEXT");
		$ok = @$ldb->exec("UPDATE campaigns SET pagelock = '".serialize(array('enabled'=>false,'action'=>'blank','url'=>'','timeout'=>10))."'");
		
		// add lptrack column
		$ok = @$ldb->exec("ALTER TABLE campaigns ADD COLUMN lptrack TEXT");
		$ok = @$ldb->exec("UPDATE campaigns SET lptrack = false");
		
		// add dyn var auto pass column
		$ok = @$ldb->exec("ALTER TABLE campaigns ADD COLUMN dynautopt TEXT");
		$ok = @$ldb->exec("UPDATE campaigns SET dynautopt = false");
		
		// update DB version
		$ok = @$ldb->exec("UPDATE config SET version = '".CLIENT_VERSION."'");

		// close db
		$ldb->close();
		
		// rewrite config.php file
		@file_put_contents('config.php', implode('', $f));
	}
	
	localApiResponse(json_encode(array('result' => true)));
	break;

case 'checkEnv':
	$errors = array();
	$warnings = array();
	$path = dirname(__FILE__);

	// warn: apc not installed
	if ( !function_exists('apcu_store') ) {
		$warnings[] = 'It is recommended that you install the PHP APCu extension for a significant performance gain.';
	}

	// pass: openSSL installed
	if ( !function_exists('openssl_random_pseudo_bytes') ) {
		$errors[] = 'Required dependency OpenSSL is not installed for PHP.';
	}

	// pass: SQLite3 installed
	if( !method_exists('SQLite3', 'escapeString') ) {
		$errors[] = 'Required dependency SQLite3 is not installed for PHP.';
	}
	
	// pass: curl installed
	if( !function_exists('curl_init') ) {
		$errors[] = 'Required dependency curl is not installed for PHP.';
	}

	// pass: constants.php exists
	if ( !file_exists('constants.php') ) {
		$errors[] = 'Client installation corrupt: "constants.php" not found in '.$path. '. Try uploading a fresh copy of the client to your host.';
	}

	// pass: config.php does not exist
	if ( file_exists('config.php') ) {
		$errors[] = 'config.php exists in '.$path.' - remove this file and retry to reinstall.';
	}

	// pass: current path is writeable
	if ( !is_writeable($path) ) {
		$errors[] = 'API path ('.$path.') is not writeable. Make sure the directory and subdirectories owner and group have the permissions: Read, Write, and Execute (77x).';
	}

	// pass: no existing db directory, or existing db directory is writeable
	if ( is_dir($path.'/db') ) {
		if ( !is_writeable($path.'/db') ) {
			$errors[] = 'Existing database path ('.$path.'/db) is not writeable. Make sure the directory and subdirectories owner and group have the permissions: Read, Write, and Execute (77x).';
		}
	}

	// return result
	$r = array(
		'result' => (count($errors) == 0),
		'errors' => $errors,
		'warnings' => $warnings
	);
	localApiResponse(json_encode($r));
	break;

case 'inst':

	/*** validate request ***/
	$body = file_get_contents('php://input');
	$inst = json_decode($body, true);
	if ( !$inst || empty($inst) || 
		 empty($inst['apiKey']) || empty($inst['apiSecret']) || empty($inst['apiSecret']) || 
	 	 empty($inst['username']) || empty($inst['password']) ) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// initialize
	require_once('constants.php');
	$errors = array();
	$path = dirname(__FILE__);

	// fail: config.php exists
	if ( file_exists('config.php') ) {
		$error = 'config.php exists in '.$path.' - remove this file and retry to reinstall.';
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 400 Bad Request');
		exit();
	}
	
	/*** validate API key & secret ***/
	$utc = time();
	$params = array('a'=>'info');
	$auth = array(
		'auth' => 2,
		'key' => $inst['apiKey'],
		'utc' => $utc,
		'sig' => hash_hmac('sha256', $utc.$inst['apiKey'], $inst['apiSecret'])
	);
	$q = http_build_query(array_merge($auth, $params));
	$url = 'http://'.API_DOMAIN.API_PATH.'api.php?'.$q;

	// execute request
	$ch = curl_init();
	$curl_config[CURLOPT_URL] = $url;
	curl_setopt_array($ch, $curl_config);
	$data = curl_exec($ch);
	$c_errno = curl_errno($ch);
	$c_err = curl_error($ch);
	curl_close($ch);

	// check curl success
	if ($c_errno !== 0) {
		$error = 'Error communicating with API: ('.$c_errno.') '.$c_err;
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	// decode
	$res = json_decode($data, true);
	if (!$res) {
		$error = $data;
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	// check status
	if (isset($res['error'])) {
		$error = $res['error'][0];
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	/*** create DB ***/
	$db = array(
		'content' => array()
	);

	// make db path
	if ( !is_dir($path.'/db') ) {
		if ( !mkdir($path.'/db', 0755, true) ) {
			$error = 'Unable to create directory '.$path.'/db';
			localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 500 Internal Server Error');
			exit();
		}
	}

	// apache HTTP_AUTHORIZATION fix
	if (@file_put_contents($path.'/.htaccess','SetEnvIf Authorization .+ HTTP_AUTHORIZATION=$0') === false) {
		$error = 'Unable to write files to directory '.$path;
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// secure directories 
	if ( (@file_put_contents($path.'/db/.htaccess','deny from all') === false) || 
		 (@file_put_contents($path.'/db/index.html','') === false) ) {
		$error = 'Unable to write files to directory '.$path.'/db';
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// generate db filename
	$bs = bin2hex(openssl_random_pseudo_bytes(16));
	$db['filename'] = substr(strtr(base64_encode($bs), '+/', '_-'), 0, 22).'.noipdb';
	$db['file'] = $path.'/db/'.$db['filename'];

	// generate db encryption key
	$bs = bin2hex(openssl_random_pseudo_bytes(16));
	$db['key'] = substr(base64_encode($bs), 0, 22);

	// escape inputs
	$db['content']['username'] = SQLite3::escapeString($inst['username']);
	$password = SQLite3::escapeString($inst['password']);

	// salt & encrypt user pass
	$bs = bin2hex(openssl_random_pseudo_bytes(16));
	$salt = substr(strtr(base64_encode($bs), '+', '.'), 0, 22);
	$db['content']['password'] = SQLite3::escapeString(crypt($password, '$2a$12$'.$salt));

	try {
		// create database
		$ldb = new SQLite3($db['file'], (SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE), $db['key']);

		// create config table
		$ok = $ldb->exec('CREATE TABLE config(
			username        TEXT, 
			password        TEXT,
			enableIntercom  INT,
			version         TEXT,
			lastgoodapiaddr TEXT,
			useropt         TEXT
		);');
		if( !$ok ) 
			throw new Exception('Unable to create DB config table '.$ldb->lastErrorMsg()); 

		// create events table
		$ok = $ldb->exec('CREATE TABLE events(
			clid TEXT,
			type TEXT,
			time INTEGER
		);');
		if( !$ok ) 
			throw new Exception('Unable to create events table '.$ldb->lastErrorMsg()); 

		// create campaigns table
		$ok = $ldb->exec('CREATE TABLE campaigns(
			name TEXT,
			cv TEXT, 
			maxrisk INT,
			info TEXT,
			fakeurl TEXT,
			realurl TEXT,
			dynvar TEXT,
			urlfilter TEXT,
			allowedcountries TEXT,
			allowedref TEXT,
			urlkeyword TEXT,
			active TEXT,
			traffic TEST,
			archived INT,
			device TEXT,
			rules TEXT,
			filters TEXT,
			schedule TEXT,
			pagelock TEXT,
			lptrack TEXT,
			dynautopt TEXT
		);');
		if( !$ok )
			throw new Exception('Unable to create campaigns table '.$ldb->lastErrorMsg()); 

		// insert config data
		$ok = $ldb->exec("INSERT INTO config VALUES(
			'".$db['content']['username']."',
			'".$db['content']['password']."',
			1,".
			"'".CLIENT_VERSION."',".
			"'',".
			"''
		);");		
		if( !$ok ) 
			throw new Exception('Unable to write config table '.$ldb->lastErrorMsg()); 

		// set permissions
		chmod($db['file'], 0664);

	} catch (Exception $e) {
		$ldb->close();
		$error = 'Failed creating database: '.$e->getMessage();
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	$ldb->close();

	/*** create config.php file ***/
	$cfgFile = "<?php \n".
		"define('APIKEY','".$inst['apiKey']."');\n".
		"define('APISECRET','".$inst['apiSecret']."');\n".
		"define('DB_FILE','".$db['filename']."');\n".
		"define('DB_KEY','".$db['key']."');\n".
		"//DB_VER=".CLIENT_VERSION."\n";
	if ( @file_put_contents('config.php', $cfgFile) === false ) {
		$error = 'Failed creating config.php file';
		localApiResponse(json_encode(array('result'=>false, 'error'=>$error)), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	/*** complete ***/
	localApiResponse(json_encode(array('result'=>true, 'error'=>array())));
	break;

default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}
