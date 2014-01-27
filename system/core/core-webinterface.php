<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Web interface core plugin
class YellowWebinterface
{
	const Version = "0.2.5";
	var $yellow;				//access to API
	var $users;					//web interface users
	var $activeLocation;		//web interface location? (boolean)
	var $activeUserFail;		//web interface login failed? (boolean)
	var $activeUserEmail;		//web interface user currently logged in
	var $rawDataOriginal;		//raw data of page in case of errors

	// Initialise plugin
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("webinterfaceLocation", "/edit/");
		$this->yellow->config->setDefault("webinterfaceUserFile", "user.ini");
		$this->users = new YellowWebinterfaceUsers($yellow);
		$this->users->load($this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile"));
	}

	// Handle web interface location
	function onRequest($serverName, $serverBase, $location, $fileName)
	{
		$statusCode = 0;
		if($this->checkWebinterfaceLocation($location))
		{
			$serverBase .= rtrim($this->yellow->config->get("webinterfaceLocation"), '/');
			list($location, $fileName) = $this->yellow->getRequestLocationFile($serverBase);
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
	
	// Handle page meta data parsing
	function onParseMeta($page, $text)
	{
		if($this->isWebinterfaceLocation() && $this->isUser())
		{
			if($page == $this->yellow->page)
			{
				if(empty($this->rawDataOriginal)) $this->rawDataOriginal = $page->rawData;
			}
		}
	}
	
	// Handle page content parsing
	function onParseContent($page, $text)
	{
		$output = NULL;
		if($this->isWebinterfaceLocation() && $this->isUser())
		{
			if($page == $this->yellow->page)
			{
				switch($page->statusCode)
				{
					case 424:	$language = $this->isUser() ? $this->users->getLanguage($this->activeUserEmail) : $page->get("language");
								$page->rawData = "---\r\n";
								$page->rawData .= "Title: ".$this->yellow->text->getText("webinterface424Title", $language)."\r\n";
								$page->rawData .= "Author: ".$this->users->getName($this->activeUserEmail)."\r\n";
								$page->rawData .= "---\r\n";
								$page->rawData .= $this->yellow->text->getText("webinterface424Text", $language);
								break;
					case 500:	$page->rawData = $this->rawDataOriginal; break;
				}
			}
			$serverBase = $this->yellow->config->get("serverBase");
			$location = trim($this->yellow->config->get("webinterfaceLocation"), '/');
			$callback = function($matches) use ($serverBase, $location)
			{
				$matches[2] = preg_replace("#^$serverBase/(?!$location)(.*)$#", "$serverBase/$location/$1", $matches[2]);
				return "<a$matches[1]href=\"$matches[2]\"$matches[3]>";
			};
			$output = preg_replace_callback("/<a(.*?)href=\"([^\"]+)\"(.*?)>/i", $callback, $text);
		}
		return $output;
	}
	
	// Handle extra HTML header lines
	function onHeaderExtra()
	{
		$header = "";
		if($this->isWebinterfaceLocation())
		{
			$location = $this->yellow->config->getHtml("serverBase").$this->yellow->config->getHtml("pluginLocation");
			$language = $this->isUser() ? $this->users->getLanguage($this->activeUserEmail) : $this->yellow->page->get("language");
			$header .= "<link rel=\"styleSheet\" type=\"text/css\" media=\"all\" href=\"{$location}core-webinterface.css\" />\n";
			$header .= "<script type=\"text/javascript\" src=\"{$location}core-webinterface.js\"></script>\n";
			$header .= "<script type=\"text/javascript\">\n";
			$header .= "// <![CDATA[\n";
			if($this->isUser())
			{
				$header .= "yellow.page.rawData = ".json_encode($this->yellow->page->rawData).";\n";
				$header .= "yellow.pages = ".json_encode($this->getPagesData()).";\n";
				$header .= "yellow.config = ".json_encode($this->getConfigData($this->activeUserEmail)).";\n";
			}
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
		return "user EMAIL PASSWORD [NAME LANGUAGE]\n";
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
	
	// Create or update user
	function userCommand($args)
	{
		$statusCode = 0;
		list($dummy, $command, $email, $password, $name, $language) = $args;
		if(!empty($email) && !empty($password) )
		{
			$fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
			$statusCode = $this->users->createUser($fileName, $email, $password, $name, $language)  ? 200 : 500;
			if($statusCode != 200) echo "ERROR updating configuration: Can't write file '$fileName'!\n";
			echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "");
			echo ($this->users->isExisting($email) ? "updated" : "created")."\n";
		} else {
			echo "Yellow $command: Invalid arguments\n";
			$statusCode = 400;
		}
		return $statusCode;
	}
	
	// Process request for an action
	function processRequestAction($serverName, $serverBase, $location, $fileName)
	{
		$statusCode = 0;
		switch($_POST["action"])
		{
			case "edit":	if(!empty($_POST["rawdata"]) && $this->checkUserPermissions($location, $fileName))
							{
								$this->rawDataOriginal = $_POST["rawdata"];
								if($this->yellow->toolbox->createFile($fileName, $_POST["rawdata"]))
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
							break;
			case "login":	$statusCode = 303;
							$locationHeader = $this->yellow->toolbox->getHttpLocationHeader($serverName, $serverBase, $location);
							$this->yellow->sendStatus($statusCode, $locationHeader);
							break;
			case "logout":	$this->users->destroyCookie("login");
							$this->activeUserEmail = "";
							$statusCode = 302;
							$locationHeader = $this->yellow->toolbox->getHttpLocationHeader($serverName, $this->yellow->config->get("serverBase"), $location);
							$this->yellow->sendStatus($statusCode, $locationHeader);
							break;
			default:		if(!is_readable($fileName))
							{
								if($this->yellow->toolbox->isFileLocation($location) && $this->yellow->isContentDirectory("$location/"))
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
			$data[$page->fileName]["title"] = $page->getHtml("title");
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
class YellowWebinterfaceUsers
{
	var $yellow;	//access to API
	var $users;		//registered users
	
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
				preg_match("/^(.*?),\s*(.*?),\s*(.*?),\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]))
				{
					$this->setUser($matches[1], $matches[2], $matches[3], $matches[4]);
					if(defined("DEBUG") && DEBUG>=3) echo "YellowWebinterfaceUsers::load email:$matches[1] $matches[3]<br/>\n";
				}
			}
		}
	}
	
	// Create or update user in file
	function createUser($fileName, $email, $password, $name, $language)
	{
		$email = strreplaceu(',', '-', $email);
		$password = hash("sha256", $email.$password);
		$fileNewUser = true;
		$fileData = @file($fileName);
		if($fileData)
		{
			foreach($fileData as $line)
			{
				preg_match("/^(.*?),\s*(.*?),\s*(.*?),\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]))
				{
					if($matches[1] == $email)
					{
						$name = strreplaceu(',', '-', empty($name) ? $matches[3] : $name);
						$language = strreplaceu(',', '-', empty($language) ? $matches[4] : $language);
						$fileDataNew .= "$email,$password,$name,$language\n";
						$fileNewUser = false;
						continue;
					}
				}
				$fileDataNew .= $line;
			}
		}
		if($fileNewUser)
		{
			$name = strreplaceu(',', '-', empty($name) ? $this->yellow->config->get("sitename") : $name);
			$language = strreplaceu(',', '-', empty($language) ? $this->yellow->config->get("language") : $language);
			$fileDataNew .= "$email,$password,$name,$language\n";
		}
		return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
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
		list($email, $salt, $session) = explode(';', $cookie);
		return $this->isExisting($email) && hash("sha256", $salt.$this->users[$email]["session"])==$session;
	}
	
	// Return user email from browser cookie
	function getCookieEmail($cookie)
	{
		list($email, $salt, $session) = explode(';', $cookie);
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

$yellow->registerPlugin("webinterface", "YellowWebinterface", YellowWebinterface::Version);
?>