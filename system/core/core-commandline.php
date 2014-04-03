<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Command line core plugin
class YellowCommandline
{
	const Version = "0.2.6";
	var $yellow;				//access to API
	var $content;				//number of content pages
	var $media;					//number of media files
	var $system;				//number of system files
	var $error;					//number of build errors
	var $locationsArguments;	//locations with arguments detected
	var $locationsPagination;	//locations with pagination detected
	var $fileNamesPlugin;		//plugin files detected
	
	// Initialise plugin
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("commandlineDefaultFile", "index.html");
		$this->yellow->config->setDefault("commandlineMediaFile", "(.*).txt");
		$this->yellow->config->setDefault("commandlineErrorFile", "error404.html");
		$this->yellow->config->setDefault("commandlineServerFile", ".htaccess");
	}
	
	// Handle command help
	function onCommandHelp()
	{
		$help .= "version\n";
		$help .= "build DIRECTORY [LOCATION]\n";
		return $help;
	}
	
	// Handle command
	function onCommand($args)
	{
		list($name, $command) = $args;
		switch($command)
		{
			case "":		$statusCode = $this->helpCommand(); break;
			case "version":	$statusCode = $this->versionCommand(); break;
			case "build":	$statusCode = $this->buildCommand($args); break;
			default:		$statusCode = $this->pluginCommand($args); break;
		}
		return $statusCode;
	}
	
	// Show available commands
	function helpCommand()
	{
		echo "Yellow command line ".YellowCommandline::Version."\n";
		foreach($this->getCommandHelp() as $line) echo (++$lineCounter>1 ? "        " : "Syntax: ")."yellow.php $line\n";
		return 200;
	}
	
	// Show software version
	function versionCommand()
	{
		echo "Yellow ".Yellow::Version."\n";
		foreach($this->yellow->plugins->plugins as $key=>$value) echo "$value[class] $value[version]\n";
		return 200;
	}
	
	// Build static pages
	function buildCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $path, $location) = $args;
		if(!empty($path) && $path!="/" && (empty($location) || $location[0]=='/'))
		{
			if($this->checkStaticConfig())
			{
				$statusCode = $this->buildStatic($location, $path);
			} else {
				$statusCode = 500;
				list($this->content, $this->media, $this->system, $this->error) = array(0, 0, 0, 1);
				$fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
				echo "ERROR bulding pages: Please configure serverName and serverBase in file '$fileName'!\n";
			}
			echo "Yellow $command: $this->content content, $this->media media, $this->system system";
			echo ", $this->error error".($this->error!=1 ? 's' : '');
			echo ", status $statusCode\n";
		} else {
			$statusCode = 400;
			echo "Yellow $command: Invalid arguments\n";
		}
		return $statusCode;
	}
	
	// Build static locations and files
	function buildStatic($location, $path)
	{
		$this->yellow->toolbox->timerStart($time);
		$this->content = $this->media = $this->system = $this->error = $statusCodeMax = 0;
		$this->locationsArguments = $this->locationsPagination = $this->fileNamesPlugin = array();
		$pluginDir = $this->yellow->config->get("pluginDir");
		$pathPlugin = rtrim($path.$this->yellow->config->get("pluginLocation"), '/');
		if(empty($location))
		{
			$pages = $this->yellow->pages->index(true);
			$fileNamesMedia = $this->yellow->toolbox->getDirectoryEntriesRecursive(
				$this->yellow->config->get("mediaDir"), "/.*/", false, false);
			$fileNamesMedia = array_merge($fileNamesMedia, $this->yellow->toolbox->getDirectoryEntries(
				".", "/".$this->yellow->config->get("commandlineMediaFile")."/", false, false, false));
			$fileNamesSystem = array($this->yellow->config->get("commandlineErrorFile"),
				$this->yellow->config->get("commandlineServerFile"));
		} else {
			$pages = new YellowPageCollection($this->yellow);
			$pages->append(new YellowPage($this->yellow, $location));
			$fileNamesMedia = $fileNamesSystem = array();
		}
		foreach($pages as $page)
		{
			$statusCodeMax = max($statusCodeMax, $this->buildStaticLocation($page->location, $path, empty($location)));
		}
		foreach($this->locationsArguments as $location)
		{
			$statusCodeMax = max($statusCodeMax, $this->buildStaticLocation($location, $path, true));
		}
		foreach($this->locationsPagination as $location)
		{
			for($pageNumber=2; $pageNumber<=999; ++$pageNumber)
			{
				$statusCode = $this->buildStaticLocation($location.$pageNumber, $path, false, true);
				$statusCodeMax = max($statusCodeMax, $statusCode);
				if($statusCode == 0) break;
			}
		}
		foreach($fileNamesMedia as $fileName)
		{
			$statusCodeMax = max($statusCodeMax, $this->buildStaticFile($fileName, "$path/$fileName"));
		}
		foreach($this->fileNamesPlugin as $fileName)
		{
			$statusCodeMax = max($statusCodeMax, $this->buildStaticFile("$pluginDir$fileName", "$pathPlugin/$fileName"));
		}
		foreach($fileNamesSystem as $fileName)
		{
			$statusCodeMax = max($statusCodeMax, $this->buildStaticFile($fileName, "$path/$fileName", false));
		}
		$this->yellow->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStatic time:$time ms\n";
		return $statusCodeMax;
	}
	
	// Build static location
	function buildStaticLocation($location, $path, $analyse = false, $probe = false)
	{		
		ob_start();
		$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
		$_SERVER["SERVER_NAME"] = $this->yellow->config->get("serverName");
		$_SERVER["REQUEST_URI"] = $this->yellow->config->get("serverBase").$location;
		$_SERVER["SCRIPT_NAME"] = $this->yellow->config->get("serverBase")."yellow.php";
		$_REQUEST = array();
		$statusCode = $this->yellow->request();
		if($statusCode != 404)
		{
			$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
			$fileName = $this->getStaticFileName($location, $path);
			$fileData = ob_get_contents();
			if($statusCode>=301 && $statusCode<=303) $fileData = $this->getStaticRedirect($this->yellow->page->getHeader("Location"));
			if(!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
			   !$this->yellow->toolbox->modifyFile($fileName, $modified))
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
			}
		}
		ob_end_clean();
		if($statusCode==200 && $analyse)
		{
			$this->analyseStaticContent($fileData);
			$this->analyseStaticMedia($fileData);
		}
		if($statusCode==404 && $probe) $statusCode = 0;
		if($statusCode != 0) ++$this->content;
		if($statusCode >= 400)
		{
			++$this->error;
			echo "ERROR building content location '$location', ".$this->yellow->page->getStatusCode(true)."\n";
		}
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticLocation status:$statusCode location:$location\n";
		return $statusCode;
	}
	
	// Build static file
	function buildStaticFile($fileNameSource, $fileNameDest, $fileTypeMedia = true)
	{
		if($fileNameSource != $this->yellow->config->get("commandlineErrorFile"))
		{
			$statusCode = $this->yellow->toolbox->copyFile($fileNameSource, $fileNameDest, true) &&
				$this->yellow->toolbox->modifyFile($fileNameDest, filemtime($fileNameSource)) ? 200 : 500;
		} else {
			ob_start();
			$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
			$_SERVER["SERVER_NAME"] = $this->yellow->config->get("serverName");
			$_SERVER["REQUEST_URI"] = $this->yellow->config->get("serverBase")."/";
			$_SERVER["SCRIPT_NAME"] = $this->yellow->config->get("serverBase")."yellow.php";
			$_REQUEST = array();
			$statusCodeRequest = 404;
			$statusCode = $this->yellow->request($statusCodeRequest);
			if($statusCode == $statusCodeRequest)
			{
				$statusCode = 200;
				$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
				if(!$this->yellow->toolbox->createFile($fileNameDest, ob_get_contents(), true) ||
				   !$this->yellow->toolbox->modifyFile($fileNameDest, $modified))
				{
					$statusCode = 500;
					$this->yellow->page->error($statusCode, "Can't write file '$fileNameDest'!");
				}
			} else {
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Error $statusCodeRequest does not exist!");
			}
			ob_end_clean();
		}
		if($fileTypeMedia) { ++$this->media; } else { ++$this->system; }
		if($statusCode >= 400)
		{
			++$this->error;
			$fileType = $fileTypeMedia ? "media file" : "system file";
			echo "ERROR building $fileType '$fileNameSource', ".$this->yellow->toolbox->getHttpStatusFormatted($statusCode)."\n";
		}
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticFile status:$statusCode file:$fileNameSource\n";
		return $statusCode;
	}
	
	// Analyse static content, detect locations with arguments and pagination
	function analyseStaticContent($text)
	{
		$serverName = $this->yellow->config->get("serverName");
		$serverBase = $this->yellow->config->get("serverBase");
		$pagination = $this->yellow->config->get("contentPagination");
		preg_match_all("/<a(.*?)href=\"([^\"]+)\"(.*?)>/i", $text, $matches);
		foreach($matches[2] as $match)
		{
			if(preg_match("/^\w+:\/+(.*?)(\/.*)$/", $match, $tokens))
			{
				if($tokens[1] != $serverName) continue;
				$match = $tokens[2];
			}
			if(!$this->yellow->toolbox->isLocationArgs($match)) continue;
			if(substru($match, 0, strlenu($serverBase)) != $serverBase) continue;
			$location = rawurldecode(substru($match, strlenu($serverBase)));
			if(!$this->yellow->toolbox->isPaginationLocation($location, $pagination))
			{
				$location = rtrim($location, '/').'/';
				if(is_null($this->locationsArguments[$location]))
				{
					$this->locationsArguments[$location] = $location;
					if(defined("DEBUG") && DEBUG>=2) echo "YellowCommandline::analyseStaticContent type:arguments location:$location\n";
				}
			} else {
				$location = rtrim($location, "0..9");
				if(is_null($this->locationsPagination[$location]))
				{
					$this->locationsPagination[$location] = $location;
					if(defined("DEBUG") && DEBUG>=2) echo "YellowCommandline::analyseStaticContent type:pagination location:$location\n";
				}
			}
		}
	}
	
	// Analyse static media, detect plugin files
	function analyseStaticMedia($text)
	{
		$serverName = $this->yellow->config->get("serverName");
		$serverBase = $this->yellow->config->get("serverBase");
		$pluginLocation = $this->yellow->config->get("pluginLocation");
		preg_match_all("#<(.*?)\"([^\"]*{$pluginLocation}[^\"]+)\"(.*?)>#", $text, $matches);
		foreach($matches[2] as $match)
		{
			if(preg_match("/^\w+:\/+(.*?)(\/.*)$/", $match, $tokens))
			{
				if($tokens[1] != $serverName) continue;
				$match = $tokens[2];
			}
			if(substru($match, 0, strlenu($serverBase.$pluginLocation)) != $serverBase.$pluginLocation) continue;
			$fileName = rawurldecode(substru($match, strlenu($serverBase.$pluginLocation)));
			if(is_null($this->fileNamesPlugin[$fileName]))
			{
				$this->fileNamesPlugin[$fileName] = $fileName;
				if(defined("DEBUG") && DEBUG>=2) echo "YellowCommandline::analyseStaticMedia type:plugin file:$fileName\n";
			}
		}
	}
	
	// Check static configuration
	function checkStaticConfig()
	{
		$serverName = $this->yellow->config->get("serverName");
		$serverBase = $this->yellow->config->get("serverBase");
		return !empty($serverName) && $this->yellow->toolbox->isValidLocation($serverBase) && $serverBase!="/";
	}
	
	// Return static file name from location
	function getStaticFileName($location, $path)
	{
		$fileName = $path.$location;
		if(!$this->yellow->toolbox->isFileLocation($location))
		{
			$fileName .= $this->yellow->config->get("commandlineDefaultFile");
		}
		return $fileName;
	}
	
	// Return static redirect data
	function getStaticRedirect($location)
	{
		$url = $this->yellow->toolbox->getHttpUrl($this->yellow->config->get("serverName"),
			$this->yellow->config->get("serverBase"), $location);
		$text  = "<!DOCTYPE html><html>\n";
		$text .= "<head>\n";
		$text .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
		$text .= "<meta http-equiv=\"refresh\" content=\"0;url=$url\" />\n";
		$text .= "</head>\n";
		$text .= "</html>\n";
		return $text;
	}
	
	// Return command help
	function getCommandHelp()
	{
		$data = array();
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onCommandHelp"))
			{
				foreach(preg_split("/[\r\n]+/", $value["obj"]->onCommandHelp()) as $line)
				{
					list($command, $text) = explode(' ', $line, 2);
					if(!empty($command) && is_null($data[$command])) $data[$command] = $line;
				}
			}
		}
		uksort($data, strnatcasecmp);
		return $data;
	}
	
	// Forward plugin command
	function pluginCommand($args)
	{
		$statusCode = 0;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if($key == "commandline") continue;
			if(method_exists($value["obj"], "onCommand"))
			{
				$statusCode = $value["obj"]->onCommand($args);
				if($statusCode != 0) break;
			}
		}
		return $statusCode;
	}
}
	
$yellow->registerPlugin("commandline", "YellowCommandline", YellowCommandline::Version);
?>