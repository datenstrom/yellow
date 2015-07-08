<?php
// Yellow is for people who make websites. http://datenstrom.se/yellow
// This file may be used and distributed under the terms of the public license.

require_once("system/core/core.php");
if(PHP_SAPI != "cli")
{
	$yellow = new Yellow();
	$yellow->plugins->load();
	$yellow->request();
} else {
	$yellow = new Yellow();
	$yellow->plugins->load();
	$statusCode = $yellow->command("commandline", $argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6]);
	exit($statusCode<=200 ? 0 : 1);
}
?>