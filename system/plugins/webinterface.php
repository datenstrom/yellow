<?php
// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Web interface plugin
class YellowWebinterface
{
	const Version = "0.6.4";
	var $yellow;				//access to API
	var $active;				//web interface is active? (boolean)
	var $userLoginFailed;		//web interface login failed? (boolean)
	var $userPermission;		//web interface can change page? (boolean)
	var $users;					//web interface users
	var $merge;					//web interface merge
	var $rawDataSource;			//raw data of page for comparison
	var $rawDataEdit;			//raw data of page for editing

	// Handle initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->users = new YellowUsers($yellow);
		$this->merge = new YellowMerge($yellow);
		$this->yellow->config->setDefault("webinterfaceServerScheme", $this->yellow->config->get("serverScheme"));
		$this->yellow->config->setDefault("webinterfaceServerName", $this->yellow->config->get("serverName"));
		$this->yellow->config->setDefault("webinterfaceLocation", "/edit/");
		$this->yellow->config->setDefault("webinterfaceUserHashAlgorithm", "bcrypt");
		$this->yellow->config->setDefault("webinterfaceUserHashCost", "10");
		$this->yellow->config->setDefault("webinterfaceUserHome", "/");
		$this->yellow->config->setDefault("webinterfaceUserFile", "user.ini");
		$this->yellow->config->setDefault("webinterfaceNewFile", "page-new-(.*).txt");
		$this->yellow->config->setDefault("webinterfaceMetaFilePrefix", "published");
		$this->users->load($this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile"));
	}

	// Handle request
	function onRequest($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->checkRequest($location))
		{
			list($serverScheme, $serverName, $base, $location, $fileName) = $this->updateRequestInformation();
			$statusCode = $this->processRequest($serverScheme, $serverName, $base, $location, $fileName);
		} else {
			$activeLocation = $this->yellow->config->get("webinterfaceLocation");
			if(rtrim($location, '/') == rtrim($activeLocation, '/'))
			{
				$statusCode = 301;
				$location = $this->yellow->lookup->normaliseUrl(
					$this->yellow->config->get("webinterfaceServerScheme"),
					$this->yellow->config->get("webinterfaceServerName"), $base, $activeLocation);
				$this->yellow->sendStatus($statusCode, $location);
			}
		}
		return $statusCode;
	}
	
	// Handle page meta data parsing
	function onParseMeta($page)
	{
		if($this->isActive() && $this->isUser())
		{
			if($page == $this->yellow->page)
			{
				if(empty($this->rawDataSource)) $this->rawDataSource = $page->rawData;
				if(empty($this->rawDataEdit)) $this->rawDataEdit = $page->rawData;
				if($page->statusCode == 424)
				{
					$title = $this->yellow->toolbox->createTextTitle($page->location);
					$this->rawDataEdit = $this->getRawDataNew($title);
				}
			}
		}
	}
	
	// Handle page content parsing of custom block
	function onParseContentBlock($page, $name, $text, $shortcut)
	{
		$output = NULL;
		if($name=="edit" && $shortcut)
		{
			$editText = "$name $text";
			if(substru($text, 0, 2)=="- ") $editText = trim(substru($text, 2));
			$output = "<a href=\"".$page->get("pageEdit")."\">".htmlspecialchars($editText)."</a>";
		}
		return $output;
	}
	
	// Handle page extra HTML data
	function onExtra($name)
	{
		$output = NULL;
		if($this->isActive() && $name=="header")
		{
			if($this->users->getNumber())
			{
				$location = $this->yellow->config->get("serverBase").$this->yellow->config->get("pluginLocation")."webinterface";
				$output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"".htmlspecialchars($location).".css\" />\n";
				$output .= "<script type=\"text/javascript\" src=\"".htmlspecialchars($location).".js\"></script>\n";
				$output .= "<script type=\"text/javascript\">\n";
				$output .= "// <![CDATA[\n";
				$output .= "yellow.page = ".json_encode($this->getPageData()).";\n";
				$output .= "yellow.config = ".json_encode($this->getConfigData()).";\n";
				$output .= "yellow.text = ".json_encode($this->getTextData()).";\n";
				if(defined("DEBUG") && DEBUG>=1) $output .= "yellow.debug = ".json_encode(DEBUG).";\n";
				$output .= "// ]]>\n";
				$output .= "</script>\n";
			}
		}
		return $output;
	}
	
	// Handle command
	function onCommand($args)
	{
		list($name, $command) = $args;		
		switch($command)
		{
			case "user":	$statusCode = $this->userCommand($args); break;
			default:		$statusCode = 0;
		}
		return $statusCode;
	}
	
	// Handle command help
	function onCommandHelp()
	{
		return "user [EMAIL PASSWORD NAME LANGUAGE STATUS HOME]\n";
	}
	
	// Update user account
	function userCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $email, $password, $name, $language, $status, $home) = $args;
		if(!empty($email) && !empty($password))
		{
			$fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
			$algorithm = $this->yellow->config->get("webinterfaceUserHashAlgorithm");
			$cost = $this->yellow->config->get("webinterfaceUserHashCost");
			$hash = $this->yellow->toolbox->createHash($password, $algorithm, $cost);
			if(empty($hash))
			{
				$statusCode = 500;
				echo "ERROR creating hash: Algorithm '$algorithm' not supported!\n";
			} else {
				$statusCode = $this->users->createUser($fileName, $email, $hash, $name, $language, $status, $home) ? 200 : 500;
				if($statusCode != 200) echo "ERROR updating configuration: Can't write file '$fileName'!\n";
			}
			echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "");
			echo ($this->users->isExisting($email) ? "updated" : "created")."\n";
		} else {
			$statusCode = 200;
			foreach($this->getUserData() as $line) echo "$line\n";
			if(!$this->users->getNumber()) echo "Yellow $command: No user accounts\n";
		}
		return $statusCode;
	}
	
	// Process request
	function processRequest($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->checkUser($location, $fileName))
		{
			switch($_POST["action"])
			{
				case "":		$statusCode = $this->processRequestShow($serverScheme, $serverName, $base, $location, $fileName); break;
				case "create":	$statusCode = $this->processRequestCreate($serverScheme, $serverName, $base, $location, $fileName); break;
				case "edit":	$statusCode = $this->processRequestEdit($serverScheme, $serverName, $base, $location, $fileName); break;
				case "delete":	$statusCode = $this->processRequestDelete($serverScheme, $serverName, $base, $location, $fileName); break;
				case "login":	$statusCode = $this->processRequestLogin($serverScheme, $serverName, $base, $location, $fileName); break;
				case "logout":	$statusCode = $this->processRequestLogout($serverScheme, $serverName, $base, $location, $fileName); break;
			}
		}
		if($statusCode == 0)
		{
			$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
			if($this->users->getNumber())
			{
				if($this->userLoginFailed) $this->yellow->page->error(500, "Login failed, [please log in](javascript:yellow.action('login');)!");
			} else {
				$url = $this->yellow->text->get("webinterfaceUserAccountUrl");
				$this->yellow->page->error(500, "You are not authorised on this server, [please add a user account]($url)!");
			}
		}
		return $statusCode;
	}
	
	// Process request to show page
	function processRequestShow($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if(is_readable($fileName))
		{
			$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
		} else {
			if($this->yellow->isRequestContentDirectory($location))
			{
				$statusCode = 301;
				$location = $this->yellow->lookup->isFileLocation($location) ? "$location/" : "/".$this->yellow->getRequestLanguage()."/";
				$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->yellow->sendStatus($statusCode, $location);
			} else {
				$statusCode = $this->userPermission ? 424 : 404;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
				$this->yellow->page->error($statusCode);
			}
		}
		return $statusCode;
	}

	// Process request to create page
	function processRequestCreate($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->userPermission && !empty($_POST["rawdataedit"]))
		{
			$this->rawDataSource = $this->rawDataEdit = rawurldecode($_POST["rawdatasource"]);
			$page = $this->getPageNew($serverScheme, $serverName, $base, $location, $fileName, rawurldecode($_POST["rawdataedit"]));
			if(!$page->isError())
			{
				if($this->yellow->toolbox->createFile($page->fileName, $page->rawData))
				{
					$statusCode = 303;
					$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $page->location);
					$this->yellow->sendStatus($statusCode, $location);
				} else {
					$statusCode = 500;
					$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
					$this->yellow->page->error($statusCode, "Can't write file '$page->fileName'!");
				}
			} else {
				$statusCode = 500;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $false);
				$this->yellow->page->error($statusCode, $page->get("pageError"));
			}
		}
		return $statusCode;
	}
	
	// Process request to edit page
	function processRequestEdit($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->userPermission && !empty($_POST["rawdataedit"]))
		{
			$this->rawDataSource = rawurldecode($_POST["rawdatasource"]);
			$this->rawDataEdit = rawurldecode($_POST["rawdataedit"]);
			$page = $this->getPageUpdate($serverScheme, $serverName, $base, $location, $fileName,
				$this->rawDataSource, $this->rawDataEdit, $this->yellow->toolbox->readFile($fileName));
			if(!$page->isError())
			{
				if($this->yellow->toolbox->renameFile($fileName, $page->fileName) &&
				   $this->yellow->toolbox->createFile($page->fileName, $page->rawData))
				{
					$statusCode = 303;
					$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $page->location);
					$this->yellow->sendStatus($statusCode, $location);
				} else {
					$statusCode = 500;
					$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
					$this->yellow->page->error($statusCode, "Can't write file '$page->fileName'!");
				}
			} else {
				$statusCode = 500;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
				$this->yellow->page->error($statusCode, $page->get("pageError"));
			}
		}
		return $statusCode;
	}

	// Process request to delete page
	function processRequestDelete($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->userPermission)
		{
			$this->rawDataSource = $this->rawDataEdit = rawurldecode($_POST["rawdatasource"]);
			if(!is_file($fileName) || $this->yellow->toolbox->deleteFile($fileName))
			{
				$statusCode = 303;
				$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->yellow->sendStatus($statusCode, $location);
			} else {
				$statusCode = 500;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
				$this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
			}
		}
		return $statusCode;
	}

	// Process request for user login
	function processRequestLogin($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		$home = $this->users->getHome();
		if(substru($location, 0, strlenu($home)) == $home)
		{
			$statusCode = 303;
			$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
			$this->yellow->sendStatus($statusCode, $location);
		} else {
			$statusCode = 302;
			$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $home);
			$this->yellow->sendStatus($statusCode, $location);
		}
		return $statusCode;
	}

	// Process request for user logout
	function processRequestLogout($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 302;
		$this->users->destroyCookie("login");
		$this->users->email = "";
		$location = $this->yellow->lookup->normaliseUrl(
			$this->yellow->config->get("serverScheme"),
			$this->yellow->config->get("serverName"),
			$this->yellow->config->get("serverBase"), $location);
		$this->yellow->sendStatus($statusCode, $location);
		return $statusCode;
	}

	// Check web interface request
	function checkRequest($location)
	{
		if($this->yellow->toolbox->getServerScheme()==$this->yellow->config->get("webinterfaceServerScheme") &&
		   $this->yellow->toolbox->getServerName()==$this->yellow->config->get("webinterfaceServerName"))
		{
			$locationLength = strlenu($this->yellow->config->get("webinterfaceLocation"));
			$this->active = substru($location, 0, $locationLength) == $this->yellow->config->get("webinterfaceLocation");
		}
		return $this->isActive();
	}
	
	// Check web interface user
	function checkUser($location, $fileName)
	{
		if($_POST["action"] == "login")
		{
			$email = $_POST["email"];
			$password = $_POST["password"];
			if($this->users->checkUser($email, $password))
			{
				$this->users->createCookie("login", $email);
				$this->users->email = $email;
				$this->userPermission = $this->getUserPermission($location, $fileName);
			} else {
				$this->userLoginFailed = true;
			}
		} else if(isset($_COOKIE["login"])) {
			list($email, $session) = $this->users->getCookieInformation($_COOKIE["login"]);
			if($this->users->checkCookie($email, $session))
			{
				$this->users->email = $email;
				$this->userPermission = $this->getUserPermission($location, $fileName);
			} else {
				$this->userLoginFailed = true;
			}
		}
		return $this->isUser();
	}

	// Return permission to change page
	function getUserPermission($location, $fileName)
	{
		$userPermission = NULL;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onUserPermission"))
			{
				$userPermission = $value["obj"]->onUserPermission($location, $fileName, $this->users);
				if(!is_null($userPermission)) break;
			}
		}
		if(is_null($userPermission))
		{
			$userPermission = is_dir(dirname($fileName)) && strlenu(basename($fileName))<128;
			$userPermission &= substru($location, 0, strlenu($this->users->getHome())) == $this->users->getHome();
		}
		return $userPermission;
	}
	
	// Return user data
	function getUserData()
	{
		$data = array();
		foreach($this->users->users as $key=>$value)
		{
			$data[$key] = "$value[email] - $value[name] $value[language] $value[status] $value[home]";
		}
		usort($data, strnatcasecmp);
		return $data;
	}
	
	// Update request information
	function updateRequestInformation()
	{
		$serverScheme = $this->yellow->config->get("webinterfaceServerScheme");
		$serverName = $this->yellow->config->get("webinterfaceServerName");
		$base = rtrim($this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation"), '/');
		$this->yellow->page->base = $base;
		return $this->yellow->getRequestInformation($serverScheme, $serverName, $base);
	}
	
	// Update page data with title
	function updateDataTitle($rawData, $title)
	{
		foreach($this->yellow->toolbox->getTextLines($rawData) as $line)
		{
			if(preg_match("/^(\s*Title\s*:\s*)(.*?)(\s*)$/i", $line, $matches)) $line = $matches[1].$title.$matches[3];
			$rawDataNew .= $line;
		}
		return $rawDataNew;
	}
	
	// Return page data title
	function getDataTitle($rawData)
	{
		$title = $this->yellow->page->get("title");
		if(preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)[\r\n]+\-\-\-[\r\n]+/s", $rawData))
		{
			foreach($this->yellow->toolbox->getTextLines($rawData) as $line)
			{
				if(preg_match("/^(\s*Title\s*:\s*)(.*?)(\s*)$/i", $line, $matches)) { $title = $matches[2]; break; }
			}
		}
		return $title;
	}
	
	// Return new page
	function getPageNew($serverScheme, $serverName, $base, $location, $fileName, $rawData)
	{
		$page = new YellowPage($this->yellow);
		$page->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
		$page->parseData($rawData, false, 0);
		if($this->yellow->lookup->isFileLocation($location) || is_file($fileName))
		{
			$page->fileName = $this->yellow->lookup->findFileFromTitle(
				$page->get($this->yellow->config->get("webinterfaceMetaFilePrefix")), $page->get("title"), $fileName,
				$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
			$page->location = $this->yellow->lookup->findLocationFromFile($page->fileName);
			if($this->yellow->pages->find($page->location))
			{
				preg_match("/^(.*?)(\d*)$/", $page->get("title"), $matches);
				$titleText = $matches[1];
				$titleNumber = $matches[2];
				if(strempty($titleNumber)) { $titleNumber = 2; $titleText = $titleText.' '; }
				for(; $titleNumber<=999; ++$titleNumber)
				{
					$page->rawData = $this->updateDataTitle($rawData, $titleText.$titleNumber);
					$page->fileName = $this->yellow->lookup->findFileFromTitle(
						$page->get($this->yellow->config->get("webinterfaceMetaFilePrefix")), $titleText.$titleNumber, $fileName,
						$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
					$page->location = $this->yellow->lookup->findLocationFromFile($page->fileName);
					if(!$this->yellow->pages->find($page->location)) { $ok = true; break; }
				}
				if(!$ok) $page->error(500, "Page '".$page->get("title")."' can not be created!");
			}
		}
		if(!$this->getUserPermission($page->location, $page->fileName)) $page->error(500, "Page '".$page->get("title")."' is not allowed!");
		return $page;
	}
	
	// Return modified page
	function getPageUpdate($serverScheme, $serverName, $base, $location, $fileName, $rawDataSource, $rawDataEdit, $rawDataFile)
	{
		$page = new YellowPage($this->yellow);
		$page->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
		$page->parseData($this->merge->merge($rawDataSource, $rawDataEdit, $rawDataFile), false, 0);
		if(empty($page->rawData)) $page->error(500, "Page has been modified by someone else!");
		if($this->yellow->lookup->isFileLocation($location) && !$page->isError())
		{
			$pageSource = new YellowPage($this->yellow);
			$pageSource->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
			$pageSource->parseData($rawDataSource, false, 0);
			$prefix = $this->yellow->config->get("webinterfaceMetaFilePrefix");
			if($pageSource->get($prefix)!=$page->get($prefix) || $pageSource->get("title")!=$page->get("title"))
			{
				$page->fileName = $this->yellow->lookup->findFileFromTitle(
					$page->get($prefix), $page->get("title"), $fileName,
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
				$page->location = $this->yellow->lookup->findLocationFromFile($page->fileName);
				if($pageSource->location != $page->location)
				{
					if(!$this->yellow->lookup->isFileLocation($page->location))
					{
						$page->error(500, "Page '".$page->get("title")."' is not allowed!");
					} else if($this->yellow->pages->find($page->location)) {
						$page->error(500, "Page '".$page->get("title")."' already exists!");
					}
				}
			}
		}
		if(!$this->getUserPermission($page->location, $page->fileName)) $page->error(500, "Page '".$page->get("title")."' is not allowed!");
		return $page;
	}
	
	// Return raw data for new page
	function getRawDataNew($title = "")
	{
		$fileName = $this->yellow->lookup->findFileFromLocation($this->yellow->page->location);
		$fileName = $this->yellow->lookup->findFileNew($fileName,
			$this->yellow->config->get("webinterfaceNewFile"), $this->yellow->config->get("configDir"),
			$this->yellow->config->get("template"));
		$fileData = $this->yellow->toolbox->readFile($fileName);
		$fileData = preg_replace("/@datetime/i", date("Y-m-d H:i:s"), $fileData);
		$fileData = preg_replace("/@date/i", date("Y-m-d"), $fileData);
		$fileData = preg_replace("/@username/i", $this->users->getName(), $fileData);
		$fileData = preg_replace("/@userlanguage/i", $this->users->getLanguage(), $fileData);
		if(!empty($title)) $fileData = $this->updateDataTitle($fileData, $title);
		return $fileData;
	}
	
	// Return page data including webinterface information
	function getPageData()
	{
		$data = array();
		if($this->isUser())
		{
			$data["title"] = $this->getDataTitle($this->rawDataEdit);
			$data["rawDataSource"] = $this->rawDataSource;
			$data["rawDataEdit"] = $this->rawDataEdit;
			$data["rawDataNew"] = $this->getRawDataNew();
			$data["userPermission"] = $this->userPermission;
			$data["pageFile"] = $this->yellow->page->get("pageFile");
			$data["parserSafeMode"] = $this->yellow->page->parserSafeMode;
			$data["statusCode"] = $this->yellow->page->statusCode;
		}
		return $data;
	}
	
	// Return configuration data including user information
	function getConfigData()
	{
		$data = $this->yellow->config->getData("", "Location");
		if($this->isUser())
		{
			$data["userEmail"] = $this->users->email;
			$data["userName"] = $this->users->getName();
			$data["userLanguage"] = $this->users->getLanguage();
			$data["userStatus"] = $this->users->getStatus();
			$data["userHome"] = $this->users->getHome();
			$data["serverScheme"] = $this->yellow->config->get("serverScheme");
			$data["serverName"] = $this->yellow->config->get("serverName");
			$data["serverBase"] = $this->yellow->config->get("serverBase");
			$data["serverTime"] = $this->yellow->config->get("serverTime");
			$data["serverLanguages"] = $this->yellow->text->getLanguages();
		} else {
			$data["login"] = $this->yellow->page->statusCode==200;
			$data["loginEmail"] = $this->yellow->config->get("loginEmail");
			$data["loginPassword"] = $this->yellow->config->get("loginPassword");
		}
		return $data;
	}
	
	// Return text strings
	function getTextData()
	{
		$language = $this->isUser() ? $this->users->getLanguage() : $this->yellow->page->get("language");
		if(!$this->yellow->text->isLanguage($language)) $language = $this->yellow->config->get("language");
		$textLanguage = array_merge($this->yellow->text->getData("language", $language));
		$textWebinterface = array_merge($this->yellow->text->getData("webinterface", $language));
		$textYellow = array_merge($this->yellow->text->getData("yellow", $language));
		return array_merge($textLanguage, $textWebinterface, $textYellow);
	}
	
	// Check if web interface request
	function isActive()
	{
		return $this->active;
	}
	
	// Check if user is logged in
	function isUser()
	{
		return !empty($this->users->email);
	}
}

// Yellow users
class YellowUsers
{
	var $yellow;	//access to API
	var $users;		//registered users
	var $email;		//current user
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->users = array();
	}

	// Load users from file
	function load($fileName)
	{
		if(defined("DEBUG") && DEBUG>=2) echo "YellowUsers::load file:$fileName<br/>\n";
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			if(preg_match("/^\#/", $line)) continue;
			preg_match("/^(.*?)\s*:\s*(.*?),\s*(.*?),\s*(.*?),\s*(.*?),\s*(.*?)\s*$/", $line, $matches);
			if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]) &&
			   !empty($matches[5]) && !empty($matches[6]))
			{
				$this->set($matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]);
				if(defined("DEBUG") && DEBUG>=3) echo "YellowUsers::load email:$matches[1]<br/>\n";
			}
		}
	}
	
	// Set user data
	function set($email, $hash, $name, $language, $status, $home)
	{
		$this->users[$email] = array();
		$this->users[$email]["email"] = $email;
		$this->users[$email]["hash"] = $hash;
		$this->users[$email]["name"] = $name;
		$this->users[$email]["language"] = $language;
		$this->users[$email]["status"] = $status;
		$this->users[$email]["home"] = $home;
	}
	
	// Create or update user in file
	function createUser($fileName, $email, $hash, $name, $language, $status, $home)
	{
		$email = strreplaceu(',', '-', $email);
		$hash = strreplaceu(',', '-', $hash);
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			preg_match("/^(.*?)\s*:\s*(.*?),\s*(.*?),\s*(.*?),\s*(.*?),\s*(.*?)\s*$/", $line, $matches);
			if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]) &&
			   !empty($matches[5]) && !empty($matches[6]))
			{
				if($matches[1] == $email)
				{
					$name = strreplaceu(',', '-', empty($name) ? $matches[3] : $name);
					$language = strreplaceu(',', '-', empty($language) ? $matches[4] : $language);
					$status = strreplaceu(',', '-', empty($status) ? $matches[5] : $status);
					$home = strreplaceu(',', '-', empty($home) ? $matches[6] : $home);
					$fileDataNew .= "$email: $hash,$name,$language,$status,$home\n";
					$found = true;
					continue;
				}
			}
			$fileDataNew .= $line;
		}
		if(!$found)
		{
			$name = strreplaceu(',', '-', empty($name) ? $this->yellow->config->get("sitename") : $name);
			$language = strreplaceu(',', '-', empty($language) ? $this->yellow->config->get("language") : $language);
			$status = strreplaceu(',', '-', empty($status) ? "active" : $status);
			$home = strreplaceu(',', '-', empty($home) ? $this->yellow->config->get("webinterfaceUserHome") : $home);
			$fileDataNew .= "$email: $hash,$name,$language,$status,$home\n";
		}
		return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
	}

	// Check user login
	function checkUser($email, $password)
	{
		$algorithm = $this->yellow->config->get("webinterfaceUserHashAlgorithm");
		return $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
			$this->yellow->toolbox->verifyHash($password, $algorithm, $this->users[$email]["hash"]);
	}

	// Create browser cookie
	function createCookie($cookieName, $email)
	{
		if($this->isExisting($email))
		{
			$serverScheme = $this->yellow->config->get("webinterfaceServerScheme");
			$location = $this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation");
			$session = $this->yellow->toolbox->createHash($this->users[$email]["hash"], "sha256");
			if(empty($session)) $session = "error-hash-algorithm-sha256";
			setcookie($cookieName, "$email,$session", time()+60*60*24*30*365, $location, "", $serverScheme=="https");
		}
	}
	
	// Destroy browser cookie
	function destroyCookie($cookieName)
	{
		$serverScheme = $this->yellow->config->get("webinterfaceServerScheme");
		$location = $this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation");
		setcookie($cookieName, "", time()-3600, $location, "", $serverScheme=="https");
	}
	
	// Return information from browser cookie
	function getCookieInformation($cookie)
	{
		return explode(',', $cookie, 2);
	}
	
	// Check user login from browser cookie
	function checkCookie($email, $session)
	{
		return $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
			$this->yellow->toolbox->verifyHash($this->users[$email]["hash"], "sha256", $session);
	}
	
	// Retun user login information
	function getUserInfo($email, $password, $name, $language, $home)
	{
		$algorithm = $this->yellow->config->get("webinterfaceUserHashAlgorithm");
		$cost = $this->yellow->config->get("webinterfaceUserHashCost");
		$hash = $this->yellow->toolbox->createHash($password, $algorithm, $cost);
		if(!empty($hash))
		{
			$email = strreplaceu(',', '-', $email);
			$hash = strreplaceu(',', '-', $hash);
			$name = strreplaceu(',', '-', empty($name) ? $this->yellow->config->get("sitename") : $name);
			$language = strreplaceu(',', '-', empty($language) ? $this->yellow->config->get("language") : $language);
			$status = strreplaceu(',', '-', empty($status) ? "active" : $status);
			$home = strreplaceu(',', '-', empty($home) ? $this->yellow->config->get("webinterfaceUserHome") : $home);
			$user = "$email: $hash,$name,$language,$status,$home\n";
		}
		return $user;
	}
	
	// Return user name
	function getName($email = "")
	{
		if(empty($email)) $email = $this->email;
		return $this->isExisting($email) ? $this->users[$email]["name"] : "";
	}

	// Return user language
	function getLanguage($email = "")
	{
		if(empty($email)) $email = $this->email;
		return $this->isExisting($email) ? $this->users[$email]["language"] : "";
	}	
	
	// Return user status
	function getStatus($email = "")
	{
		if(empty($email)) $email = $this->email;
		return $this->isExisting($email) ? $this->users[$email]["status"] : "";
	}
	
	// Return user home
	function getHome($email = "")
	{
		if(empty($email)) $email = $this->email;
		return $this->isExisting($email) ? $this->users[$email]["home"] : "";
	}
	
	// Return number of users
	function getNumber()
	{
		return count($this->users);
	}
	
	// Check if user exists
	function isExisting($email)
	{
		return !is_null($this->users[$email]);
	}
}
	
// Yellow merge
class YellowMerge
{
	var $yellow;		//access to API
	const Add = '+';	//merge types
	const Modify = '*';
	const Remove = '-';
	const Same = ' ';
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
	}
	
	// Merge text, NULL if not possible
	function merge($textSource, $textMine, $textYours, $showDiff = false)
	{
		if($textMine != $textYours)
		{
			$diffMine = $this->buildDiff($textSource, $textMine);
			$diffYours = $this->buildDiff($textSource, $textYours);
			$diff = $this->mergeDiff($diffMine, $diffYours);
			$output = $this->getOutput($diff, $showDiff);
		} else {
			$output = $textMine;
		}
		return $output;
	}
	
	// Build differences to common source
	function buildDiff($textSource, $textOther)
	{
		$diff = array();
		$lastRemove = -1;
		$textStart = 0;
		$textSource = $this->yellow->toolbox->getTextLines($textSource);
		$textOther = $this->yellow->toolbox->getTextLines($textOther);
		$sourceEnd = $sourceSize = count($textSource);
		$otherEnd = $otherSize = count($textOther);
		while($textStart<$sourceEnd && $textStart<$otherEnd && $textSource[$textStart]==$textOther[$textStart]) ++$textStart;
		while($textStart<$sourceEnd && $textStart<$otherEnd && $textSource[$sourceEnd-1]==$textOther[$otherEnd-1])
		{
			--$sourceEnd; --$otherEnd;
		}
		for($pos=0; $pos<$textStart; ++$pos) array_push($diff, array(YellowMerge::Same, $textSource[$pos], false));
		$lcs = $this->buildDiffLCS($textSource, $textOther, $textStart, $sourceEnd-$textStart, $otherEnd-$textStart);
		for($x=0,$y=0,$xEnd=$otherEnd-$textStart,$yEnd=$sourceEnd-$textStart; $x<$xEnd || $y<$yEnd;)
		{
			$max = $lcs[$y][$x];
			if($y<$yEnd && $lcs[$y+1][$x]==$max)
			{
				array_push($diff, array(YellowMerge::Remove, $textSource[$textStart+$y], false));
				if($lastRemove == -1) $lastRemove = count($diff)-1;
				++$y;
				continue;
			}
			if($x<$xEnd && $lcs[$y][$x+1]==$max)
			{
				if($lastRemove==-1 || $diff[$lastRemove][0]!=YellowMerge::Remove)
				{
					array_push($diff, array(YellowMerge::Add, $textOther[$textStart+$x], false));
					$lastRemove = -1;
				} else {
					$diff[$lastRemove] = array(YellowMerge::Modify, $textOther[$textStart+$x], false);
					++$lastRemove; if(count($diff)==$lastRemove) $lastRemove = -1;
				}
				++$x;
				continue;
			}
			array_push($diff, array(YellowMerge::Same, $textSource[$textStart+$y], false));
			$lastRemove = -1;
			++$x;
			++$y;
		}
		for($pos=$sourceEnd;$pos<$sourceSize; ++$pos) array_push($diff, array(YellowMerge::Same, $textSource[$pos], false));
		return $diff;
	}
	
	// Build longest common subsequence
	function buildDiffLCS($textSource, $textOther, $textStart, $yEnd, $xEnd)
	{
		$lcs = array_fill(0, $yEnd+1, array_fill(0, $xEnd+1, 0));
		for($y=$yEnd-1; $y>=0; --$y)
		{
			for($x=$xEnd-1; $x>=0; --$x)
			{
				if($textSource[$textStart+$y] == $textOther[$textStart+$x])
				{
					$lcs[$y][$x] = $lcs[$y+1][$x+1]+1;
				} else {
					$lcs[$y][$x] = max($lcs[$y][$x+1], $lcs[$y+1][$x]);
				}
			}
		}
		return $lcs;
	}
	
	// Merge differences
	function mergeDiff($diffMine, $diffYours)
	{
		$diff = array();
		$posMine = $posYours = 0;
		while($posMine<count($diffMine) && $posYours<count($diffYours))
		{
			$typeMine = $diffMine[$posMine][0];
			$typeYours = $diffYours[$posYours][0];
			if($typeMine==YellowMerge::Same)
			{
				array_push($diff, $diffYours[$posYours]);
			} else if($typeYours==YellowMerge::Same) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeMine==YellowMerge::Add && $typeYours==YellowMerge::Add) {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
			} else if($typeMine==YellowMerge::Modify && $typeYours==YellowMerge::Modify) {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
			} else if($typeMine==YellowMerge::Remove && $typeYours==YellowMerge::Remove) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeMine==YellowMerge::Add) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeYours==YellowMerge::Add) {
				array_push($diff, $diffYours[$posYours]);
			} else {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], true);
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
			if($typeMine==YellowMerge::Add || $typeYours==YellowMerge::Add)
			{
				if($typeMine==YellowMerge::Add) ++$posMine;
				if($typeYours==YellowMerge::Add) ++$posYours;
			} else {
				++$posMine;
				++$posYours;
			}
		}
		for(;$posMine<count($diffMine); ++$posMine)
		{
			array_push($diff, $diffMine[$posMine]);
			$typeMine = $diffMine[$posMine][0]; $typeYours = ' ';
			if(defined("DEBUG") && DEBUG>=2) echo "YellowMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
		}
		for(;$posYours<count($diffYours); ++$posYours)
		{
			array_push($diff, $diffYours[$posYours]);
			$typeYours = $diffYours[$posYours][0]; $typeMine = ' ';
			if(defined("DEBUG") && DEBUG>=2) echo "YellowMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
		}
		return $diff;
	}
	
	// Merge potential conflict
	function mergeConflict(&$diff, $diffMine, $diffYours, $conflict)
	{
		if(!$conflict && $diffMine[1]==$diffYours[1])
		{
			array_push($diff, $diffMine);
		} else {
			array_push($diff, array($diffMine[0], $diffMine[1], true));
			array_push($diff, array($diffYours[0], $diffYours[1], true));
		}
	}
	
	// Return merged text, NULL if not possible
	function getOutput($diff, $showDiff = false)
	{
		$output = "";
		if(!$showDiff)
		{
			for($i=0; $i<count($diff); ++$i)
			{
				if($diff[$i][0] != YellowMerge::Remove) $output .= $diff[$i][1];
				$conflict |= $diff[$i][2];
			}
		} else {
			for($i=0; $i<count($diff); ++$i)
			{
				$output .= $diff[$i][2] ? "! " : $diff[$i][0].' ';
				$output .= $diff[$i][1];
			}
		}
		return !$conflict ? $output : NULL;
	}
}

$yellow->plugins->register("webinterface", "YellowWebinterface", YellowWebinterface::Version);
?>