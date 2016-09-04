<?php
// Yellow is for people who make websites. http://datenstrom.se/yellow
// This file may be used and distributed under the terms of the public license.

version_compare(PHP_VERSION, "5.3", '>=') || die("Yellow requires PHP 5.3 or higher!");

require_once("system/plugins/core.php");
if(PHP_SAPI!="cli")
{
	$yellow = new YellowCore();
	$yellow->plugins->load();
	$yellow->request();
} else {
	$yellow = new YellowCore();
	$yellow->plugins->load();
	$statusCode = $yellow->command($argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7]);
	exit($statusCode<400 ? 0 : 1);
}
?>