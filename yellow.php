<?php
// Yellow is for people who make websites. https://github.com/markseu/yellowcms
// For more information see Yellow documentation.

require_once("system/core/core.php");
if(PHP_SAPI != "cli")
{
	$yellow = new Yellow();
	$yellow->plugins->load();
	$yellow->request();
} else {
	$yellow = new Yellow();
	$yellow->plugins->load();
	$statusCode = $yellow->plugin("commandline", $argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
	exit($statusCode<=200 ? 0 : 1);
}
?>