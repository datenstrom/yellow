<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Web interface core plugin
class YellowWebinterface
{
	const Version = "0.4.6";
	var $yellow;				//access to API
	var $active;				//web interface is active? (boolean)
	var $userLoginFailed;		//web interface login failed? (boolean)
	var $userPermission;		//web interface can modify page? (boolean)
	var $users;					//web interface users
	var $merge;					//web interface merge
	var $rawDataSource;			//raw data of page for comparison
	var $rawDataEdit;			//raw data of page for editing

	// Handle plugin initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->users = new YellowWebinterfaceUsers($yellow);
		$this->merge = new YellowWebinterfaceMerge($yellow);
		$this->yellow->config->setDefault("webinterfaceLocation", "/edit/");
		$this->yellow->config->setDefault("webinterfaceServerScheme", "http");
		$this->yellow->config->setDefault("webinterfaceServerName", $this->yellow->config->get("serverName"));
		$this->yellow->config->setDefault("webinterfaceUserHashAlgorithm", "bcrypt");
		$this->yellow->config->setDefault("webinterfaceUserHashCost", "10");
		$this->yellow->config->setDefault("webinterfaceUserFile", "user.ini");
		$this->yellow->config->setDefault("webinterfaceFilePrefix", "published");
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
				$locationHeader = $this->yellow->toolbox->getLocationHeader(
					$this->yellow->config->get("webinterfaceServerScheme"),
					$this->yellow->config->get("webinterfaceServerName"), $base, $activeLocation);
				$this->yellow->sendStatus($statusCode, false, $locationHeader);
			}
		}
		return $statusCode;
	}
	
	// Handle page meta data parsing
	function onParseMeta($page, $text)
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
					$this->rawDataEdit = $this->getDataNew($title);
				}
			}
		}
	}
	
	// Handle page content parsing
	function onParseContent($page, $text)
	{
		$output = NULL;
		if($this->isActive() && $this->isUser())
		{
			$serverBase = $this->yellow->config->get("serverBase");
			$activePath = trim($this->yellow->config->get("webinterfaceLocation"), '/');
			$callback = function($matches) use ($serverBase, $activePath)
			{
				$matches[2] = preg_replace("#^$serverBase/(?!$activePath)(.*)$#", "$serverBase/$activePath/$1", $matches[2]);
				return "<a$matches[1]href=\"$matches[2]\"$matches[3]>";
			};
			$output = preg_replace_callback("/<a(.*?)href=\"([^\"]+)\"(.*?)>/i", $callback, $text);
		}
		return $output;
	}
	
	// Handle page extra header
	function onHeaderExtra($page)
	{
		$header = "";
		if($this->isActive())
		{
			$location = $this->yellow->config->getHtml("serverBase").$this->yellow->config->getHtml("pluginLocation");
			$header .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$location}core-webinterface.css\" />\n";
			$header .= "<script type=\"text/javascript\" src=\"{$location}core-webinterface.js\"></script>\n";
			$header .= "<script type=\"text/javascript\">\n";
			$header .= "// <![CDATA[\n";
			if($this->isUser())
			{
				$header .= "yellow.page.userPermission = ".json_encode($this->userPermission).";\n";
				$header .= "yellow.page.rawDataSource = ".json_encode($this->rawDataSource).";\n";
				$header .= "yellow.page.rawDataEdit = ".json_encode($this->rawDataEdit).";\n";
				$header .= "yellow.page.rawDataNew = ".json_encode($this->getDataNew()).";\n";
				$header .= "yellow.page.parserSafeMode = ".json_encode($page->parserSafeMode).";\n";
				$header .= "yellow.page.statusCode = ".json_encode($page->statusCode).";\n";
			}
			$header .= "yellow.config = ".json_encode($this->getDataConfig()).";\n";
			$language = $this->isUser() ? $this->users->getLanguage() : $page->get("language");
			$header .= "yellow.text = ".json_encode($this->yellow->text->getData("webinterface", $language)).";\n";
			if(defined("DEBUG")) $header .= "yellow.debug = ".json_encode(DEBUG).";\n";
			$header .= "// ]]>\n";
			$header .= "</script>\n";
		}
		return $header;
	}
	
	// Handle command help
	function onCommandHelp()
	{
		return "user EMAIL PASSWORD [NAME LANGUAGE HOME]\n";
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
	
	// Create or update user account
	function userCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $email, $password, $name, $language, $home) = $args;
		if(!empty($email) && !empty($password) && (empty($home) || $home[0]=='/'))
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
				$statusCode = $this->users->createUser($fileName, $email, $hash, $name, $language, $home) ? 200 : 500;
				if($statusCode != 200) echo "ERROR updating configuration: Can't write file '$fileName'!\n";
			}
			echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "");
			echo ($this->users->isExisting($email) ? "updated" : "created")."\n";
		} else {
			echo "Yellow $command: Invalid arguments\n";
			$statusCode = 400;
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
			$statusCode = $this->userLoginFailed ? 401 : 0;
			$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, false);
		}
		return $statusCode;
	}
	
	// Process request to show page
	function processRequestShow($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if(is_readable($fileName))
		{
			$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, 0, false);
		} else {
			if($this->yellow->toolbox->isFileLocation($location) && $this->yellow->isContentDirectory("$location/"))
			{
				$statusCode = 301;
				$locationHeader = $this->yellow->toolbox->getLocationHeader($serverScheme, $serverName, $base, "$location/");
				$this->yellow->sendStatus($statusCode, false, $locationHeader);
			} else {
				$statusCode = $this->userPermission ? 424 : 404;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, false);
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
					$locationHeader = $this->yellow->toolbox->getLocationHeader($serverScheme, $serverName, $base, $page->location);
					$this->yellow->sendStatus($statusCode, false, $locationHeader);
				} else {
					$statusCode = 500;
					$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, false);
					$this->yellow->page->error($statusCode, "Can't write file '$page->fileName'!");
				}
			} else {
				$statusCode = 500;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, false);
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
				$this->rawDataSource, $this->rawDataEdit, $this->yellow->toolbox->getFileData($fileName));
			if(!$page->isError())
			{
				if($this->yellow->toolbox->renameFile($fileName, $page->fileName) &&
				   $this->yellow->toolbox->createFile($page->fileName, $page->rawData))
				{
					$statusCode = 303;
					$locationHeader = $this->yellow->toolbox->getLocationHeader($serverScheme, $serverName, $base, $page->location);
					$this->yellow->sendStatus($statusCode, false, $locationHeader);
				} else {
					$statusCode = 500;
					$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, false);
					$this->yellow->page->error($statusCode, "Can't write file '$page->fileName'!");
				}
			} else {
				$statusCode = 500;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, false);
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
				$locationHeader = $this->yellow->toolbox->getLocationHeader($serverScheme, $serverName, $base, $location);
				$this->yellow->sendStatus($statusCode, false, $locationHeader);
			} else {
				$statusCode = 500;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, false);
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
			$locationHeader = $this->yellow->toolbox->getLocationHeader($serverScheme, $serverName, $base, $location);
			$this->yellow->sendStatus($statusCode, false, $locationHeader);
		} else {
			$statusCode = 302;
			$locationHeader = $this->yellow->toolbox->getLocationHeader($serverScheme, $serverName, $base, $home);
			$this->yellow->sendStatus($statusCode, false, $locationHeader);
		}
		return $statusCode;
	}

	// Process request for user logout
	function processRequestLogout($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 302;
		$this->users->destroyCookie("login");
		$this->users->email = "";
		$locationHeader = $this->yellow->toolbox->getLocationHeader(
			$this->yellow->config->get("serverScheme"),
			$this->yellow->config->get("serverName"),
			$this->yellow->config->get("serverBase"), $location);
		$this->yellow->sendStatus($statusCode, false, $locationHeader);
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

	// Return permission to modify page
	function getUserPermission($location, $fileName)
	{
		$userPermission = true;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onUserPermission"))
			{
				$userPermission = $value["obj"]->onUserPermission($location, $fileName, $this->users);
				if(!$userPermission) break;
			}
		}
		$userPermission &= is_dir(dirname($fileName)) && strlenu(basename($fileName))<128;
		return $userPermission;
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
	
	// Return new page
	function getPageNew($serverScheme, $serverName, $base, $location, $fileName, $rawData)
	{
		$page = new YellowPage($this->yellow, $serverScheme, $serverName, $base, $location, $fileName);
		$page->parseData($rawData, 0, false);
		$page->fileName = $this->yellow->toolbox->findFileFromTitle(
			$page->get($this->yellow->config->get("webinterfaceFilePrefix")), $page->get("title"), $fileName,
			$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
		$page->location = $this->yellow->toolbox->findLocationFromFile(
			$page->fileName, $this->yellow->config->get("contentDir"),
			$this->yellow->config->get("contentRootDir"), $this->yellow->config->get("contentHomeDir"),
			$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
		if($this->yellow->pages->find($page->location))
		{
			preg_match("/^(.*?)(\d*)$/", $page->get("title"), $matches);
			$titleText = $matches[1];
			$titleNumber = $matches[2];
			if(strempty($titleNumber)) { $titleNumber = 2; $titleText = $titleText.' '; }
			for(; $titleNumber<=999; ++$titleNumber)
			{
				$page->rawData = $this->updateDataTitle($rawData, $titleText.$titleNumber);
				$page->fileName = $this->yellow->toolbox->findFileFromTitle(
					$page->get($this->yellow->config->get("webinterfaceFilePrefix")), $titleText.$titleNumber, $fileName,
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
				$page->location = $this->yellow->toolbox->findLocationFromFile(
					$page->fileName, $this->yellow->config->get("contentDir"),
					$this->yellow->config->get("contentRootDir"), $this->yellow->config->get("contentHomeDir"),
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
				if(!$this->yellow->pages->find($page->location)) { $ok = true; break; }
			}
			if(!$ok) $page->error(500, "Page '".$page->get("title")."' can not be created!");
		}
		if(!$this->getUserPermission($page->location, $page->fileName)) $page->error(500, "Page '".$page->get("title")."' is not allowed!");
		return $page;
	}
	
	// Return modified page
	function getPageUpdate($serverScheme, $serverName, $base, $location, $fileName, $rawDataSource, $rawDataEdit, $rawDataFile)
	{
		$page = new YellowPage($this->yellow, $serverScheme, $serverName, $base, $location, $fileName);
		$page->parseData($this->merge->merge($rawDataSource, $rawDataEdit, $rawDataFile), 0, false);
		if(empty($page->rawData)) $page->error(500, "Page has been modified by someone else!");
		if($this->yellow->toolbox->isFileLocation($location) && !$page->isError())
		{
			$pageSource = new YellowPage($this->yellow, $serverScheme, $serverName, $base, $location, $fileName);
			$pageSource->parseData($rawDataSource, 0, false);
			$prefix = $this->yellow->config->get("webinterfaceFilePrefix");
			if($pageSource->get($prefix)!=$page->get($prefix) || $pageSource->get("title")!=$page->get("title"))
			{
				$page->fileName = $this->yellow->toolbox->findFileFromTitle(
					$page->get($prefix), $page->get("title"), $fileName,
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
				$page->location = $this->yellow->toolbox->findLocationFromFile(
					$page->fileName, $this->yellow->config->get("contentDir"),
					$this->yellow->config->get("contentRootDir"), $this->yellow->config->get("contentHomeDir"),
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
				if($pageSource->location!=$page->location && $this->yellow->pages->find($page->location))
				{
					$page->error(500, "Page '".$page->get("title")."' already exists!");
				}
			}
		}
		if(!$this->getUserPermission($page->location, $page->fileName)) $page->error(500, "Page '".$page->get("title")."' is not allowed!");
		return $page;
	}
	
	// Return content data for new page
	function getDataNew($title = "")
	{
		$fileName = $this->yellow->toolbox->findFileFromLocation(
			$this->yellow->page->location, $this->yellow->config->get("contentDir"),
			$this->yellow->config->get("contentRootDir"), $this->yellow->config->get("contentHomeDir"),
			$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
		$fileName = $this->yellow->toolbox->findFileNew($fileName,
			$this->yellow->config->get("configDir"), $this->yellow->config->get("newPageFile"),
			$this->yellow->config->get("contentDefaultFile"));
		$fileData = $this->yellow->toolbox->getFileData($fileName);
		$fileData = preg_replace("/@date/i", date("Y-m-d"), $fileData);
		$fileData = preg_replace("/@username/i", $this->users->getName(), $fileData);
		$fileData = preg_replace("/@userlanguage/i", $this->users->getLanguage(), $fileData);
		if(!empty($title)) $fileData = $this->updateDataTitle($fileData, $title);
		return $fileData;
	}
	
	// Return configuration data including information of current user
	function getDataConfig()
	{
		$data = $this->yellow->config->getData("", "Location");
		if($this->isUser())
		{
			$data["userEmail"] = $this->users->email;
			$data["userName"] = $this->users->getName();
			$data["userLanguage"] = $this->users->getLanguage();
			$data["userHome"] = $this->users->getHome();
			$data["serverScheme"] = $this->yellow->config->get("serverScheme");
			$data["serverName"] = $this->yellow->config->get("serverName");
			$data["serverBase"] = $this->yellow->config->get("serverBase");
		} else {
			$data["loginEmail"] = $this->yellow->config->get("loginEmail");
			$data["loginPassword"] = $this->yellow->config->get("loginPassword");
		}
		return $data;
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

// Yellow web interface users
class YellowWebinterfaceUsers
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
		$fileData = @file($fileName);
		if($fileData)
		{
			foreach($fileData as $line)
			{
				if(preg_match("/^\//", $line)) continue;
				preg_match("/^(.*?),\s*(.*?),\s*(.*?),\s*(.*?),\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]))
				{
					$this->set($matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
					if(defined("DEBUG") && DEBUG>=3) echo "YellowWebinterfaceUsers::load email:$matches[1] $matches[3]<br/>\n";
				}
			}
		}
	}
	
	// Set user data
	function set($email, $hash, $name, $language, $home)
	{
		$this->users[$email] = array();
		$this->users[$email]["email"] = $email;
		$this->users[$email]["hash"] = $hash;
		$this->users[$email]["name"] = $name;
		$this->users[$email]["language"] = $language;
		$this->users[$email]["home"] = $home;
	}
	
	// Create or update user in file
	function createUser($fileName, $email, $hash, $name, $language, $home)
	{
		$email = strreplaceu(',', '-', $email);
		$hash = strreplaceu(',', '-', $hash);
		$fileData = @file($fileName);
		if($fileData)
		{
			foreach($fileData as $line)
			{
				preg_match("/^(.*?),\s*(.*?),\s*(.*?),\s*(.*?),\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]))
				{
					if($matches[1] == $email)
					{
						$name = strreplaceu(',', '-', empty($name) ? $matches[3] : $name);
						$language = strreplaceu(',', '-', empty($language) ? $matches[4] : $language);
						$home = strreplaceu(',', '-', empty($home) ? $matches[5] : $home);
						$fileDataNew .= "$email,$hash,$name,$language,$home\n";
						$found = true;
						continue;
					}
				}
				$fileDataNew .= $line;
			}
		}
		if(!$found)
		{
			$name = strreplaceu(',', '-', empty($name) ? $this->yellow->config->get("sitename") : $name);
			$language = strreplaceu(',', '-', empty($language) ? $this->yellow->config->get("language") : $language);
			$home = strreplaceu(',', '-', empty($home) ? "/" : $home);
			$fileDataNew .= "$email,$hash,$name,$language,$home\n";
		}
		return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
	}

	// Check user login
	function checkUser($email, $password)
	{
		$algorithm = $this->yellow->config->get("webinterfaceUserHashAlgorithm");
		return $this->isExisting($email) && $this->yellow->toolbox->verifyHash($password, $algorithm, $this->users[$email]["hash"]);
	}

	// Create browser cookie
	function createCookie($cookieName, $email)
	{
		if($this->isExisting($email))
		{
			$location = $this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation");
			$session = $this->yellow->toolbox->createHash($this->users[$email]["hash"], "sha256");
			if(empty($session)) $session = "error-hash-algorithm-sha256";
			setcookie($cookieName, "$email,$session", time()+60*60*24*30*365, $location,
				$this->yellow->config->get("webinterfaceServerName"),
				$this->yellow->config->get("webinterfaceServerScheme")=="https");
		}
	}
	
	// Destroy browser cookie
	function destroyCookie($cookieName)
	{
		$location = $this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation");
		setcookie($cookieName, "", time()-3600,
			$location, $this->yellow->config->get("webinterfaceServerName"),
			$this->yellow->config->get("webinterfaceServerScheme")=="https");
	}
	
	// Return information from browser cookie
	function getCookieInformation($cookie)
	{
		return explode(',', $cookie, 2);
	}
	
	// Check user login from browser cookie
	function checkCookie($email, $session)
	{
		return $this->isExisting($email) && $this->yellow->toolbox->verifyHash($this->users[$email]["hash"], "sha256", $session);
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

	// Return user home
	function getHome($email = "")
	{
		if(empty($email)) $email = $this->email;
		return $this->isExisting($email) ? $this->users[$email]["home"] : "";
	}
	
	// Check if user exists
	function isExisting($email)
	{
		return !is_null($this->users[$email]);
	}
}
	
// Yellow web interface merge
class YellowWebinterfaceMerge
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
		for($pos=0; $pos<$textStart; ++$pos) array_push($diff, array(YellowWebinterfaceMerge::Same, $textSource[$pos], false));
		$lcs = $this->buildDiffLCS($textSource, $textOther, $textStart, $sourceEnd-$textStart, $otherEnd-$textStart);
		for($x=0,$y=0,$xEnd=$otherEnd-$textStart,$yEnd=$sourceEnd-$textStart; $x<$xEnd || $y<$yEnd;)
		{
			$max = $lcs[$y][$x];
			if($y<$yEnd && $lcs[$y+1][$x]==$max)
			{
				array_push($diff, array(YellowWebinterfaceMerge::Remove, $textSource[$textStart+$y], false));
				if($lastRemove == -1) $lastRemove = count($diff)-1;
				++$y;
				continue;
			}
			if($x<$xEnd && $lcs[$y][$x+1]==$max)
			{
				if($lastRemove==-1 || $diff[$lastRemove][0]!=YellowWebinterfaceMerge::Remove)
				{
					array_push($diff, array(YellowWebinterfaceMerge::Add, $textOther[$textStart+$x], false));
					$lastRemove = -1;
				} else {
					$diff[$lastRemove] = array(YellowWebinterfaceMerge::Modify, $textOther[$textStart+$x], false);
					++$lastRemove; if(count($diff)==$lastRemove) $lastRemove = -1;
				}
				++$x;
				continue;
			}
			array_push($diff, array(YellowWebinterfaceMerge::Same, $textSource[$textStart+$y], false));
			$lastRemove = -1;
			++$x;
			++$y;
		}
		for($pos=$sourceEnd;$pos<$sourceSize; ++$pos) array_push($diff, array(YellowWebinterfaceMerge::Same, $textSource[$pos], false));
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
			if($typeMine==YellowWebinterfaceMerge::Same)
			{
				array_push($diff, $diffYours[$posYours]);
			} else if($typeYours==YellowWebinterfaceMerge::Same) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeMine==YellowWebinterfaceMerge::Add && $typeYours==YellowWebinterfaceMerge::Add) {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
			} else if($typeMine==YellowWebinterfaceMerge::Modify && $typeYours==YellowWebinterfaceMerge::Modify) {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
			} else if($typeMine==YellowWebinterfaceMerge::Remove && $typeYours==YellowWebinterfaceMerge::Remove) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeMine==YellowWebinterfaceMerge::Add) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeYours==YellowWebinterfaceMerge::Add) {
				array_push($diff, $diffYours[$posYours]);
			} else {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], true);
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowWebinterfaceMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
			if($typeMine==YellowWebinterfaceMerge::Add || $typeYours==YellowWebinterfaceMerge::Add)
			{
				if($typeMine==YellowWebinterfaceMerge::Add) ++$posMine;
				if($typeYours==YellowWebinterfaceMerge::Add) ++$posYours;
			} else {
				++$posMine;
				++$posYours;
			}
		}
		for(;$posMine<count($diffMine); ++$posMine)
		{
			array_push($diff, $diffMine[$posMine]);
			$typeMine = $diffMine[$posMine][0]; $typeYours = ' ';
			if(defined("DEBUG") && DEBUG>=2) echo "YellowWebinterfaceMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
		}
		for(;$posYours<count($diffYours); ++$posYours)
		{
			array_push($diff, $diffYours[$posYours]);
			$typeYours = $diffYours[$posYours][0]; $typeMine = ' ';
			if(defined("DEBUG") && DEBUG>=2) echo "YellowWebinterfaceMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
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
				if($diff[$i][0] != YellowWebinterfaceMerge::Remove) $output .= $diff[$i][1];
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