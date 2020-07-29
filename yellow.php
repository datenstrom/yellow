<?php
// Datenstrom Yellow, https://datenstrom.se/yellow/

require("system/extensions/core.php");

if (PHP_SAPI!="cli") {
    $yellow = new YellowCore();
    $yellow->load();
    $yellow->request();
} else {
    $yellow = new YellowCore();
    $yellow->load();
    exit($yellow->command());
}
