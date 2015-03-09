<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Command line core plugin
class YellowCommandline
{
	const Version = "0.5.1";
	var $yellow;				//access to API
	var $content;				//number of content pages
	var $media;					//number of media files
	var $system;				//number of system files
	var $error;					//number of build errors
	var $locationsArguments;	//locations with arguments detected
	var $locationsPagination;	//locations with pagination detected
	
	// Handle plugin initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("commandlineIgnoreLocation", "");
		$this->yellow->config->setDefault("commandlineSystemFile", ".htaccess");
	}
	
	// Handle command help
	function onCommandHelp()
	{
		$help .= "version\n";
		$help .= "build [DIRECTORY LOCATION]\n";
		$help .= "clean [DIRECTORY LOCATION]\n";
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
			case "clean":	$statusCode = $this->cleanCommand($args); break;
			default:		$statusCode = $this->pluginCommand($args);
		}
		if($statusCode == 0)
		{
			$statusCode = 400;
			echo "Yellow $command: Command not found\n";
		}
		return $statusCode;
	}
	
	// Show available commands
	function helpCommand()
	{
		echo "Yellow ".Yellow::Version."\n";
		foreach($this->getCommandHelp() as $line) echo (++$lineCounter>1 ? "        " : "Syntax: ")."yellow.php $line\n";
		return 200;
	}
	
	// Show software version
	function versionCommand()
	{
		echo "Yellow ".Yellow::Version."\n";
		foreach($this->getPluginVersion() as $line) echo "$line\n";
		return 200;
	}
	
	// Build static pages
	function buildCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $path, $location) = $args;
		if(empty($location) || $location[0]=='/')
		{
			if($this->checkStaticConfig())
			{
				$statusCode = $this->buildStatic($path, $location);
			} else {
				$statusCode = 500;
				list($this->content, $this->media, $this->system, $this->error) = array(0, 0, 0, 1);
				$fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
				echo "ERROR bulding pages: Please configure serverScheme, serverName and serverBase in file '$fileName'!\n";
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
	
	// Build static directories and files
	function buildStatic($path, $location)
	{
		$this->yellow->toolbox->timerStart($time);
		$path = rtrim(empty($path) ? $this->yellow->config->get("staticDir") : $path, '/');
		$this->content = $this->media = $this->system = $this->error = $statusCode = 0;
		$this->locationsArguments = $this->locationsPagination = array();
		if(empty($location))
		{
			$statusCode = $this->cleanStatic($path, $location);
			foreach($this->getStaticLocations() as $location)
			{
				$statusCode = max($statusCode, $this->buildStaticRequest($path, $location, true));
			}
			foreach($this->locationsArguments as $location)
			{
				$statusCode = max($statusCode, $this->buildStaticRequest($path, $location, true));
			}
			foreach($this->locationsPagination as $location)
			{
				for($pageNumber=2; $pageNumber<=999; ++$pageNumber)
				{
					$statusCodeLocation = $this->buildStaticRequest($path, $location.$pageNumber, false, true);
					$statusCode = max($statusCode, $statusCodeLocation);
					if($statusCodeLocation == 0) break;
				}
			}
			$statusCode = max($statusCode, $this->buildStaticError($path, 404));
			foreach($this->getStaticFilesMedia($path) as $fileNameSource=>$fileNameDest)
			{
				$statusCode = max($statusCode, $this->buildStaticFile($fileNameSource, $fileNameDest, true));
			}
			foreach($this->getStaticFilesSystem($path) as $fileNameSource=>$fileNameDest)
			{
				$statusCode = max($statusCode, $this->buildStaticFile($fileNameSource, $fileNameDest, false));
			}
		} else {
			$statusCode = $this->buildStaticRequest($path, $location);
		}
		$this->yellow->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStatic time:$time ms\n";
		return $statusCode;
	}
	
	// Build static request
	function buildStaticRequest($path, $location, $analyse = false, $probe = false)
	{		
		ob_start();
		$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
		$_SERVER["SERVER_NAME"] = $this->yellow->config->get("serverName");
		$_SERVER["REQUEST_URI"] = $this->yellow->config->get("serverBase").$location;
		$_SERVER["SCRIPT_NAME"] = $this->yellow->config->get("serverBase")."yellow.php";
		$_REQUEST = array();
		$statusCode = $this->yellow->request();
		if($statusCode < 400)
		{
			$fileName = $this->yellow->toolbox->findStaticFileFromLocation($location, $path,
				$this->yellow->config->get("staticDefaultFile"));
			$fileData = ob_get_contents();
			$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
			if(!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
			   !$this->yellow->toolbox->modifyFile($fileName, $modified))
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
			}
		}
		ob_end_clean();
		if($statusCode==200 && $analyse) $this->analyseStaticContent($fileData);
		if($statusCode==404 && $probe) $statusCode = 0;
		if($statusCode != 0) ++$this->content;
		if($statusCode >= 400)
		{
			++$this->error;
			echo "ERROR building content location '$location', ".$this->yellow->page->getStatusCode(true)."\n";
		}
		if(defined("DEBUG") && DEBUG>=3) echo $fileData;
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticRequest status:$statusCode location:$location\n";
		return $statusCode;
	}
	
	// Build static error
	function buildStaticError($path, $statusCodeRequest)
	{
		ob_start();
		$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
		$_SERVER["SERVER_NAME"] = $this->yellow->config->get("serverName");
		$_SERVER["REQUEST_URI"] = $this->yellow->config->get("serverBase")."/";
		$_SERVER["SCRIPT_NAME"] = $this->yellow->config->get("serverBase")."yellow.php";
		$_REQUEST = array();
		$fileName = "$path/".$this->yellow->config->get("staticErrorFile");
		$statusCode = $this->yellow->request($statusCodeRequest);
		if($statusCode == $statusCodeRequest)
		{
			$statusCode = 200;
			$fileData = ob_get_contents();
			$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
			if(!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
			   !$this->yellow->toolbox->modifyFile($fileName, $modified))
			{
				$statusCode = 500;
				$this->yellow->page->statusCode = $statusCode;
				$this->yellow->page->set("pageError", "Can't write file '$fileName'!");
			}
		}
		ob_end_clean();
		++$this->system;
		if($statusCode >= 400)
		{
			++$this->error;
			echo "ERROR building error file, ".$this->yellow->page->getStatusCode(true)."\n";
		}
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticError status:$statusCode file:$fileName\n";
		return $statusCode;
	}
	
	// Build static file
	function buildStaticFile($fileNameSource, $fileNameDest, $fileTypeMedia)
	{
		$statusCode = $this->yellow->toolbox->copyFile($fileNameSource, $fileNameDest, true) &&
			$this->yellow->toolbox->modifyFile($fileNameDest, filemtime($fileNameSource)) ? 200 : 500;
		if($fileTypeMedia) { ++$this->media; } else { ++$this->system; }
		if($statusCode >= 400)
		{
			++$this->error;
			$fileType = $fileTypeMedia ? "media file" : "system file";
			$fileError = $this->yellow->toolbox->getHttpStatusFormatted($statusCode);
			echo "ERROR building $fileType, $fileError: Can't write file '$fileNameDest'!\n";
		}
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticFile status:$statusCode file:$fileNameDest\n";
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
	
	// Clean static pages
	function cleanCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $path, $location) = $args;
		if(empty($location) || $location[0]=='/')
		{
			$statusCode = $this->cleanStatic($path, $location);
			echo "Yellow $command: Static page".(empty($location) ? "s" : "")." ".($statusCode!=200 ? "not " : "")."cleaned\n";
		} else {
			$statusCode = 400;
			echo "Yellow $command: Invalid arguments\n";
		}
		return $statusCode;
	}
	
	// Clean static directories and files
	function cleanStatic($path, $location)
	{
		$statusCode = 200;
		$path = rtrim(empty($path) ? $this->yellow->config->get("staticDir") : $path, '/');
		if(empty($location))
		{
			$statusCode = max($statusCode, $this->pluginCommand(array("all", "clean")));
			$statusCode = max($statusCode, $this->cleanStaticDirectory($path));
		} else {
			$statusCode = $this->cleanStaticFile($path, $location);
		}
		return $statusCode;
	}
	
	// Clean static directory
	function cleanStaticDirectory($path)
	{
		$statusCode = 200;
		if($this->isYellowDirectory($path))
		{
			if(is_file("$path/yellow.php") || $path=="/" || !$this->yellow->toolbox->deleteDirectory($path, true))
			{
				$statusCode = 500;
				echo "ERROR cleaning pages: Can't delete directory '$path'!\n";
			}
		}
		return $statusCode;
	}
	
	// Clean static file
	function cleanStaticFile($path, $location)
	{
		$statusCode = 200;
		$fileName = $this->yellow->toolbox->findStaticFileFromLocation($location, $path,
			$this->yellow->config->get("staticDefaultFile"));
		if($this->isYellowDirectory($path) && is_file($fileName))
		{
			$entry = basename($fileName);
			if($entry!="yellow.php" && substru($entry, 0, 1)!=".")
			{
				if(!$this->yellow->toolbox->deleteFile($fileName))
				{
					$statusCode = 500;
					echo "ERROR cleaning pages: Can't delete file '$fileName'!\n";
				}
			}
		}
		return $statusCode;
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
	
	// Check static configuration
	function checkStaticConfig()
	{
		$serverScheme = $this->yellow->config->get("serverScheme");
		$serverName = $this->yellow->config->get("serverName");
		$serverBase = $this->yellow->config->get("serverBase");
		return !empty($serverScheme) && !empty($serverName) &&
			$this->yellow->toolbox->isValidLocation($serverBase) && $serverBase!="/";
	}
	
	// Return static locations from file system
	function getStaticLocations()
	{
		$locations = array();
		$serverScheme = $this->yellow->config->get("serverScheme");
		$serverName = $this->yellow->config->get("serverName");
		$serverBase = $this->yellow->config->get("serverBase");
		$this->yellow->page->setRequestInformation($serverScheme, $serverName, $serverBase, "", "");
		if($this->yellow->config->isExisting("commandlineIgnoreLocation"))
		{
			$regex = "#^".$this->yellow->config->get("commandlineIgnoreLocation")."#";
		}
		foreach($this->yellow->pages->index(true, true) as $page)
		{
			if(!empty($regex) && preg_match($regex, $page->location)) continue;
			array_push($locations, $page->location);
		}
		if(!$this->yellow->pages->find("/") && $this->yellow->config->get("multiLanguageMode")) array_unshift($locations, "/");
		return $locations;
	}
	
	// Return static media files
	function getStaticFilesMedia($path)
	{
		$files = array();
		$fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive(
			$this->yellow->config->get("mediaDir"), "/.*/", false, false);
		foreach($fileNames as $fileName) $files[$fileName] = "$path/$fileName";
		return $files;
	}
	
	// Return static system files
	function getStaticFilesSystem($path)
	{
		$files = array();
		$fileNames = $this->yellow->toolbox->getDirectoryEntries(
			$this->yellow->config->get("pluginDir"), "/\.(css|js|jpg|png|txt|woff)/", false, false);
		foreach($fileNames as $fileName)
		{
			$files[$fileName] = $path.$this->yellow->config->get("pluginLocation").basename($fileName);
		}
		$fileNames = $this->yellow->toolbox->getDirectoryEntries(
			$this->yellow->config->get("themeDir"), "/\.(css|js|jpg|png|txt|woff)/", false, false);
		foreach($fileNames as $fileName)
		{
			$files[$fileName] = $path.$this->yellow->config->get("themeLocation").basename($fileName);
		}
		$fileNames = array();
		array_push($fileNames, $this->yellow->config->get("commandlineSystemFile"));
		array_push($fileNames, $this->yellow->config->get("configDir").$this->yellow->config->get("robotsTextFile"));
		foreach($fileNames as $fileName) $files[$fileName] = "$path/".basename($fileName);
		return $files;
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
	
	// Return plugin version
	function getPluginVersion()
	{
		$data = array();
		foreach($this->yellow->plugins->plugins as $key=>$value) $data[$key] = "$value[class] $value[version]";
		usort($data, strnatcasecmp);
		return $data;
	}
	
	// Check if directory contains Yellow files
	function isYellowDirectory($path)
	{
		return is_file("$path/yellow.php") || is_file("$path/".$this->yellow->config->get("commandlineSystemFile"));
	}
}
	
$yellow->plugins->register("commandline", "YellowCommandline", YellowCommandline::Version);
?>