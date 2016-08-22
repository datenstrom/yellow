<?php
// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Update plugin
class YellowUpdate
{
	const VERSION = "0.6.10";
	var $yellow;					//access to API
	
	// Handle initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("updatePluginsUrl", "https://github.com/datenstrom/yellow-plugins");
		$this->yellow->config->setDefault("updateThemesUrl", "https://github.com/datenstrom/yellow-themes");
		$this->yellow->config->setDefault("updateVersionFile", "version.ini");
		$this->yellow->config->setDefault("updateInformationFile", "update.ini");
	}
	
	// Handle request
	function onRequest($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->isInstallation())
		{
			$statusCode = $this->processRequestInstallation($serverScheme, $serverName, $base, $location, $fileName);
		} else {
			$statusCode = $this->processRequestPending($serverScheme, $serverName, $base, $location, $fileName);
		}
		return $statusCode;
	}
	
	// Handle command
	function onCommand($args)
	{
		list($command) = $args;
		switch($command)
		{
			case "update":	$statusCode = $this->updateCommand($args); break;
			default:		$statusCode = $this->updateCommandPending($args); break;
		}
		return $statusCode;
	}
	
	// Handle command help
	function onCommandHelp()
	{
		return "update [FEATURE]";
	}
	
	// Update website
	function updateCommand($args)
	{
		list($command, $feature) = $args;
		list($statusCode, $data) = $this->getSoftwareUpdate($feature);
		if(!empty($data))
		{
			foreach($data as $key=>$value)
			{
				list($version) = explode(',', $value);
				echo "$key $version\n";
			}
			if($statusCode==200) $statusCode = $this->download($data);
			if($statusCode==200) $statusCode = $this->update();
			if($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
			echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated\n";
		} else {
			if($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
			echo "Yellow $command: No updates available\n";
		}
		return $statusCode;
	}
	
	// Update pending software
	function updateCommandPending($args)
	{
		$statusCode = 0;
		if($this->isSoftwarePending())
		{
			$statusCode = $this->update();
			if($statusCode!=0)
			{
				if($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
				echo "Yellow has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
			}
		}
		return $statusCode;
	}
	
	// Download available software
	function download($data)
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

	// Update downloaded software
	function update()
	{
		$statusCode = 0;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onUpdate"))
			{
				$statusCode = $value["obj"]->onUpdate($this->yellow->getRequestHandler());
				if($statusCode!=0) break;
			}
		}
		if($statusCode==0)
		{
			$path = $this->yellow->config->get("pluginDir");
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry)
			{
				if(defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::update file:$entry<br/>\n";
				$statusCode = max($statusCode, $this->updateSoftwareArchive($entry));
			}
			$path = $this->yellow->config->get("themeDir");
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry)
			{
				if(defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::update file:$entry<br/>\n";
				$statusCode = max($statusCode, $this->updateSoftwareArchive($entry));
			}
		}
		return $statusCode;
	}
	
	// Update software from archive
	function updateSoftwareArchive($path)
	{
		$statusCode = 0;
		$zip = new ZipArchive();
		if($zip->open($path)===true)
		{
			$fileNameInformation = $this->yellow->config->get("updateInformationFile");
			for($i=0; $i<$zip->numFiles; ++$i)
			{
				$fileName = $zip->getNameIndex($i);
				if(empty($pathBase))
				{
					preg_match("#^(.*\/).*?$#", $fileName, $matches);
					$pathBase = $matches[1];
				}
				if($fileName==$pathBase.$fileNameInformation)
				{
					$fileData = $zip->getFromIndex($i);
					break;
				}
			}
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				if(preg_match("/^\#/", $line)) continue;
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(lcfirst($matches[1])=="plugin" || lcfirst($matches[1])=="theme") $software = $matches[2];
				if(!empty($software) && !empty($matches[1]) && !empty($matches[2]))
				{
					list($fileName, $flags) = explode(',', $matches[2], 2);
					$fileData = $zip->getFromName($pathBase.$fileName);
					$metaData = $zip->statName($pathBase.$fileName);
					$modified = $metaData ? $metaData["mtime"] : 0;
					$statusCode = $this->updateSoftwareFile($matches[1], $fileData, $modified, $flags, $software);
					if($statusCode!=200) break;
				}
			}
			$zip->close();
			if($statusCode==200 && !$this->yellow->toolbox->deleteFile($path))
			{
				$statusCode = 500;
				$this->yellow->page->error($statusCode, "Can't delete file '$path'!");
			}
		}
		return $statusCode;
	}
	
	// Update software file
	function updateSoftwareFile($fileName, $fileData, $modified, $flags, $software)
	{
		$statusCode = 200;
		$fileName = $this->yellow->toolbox->normaliseTokens($fileName);
		if($this->yellow->lookup->isValidFile($fileName) && !empty($flags))
		{
			$create = $update = $delete = false;
			if(preg_match("/create/i", $flags) && !is_file($fileName) && !empty($fileData)) $create = true;
			if(preg_match("/update/i", $flags) && is_file($fileName) && !empty($fileData)) $update = true;
			if(preg_match("/delete/i", $flags) && is_file($fileName)) $delete = true;
			if(preg_match("/optional/i", $flags) && $this->isSoftware($software)) $create = $update = $delete = false;
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
			if(defined("DEBUG") && DEBUG>=3) echo "YellowUpdate::updateSoftwareFile file:$fileName flags:$flags<br/>\n";
		}
		return $statusCode;
	}
	
	// Update installation files
	function updateInstallation($feature)
	{
		$ok = true;
		$path = $this->yellow->config->get("pluginDir");
		$regex = "/^.*\\".$this->yellow->config->get("downloadExtension")."$/";
		foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
		{
			if(stristr(basename($entry), $feature))
			{
				if($this->updateSoftwareArchive($entry)!=200) $ok = false;
			}
		}
		if($ok)
		{
			foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
			{
				$this->yellow->toolbox->deleteFile($entry);
			}
		}
		return $ok;
	}
	
	// Process request to install pending software
	function processRequestPending($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->isContentFile($fileName) && $this->isSoftwarePending())
		{
			$statusCode = $this->update();
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
	function processRequestInstallation($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->isContentFile($fileName) && $this->isInstallation())
		{
			$this->yellow->pages->pages["root/"] = array();
			$this->yellow->page = new YellowPage($this->yellow);
			$this->yellow->page->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
			$this->yellow->page->parseData($this->getRawDataInstallation($this->yellow->getRequestLanguage()), false, 404);
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
					$status = $this->updateInstallation($feature) ? "ok" : "error";
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
	function getRawDataInstallation($language)
	{
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
			if(count($this->getFeatures())>1)
			{
				$rawData .= "<p>".$this->yellow->text->get("webinterfaceInstallationFeature")."<p>";
				foreach($this->getFeatures() as $feature)
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
	
	// Return configuration data for installation
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

	// Return installation features
	function getFeatures()
	{
		$data = array("website");
		$path = $this->yellow->config->get("pluginDir");
		$regex = "/^.*\\".$this->yellow->config->get("downloadExtension")."$/";
		foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry)
		{
			if(preg_match("/^(.*?)-(.*?)\./", $entry, $matches))
			{
				array_push($data, $matches[2]);
			}
		}
		return $data;
	}
	
	// Return software update
	function getSoftwareUpdate($feature)
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
			foreach($this->yellow->plugins->getData() as $key=>$value) $data[$key] = $value;
			foreach($this->yellow->themes->getData() as $key=>$value) $data[$key] = $value;
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
				$statusCode = 444;
				$this->yellow->page->error($statusCode, "No response from server!");
			} else {
				$this->yellow->page->error($statusCode, "Can't download file '$url'!");
			}
			if(defined("DEBUG") && DEBUG>=3) echo "YellowUpdate::getSoftwareFile status:$statusCode url:$url<br/>\n";
		} else {
			$statusCode = 500;
			$this->yellow->page->error($statusCode, "Plugin 'update' requires cURL library!");
		}
		return array($statusCode, $fileData);
	}
	
	// Check if software exists
	function isSoftware($software)
	{
		$data = $this->yellow->plugins->getData();
		return !is_null($data[$software]);
	}
	
	// Check if pending software exists
	function isSoftwarePending()
	{
		$path = $this->yellow->config->get("pluginDir");
		$foundPlugins = count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false))>0;
		$path = $this->yellow->config->get("themeDir");
		$foundThemes = count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false))>0;
		return $foundPlugins || $foundThemes;
	}
	
	// Check if installation requested
	function isInstallation()
	{
		return $this->yellow->config->get("installationMode") && PHP_SAPI!="cli";
	}

	// Check if content file
	function isContentFile($fileName)
	{
		$contentDirLength = strlenu($this->yellow->config->get("contentDir"));
		return substru($fileName, 0, $contentDirLength)==$this->yellow->config->get("contentDir");
	}
}
	
$yellow->plugins->register("update", "YellowUpdate", YellowUpdate::VERSION, 1);
?>