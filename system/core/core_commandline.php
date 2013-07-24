<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Command line core plugin
class Yellow_Commandline
{
	const Version = "0.1.1";
	var $yellow;			//access to API

	// Initialise plugin
	function initPlugin($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("commandBuildDefaultFile", "index.html");
	}
	
	// Handle command
	function onCommand($args)
	{
		list($name, $command) = $args;
		switch($command)
		{
			case "build":	$statusCode = $this->build($args); break;
			case "version":	$statusCode = $this->version(); break;
			default:		$statusCode = $this->help();
		}
		return $statusCode;
	}
	
	// Show available commands
	function help()
	{
		echo "Yellow command line ".Yellow_Commandline::Version."\n";
		echo "Syntax: yellow.php build DIRECTORY [LOCATION]\n";
		echo "        yellow.php version\n";
		return 0;
	}
	
	// Show software version
	function version()
	{
		echo "Yellow ".Yellow::Version."\n";
		foreach($this->yellow->plugins->plugins as $key=>$value) echo "$value[class] $value[version]\n";
		return 0;
	}	
	
	// Build static website
	function build($args)
	{		
		$statusCodeMax = $errorCount = 0;
		list($name, $command, $path, $location) = $args;
		if(!empty($path) && $path!="/")
		{
			$this->yellow->toolbox->timerStart($time);
			if(empty($location))
			{
				$pages = $this->yellow->pages->index(true);
				$fileNames = $this->yellow->toolbox->getDirectoryEntriesrecursive($this->yellow->config->get("mediaDir"), "/.*/", false, false);
			} else {
				$pages = new Yellow_PageCollection($this->yellow, $location);
				$pages->append(new Yellow_Page($this->yellow, $location));
				$fileNames = array();
			}
			foreach($pages as $page)
			{
				$statusCode = $this->buildContentFile($path, $page->location);
				$statusCodeMax = max($statusCodeMax, $statusCode);
				if($statusCode >= 400)
				{
					++$errorCount;
					echo "ERROR building location '".$page->location."', ".$this->yellow->page->getStatusCode(true)."\n";
				}
				if(defined("DEBUG") && DEBUG>=1) echo "Yellow_Commandline::build status:$statusCode location:".$page->location."\n";
			}
			foreach($fileNames as $fileName)
			{
				$statusCode = $this->buildMediaFile($fileName, "$path/$fileName");
				$statusCodeMax = max($statusCodeMax, $statusCode);
				if($statusCode >= 400)
				{
					++$errorCount;
					echo "ERROR building file '$path/$fileName', ".$this->yellow->toolbox->getHttpStatusFormatted($statusCode)."\n";
				}
				if(defined("DEBUG") && DEBUG>=1) echo "Yellow_Commandline::build status:$statusCode file:$fileName\n";
			}
			$this->yellow->toolbox->timerStop($time);
			if(defined("DEBUG") && DEBUG>=1) echo "Yellow_Commandline::build time:$time ms\n";
			echo "Yellow build: ".count($pages)." content, ".count($fileNames)." media";
			echo ", $errorCount error".($errorCount!=1 ? 's' : '');
			echo ", status $statusCodeMax\n";
		} else {
			echo "Yellow build: Invalid arguments\n";
		}
		return $statusCodeMax;
	}
	
	// Build content file
	function buildContentFile($path, $location)
	{		
		ob_start();
		$_SERVER["REQUEST_URI"] = $this->yellow->config->get("baseLocation").$location;
		$_SERVER["SCRIPT_NAME"] = $this->yellow->config->get("baseLocation")."yellow.php";
		$_SERVER["SERVER_NAME"] = $this->yellow->config->get("serverName");
		$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
		$fileName = $path.$location;
		if(!$this->yellow->toolbox->isFileLocation($location)) $fileName .= $this->yellow->config->get("commandBuildDefaultFile");
		$statusCode = $this->yellow->request();
		if($statusCode != 404)
		{
			$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
			if(!$this->yellow->toolbox->makeFile($fileName, ob_get_contents(), true) ||
			   !$this->yellow->toolbox->modifyFile($fileName, $modified))
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
			}
			list($contentType) = explode(';', $this->yellow->page->getHeader("Content-Type"));
			if($contentType != "text/html")
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Unsupported type '$contentType'!");				
			}
		}
		ob_end_clean();
		return $statusCode;
	}
	
	// Build media file
	function buildMediaFile($fileNameSource, $fileNameDest)
	{
		$statusCode = 200;
		if(!$this->yellow->toolbox->copyFile($fileNameSource, $fileNameDest, true) ||
		   !$this->yellow->toolbox->modifyFile($fileNameDest, filemtime($fileNameSource)))
		{
			$statusCode = 500;
		}
		return $statusCode;
	}
}
	
$yellow->registerPlugin("commandline", "Yellow_Commandline", Yellow_Commandline::Version);
?>