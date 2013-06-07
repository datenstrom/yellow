<?php
require_once("system/core/core.php");
if(PHP_SAPI != "cli")
{
	$yellow = new Yellow();
	$yellow->plugins->load();
	$yellow->request();
} else {
	$yellow = new Yellow();
	$yellow->plugins->load();
	$yellow->plugin("commandline", $argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
}
?>