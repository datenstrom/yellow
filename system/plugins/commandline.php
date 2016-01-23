<?php
// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Command line plugin
class YellowCommandline
{
	const Version = "0.6.4";
	var $yellow;					//access to API
	var $files;						//number of files
	var $errors;					//number of errors
	var $locationsArgs;				//locations with location arguments detected
	var $locationsArgsPagination;	//locations with pagination arguments detected
	
	// Handle initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("commandlineVersionUrl", "https://github.com/datenstrom/yellow-extensions");
	}
	
	// Handle command
	function onCommand($args)
	{
		list($name, $command) = $args;
		switch($command)
		{
			case "":		$statusCode = $this->helpCommand(); break;
			case "version":	$statusCode = $this->versionCommand($args); break;
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
	
	// Handle command help
	function onCommandHelp()
	{
		$help .= "version\n";
		$help .= "build [DIRECTORY LOCATION]\n";
		$help .= "clean [DIRECTORY LOCATION]\n";
		return $help;
	}
	
	// Show available commands
	function helpCommand()
	{
		echo "Yellow ".YellowCore::Version."\n";
		foreach($this->getCommandHelp() as $line) echo (++$lineCounter>1 ? "        " : "Syntax: ")."yellow.php $line\n";
		return 200;
	}
	
	// Show software version and updates
	function versionCommand($args)
	{
		$statusCode = 0;
		$serverSoftware = $this->yellow->toolbox->getServerSoftware();
		echo "Yellow ".YellowCore::Version.", PHP ".PHP_VERSION.", $serverSoftware\n";
		$url = $this->yellow->config->get("commandlineVersionUrl");
		list($dummy, $command) = $args;
		list($statusCode, $versionCurrent) = $this->getPluginVersion();
		list($statusCode, $versionLatest) = $this->getPluginVersion($url);
		foreach($versionCurrent as $key=>$value)
		{
			if($versionCurrent[$key] >= $versionLatest[$key])
			{
				echo "$key $value\n";
			} else {
				echo "$key $value - Update available\n";
				++$updates;
			}
		}
		if($statusCode != 200) echo "ERROR checking updates at $url: $versionLatest[error]\n";
		if($updates) echo "Yellow $command: $updates update".($updates==1 ? "":"s")." available at $url\n";
		return $statusCode;
	}
		
	// Build static files
	function buildCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $path, $location) = $args;
		if(empty($location) || $location[0]=='/')
		{
			if($this->checkStaticConfig() && $this->checkStaticFilesystem())
			{
				$statusCode = $this->buildStatic($path, $location);
			} else {
				$statusCode = 500;
				$this->files = 0; $this->errors = 1;
				if(!$this->checkStaticFilesystem())
				{
					echo "ERROR building files: Static website not supported on Windows file system!\n";
				} else {
					$fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
					echo "ERROR building files: Please configure ServerScheme, ServerName, ServerBase, ServerTime in file '$fileName'!\n";
				}
			}
			echo "Yellow $command: $this->files file".($this->files!=1 ? 's' : '');
			echo ", $this->errors error".($this->errors!=1 ? 's' : '');
			echo ", status $statusCode\n";
		} else {
			$statusCode = 400;
			echo "Yellow $command: Invalid arguments\n";
		}
		return $statusCode;
	}
	
	// Build static files and additional locations
	function buildStatic($path, $location)
	{
		$this->yellow->toolbox->timerStart($time);
		$path = rtrim(empty($path) ? $this->yellow->config->get("staticDir") : $path, '/');
		$this->files = $this->errors = $statusCode = 0;
		$this->locationsArgs = $this->locationsArgsPagination = array();
		if(empty($location))
		{
			$statusCode = $this->cleanStatic($path, $location);
			foreach($this->getContentLocations() as $location)
			{
				$statusCode = max($statusCode, $this->buildStaticFile($path, $location, true));
			}
			foreach($this->locationsArgs as $location)
			{
				$statusCode = max($statusCode, $this->buildStaticFile($path, $location, true));
			}
			foreach($this->locationsArgsPagination as $location)
			{
				if(substru($location, -1) != ':')
				{
					$statusCode = max($statusCode, $this->buildStaticFile($path, $location, false, true));
				}
				for($pageNumber=2; $pageNumber<=999; ++$pageNumber)
				{
					$statusCodeLocation = $this->buildStaticFile($path, $location.$pageNumber, false, true);
					$statusCode = max($statusCode, $statusCodeLocation);
					if($statusCodeLocation == 100) break;
				}
			}
			foreach($this->getMediaLocations() as $location)
			{
				$statusCode = max($statusCode, $this->buildStaticFile($path, $location));
			}
			foreach($this->getSystemLocations() as $location)
			{
				$statusCode = max($statusCode, $this->buildStaticFile($path, $location));
			}
			$statusCode = max($statusCode, $this->buildStaticFile($path, "/error", false, false, true));
		} else {
			$statusCode = $this->buildStaticFile($path, $location);
		}
		$this->yellow->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStatic time:$time ms\n";
		return $statusCode;
	}
	
	// Build static file
	function buildStaticFile($path, $location, $analyse = false, $probe = false, $error = false)
	{
		$this->yellow->page = new YellowPage($this->yellow);
		$this->yellow->page->fileName = substru($location, 1);
		if(!is_readable($this->yellow->page->fileName))
		{
			ob_start();
			$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
			$_SERVER["SERVER_NAME"] = $this->yellow->config->get("serverName");
			$_SERVER["REQUEST_URI"] = $this->yellow->config->get("serverBase").$location;
			$_SERVER["SCRIPT_NAME"] = $this->yellow->config->get("serverBase")."/yellow.php";
			$_REQUEST = array();
			$statusCode = $this->yellow->request();
			if($statusCode<400 || $error)
			{
				$fileData = ob_get_contents();
				$modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
				if($modified == 0) $modified = filemtime($this->yellow->page->fileName);
				if($statusCode>=301 && $statusCode<=303)
				{
					$fileData = $this->getStaticRedirect($this->yellow->page->getHeader("Location"));
					$modified = time();
				}
				$fileName = $this->getStaticFile($path, $location, $statusCode);
				if(!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
				   !$this->yellow->toolbox->modifyFile($fileName, $modified))
				{
					$statusCode = 500;
					$this->yellow->page->statusCode = $statusCode;
					$this->yellow->page->set("pageError", "Can't write file '$fileName'!");
				}
			}
			ob_end_clean();
		} else {
			$statusCode = 200;
			$fileName = $this->getStaticFile($path, $location, $statusCode);
			if(!$this->yellow->toolbox->copyFile($this->yellow->page->fileName, $fileName, true) ||
			   !$this->yellow->toolbox->modifyFile($fileName, filemtime($this->yellow->page->fileName)))
			{
				$statusCode = 500;
				$this->yellow->page->statusCode = $statusCode;
				$this->yellow->page->set("pageError", "Can't write file '$fileName'!");
			}
		}
		if($statusCode==200 && $analyse) $this->analyseStaticFile($fileData);
		if($statusCode==404 && $error) $statusCode = 200;
		if($statusCode==404 && $probe) $statusCode = 100;
		if($statusCode >= 200) ++$this->files;
		if($statusCode >= 400)
		{
			++$this->errors;
			echo "ERROR building location '$location', ".$this->yellow->page->getStatusCode(true)."\n";
		}
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCommandline::buildStaticFile status:$statusCode location:$location\n";
		return $statusCode;
	}
	
	// Analyse static file, detect locations with arguments
	function analyseStaticFile($text)
	{
		$serverName = $this->yellow->config->get("serverName");
		$serverBase = $this->yellow->config->get("serverBase");
		$pagination = $this->yellow->config->get("contentPagination");
		preg_match_all("/<a(.*?)href=\"([^\"]+)\"(.*?)>/i", $text, $matches);
		foreach($matches[2] as $match)
		{
			if(preg_match("/^(.*?)#(.*)$/", $match, $tokens)) $match = $tokens[1];
			if(preg_match("/^\w+:\/+(.*?)(\/.*)$/", $match, $tokens))
			{
				if($tokens[1] != $serverName) continue;
				$match = $tokens[2];
			}
			if(!$this->yellow->toolbox->isLocationArgs($match)) continue;
			if(substru($match, 0, strlenu($serverBase)) != $serverBase) continue;
			$location = rawurldecode(substru($match, strlenu($serverBase)));
			if(!$this->yellow->toolbox->isLocationArgsPagination($location, $pagination))
			{
				$location = rtrim($location, '/').'/';
				if(is_null($this->locationsArgs[$location]))
				{
					$this->locationsArgs[$location] = $location;
					if(defined("DEBUG") && DEBUG>=2) echo "YellowCommandline::analyseStaticFile detected location:$location\n";
				}
			} else {
				$location = rtrim($location, "0..9");
				if(is_null($this->locationsArgsPagination[$location]))
				{
					$this->locationsArgsPagination[$location] = $location;
					if(defined("DEBUG") && DEBUG>=2) echo "YellowCommandline::analyseStaticFile detected location:$location\n";
				}
			}
		}
	}
	
	// Clean static files
	function cleanCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $path, $location) = $args;
		if(empty($location) || $location[0]=='/')
		{
			$statusCode = $this->cleanStatic($path, $location);
			echo "Yellow $command: Static file".(empty($location) ? "s" : "")." ".($statusCode!=200 ? "not " : "")."cleaned\n";
		} else {
			$statusCode = 400;
			echo "Yellow $command: Invalid arguments\n";
		}
		return $statusCode;
	}
	
	// Clean static files and directories
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
		if(is_dir($path) && $this->checkStaticDirectory($path))
		{
			if(!$this->yellow->toolbox->deleteDirectory($path, true))
			{
				$statusCode = 500;
				echo "ERROR cleaning files: Can't delete directory '$path'!\n";
			}
		}
		return $statusCode;
	}
	
	// Clean static file
	function cleanStaticFile($path, $location)
	{
		$statusCode = 200;
		$fileName = $this->getStaticFile($path, $location, $statusCode);
		if(is_file($fileName))
		{
			if(!$this->yellow->toolbox->deleteFile($fileName))
			{
				$statusCode = 500;
				echo "ERROR cleaning files: Can't delete file '$fileName'!\n";
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
			$this->yellow->lookup->isValidLocation($serverBase) && $serverBase!="/";
	}
	
	// Check static filesystem
	function checkStaticFilesystem()
	{
		return strtoupperu(substru(PHP_OS, 0, 3)) != "WIN";
	}
	
	// Check static directory
	function checkStaticDirectory($path)
	{
		$ok = false;
		if(!empty($path))
		{
			if($path == rtrim($this->yellow->config->get("staticDir"), '/')) $ok = true;
			if(is_file("$path/".$this->yellow->config->get("staticDefaultFile"))) $ok = true;
			if(is_file("$path/yellow.php")) $ok = false;
		}
		return $ok;
	}
	
	// Return static file
	function getStaticFile($path, $location, $statusCode)
	{
		if($statusCode < 400)
		{
			$fileName = $path.$location;
			if(!$this->yellow->lookup->isFileLocation($location)) $fileName .= $this->yellow->config->get("staticDefaultFile");
		} else if($statusCode == 404) {
			$fileName = $path."/".$this->yellow->config->get("staticErrorFile");
		}
		return $fileName;
	}
	
	// Return static redirect
	function getStaticRedirect($location)
	{
		$output = "<!DOCTYPE html><html>\n<head>\n";
		$output .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
		$output .= "<meta http-equiv=\"refresh\" content=\"0;url=".htmlspecialchars($location)."\" />\n";
		$output .= "</head>\n</html>";
		return $output;
	}
	
	// Return content locations
	function getContentLocations()
	{
		$locations = array();
		$serverScheme = $this->yellow->config->get("serverScheme");
		$serverName = $this->yellow->config->get("serverName");
		$serverBase = $this->yellow->config->get("serverBase");
		$this->yellow->page->setRequestInformation($serverScheme, $serverName, $serverBase, "", "");
		foreach($this->yellow->pages->index(true, true) as $page)
		{
			if($page->get("status")!="ignore" && $page->get("status")!="draft")
			{
				array_push($locations, $page->location);
			}
		}
		if(!$this->yellow->pages->find("/") && $this->yellow->config->get("multiLanguageMode")) array_unshift($locations, "/");
		return $locations;
	}
	
	// Return media locations
	function getMediaLocations()
	{
		$locations = array();
		$fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive(
			$this->yellow->config->get("mediaDir"), "/.*/", false, false);
		foreach($fileNames as $fileName)
		{
			array_push($locations, "/".$fileName);
		}
		return $locations;
	}

	// Return system locations
	function getSystemLocations()
	{
		$locations = array();
		$fileNames = $this->yellow->toolbox->getDirectoryEntries(
			$this->yellow->config->get("pluginDir"), "/\.(css|js|jpg|png|txt|woff)/", false, false);
		foreach($fileNames as $fileName)
		{
			array_push($locations, $this->yellow->config->get("pluginLocation").basename($fileName));
		}
		$fileNames = $this->yellow->toolbox->getDirectoryEntries(
			$this->yellow->config->get("themeDir"), "/\.(css|js|jpg|png|txt|woff)/", false, false);
		foreach($fileNames as $fileName)
		{
			array_push($locations, $this->yellow->config->get("themeLocation").basename($fileName));
		}
		array_push($locations, "/".$this->yellow->config->get("robotsFile"));
		return $locations;
	}
	
	// Return plugin version
	function getPluginVersion($url = "")
	{
		$version = array();
		if(empty($url))
		{
			$statusCode = 200;
			$version["YellowCore"] = YellowCore::Version;
			foreach($this->yellow->plugins->plugins as $key=>$value) $version[$value["class"]] = $value[version];
		} else {
			if(extension_loaded("curl"))
			{
				$pluginVersionUrl = $this->getPluginVersionUrl($url);
				$curlHandle = curl_init();
				curl_setopt($curlHandle, CURLOPT_URL, $pluginVersionUrl);
				curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowCore/".YellowCore::Version).")";
				curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
				$rawData = curl_exec($curlHandle);
				$statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
				curl_close($curlHandle);
				if($statusCode == 200)
				{
					if(defined("DEBUG") && DEBUG>=2) echo "YellowCommandline::getPluginVersion file:$pluginVersionUrl\n";
					foreach($this->yellow->toolbox->getTextLines($rawData) as $line)
					{
						if(preg_match("/^(\w+)\s*:\s*([0-9\.]+)/", $line, $matches))
						{
							$version[$matches[1]] = $matches[2];
							if(defined("DEBUG") && DEBUG>=3) echo "YellowCommandline::getPluginVersion $matches[1]:$matches[2]\n";
						}
					}
				}
				if($statusCode == 0) $statusCode = 444;
				$version["error"] = $this->yellow->toolbox->getHttpStatusFormatted($statusCode);
			} else {
				$statusCode = 500;
				$version["error"] = "Plugin 'commandline' requires cURL library!";
			}
		}
		uksort($version, strnatcasecmp);
		return array($statusCode, $version);
	}
	
	// Return plugin version URL from repository
	function getPluginVersionUrl($url)
	{
		if(preg_match("#^https://github.com/(.+)$#", $url, $matches))
		{
			$url = "https://raw.githubusercontent.com/".$matches[1]."/master/version.ini";
		}
		return $url;
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
}
	
$yellow->plugins->register("commandline", "YellowCommandline", YellowCommandline::Version);
?>