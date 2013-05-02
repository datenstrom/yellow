<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Example plugin
class Yellow_ExamplePlugin
{
	//You can download plugins and extensions from Github.
	//See https://github.com/markseu/yellowcms-extensions
	const Version = "0.0.0";
}

$yellow->registerPlugin("example", "Yellow_ExamplePlugin", Yellow_ExamplePlugin::Version);
?>