<?php
// Copyright (c) 2013-2017 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Language plugin
class YellowLanguage
{
	const VERSION = "0.6.13";
}

$yellow->plugins->register("language", "YellowLanguage", YellowLanguage::VERSION);
?>