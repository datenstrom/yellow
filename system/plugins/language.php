<?php
// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Language plugin
class YellowLanguage
{
	const VERSION = "0.6.10";
	var $yellow;			//access to API
	
	// Handle initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
	}
}

$yellow->plugins->register("language", "YellowLanguage", YellowLanguage::VERSION);
?>