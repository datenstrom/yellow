<?php
// Datenstrom Yellow is for people who make websites, https://datenstrom.se/yellow/
// Copyright (c) 2013-2020 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

require_once("system/extensions/core.php");

if (PHP_SAPI!="cli") {
    $yellow = new YellowCore();
    $yellow->load();
    $yellow->request();
} else {
    $yellow = new YellowCore();
    $yellow->load();
    exit($yellow->command($argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7]));
}
