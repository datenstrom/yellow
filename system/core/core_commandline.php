<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Command line core plugin
class Yellow_Commandline
{
	const Version = "0.1.2";
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
	
	// Build website
	function build($args)
	{
		$statusCode = 0;
		list($name, $command, $path, $location) = $args;
		if(!empty($path) && $path!="/")
		{
			if($this->yellow->config->isExisting("serverName") && $this->yellow->config->isExisting("serverBase"))
			{
				$this->yellow->toolbox->timerStart($time);
				$serverName = $this->yellow->config->get("serverName");
				$serverBase = $this->yellow->config->get("serverBase");
				list($statusCode, $contentCount, $mediaCount, $errorCount) = $this->buildStatic($serverName, $serverBase, $location, $path);
				$this->yellow->toolbox->timerStop($time);
				if(defined("DEBUG") && DEBUG>=1) echo "Yellow_Commandline::build time:$time ms\n";
			} else {
				list($statusCode, $contentCount, $mediaCount, $errorCount) = array(500, 0, 0, 1);
				$fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
				echo "ERROR bulding website: Please configure serverName and serverBase in file '$fileName'!\n";
			}
			echo "Yellow build: $contentCount content, $mediaCount media";
			echo ", $errorCount error".($errorCount!=1 ? 's' : '');
			echo ", status $statusCode\n";
		} else {
			echo "Yellow build: Invalid arguments\n";
		}
		return $statusCode;
	}
	
	// Build static files
	function buildStatic($serverName, $serverBase, $location, $path)
	{
		$statusCodeMax = $errorCount = 0;
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
			$statusCode = $this->buildStaticFile($serverName, $serverBase, $page->location, $path);
			$statusCodeMax = max($statusCodeMax, $statusCode);
			if($statusCode >= 400)
			{
				++$errorCount;
				echo "ERROR building location '".$page->location."', ".$this->yellow->page->getStatusCode(true)."\n";
			}
			if(defined("DEBUG") && DEBUG>=1) echo "Yellow_Commandline::buildStatic status:$statusCode location:".$page->location."\n";
		}
		foreach($fileNames as $fileName)
		{
			$statusCode = $this->copyStaticFile($fileName, "$path/$fileName") ? 200 : 500;
			$statusCodeMax = max($statusCodeMax, $statusCode);
			if($statusCode >= 400)
			{
				++$errorCount;
				echo "ERROR building file '$path/$fileName', ".$this->yellow->toolbox->getHttpStatusFormatted($statusCode)."\n";
			}
			if(defined("DEBUG") && DEBUG>=1) echo "Yellow_Commandline::buildStatic status:$statusCode file:$fileName\n";
		}
		return array($statusCodeMax, count($pages), count($fileNames), $errorCount);
	}
	
	// Build static file
	function buildStaticFile($serverName, $serverBase, $location, $path)
	{		
		ob_start();
		$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
		$_SERVER["SERVER_NAME"] = $serverName;
		$_SERVER["REQUEST_URI"] = $serverBase.$location;
		$_SERVER["SCRIPT_NAME"] = $serverBase."yellow.php";
		$statusCode = $this->yellow->request();
		if($statusCode != 404)
		{
			$ok = true;
			$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
			if(preg_match("/^(\w+)\/(\w+)/", $this->yellow->page->getHeader("Content-Type"), $matches)) 
			{
				$contentType = "$matches[1]/$matches[2]";
				$locationExtension = $this->getStaticLocation($location, ".$matches[2]");
			}
			if(empty($contentType) || $contentType=="text/html")
			{
				$fileName = $this->getStaticFileName($location, $path);
				$fileData = ob_get_contents();
				if($statusCode == 301) $fileData = $this->getStaticRedirect($this->yellow->page->getHeader("Location"));
				$ok = $this->makeStaticFile($fileName, $fileData, $modified);
			} else {
				$fileName = $this->getStaticFileName($location, $path);
				$fileData = $this->getStaticRedirect("http://$serverName$serverBase$locationExtension");
				$ok = $this->makeStaticFile($fileName, $fileData, $modified);
				if($ok)
				{
					$fileName = $this->getStaticFileName($locationExtension, $path);
					$fileData = ob_get_contents();
					$ok = $this->makeStaticFile($fileName, $fileData, $modified);
				}
			}
			if(!$ok)
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
			}
		}
		ob_end_clean();
		return $statusCode;
	}
	
	// Create static file
	function makeStaticFile($fileName, $fileData, $modified)
	{
		return $this->yellow->toolbox->makeFile($fileName, $fileData, true) &&
			$this->yellow->toolbox->modifyFile($fileName, $modified);
	}
	
	// Copy static file
	function copyStaticFile($fileNameSource, $fileNameDest)
	{
		return  $this->yellow->toolbox->copyFile($fileNameSource, $fileNameDest, true) &&
			$this->yellow->toolbox->modifyFile($fileNameDest, filemtime($fileNameSource));
	}
	
	// Return static file name from location
	function getStaticFileName($location, $path)
	{
		$fileName = $path.$location;
		if(!$this->yellow->toolbox->isFileLocation($location))
		{
			$fileName .= $this->yellow->config->get("commandBuildDefaultFile");
		}
		return $fileName;
	}
	
	// Return static location with file extension
	function getStaticLocation($location, $extension)
	{
		if(!$this->yellow->toolbox->isFileLocation($location)) $location .= "index";
		return $location.$extension;
	}
	
	// Return static redirect data
	function getStaticRedirect($url)
	{
		$data  = "<!DOCTYPE html><html>\n";
		$data .= "<head>\n";
		$data .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
		$data .= "<meta http-equiv=\"refresh\" content=\"0;url=$url\" />\n";
		$data .= "</head>\n";
		$data .= "</html>\n";
		return $data;
	}
}
	
$yellow->registerPlugin("commandline", "Yellow_Commandline", Yellow_Commandline::Version);
?>