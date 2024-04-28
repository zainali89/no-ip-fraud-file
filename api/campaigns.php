<?php

require_once('auth.php');

$action = isset($_GET['a']) ? $_GET['a'] : 'list';
switch ($action) {
case 'list':
	// load campaigns form sqlite
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	$c = array();
	$res = $ldb->query('SELECT * FROM campaigns');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		if($row['name'] != 'default') {
			$row['active'] = intval($row['active']);
			$row['archived'] = intval($row['archived']);
			$row['realurl'] = unserialize($row['realurl']);
			$row['dynvar'] = unserialize($row['dynvar']);
			$row['schedule'] = unserialize($row['schedule']);
			$row['urlfilter'] = unserialize($row['urlfilter']);
			$row['pagelock'] = unserialize($row['pagelock']);
			$row['total'] = 0;
			$row['block'] = 0;
			$row['cv'] = 0;
			$row['traffic'] = '--';
			$row['bleedrate'] = 0;
			$c[$row['name']] = $row;
		}
	}
	$ldb->close();

	// no campaigns
	if (sizeof($c) == 0) {
		localApiResponse(json_encode(array()));
		exit();
	}

	// specified date (defaults to today)
	$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', time());
	$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d', time());
	
	// get stats from api
	try {
		$apiData = json_decode(noipApiRq(array(
			'a' => 'stats',
			'from' => $from,
			'to' => $to
		)), true);
	} catch (Exception $e) {
		localApiResponse(json_encode(array_values($c)));
		exit();
	}

	// check success
	if (!$apiData || isset($apiData['error']) || !isset($apiData['data'])) {
		$r = array('error' => isset($apiData['error']) ? $apiData['error'] : 'unknown error');
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// merge sqlite results with api results
	foreach ($apiData['data'] as $d) {
		$clid = $d['clid'];
		if (isset($c[$clid])) {
			$c[$clid]['traffic'] = $d['ts'];
			$c[$clid]['block'] = $d['block'];
			$c[$clid]['total'] = $d['total'];
			$c[$clid]['cv'] = $d['cv'];
			if ($d['total'] > 0) {
				$c[$clid]['bleedrate'] = ($d['block'] / $d['total']) * 100;
			}
		}
	}

	// return data
	localApiResponse(json_encode(array_values($c)));

	break;

case 'changeStatus':
	// validate request
	if (!isset($_GET['clid']) || !isset($_GET['status']) || 
	  ($_GET['status'] != '-1' && $_GET['status'] != '0' && $_GET['status'] != '1' && $_GET['status'] != '2' && $_GET['status'] != '3')) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load local db
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// multiple clids
	$clids = explode('|', $_GET['clid']);

	// update sqlite
	foreach ($clids as $clid) {
		// set status
		$ok = $ldb->exec("UPDATE campaigns SET
				active=".SQLite3::escapeString($_GET['status'])." 
			WHERE name='".SQLite3::escapeString($clid)."'
		");
		// check success
		if (!$ok) {
			$ldb->close();
			$r = array('error' => $ldb->lastErrorMsg());
			localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
			exit();
		}

		// add event
		$ldb->exec("INSERT INTO events VALUES (
			'".SQLite3::escapeString($clid)."', 
			'".getEventFromStatus($_GET['status'])."',".
			time()."
		);");

		// clear apc
		if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$clid);
	}

	$ldb->close();

	// clear apc
	if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$_GET['clid']);

	localApiResponse(''); // ok

	break;

case 'setSchedule':
	// validate request
	if (!isset($_GET['clids'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// check body (slots)
	$body = file_get_contents('php://input');
	$slots = json_decode($body, true);
	if (!$slots || empty($slots)) {
		$slots = array();
	}

	// load local db
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	
	// multiple clids
	$clids = explode('|', $_GET['clids']);

	// serialize array
	$sslots = serialize($slots);

	// update sqlite
	foreach ($clids as $clid) {
		$ok = $ldb->exec("UPDATE campaigns SET
				schedule='".SQLite3::escapeString($sslots)."'  
			WHERE name='".SQLite3::escapeString($clid)."'
		");
		// check success
		if (!$ok) {
			$ldb->close();
			$r = array('error' => $ldb->lastErrorMsg());
			localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
			exit();
		}
		// clear apc
		if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$clid);
	}

	$ldb->close();

	// clear apc
	if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$_GET['clids']);

	localApiResponse(''); // ok
	break;

case 'get':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load campaign from sqlite
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	$res = $ldb->querySingle('SELECT * FROM campaigns WHERE name=\''.SQLite3::escapeString($_GET['clid']).'\'', true);
	$lastError = $ldb->lastErrorMsg();
	$ldb->close();

	// check success
	if (!$res) {
		$r = array('error' => $lastError);
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// parse fields
	if (sizeof($res) > 0) {
		// force integers
		if($res['name'] != 'default') {
			$res['realurl'] = unserialize($res['realurl']);
			for ($i=0;$i<sizeof($res['realurl']);$i++) { 
				$res['realurl'][$i]['perc'] = intval($res['realurl'][$i]['perc']);
			}
			$res['active'] = intval($res['active']);
			$res['archived'] = intval($res['archived']);
			$res['dynvar'] = unserialize($res['dynvar']);
			$res['urlfilter'] = unserialize($res['urlfilter']);
			$res['rules'] = unserialize($res['rules']);
			$res['filters'] = unserialize($res['filters']);
			$res['schedule'] = unserialize($res['schedule']);
			$res['pagelock'] = unserialize($res['pagelock']);
		}
	}

	// return result
	localApiResponse(json_encode($res));

	break;

case 'create':
	// validate request
	$body = file_get_contents('php://input');
	$camp = json_decode($body, true);
	if (!$camp || empty($camp)) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load local db
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// parse fields
	$res = null;
	do {
		if (empty($camp['name']) || !is_null($res)) {
			$camp['name'] = substr(str_repeat(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"),mt_rand(2,4)), 0, 8);
		}
		$res = $ldb->querySingle('SELECT * FROM campaigns WHERE name=\''.SQLite3::escapeString($camp['name']).'\'');
	} while(!is_null($res));

	$camp['realurl'] = serialize($camp['realurl']);
	$camp['dynvar'] = serialize($camp['dynvar']);
	$camp['urlfilter'] = serialize($camp['urlfilter']);
	$camp['rules'] = serialize($camp['rules']);
	$camp['filters'] = serialize($camp['filters']);
	$camp['schedule'] = serialize($camp['schedule']);
	$camp['pagelock'] = serialize($camp['pagelock']);

	// save to sqlite
	$ok = $ldb->exec("INSERT INTO campaigns (
		name,
		cv,
		info,
		fakeurl,
		realurl,
		dynvar,
		urlfilter,
		active,
		traffic,
		archived,
		rules,
		filters,
		schedule,
		urlkeyword,
		pagelock,
		lptrack,
		dynautopt
	)
	VALUES(".
		"'".SQLite3::escapeString($camp['name'])."',".
		"'".SQLite3::escapeString(CLIENT_VERSION)."',".
		"'".SQLite3::escapeString($camp['info'])."',".
		"'".SQLite3::escapeString($camp['fakeurl'])."',".
		"'".SQLite3::escapeString($camp['realurl'])."',".
		"'".SQLite3::escapeString($camp['dynvar'])."',".
		"'".SQLite3::escapeString($camp['urlfilter'])."',".
		SQLite3::escapeString($camp['active']).",".
		"'".SQLite3::escapeString($camp['traffic'])."',".
		"0,".
		"'".SQLite3::escapeString($camp['rules'])."',".
		"'".SQLite3::escapeString($camp['filters'])."',".
		"'".SQLite3::escapeString($camp['schedule'])."',".
		"'".SQLite3::escapeString($camp['urlkeyword'])."',".
		"'".SQLite3::escapeString($camp['pagelock'])."',".
		"'".SQLite3::escapeString($camp['lptrack'])."',".
		"'".SQLite3::escapeString($camp['dynautopt'])."'
	)");
	$lastError = $ldb->lastErrorMsg();

	// check success
	if (!$ok) {
		$ldb->close();
		$r = array('error' => $lastError);
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// add created event
	$t = time();
	$ldb->exec("INSERT INTO events VALUES (
		'".SQLite3::escapeString($camp['name'])."', 
		'created',".
		$t."
	);");

	// add under-review event
	$ldb->exec("INSERT INTO events VALUES (
		'".SQLite3::escapeString($camp['name'])."', 
		'under-review',".
		($t+1)."
	);");

	$ldb->close();

	// return saved clid
	localApiResponse('{"clid": "'.$camp['name'].'"}'); 

	break;

case 'update':
	// validate request
	$body = file_get_contents('php://input');
	$camp = json_decode($body, true);
	if (!$camp || empty($camp)) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load local db
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// get campaign
	$res = $ldb->querySingle('SELECT * FROM campaigns WHERE name=\''.SQLite3::escapeString($camp['name']).'\'', true);
	$lastError = $ldb->lastErrorMsg();
	// check success
	if (!$res) {
		$r = array('error' => $lastError);
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	// store event if status changed
	if ($res['active'] != $camp['active']) {
		$ldb->exec("INSERT INTO events VALUES (
			'".SQLite3::escapeString($camp['name'])."', 
			'".getEventFromStatus($camp['active'])."', ".
			time()."
		);");
	}
	
	// disable pagelock if no urls
	if ($camp['pagelock']['enabled'] == true) {
		$plok = false;
		foreach ($camp['realurl'] as $u) {
			if (substr($u['url'], 0, 4) == 'http') $plok = true;
		}
		if ($plok === false) $camp['pagelock']['enabled'] = false;
	}

	// parse fields
	$camp['realurl'] = serialize($camp['realurl']);
	$camp['dynvar'] = serialize($camp['dynvar']);
	$camp['urlfilter'] = serialize($camp['urlfilter']);
	$camp['rules'] = serialize($camp['rules']);
	$camp['filters'] = serialize($camp['filters']);
	$camp['schedule'] = serialize($camp['schedule']);
	$camp['pagelock'] = serialize($camp['pagelock']);

	// save to sqlite
	$ok = $ldb->exec("UPDATE campaigns SET
			cv='".SQLite3::escapeString(CLIENT_VERSION)."',
			info='".SQLite3::escapeString($camp['info'])."',
			fakeurl='".SQLite3::escapeString($camp['fakeurl'])."',
			realurl='".SQLite3::escapeString($camp['realurl'])."',
			dynvar='".SQLite3::escapeString($camp['dynvar'])."',
			urlfilter='".SQLite3::escapeString($camp['urlfilter'])."',
			active=".SQLite3::escapeString($camp['active']).",
			traffic='".SQLite3::escapeString($camp['traffic'])."',
			rules='".SQLite3::escapeString($camp['rules'])."',
			filters='".SQLite3::escapeString($camp['filters'])."',
			schedule='".SQLite3::escapeString($camp['schedule'])."',
			urlkeyword='".SQLite3::escapeString($camp['urlkeyword'])."',
			pagelock='".SQLite3::escapeString($camp['pagelock'])."',
			lptrack='".SQLite3::escapeString($camp['lptrack'])."',
			dynautopt='".SQLite3::escapeString($camp['dynautopt'])."'
		WHERE name='".SQLite3::escapeString($camp['name'])."'
	");
	$lastError = $ldb->lastErrorMsg();
	$ldb->close();

	// check success
	if (!$ok) {
		$r = array('error' => $lastError);
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	
	// clear apc
	if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$camp['name']);

	// return saved clid
	localApiResponse(''); 

	break;

case 'archive':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load local db
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// multiple clids
	$clids = explode('|', $_GET['clid']);
	
	// update sqlite
	foreach ($clids as $clid) {
		// perform update
		$ok = $ldb->exec("UPDATE campaigns SET
				archived=1, active=-1   
			WHERE name='".SQLite3::escapeString($clid)."'
		");
		// check success
		if (!$ok) {
			$ldb->close();
			$r = array('error' => $ldb->lastErrorMsg());
			localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
			exit();
		}

		// add event
		$ldb->exec("INSERT INTO events VALUES (
			'".SQLite3::escapeString($clid)."', 
			'archived', ".
			time()."
		);");

		// delete associated events
		try {
			$apiJson = noipApiRq(array(
				'a' => 'deleteeventclid',
				'clid' => $_GET['clid']
			));
		} catch (Exception $e) {
			$r = array('error' => $e->getMessage());
			localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		}

		// check success
		$apiData = json_decode($apiJson, true);
		if (isset($apiData['error'])) {
			localApiResponse($apiJson, 'HTTP/1.0 500 Internal Server Error'); 
			return;
		} 

		// clear apc
		if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$clid);
	}

	$ldb->close();

	// return result
	localApiResponse(''); // ok

	break;

case 'unarchive':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load local db
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	
	// multiple clids
	$clids = explode('|', $_GET['clid']);
	
	// update sqlite
	foreach ($clids as $clid) {
		// unarchive
		$ok = $ldb->exec("UPDATE campaigns SET
				archived=0  
			WHERE name='".SQLite3::escapeString($clid)."'
		");
		// check success
		if (!$ok) {
			$ldb->close();
			$r = array('error' => $ldb->lastErrorMsg());
			localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
			exit();
		}

		// add event
		$ldb->exec("INSERT INTO events VALUES (
			'".SQLite3::escapeString($clid)."', 
			'unarchived', ".
			time()."
		);");

		// clear apc
		if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$clid);
	}

	$ldb->close();

	// return result
	localApiResponse(''); // ok

	break;

case 'getPhpDeploy':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// build php
	$clid = $_GET['clid'];
	$apploc = $_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'];
	$apploc = preg_replace('!(^.*/)(.+?/.+?.php)$!i','$1',$apploc).'api/';
	$res = "<?php\n" .
		"//define critical variables - do not change!\n" .
		"define('APPLOC','$apploc');\n" .
		"\$_GET['clid'] = '$clid';\n" .
		"include(APPLOC.'go.php');\n" .
		"noIpFraud();\n";

	// return result
	localApiResponse($res, NULL, 'text/plain');

	break;

case 'getPhpEmbed':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// build php
	$clid = $_GET['clid'];
	$apploc = $_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'];
	$apploc = preg_replace('!(^.*/)(.+?/.+?.php)$!i','$1',$apploc).'api/';

	$res = "<?php\n" .
			"//WARNING: ADVANCED NOIPFRAUD TEMPLATE\n" .
			"//define critical variables - do not change!\n" .
			"define('APPLOC','$apploc');\n" . 
			"\$_GET['clid'] = '$clid';\n" .
			"include(APPLOC.'go.php');\n" .
			"if ( \$isItSafe ) {\n" .
			"	//visitor safe - redirect\n" .
			"	noIpFraud();\n" .
			"}\n" .
			"//include your safe landing page below the next line\n" .
			"?>  \n";

	// return result
	localApiResponse($res, NULL, 'text/plain');

	break;

case 'getDirList':

	if ( defined('CUSTOM_INCL_PATH') && file_exists(CUSTOM_INCL_PATH) ) {
		$path=CUSTOM_INCL_PATH;
	} else {
		$apploc = $_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'];
		$apploc = preg_replace('!(^.*/)(.+?/.+?.php)$!i','$1',$apploc);
		$path = realpath($apploc.'/../').'/';
	}

	$r = array(
		'id' => '/',
		'name' => '/'
	);
	recurseDirs($path, 0, $r);

	localApiResponse(json_encode(array(
		'folder' => $r,
		'base' => $path
	)));

	break;
	
case 'setPageLock':
	// validate request
	if (!isset($_GET['clid'])) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}
	$clid = $_GET['clid'];
	$body = file_get_contents('php://input');
	$pagelock = json_decode($body, true);
	if (!$pagelock || empty($pagelock)) {
		$r = array('error' => 'invalid request');
		localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
		exit();
	}

	// load local db
	try {
		$ldb = getLocalDb();
	} catch (Exception $e) {
		$r = array('error' => $e->getMessage());
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}

	// parse fields
	$pagelock = serialize($pagelock);

	// save to sqlite
	$ok = $ldb->exec("UPDATE campaigns SET
			pagelock='".SQLite3::escapeString($pagelock)."'
		WHERE name='".SQLite3::escapeString($clid)."'
	");
	$lastError = $ldb->lastErrorMsg();
	$ldb->close();

	// check success
	if (!$ok) {
		$r = array('error' => $lastError);
		localApiResponse(json_encode($r), 'HTTP/1.0 500 Internal Server Error');
		exit();
	}
	
	// clear apc
	if (function_exists('apcu_delete')) apcu_delete('noipfraud-'.$clid);

	// return saved clid
	localApiResponse(''); 

	break;

default:
	$r = array('error' => 'unknown action');
	localApiResponse(json_encode($r), 'HTTP/1.0 400 Bad Request');
	exit();
}

function recurseDirs($main, $count=0, &$t){
    $dirHandle = opendir($main);
    while($file = readdir($dirHandle)){
		if ($file != '.' && $file != '..') { 
			if(is_dir($main.$file."/")){
				if(!isset($t['children'])) { $t['children'] = array(); }
				$t['children'][] = array(
					'id' => utf8_encode($file),
					'name' => utf8_encode($file),
					'type' => 'folder'
				);
				$count = recurseDirs($main.$file."/",$count,$t['children'][count($t['children'])-1]); 
			}
			else{
				$count++;
				if(!isset($t['children'])) { $t['children'] = array(); }
				$t['children'][] = array(
					'id' => utf8_encode($file),
					'name' => utf8_encode($file),
					'type' => 'file'
				);
			}
		}
    }
    return $count;
}
