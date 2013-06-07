<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Command line core plugin
class Yellow_Commandline
{
	const Version = "0.0.0"; //Hello command line!
	var $yellow;			//access to API

	// Initialise plugin
	function initPlugin($yellow)
	{
		$this->yellow = $yellow;		
	}
	
	// Handle command
	function onCommand($args)
	{
		$statusCode = 0;
		list($name, $command) = $args;
		if($command == "version") $statusCode = $this->version($args);
		else $this->help();
		return $statusCode;
	}
	
	// Show available commands
	function help()
	{
		echo "Yellow command line ".Yellow_Commandline::Version."\n";
		echo "Syntax: yellow version\n";
	}

	// Show software version
	function version($args)
	{
		echo "Yellow ".Yellow::Version."\n";
		foreach($this->yellow->plugins->plugins as $key=>$value) echo "$value[class] $value[version]\n";
		return 0;
	}
}
	
$yellow->registerPlugin("commandline", "Yellow_Commandline", Yellow_Commandline::Version);
?>