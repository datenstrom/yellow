<?php
// Copyright (c) 2013-2017 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Update plugin
class YellowUpdate
{
	const VERSION = "0.6.11";
	var $yellow;					//access to API
	
	// Handle initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("updatePluginsUrl", "https://github.com/datenstrom/yellow-plugins");
		$this->yellow->config->setDefault("updateThemesUrl", "https://github.com/datenstrom/yellow-themes");
		$this->yellow->config->setDefault("updateVersionFile", "version.ini");
		$this->yellow->config->setDefault("updateInformationFile", "update.ini");
		$this->yellow->config->setDefault("updateNotification", "none");
	}
	
	// Handle update
	function onUpdate($name)
	{
		if(empty($name)) $this->processUpdateNotification();
	}
	
	// Handle request
	function onRequest($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->isInstallationMode())
		{
			$statusCode = $this->processRequestInstallationMode($serverScheme, $serverName, $base, $location, $fileName);
		} else {
			$statusCode = $this->processRequestInstallationPending($serverScheme, $serverName, $base, $location, $fileName);
		}
		return $statusCode;
	}
	
	// Handle command
	function onCommand($args)
	{
		list($command) = $args;
		switch($command)
		{
			case "clean":	$statusCode = $this->cleanCommand($args); break;				
			case "update":	$statusCode = $this->updateCommand($args); break;
			default:		$statusCode = $this->processCommandInstallationPending($args); break;
		}
		return $statusCode;
	}
	
	// Handle command help
	function onCommandHelp()
	{
		return "update [FEATURE]";
	}
	
	// Clean downloads
	function cleanCommand($args)
	{
		$statusCode = 0;
		list($command, $path) = $args;
		if($path=="all")
		{
			$path = $this->yellow->config->get("pluginDir");
			$regex = "/^.*\\".$this->yellow->config->get("downloadExtension")."$/";
			foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, false, false) as $entry)
			{
				if(!$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
			}
			if($statusCode==500) echo "ERROR cleaning downloads: Can't delete files in directory '$path'!\n";
		}
		return $statusCode;
	}
	
	// Update website
	function updateCommand($args)
	{
		list($command, $feature, $option) = $args;
		list($statusCode, $data) = $this->getSoftwareUpdates($feature);
		if(!empty($data))
		{
			foreach($data as $key=>$value)
			{
				list($version) = explode(',', $value);
				echo "$key $version\n";
			}
			if($statusCode==200) $statusCode = $this->downloadSoftware($data);
			if($statusCode==200) $statusCode = $this->updateSoftware($option);
			if($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
			echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated\n";
		} else {
			if($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
			echo "Yellow $command: No updates available\n";
		}
		return $statusCode;
	}
	
	// Download software
	function downloadSoftware($data)
	{
		$statusCode = 0;
		$path = $this->yellow->config->get("pluginDir");
		$fileExtension = $this->yellow->config->get("downloadExtension");
		foreach($data as $key=>$value)
		{
			$fileName = strtoloweru("$path$key.zip");
			list($version, $url) = explode(',', $value);
			list($statusCode, $fileData) = $this->getSoftwareFile($url);
			if(empty($fileData) || !$this->yellow->toolbox->createFile($fileName.$fileExtension, $fileData))
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
				break;
			}
		}
		if($statusCode==200)
		{
			foreach($data as $key=>$value)
			{
				$fileName = strtoloweru("$path$key.zip");
				if(!$this->yellow->toolbox->renameFile($fileName.$fileExtension, $fileName))
				{
					$statusCode = 500;
					$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
				}
			}
		}
		return $statusCode;
	}

	// Update software
	function updateSoftware($option = "")
	{
		$statusCode = 0;
		$path = $this->yellow->config->get("pluginDir");
		foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry)
		{
			$statusCode = max($statusCode, $this->updateSoftwareArchive($entry, $option));
		}
		$path = $this->yellow->config->get("themeDir");
		foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry)
		{
			$statusCode = max($statusCode, $this->updateSoftwareArchive($entry, $option));
		}
		return $statusCode;
	}

	// Update software from archive
	function updateSoftwareArchive($path, $option = "")
	{
		$statusCode = 0;
		$zip = new ZipArchive();
		if($zip->open($path)===true)
		{
			if(defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::updateSoftwareArchive file:$path<br/>\n";
			if(strtoloweru($option)=="force") $force = true;
			if(preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
			$fileData = $zip->getFromName($pathBase.$this->yellow->config->get("updateInformationFile"));
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]))
				{
					if(is_file($matches[1])) { $lastPublished = filemtime($matches[1]); break; }
				}
			}
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(lcfirst($matches[1])=="plugin" || lcfirst($matches[1])=="theme") $software = $matches[2];
				if(lcfirst($matches[1])=="published") $modified = strtotime($matches[2]);
				if(!empty($software) && !empty($matches[1]) && !empty($matches[2]))
				{
					list($entry, $flags) = explode(',', $matches[2], 2);
					$fileName = $matches[1];
					$fileData = $zip->getFromName($pathBase.$entry);
					$lastModified = $this->yellow->toolbox->getFileModified($fileName);
					$statusCode = $this->updateSoftwareFile($fileName, $fileData, $modified, $lastModified, $lastPublished, $flags, $force, $software);
					if($statusCode!=200) break;
				}
			}
			$zip->close();
			if($statusCode==200)
			{
				$updateNotification = $this->yellow->config->get("updateNotification");
				if($updateNotification=="none") $updateNotification = "";
				if(!empty($updateNotification)) $updateNotification .= ",";
				$updateNotification .= $software;
				$fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
				if(!$this->yellow->config->update($fileNameConfig, array("updateNotification" => $updateNotification)))
				{
					$statusCode = 500;
					$this->yellow->page->error(500, "Can't write file '$fileNameConfig'!");
				}
			}
		}
		if(!$this->yellow->toolbox->deleteFile($path))
		{
			$statusCode = 500;
			$this->yellow->page->error($statusCode, "Can't delete file '$path'!");
		}
		return $statusCode;
	}
	
	// Update software file
	function updateSoftwareFile($fileName, $fileData, $modified, $lastModified, $lastPublished, $flags, $force, $software)
	{
		$statusCode = 200;
		$fileName = $this->yellow->toolbox->normaliseTokens($fileName);
		if($this->yellow->lookup->isValidFile($fileName) && !empty($flags))
		{
			$create = $update = $delete = false;
			if(preg_match("/create/i", $flags) && !is_file($fileName) && !empty($fileData)) $create = true;
			if(preg_match("/update/i", $flags) && is_file($fileName) && !empty($fileData)) $update = true;
			if(preg_match("/delete/i", $flags) && is_file($fileName)) $delete = true;
			if(preg_match("/careful/i", $flags) && is_file($fileName) && $lastModified!=$lastPublished && !$force) $update = false;
			if(preg_match("/optional/i", $flags) && $this->isSoftwareExisting($software)) $create = $update = $delete = false;
			if($create)
			{
				if(!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
				   !$this->yellow->toolbox->modifyFile($fileName, $modified))
				{
					$statusCode = 500;
					$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
				}
			}
			if($update)
			{
				if(!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->config->get("trashDir")) ||
				   !$this->yellow->toolbox->createFile($fileName, $fileData) ||
				   !$this->yellow->toolbox->modifyFile($fileName, $modified))
				{
					$statusCode = 500;
					$this->yellow->page->error($statusCode, "Can't update file '$fileName'!");
				}
			}
			if($delete)
			{
				if(!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->config->get("trashDir")))
				{
					$statusCode = 500;
					$this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
				}
			}
			if(defined("DEBUG") && DEBUG>=2)
			{
				$debug = "action:".($create ? "create" : "").($update ? "update" : "").($delete ? "delete" : "");
				if(!$create && !$update && !$delete) $debug = "action:none";
				echo "YellowUpdate::updateSoftwareFile file:$fileName $debug<br/>\n";
			}
		}
		return $statusCode;
	}
	
	// Update software features
	function updateSoftwareFeatures($feature)
	{
		$statusCode = 200;
		$path = $this->yellow->config->get("pluginDir");
		$regex = "/^.*\\".$this->yellow->config->get("installationExtension")."$/";
		foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
		{
			if(stristr(basename($entry), $feature))
			{
				$statusCode = max($statusCode, $this->updateSoftwareArchive($entry));
			}
		}
		if($statusCode==200)
		{
			foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
			{
				$this->yellow->toolbox->deleteFile($entry);
			}
		}
		return $statusCode;
	}
	
	// Process update notification for recently installed software
	function processUpdateNotification()
	{
		if($this->yellow->config->get("updateNotification")!="none")
		{
			$tokens = explode(',', $this->yellow->config->get("updateNotification"));
			foreach($this->yellow->plugins->plugins as $key=>$value)
			{
				if(in_array($value["plugin"], $tokens) && method_exists($value["obj"], "onUpdate")) $value["obj"]->onUpdate($key);
			}
			$fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
			$this->yellow->config->update($fileNameConfig, array("updateNotification" => "none"));
		}
	}
	
	// Process command to install pending software
	function processCommandInstallationPending($args)
	{
		$statusCode = 0;
		if($this->isSoftwarePending())
		{
			$statusCode = $this->updateSoftware();
			if($statusCode!=0)
			{
				if($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
				echo "Yellow has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
			}
		}
		return $statusCode;
	}
	
	// Process request to install pending software
	function processRequestInstallationPending($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->yellow->lookup->isContentFile($fileName) && $this->isSoftwarePending())
		{
			$statusCode = $this->updateSoftware();
			if($statusCode==200)
			{
				$statusCode = 303;
				$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->yellow->sendStatus($statusCode, $location);
			}
		}
		return $statusCode;
	}
	
	// Process request to install website
	function processRequestInstallationMode($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->yellow->lookup->isContentFile($fileName) && $this->isInstallationMode())
		{
			$this->yellow->pages->pages["root/"] = array();
			$this->yellow->page = new YellowPage($this->yellow);
			$this->yellow->page->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
			$this->yellow->page->parseData($this->getRawDataInstallation(), false, 404);
			$this->yellow->page->parserSafeMode = false;
			$this->yellow->page->parseContent();
			$name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
			$email = trim($_REQUEST["email"]);
			$password = trim($_REQUEST["password"]);
			$language = trim($_REQUEST["language"]);
			$feature = trim($_REQUEST["feature"]);
			$status = trim($_REQUEST["status"]);
			if($status=="install")
			{
				$status = "ok";
				$fileNameHome = $this->yellow->lookup->findFileFromLocation("/");
				$fileData = strreplaceu("\r\n", "\n", $this->yellow->toolbox->readFile($fileNameHome));
				if($fileData==$this->getRawDataHome("en") && $language!="en")
				{
					$status = $this->yellow->toolbox->createFile($fileNameHome, $this->getRawDataHome($language)) ? "ok" : "error";
					if($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameHome'!");
				}
			}
			if($status=="ok")
			{
				if(!empty($email) && !empty($password) && $this->yellow->plugins->isExisting("webinterface"))
				{
					$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
					$status = $this->yellow->plugins->get("webinterface")->users->update($fileNameUser, $email, $password, $name, $language) ? "ok" : "error";
					if($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
				}
			}
			if($status=="ok")
			{
				if(!empty($feature))
				{
					$status = $this->updateSoftwareFeatures($feature)==200 ? "ok" : "error";
					if($status=="error") $this->yellow->page->error(500, "Can't install feature '$feature'!");
				}
			}
			if($status=="ok")
			{
				if($this->yellow->config->get("sitename")=="Yellow") $_REQUEST["sitename"] = $name;
				$fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
				$status = $this->yellow->config->update($fileNameConfig, $this->getConfigData()) ? "done" : "error";
				if($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameConfig'!");
			}
			if($status=="done")
			{
				$statusCode = 303;
				$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->yellow->sendStatus($statusCode, $location);
			} else {
				$statusCode = $this->yellow->sendPage();
			}
		}
		return $statusCode;
	}
	
	// Return raw data for installation page
	function getRawDataInstallation()
	{
		$language = $this->yellow->toolbox->detectBrowserLanguage($this->yellow->text->getLanguages(), $this->yellow->config->get("language"));
		$fileName = strreplaceu("(.*)", "installation", $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceNewFile"));
		$rawData = $this->yellow->toolbox->readFile($fileName);
		if(empty($rawData))
		{
			$this->yellow->text->setLanguage($language);
			$rawData = "---\nTitle:".$this->yellow->text->get("webinterfaceInstallationTitle")."\nLanguage:$language\nNavigation:navigation\n---\n";
			$rawData .= "<form class=\"installation-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
			$rawData .= "<p><label for=\"name\">".$this->yellow->text->get("webinterfaceSignupName")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"name\" id=\"name\" value=\"\"></p>\n";
			$rawData .= "<p><label for=\"email\">".$this->yellow->text->get("webinterfaceSignupEmail")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"email\" id=\"email\" value=\"\"></p>\n";
			$rawData .= "<p><label for=\"password\">".$this->yellow->text->get("webinterfaceSignupPassword")."</label><br /><input class=\"form-control\" type=\"password\" maxlength=\"64\" name=\"password\" id=\"password\" value=\"\"></p>\n";
			if(count($this->yellow->text->getLanguages())>1)
			{
				$rawData .= "<p>";
				foreach($this->yellow->text->getLanguages() as $language)
				{
					$checked = $language==$this->yellow->text->language ? " checked=\"checked\"" : "";
					$rawData .= "<label for=\"$language\"><input type=\"radio\" name=\"language\" id=\"$language\" value=\"$language\"$checked> ".$this->yellow->text->getTextHtml("languageDescription", $language)."</label><br />";
				}
				$rawData .= "</p>\n";
			}
			if(count($this->getSoftwareFeatures())>1)
			{
				$rawData .= "<p>".$this->yellow->text->get("webinterfaceInstallationFeature")."<p>";
				foreach($this->getSoftwareFeatures() as $feature)
				{
					$checked = $feature=="website" ? " checked=\"checked\"" : "";
					$rawData .= "<label for=\"$feature\"><input type=\"radio\" name=\"feature\" id=\"$feature\" value=\"$feature\"$checked> ".ucfirst($feature)."</label><br />";
				}
				$rawData .= "</p>\n";
			}
			$rawData .= "<input class=\"btn\" type=\"submit\" value=\"".$this->yellow->text->get("webinterfaceOkButton")."\" />\n";
			$rawData .= "<input type=\"hidden\" name=\"status\" value=\"install\" />\n";
			$rawData .= "</form>\n";
		}
		return $rawData;
	}
	
	// Return raw data for home page
	function getRawDataHome($language)
	{
		$rawData = "---\nTitle: Home\n---\n".strreplaceu("\\n", "\n", $this->yellow->text->getText("webinterfaceInstallationHomePage", $language));
		return $rawData;
	}
	
	// Return configuration data
	function getConfigData()
	{
		$data = array();
		foreach($_REQUEST as $key=>$value)
		{
			if(!$this->yellow->config->isExisting($key)) continue;
			$data[$key] = trim($value);
		}
		$data["# serverScheme"] = $this->yellow->toolbox->getServerScheme();
		$data["# serverName"] = $this->yellow->toolbox->getServerName();
		$data["# serverBase"] = $this->yellow->toolbox->getServerBase();
		$data["# serverTime"] = $this->yellow->toolbox->getServerTime();
		$data["installationMode"] = "0";
		return $data;
	}

	// Return software features
	function getSoftwareFeatures()
	{
		$data = array("website");
		$path = $this->yellow->config->get("pluginDir");
		$regex = "/^.*\\".$this->yellow->config->get("installationExtension")."$/";
		foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry)
		{
			if(preg_match("/^(.*?)-(.*?)\./", $entry, $matches))
			{
				array_push($data, $matches[2]);
			}
		}
		return $data;
	}
	
	// Return software updates
	function getSoftwareUpdates($feature)
	{
		$data = array();
		list($statusCode, $dataCurrent) = $this->getSoftwareVersion();
		list($statusCode, $dataLatest) = $this->getSoftwareVersion(true, true);
		foreach($dataCurrent as $key=>$value)
		{
			list($version) = explode(',', $dataLatest[$key]);
			if(empty($feature))
			{
				if(strnatcasecmp($dataCurrent[$key], $version)<0) $data[$key] = $dataLatest[$key];
			} else {
				if(stristr($key, $feature) && $version) $data[$key] = $dataLatest[$key];
			}
		}
		return array($statusCode, $data);
	}

	// Return software version
	function getSoftwareVersion($latest = false, $rawFormat = false)
	{
		$data = array();
		if($latest)
		{
			$urlPlugins = $this->yellow->config->get("updatePluginsUrl")."/raw/master/".$this->yellow->config->get("updateVersionFile");
			$urlThemes = $this->yellow->config->get("updateThemesUrl")."/raw/master/".$this->yellow->config->get("updateVersionFile");
			list($statusCodePlugins, $fileDataPlugins) = $this->getSoftwareFile($urlPlugins, $rawFormat);
			list($statusCodeThemes, $fileDataThemes) = $this->getSoftwareFile($urlThemes, $rawFormat);
			$statusCode = max($statusCodePlugins, $statusCodeThemes);
			if($statusCode==200)
			{
				foreach($this->yellow->toolbox->getTextLines($fileDataPlugins."\n".$fileDataThemes) as $line)
				{
					preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
					if(!empty($matches[1]) && !empty($matches[2]))
					{
						list($version) = explode(',', $matches[2]);
						$data[$matches[1]] = $rawFormat ? $matches[2] : $version;
					}
				}
			}
		} else {
			$statusCode = 200;
			$data = array_merge($this->yellow->plugins->getData(), $this->yellow->themes->getData());
		}
		return array($statusCode, $data);
	}
	
	// Return software file
	function getSoftwareFile($url)
	{
		$fileData = "";
		if(extension_loaded("curl"))
		{
			$urlRequest = $url;
			if(preg_match("#^https://github.com/(.+)/raw/(.+)$#", $url, $matches))
			{
				$urlRequest = "https://raw.githubusercontent.com/".$matches[1]."/".$matches[2];
			}
			$curlHandle = curl_init();
			curl_setopt($curlHandle, CURLOPT_URL, $urlRequest);
			curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowCore/".YellowCore::VERSION).")";
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
			$rawData = curl_exec($curlHandle);
			$statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
			curl_close($curlHandle);
			if($statusCode==200)
			{
				$fileData = $rawData;
			} else if($statusCode==0) {
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't connect to server!");
			} else {
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't download file '$url'!");
			}
			if(defined("DEBUG") && DEBUG>=3) echo "YellowUpdate::getSoftwareFile status:$statusCode url:$url<br/>\n";
		} else {
			$statusCode = 500;
			$this->yellow->page->error($statusCode, "Plugin 'update' requires cURL library!");
		}
		return array($statusCode, $fileData);
	}
	
	// Check if software installation is pending
	function isSoftwarePending()
	{
		$path = $this->yellow->config->get("pluginDir");
		$foundPlugins = count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false))>0;
		$path = $this->yellow->config->get("themeDir");
		$foundThemes = count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false))>0;
		return $foundPlugins || $foundThemes;
	}

	// Check if software exists
	function isSoftwareExisting($software)
	{
		$data = array_merge($this->yellow->plugins->getData(), $this->yellow->themes->getData());
		return !is_null($data[$software]);
	}

	// Check if installation mode
	function isInstallationMode()
	{
		return $this->yellow->config->get("installationMode") && PHP_SAPI!="cli";
	}
}
	
$yellow->plugins->register("update", "YellowUpdate", YellowUpdate::VERSION, 1);
?>