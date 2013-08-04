<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Web interface core plugin
class Yellow_Webinterface
{
	const Version = "0.1.7";
	var $yellow;				//access to API
	var $users;					//web interface users
	var $activeLocation;		//web interface location? (boolean)
	var $activeUserFail;		//web interface login failed? (boolean)
	var $activeUserEmail;		//web interface user currently logged in
	var $rawDataOriginal;		//raw data of page in case of errors

	// Initialise plugin
	function initPlugin($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("webinterfaceLocation", "/edit/");
		$this->yellow->config->setDefault("webinterfaceUserFile", "user.ini");
		$this->users = new Yellow_WebinterfaceUsers();
		$this->users->load($this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile"));
	}

	// Handle web interface location
	function onRequest($serverName, $serverBase, $location, $fileName)
	{
		$statusCode = 0;
		if($this->checkWebinterfaceLocation($location))
		{
			$serverBase .= rtrim($this->yellow->config->get("webinterfaceLocation"), '/');
			$location = $this->yellow->getRelativeLocation($serverBase);
			$fileName = $this->yellow->getContentFileName($location);
			if($this->checkUser()) $statusCode = $this->processRequestAction($serverName, $serverBase, $location, $fileName);
			if($statusCode == 0) $statusCode = $this->yellow->processRequest($serverName, $serverBase, $location, $fileName,
													false, $this->activeUserFail ? 401 : 0);
		} else {
			if($this->yellow->config->get("webinterfaceLocation") == "$location/")
			{
				$statusCode = 301;
				$locationHeader = $this->yellow->toolbox->getHttpLocationHeader($serverName, $serverBase, "$location/");
				$this->yellow->sendStatus($statusCode, $locationHeader);
			}
		}
		return $statusCode;
	}
	
	// Handle web content parsing
	function onParseContent($text, $statusCode)
	{
		if($this->isWebinterfaceLocation() && $this->isUser())
		{
			$serverBase = $this->yellow->config->get("serverBase");
			$webinterfaceLocation = trim($this->yellow->config->get("webinterfaceLocation"), '/');
			$text = preg_replace("#<a(.*?)href=\"$serverBase/(?!$webinterfaceLocation)(.*?)\"(.*?)>#",
								 "<a$1href=\"$serverBase/$webinterfaceLocation/$2\"$3>", $text);
			switch($statusCode)
			{
				case 200:	$this->rawDataOriginal = $this->yellow->page->rawData; break;
				case 424:	$language = $this->isUser() ? $this->users->getLanguage($this->activeUserEmail) : $this->yellow->page->get("language");
							$this->yellow->page->rawData = "---\r\n";
							$this->yellow->page->rawData .= "Title: ".$this->yellow->text->getLanguageText($language, "webinterface424Title")."\r\n";
							$this->yellow->page->rawData .= "Author: ".$this->users->getName($this->activeUserEmail)."\r\n";
							$this->yellow->page->rawData .= "---\r\n";
							$this->yellow->page->rawData .= $this->yellow->text->getLanguageText($language, "webinterface424Text");
							break;
				case 500:	$this->yellow->page->rawData = $this->rawDataOriginal; break;
			}
		}
		return $text;
	}
	
	// Handle extra HTML header lines
	function onHeaderExtra()
	{
		$header = "";
		if($this->isWebinterfaceLocation())
		{
			$location = $this->yellow->config->getHtml("serverBase").$this->yellow->config->getHtml("pluginLocation");
			$language = $this->isUser() ? $this->users->getLanguage($this->activeUserEmail) : $this->yellow->page->get("language");
			$header .= "<link rel=\"styleSheet\" type=\"text/css\" media=\"all\" href=\"{$location}core_webinterface.css\" />\n";
			$header .= "<script type=\"text/javascript\" src=\"{$location}core_webinterface.js\"></script>\n";
			$header .= "<script type=\"text/javascript\">\n";
			$header .= "// <![CDATA[\n";
			if($this->isUser())
			{
				$header .= "yellow.page.rawData = ".json_encode($this->yellow->page->rawData).";\n";
				$header .= "yellow.pages = ".json_encode($this->getPagesData()).";\n";
				$header .= "yellow.config = ".json_encode($this->getConfigData($this->activeUserEmail)).";\n";
			}
			$header .= "yellow.text = ".json_encode($this->yellow->text->getData($language, "webinterface")).";\n";
			if(defined("DEBUG")) $header .= "yellow.debug = ".json_encode(DEBUG).";\n";
			$header .= "// ]]>\n";
			$header .= "</script>\n";
		}
		return $header;
	}
	
	// Process request for an action
	function processRequestAction($serverName, $serverBase, $location, $fileName)
	{
		$statusCode = 0;
		if($_POST["action"] == "edit")
		{
			if(!empty($_POST["rawdata"]) && $this->checkUserPermissions($location, $fileName))
			{
				$this->rawDataOriginal = $_POST["rawdata"];
				if($this->yellow->toolbox->makeFile($fileName, $_POST["rawdata"]))
				{
					$statusCode = 303;
					$locationHeader = $this->yellow->toolbox->getHttpLocationHeader($serverName, $serverBase, $location);
					$this->yellow->sendStatus($statusCode, $locationHeader);
				} else {
					$statusCode = 500;
					$this->yellow->processRequest($serverName, $serverBase, $location, $fileName, false, $statusCode);
					$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
				}
			}
		} else if($_POST["action"]== "login") {
			$statusCode = 303;
			$locationHeader = $this->yellow->toolbox->getHttpLocationHeader($serverName, $serverBase, $location);
			$this->yellow->sendStatus($statusCode, $locationHeader);
		} else if($_POST["action"]== "logout") {
			$this->users->destroyCookie("login");
			$this->activeUserEmail = "";
			$statusCode = 302;
			$locationHeader = $this->yellow->toolbox->getHttpLocationHeader($serverName, $this->yellow->config->get("serverBase"), $location);
			$this->yellow->sendStatus($statusCode, $locationHeader);
		} else {
			if(!is_readable($fileName))
			{
				if($this->yellow->toolbox->isFileLocation($location) && is_dir($this->yellow->getContentDirectory("$location/")))
				{
					$statusCode = 301;
					$locationHeader = $this->yellow->toolbox->getHttpLocationHeader($serverName, $serverBase, "$location/");
					$this->yellow->sendStatus($statusCode, $locationHeader);
				} else {
					$statusCode = $this->checkUserPermissions($location, $fileName) ? 424 : 404;
					$this->yellow->processRequest($serverName, $serverBase, $location, $fileName, false, $statusCode);
				}
			}
		}
		return $statusCode;
	}
	
	// Check web interface location
	function checkWebinterfaceLocation($location)
	{
		$locationLength = strlenu($this->yellow->config->get("webinterfaceLocation"));
		$this->activeLocation = substru($location, 0, $locationLength) == $this->yellow->config->get("webinterfaceLocation");
		return $this->isWebinterfaceLocation();
	}
	
	// Check user login
	function checkUser()
	{
		if($_POST["action"] == "login")
		{
			$email = $_POST["email"];
			$password = $_POST["password"];
			if($this->users->checkUser($email, $password))
			{
				$this->users->createCookie("login", $email);
				$this->activeUserEmail = $email;
			} else {
				$this->activeUserFail = true;
			}
		} else if(isset($_COOKIE["login"])) {
			$cookie = $_COOKIE["login"];
			if($this->users->checkCookie($cookie))
			{
				$this->activeUserEmail = $this->users->getCookieEmail($cookie);
			} else {
				$this->activeUserFail = true;
			}
		}
		return $this->isUser();
	}
	
	// Check users permissions for creating new page
	function checkUserPermissions($location, $fileName)
	{
		$path = dirname($fileName);
		return is_dir($path) && strlenu(basename($fileName))<128;
	}
	
	// Check if web interface location
	function isWebinterfaceLocation()
	{
		return $this->activeLocation;
	}
	
	// Check if user is logged in
	function isUser()
	{
		return !empty($this->activeUserEmail);
	}

	// Return page tree with content information, two levels
	function getPagesData()
	{
		$data = array();
		foreach($this->yellow->pages->index(true, 2) as $page)
		{
			$data[$page->fileName] = array();
			$data[$page->fileName]["location"] = $page->getLocation();
			$data[$page->fileName]["modified"] = $page->getModified();
			$data[$page->fileName]["title"] = $page->getTitle();
		}
		return $data;
	}
	
	// Return configuration data including user information
	function getConfigData($email)
	{
		$data = array("userEmail" => $email,
					  "userName" => $this->users->getName($email),
					  "userLanguage" => $this->users->getLanguage($email),
					  "serverName" => $this->yellow->config->get("serverName"),
					  "serverBase" => $this->yellow->config->get("serverBase"));
		return array_merge($data, $this->yellow->config->getData("Location"));
	}
}

// Yellow web interface users
class Yellow_WebinterfaceUsers
{
	var $users;		//registered users
	
	function __construct()
	{
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
				preg_match("/^(.*?),\s*(.*?),\s*(.*?),\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]))
				{
					$this->setUser($matches[1], $matches[2], $matches[3], $matches[4]);
					if(defined("DEBUG") && DEBUG>=3) echo "Yellow_WebinterfaceUsers::load email:$matches[1] $matches[3]<br/>\n";
				}
			}
		}
	}

	// Set user data
	function setUser($email, $password, $name, $language)
	{
		$this->users[$email] = array();
		$this->users[$email]["email"] = $email;
		$this->users[$email]["password"] = $password;
		$this->users[$email]["name"] = $name;
		$this->users[$email]["language"] = $language;
		$this->users[$email]["session"] = hash("sha256", $email.$password.$password.$email);
	}
	
	// Check user login
	function checkUser($email, $password)
	{
		return $this->isExisting($email) && hash("sha256", $email.$password)==$this->users[$email]["password"];
	}

	// Create browser cookie
	function createCookie($cookieName, $email)
	{
		if($this->isExisting($email))
		{
			$salt = hash("sha256", uniqid(mt_rand(), true));
			$text = $email.";".$salt.";".hash("sha256", $salt.$this->users[$email]["session"]);
			setcookie($cookieName, $text, time()+60*60*24*30*365*10, "/");
		}
	}
	
	// Destroy browser cookie
	function destroyCookie($cookieName)
	{
		setcookie($cookieName, "", time()-3600, "/");
	}
	
	// Check user login from browser cookie
	function checkCookie($cookie)
	{
		list($email, $salt, $session) = explode(";", $cookie);
		return $this->isExisting($email) && hash("sha256", $salt.$this->users[$email]["session"])==$session;
	}
	
	// Return user email from browser cookie
	function getCookieEmail($cookie)
	{
		list($email, $salt, $session) = explode(";", $cookie);
		return $email;
	}
	
	// Return user name
	function getName($email)
	{
		return $this->isExisting($email) ? $this->users[$email]["name"] : "";
	}

	// Return user language
	function getLanguage($email)
	{
		return $this->isExisting($email) ? $this->users[$email]["language"] : "";
	}	
	
	// Check if user exists
	function isExisting($email)
	{
		return !is_null($this->users[$email]);
	}
}

$yellow->registerPlugin("webinterface", "Yellow_Webinterface", Yellow_Webinterface::Version);
?>