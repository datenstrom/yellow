<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Example plugin
class YellowExample
{
	const Version = "0.0.0";
}

$yellow->registerPlugin("example", "YellowExample", YellowExample::Version);
?>