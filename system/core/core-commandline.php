<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Command line core plugin
class YellowCommandline
{
	const Version = "0.2.3";
	var $yellow;			//access to API

	// Initialise plugin
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("commandlineDefaultFile", "index.html");
		$this->yellow->config->setDefault("commandlineMediaFile", "(.*).txt");
		$this->yellow->config->setDefault("commandlineSystemErrorFile", "error404.html");
		$this->yellow->config->setDefault("commandlineSystemServerFile", ".htaccess");
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
			if($this->yellow->config->isExisting("serverName"))
			{
				list($statusCode, $content, $media, $system, $error) = $this->buildStatic($location, $path);
			} else {
				list($statusCode, $content, $media, $system, $error) = array(500, 0, 0, 0, 1);
				$fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
				echo "ERROR bulding pages: Please configure serverName and serverBase in file '$fileName'!\n";
			}
			echo "Yellow $command: $content content, $media media, $system system";
			echo ", $error error".($error!=1 ? 's' : '');
			echo ", status $statusCode\n";
		} else {
			echo "Yellow $command: Invalid arguments\n";
			$statusCode = 400;
		}
		return $statusCode;
	}
	
	// Build static files
	function buildStatic($location, $path)
	{
		$this->yellow->toolbox->timerStart($time);
		$statusCodeMax = $error = 0;
		if(empty($location))
		{
			$pages = $this->yellow->pages->index(true);
			$fileNamesMedia = $this->yellow->toolbox->getDirectoryEntriesRecursive(
				$this->yellow->config->get("mediaDir"), "/.*/", false, false);
			$fileNamesMedia = array_merge($fileNamesMedia, $this->yellow->toolbox->getDirectoryEntries(
				".", "/".$this->yellow->config->get("commandlineMediaFile")."/", false, false, false));
			$fileNamesSystem = array($this->yellow->config->get("commandlineSystemErrorFile"),
				$this->yellow->config->get("commandlineSystemServerFile"));
		} else {
			$pages = new YellowPageCollection($this->yellow);
			$pages->append(new YellowPage($this->yellow, $location));
			$fileNamesMedia = $fileNamesSystem = array();
		}
		foreach($pages as $page)
		{
			$statusCode = $this->buildStaticLocation($page->location, $path);
			$statusCodeMax = max($statusCodeMax, $statusCode); if($statusCode >= 400) ++$error;
		}
		foreach($fileNamesMedia as $fileName)
		{
			$statusCode = $this->buildStaticFile($fileName, "$path/$fileName", "media file");
			$statusCodeMax = max($statusCodeMax, $statusCode); if($statusCode >= 400) ++$error;
		}
		foreach($fileNamesSystem as $fileName)
		{
			$statusCode = $this->buildStaticFile($fileName, "$path/$fileName", "system file");
			$statusCodeMax = max($statusCodeMax, $statusCode); if($statusCode >= 400) ++$error;
		}
		$this->yellow->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStatic time:$time ms\n";
		return array($statusCodeMax, count($pages), count($fileNamesMedia), count($fileNamesSystem), $error);
	}
	
	// Build static location
	function buildStaticLocation($location, $path)
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
			$fileOk = true;
			$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
			list($contentType, $contentEncoding) = explode(';', $this->yellow->page->getHeader("Content-Type"), 2);
			$staticLocation = $this->getStaticLocation($location, $contentType);
			if($location == $staticLocation)
			{
				$fileName = $this->getStaticFileName($location, $path);
				$fileData = ob_get_contents();
				if($statusCode>=301 && $statusCode<=303) $fileData = $this->getStaticRedirect($this->yellow->page->getHeader("Location"));
				$fileOk = $this->yellow->toolbox->createFile($fileName, $fileData, true) &&
					$this->yellow->toolbox->modifyFile($fileName, $modified);
			} else {
				if(!$this->yellow->toolbox->isFileLocation($location))
				{
					$fileName = $this->getStaticFileName($location, $path);
					$fileData = $this->getStaticRedirect($staticLocation);
					$fileOk = $this->yellow->toolbox->createFile($fileName, $fileData, true) &&
						$this->yellow->toolbox->modifyFile($fileName, $modified);
					if($fileOk)
					{
						$fileName = $this->getStaticFileName($staticLocation, $path);
						$fileData = ob_get_contents();
						$fileOk = $this->yellow->toolbox->createFile($fileName, $fileData, true) &&
							$this->yellow->toolbox->modifyFile($fileName, $modified);
					}
				} else {
					$statusCode = 409;
					$this->yellow->page->error($statusCode, "Type '$contentType' does not match file name!");
				}
			}
			if(!$fileOk)
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
			}
		}
		ob_end_clean();
		if($statusCode>=400) echo "ERROR building location '$location', ".$this->yellow->page->getStatusCode(true)."\n";
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticLocation status:$statusCode location:$location\n";
		return $statusCode;
	}
	
	// Build static file
	function buildStaticFile($fileNameSource, $fileNameDest, $fileType)
	{
		if($fileNameSource != $this->yellow->config->get("commandlineSystemErrorFile"))
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
		if($statusCode>=400) echo "ERROR building $fileType '$fileNameSource', ".$this->yellow->toolbox->getHttpStatusFormatted($statusCode)."\n";
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticFile status:$statusCode file:$fileNameSource\n";
		return $statusCode;
	}
	
	// Return static location corresponding to content type
	function getStaticLocation($location, $contentType)
	{
		if(!empty($contentType))
		{
			$extension = ($pos = strrposu($location, '.')) ? substru($location, $pos) : "";
			if($contentType == "text/html")
			{
				if($this->yellow->toolbox->isFileLocation($location))
				{
					if(!empty($extension) && $extension!=".html") $location .= ".html";
				}
			} else {
				if($this->yellow->toolbox->isFileLocation($location))
				{
					if(empty($extension)) $location .= ".unknown";
				} else {
					if(preg_match("/^(\w+)\/(\w+)/", $contentType, $matches)) $extension = ".$matches[2]";
					$location .= "index$extension";
				}
			}
		}
		return $location;
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