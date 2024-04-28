<?php

function loggedIn() {
	require_once('common.php');
	if(empty($_GET['tok'])) return false;
	if(!checkAuth($_GET['tok'])) return false;
	return true;
}

if (!loggedIn()) {
	header("HTTP/1.1 404 Not Found");
	exit();
}

if (isset($_GET['phpinfo'])) {
    phpinfo();
    exit();
}

?>

<html>
    <head>
        <title>Diagnostics</title>
    </head>
    <body>

        <h1>mt_rand()</h1>
        <?php echo mt_rand(); ?>

        <h1>Env Check</h1>
        <iframe width="100%" border="0" style="border:0px;width:100%;" src="state.php?a=checkEnv"></iframe>

        <h1>phpinfo()</h1>
        <iframe width="100%" height="100%" border="0" style="border:0px;width:100%;height:100%;" src="?phpinfo=1&tok=<?php echo $_GET['tok'] ?>"></iframe>
        
    </body>
</html>