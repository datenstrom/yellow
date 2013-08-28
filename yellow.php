<?php
// Yellow is a CMS for people who make websites. https://github.com/markseu/yellowcms
// For more information see Yellow documentation. Have fun making your website.

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