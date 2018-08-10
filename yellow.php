<?php
// Datenstrom Yellow is for people who make websites. https://datenstrom.se/yellow/
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

version_compare(PHP_VERSION, "5.4", ">=") || die("Datenstrom Yellow requires PHP 5.4 or higher!");
extension_loaded("mbstring") || die("Datenstrom Yellow requires PHP mbstring extension!");
extension_loaded("curl") || die("Datenstrom Yellow requires PHP cURL extension!");
extension_loaded("zip") || die("Datenstrom Yellow requires PHP zip extension!");
require_once("system/plugins/core.php");

if (PHP_SAPI!="cli") {
    $yellow = new YellowCore();
    $yellow->load();
    $yellow->request();
} else {
    $yellow = new YellowCore();
    $yellow->load();
    $statusCode = $yellow->command($argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7]);
    exit($statusCode<400 ? 0 : 1);
}
