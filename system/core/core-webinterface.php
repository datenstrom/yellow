<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Web interface core plugin
class YellowWebinterface
{
	const Version = "0.3.3";
	var $yellow;				//access to API
	var $users;					//web interface users
	var $active;				//web interface is active? (boolean)
	var $loginFailed;			//web interface login failed? (boolean)
	var $rawDataOriginal;		//raw data of page in case of errors

	// Handle plugin initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("webinterfaceLocation", "/edit/");
		$this->yellow->config->setDefault("webinterfaceServerScheme", "https");
		$this->yellow->config->setDefault("webinterfaceServerName", $this->yellow->config->get("serverName"));
		$this->yellow->config->setDefault("webinterfaceUserHashAlgorithm", "bcrypt");
		$this->yellow->config->setDefault("webinterfaceUserHashCost", "10");
		$this->yellow->config->setDefault("webinterfaceUserFile", "user.ini");
		$this->yellow->config->setDefault("webinterfaceNewPage", "default");
		$this->users = new YellowWebinterfaceUsers($yellow);
		$this->users->load($this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile"));
	}

	// Handle request
	function onRequest($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->checkRequest($location))
		{
			list($serverScheme, $serverName, $base, $location, $fileName) = $this->getRequestInformation();
			if($this->checkUser()) $statusCode = $this->processRequestAction($serverScheme, $serverName, $base, $location, $fileName);
			if($statusCode == 0) $statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName,
													false, $this->loginFailed ? 401 : 0);
		} else {
			$activeLocation = $this->yellow->config->get("webinterfaceLocation");
			if(rtrim($location, '/') == rtrim($activeLocation, '/'))
			{
				$statusCode = 301;
				$locationHeader = $this->yellow->toolbox->getLocationHeader(
					$this->yellow->config->get("webinterfaceServerScheme"),
					$this->yellow->config->get("webinterfaceServerName"),
					$base, $activeLocation);
				$this->yellow->sendStatus($statusCode, $locationHeader);
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
				if(empty($this->rawDataOriginal)) $this->rawDataOriginal = $page->rawData;
			}
		}
	}
	
	// Handle page content parsing
	function onParseContent($page, $text)
	{
		$output = NULL;
		if($this->isActive() && $this->isUser())
		{
			if($page == $this->yellow->page)
			{
				switch($page->statusCode)
				{
					case 424:	$page->rawData = $this->getPageData(); break;
					case 500:	$page->rawData = $this->rawDataOriginal; break;
				}
			}
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
			$header .= "<link rel=\"styleSheet\" type=\"text/css\" media=\"all\" href=\"{$location}core-webinterface.css\" />\n";
			$header .= "<script type=\"text/javascript\" src=\"{$location}core-webinterface.js\"></script>\n";
			$header .= "<script type=\"text/javascript\">\n";
			$header .= "// <![CDATA[\n";
			if($this->isUser())
			{
				$permissions = $this->checkPermissions($page->location, $page->fileName);
				$header .= "yellow.page.rawData = ".json_encode($page->rawData).";\n";
				$header .= "yellow.page.permissions = " .json_encode($permissions).";\n";
				$header .= "yellow.config = ".json_encode($this->getConfigData()).";\n";
			}
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
				$statusCode = $this->users->createUser($fileName, $email, $hash, $name, $language, $home)  ? 200 : 500;
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
	
	// Process request for an action
	function processRequestAction($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		switch($_POST["action"])
		{
			case "edit":	if(!empty($_POST["rawdata"]) && $this->checkPermissions($location, $fileName))
							{
								$this->rawDataOriginal = $_POST["rawdata"];
								if($this->yellow->toolbox->createFile($fileName, $_POST["rawdata"]))
								{
									$statusCode = 303;
									$locationHeader = $this->yellow->toolbox->getLocationHeader(
										$serverScheme, $serverName, $base, $location);
									$this->yellow->sendStatus($statusCode, $locationHeader);
								} else {
									$statusCode = 500;
									$this->yellow->processRequest(
										$serverScheme, $serverName, $base, $location, $fileName, false, $statusCode);
									$this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
								}
							}
							break;
			case "login":	$home = $this->users->getHome();
							if(substru($location, 0, strlenu($home)) == $home)
							{
								$statusCode = 303;
								$locationHeader = $this->yellow->toolbox->getLocationHeader(
									$serverScheme, $serverName, $base, $location);
								$this->yellow->sendStatus($statusCode, $locationHeader);
							} else {
								$statusCode = 302;
								$locationHeader = $this->yellow->toolbox->getLocationHeader(
									$serverScheme, $serverName, $base, $home);
								$this->yellow->sendStatus($statusCode, $locationHeader);
							}
							break;
			case "logout":	$this->users->destroyCookie("login");
							$this->users->email = "";
							$statusCode = 302;
							$locationHeader = $this->yellow->toolbox->getLocationHeader(
								$this->yellow->config->get("serverScheme"),
								$this->yellow->config->get("serverName"),
								$this->yellow->config->get("serverBase"), $location);
							$this->yellow->sendStatus($statusCode, $locationHeader);
							break;
			default:		if(!is_readable($fileName))
							{
								if($this->yellow->toolbox->isFileLocation($location) && $this->yellow->isContentDirectory("$location/"))
								{
									$statusCode = 301;
									$locationHeader = $this->yellow->toolbox->getLocationHeader(
										$serverScheme, $serverName, $base, "$location/");
									$this->yellow->sendStatus($statusCode, $locationHeader);
								} else {
									$statusCode = $this->checkPermissions($location, $fileName) ? 424 : 404;
									$this->yellow->processRequest(
										$serverScheme, $serverName, $base, $location, $fileName, false, $statusCode);
								}
							}
		}
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
	function checkUser()
	{
		if($_POST["action"] == "login")
		{
			$email = $_POST["email"];
			$password = $_POST["password"];
			if($this->users->checkUser($email, $password))
			{
				$this->users->createCookie("login", $email);
				$this->users->email = $email;
			} else {
				$this->loginFailed = true;
			}
		} else if(isset($_COOKIE["login"])) {
			list($email, $session) = $this->users->getCookieInformation($_COOKIE["login"]);
			if($this->users->checkCookie($email, $session))
			{
				$this->users->email = $email;
			} else {
				$this->loginFailed = true;
			}
		}
		return $this->isUser();
	}

	// Check permissions for changing page
	function checkPermissions($location, $fileName)
	{
		$permissions = true;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onCheckPermissions"))
			{
				$permissions = $value["obj"]->onCheckPermissions($location, $fileName, $this->users);
				if(!$permissions) break;
			}
		}
		$permissions &= is_dir(dirname($fileName)) && strlenu(basename($fileName))<128;
		return $permissions;
	}
	
	// Return request information
	function getRequestInformation()
	{
		$serverScheme = $this->yellow->config->get("webinterfaceServerScheme");
		$serverName = $this->yellow->config->get("webinterfaceServerName");
		$base = rtrim($this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation"), '/');
		return $this->yellow->getRequestInformation($serverScheme, $serverName, $base);
	}
	
	// Return content data for new page
	function getPageData()
	{
		$fileData = "";
		$fileName = $this->yellow->toolbox->findFileFromLocation($this->yellow->page->location,
			$this->yellow->config->get("contentDir"), $this->yellow->config->get("contentHomeDir"),
			$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
		$fileName = $this->yellow->toolbox->findNameFromFile($fileName,
			$this->yellow->config->get("configDir"), $this->yellow->config->get("webinterfaceNewPage"),
			$this->yellow->config->get("contentExtension"), true);
		$fileHandle = @fopen($fileName, "r");
		if($fileHandle)
		{
			$fileData = fread($fileHandle, filesize($fileName));
			fclose($fileHandle);
		}
		return $fileData;
	}
	
	// Return configuration data including information of current user
	function getConfigData()
	{
		$data = array("userEmail" => $this->users->email,
					  "userName" => $this->users->getName(),
					  "userLanguage" => $this->users->getLanguage(),
					  "userHome" => $this->users->getHome(),
					  "serverScheme" => $this->yellow->config->get("serverScheme"),
					  "serverName" => $this->yellow->config->get("serverName"),
					  "serverBase" => $this->yellow->config->get("serverBase"));
		return array_merge($data, $this->yellow->config->getData("Location"));
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
		$fileNewUser = true;
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

$yellow->plugins->register("webinterface", "YellowWebinterface", YellowWebinterface::Version);
?>