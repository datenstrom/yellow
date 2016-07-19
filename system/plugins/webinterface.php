<?php
// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Web interface plugin
class YellowWebinterface
{
	const VERSION = "0.6.9";
	var $yellow;				//access to API
	var $active;				//web interface is active? (boolean)
	var $userEmail;				//web interface user
	var $userLanguage;			//web interface user language
	var $userRestrictions;		//web interface user can change page? (boolean)
	var $action;				//web interface action
	var $status;				//web interface status
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
		$this->yellow->config->setDefault("webinterfaceUserPasswordMinLength", "4");
		$this->yellow->config->setDefault("webinterfaceUserHashAlgorithm", "bcrypt");
		$this->yellow->config->setDefault("webinterfaceUserHashCost", "10");
		$this->yellow->config->setDefault("webinterfaceUserStatus", "active");
		$this->yellow->config->setDefault("webinterfaceUserPending", "none");
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
		}
		return $statusCode;
	}
	
	// Handle page meta data parsing
	function onParseMeta($page)
	{
		if($this->isActive() && $page==$this->yellow->page)
		{
			if($this->isUser())
			{
				if(empty($this->rawDataSource)) $this->rawDataSource = $page->rawData;
				if(empty($this->rawDataEdit)) $this->rawDataEdit = $page->rawData;
				if($page->statusCode==424) $this->rawDataEdit = $this->getRawDataNew($page->location);
			}
			if(empty($this->userLanguage)) $this->userLanguage = $page->get("language");
			if(empty($this->action)) $this->action = $this->isUser() ? "none" : "login";
			if(empty($this->status)) $this->status = "none";
			if($this->status=="error") $this->action = "error";
		}
	}
	
	// Handle page content parsing of custom block
	function onParseContentBlock($page, $name, $text, $shortcut)
	{
		$output = null;
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
		$output = null;
		if($this->isActive() && $name=="header")
		{
			$location = $this->yellow->config->get("serverBase").$this->yellow->config->get("pluginLocation")."webinterface";
			$output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"".htmlspecialchars($location).".css\" />\n";
			$output .= "<script type=\"text/javascript\" src=\"".htmlspecialchars($location).".js\"></script>\n";
			$output .= "<script type=\"text/javascript\">\n";
			$output .= "// <![CDATA[\n";
			$output .= "yellow.page = ".json_encode($this->getPageData()).";\n";
			$output .= "yellow.config = ".json_encode($this->getConfigData()).";\n";
			$output .= "yellow.text = ".json_encode($this->getTextData()).";\n";
			$output .= "// ]]>\n";
			$output .= "</script>\n";
		}
		return $output;
	}
	
	// Handle command
	function onCommand($args)
	{
		list($command) = $args;
		switch($command)
		{
			case "clean":	$statusCode = $this->cleanCommand($args); break;
			case "user":	$statusCode = $this->userCommand($args); break;
			default:		$statusCode = 0;
		}
		return $statusCode;
	}
	
	// Handle command help
	function onCommandHelp()
	{
		return "user [EMAIL PASSWORD NAME LANGUAGE]\n";
	}

	// Clean user accounts
	function cleanCommand($args)
	{
		$statusCode = 0;
		list($command, $path) = $args;
		if($path=="all")
		{
			$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
			if(!$this->users->clean($fileNameUser)) $statusCode = 500;
			if($statusCode==500) echo "ERROR cleaning configuration: Can't write file '$fileNameUser'!\n";
		}
		return $statusCode;
	}
	
	// Update user account
	function userCommand($args)
	{
		$statusCode = 0;
		list($command, $email, $password, $name, $language) = $args;
		if(!empty($email) && !empty($password))
		{
			$userExisting = $this->users->isExisting($email);
			$status = $this->getUserAccount($email, $password, $command);
			switch($status)
			{
				case "invalid":	echo "ERROR updating configuration: Please enter a valid email!\n"; break;
				case "weak": echo "ERROR updating configuration: Please enter a different password!\n"; break;
			}
			if($status=="ok")
			{
				$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
				$status = $this->users->update($fileNameUser, $email, $password, $name, $language, "active") ? "ok" : "error";
				if($status=="error") echo "ERROR updating configuration: Can't write file '$fileNameUser'!\n";
			}
			if($status=="ok")
			{
				$algorithm = $this->yellow->config->get("webinterfaceUserHashAlgorithm");
				$status = substru($this->users->getHash($email), 0, 5)!="error-hash" ? "ok" : "error";
				if($status=="error") echo "ERROR updating configuration: Hash algorithm '$algorithm' not supported!\n";
			}
			$statusCode = $status=="ok" ? 200 : 500;
			echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "");
			echo ($userExisting ? "updated" : "created")."\n";
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
			switch($_REQUEST["action"])
			{
				case "":			$statusCode = $this->processRequestShow($serverScheme, $serverName, $base, $location, $fileName); break;
				case "login":		$statusCode = $this->processRequestLogin($serverScheme, $serverName, $base, $location, $fileName); break;
				case "logout":		$statusCode = $this->processRequestLogout($serverScheme, $serverName, $base, $location, $fileName); break;
				case "signup":		$statusCode = $this->processRequestSignup($serverScheme, $serverName, $base, $location, $fileName); break;
				case "confirm":		$statusCode = $this->processRequestConfirm($serverScheme, $serverName, $base, $location, $fileName); break;
				case "approve":		$statusCode = $this->processRequestApprove($serverScheme, $serverName, $base, $location, $fileName); break;
				case "recover":		$statusCode = $this->processRequestRecover($serverScheme, $serverName, $base, $location, $fileName); break;
				case "settings":	$statusCode = $this->processRequestSettings($serverScheme, $serverName, $base, $location, $fileName); break;
				case "create":		$statusCode = $this->processRequestCreate($serverScheme, $serverName, $base, $location, $fileName); break;
				case "edit":		$statusCode = $this->processRequestEdit($serverScheme, $serverName, $base, $location, $fileName); break;
				case "delete":		$statusCode = $this->processRequestDelete($serverScheme, $serverName, $base, $location, $fileName); break;
			}
		} else {
			switch($_REQUEST["action"])
			{
				case "signup":		$statusCode = $this->processRequestSignup($serverScheme, $serverName, $base, $location, $fileName); break;
				case "confirm":		$statusCode = $this->processRequestConfirm($serverScheme, $serverName, $base, $location, $fileName); break;
				case "approve":		$statusCode = $this->processRequestApprove($serverScheme, $serverName, $base, $location, $fileName); break;
				case "recover":		$statusCode = $this->processRequestRecover($serverScheme, $serverName, $base, $location, $fileName); break;
			}
		}
		if($statusCode==0)
		{
			$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
			if($this->action=="fail") $this->yellow->page->error(500, "Login failed, [please log in](javascript:yellow.action('login');)!");
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
				$location = $this->yellow->lookup->isFileLocation($location) ? "$location/" : "/".$this->yellow->getRequestLanguage(true)."/";
				$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->yellow->sendStatus($statusCode, $location);
			} else {
				$statusCode = $this->userRestrictions ? 404 : 424;
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
				$this->yellow->page->error($statusCode);
			}
		}
		return $statusCode;
	}

	// Process request for user login
	function processRequestLogin($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		$home = $this->users->getHome($this->userEmail);
		if(substru($location, 0, strlenu($home))==$home)
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
		$this->userEmail = "";
		$this->users->destroyCookie("login");
		$location = $this->yellow->lookup->normaliseUrl(
			$this->yellow->config->get("serverScheme"),
			$this->yellow->config->get("serverName"),
			$this->yellow->config->get("serverBase"), $location);
		$this->yellow->sendStatus($statusCode, $location);
		return $statusCode;
	}

	// Process request for user signup
	function processRequestSignup($serverScheme, $serverName, $base, $location, $fileName)
	{
		$this->action = "signup";
		$this->status = "ok";
		$name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
		$email = trim($_REQUEST["email"]);
		$password = trim($_REQUEST["password"]);
		if(empty($name) || empty($email) || empty($password)) $this->status = "incomplete";
		if($this->status=="ok") $this->status = $this->getUserAccount($email, $password, $this->action);
		if($this->status=="ok" && !$this->users->isWebmaster()) $this->status = "next";
		if($this->status=="ok" && $this->users->isExisting($email)) $this->status = "next";
		if($this->status=="ok")
		{
			$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
			$this->status = $this->users->update($fileNameUser, $email, $password, $name, "", "unconfirmed") ? "ok" : "error";
			if($this->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
		}
		if($this->status=="ok")
		{
			$this->status = $this->sendMail($serverScheme, $serverName, $base, $email, "confirm") ? "next" : "error";
			if($this->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
		}
		$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
		return $statusCode;
	}
	
	// Process request to confirm user signup
	function processRequestConfirm($serverScheme, $serverName, $base, $location, $fileName)
	{
		$this->action = "confirm";
		$this->status = "ok";
		$email = $_REQUEST["email"];
		$this->status = $this->getUserRequest($email, $_REQUEST["action"], $_REQUEST["expire"], $_REQUEST["id"]);
		if($this->status=="ok")
		{
			$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
			$this->status = $this->users->update($fileNameUser, $email, "", "", "", "unapproved") ? "ok" : "error";
			if($this->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
		}
		if($this->status=="ok")
		{
			$this->status = $this->sendMail($serverScheme, $serverName, $base, $email, "approve") ? "done" : "error";
			if($this->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
		}
		$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
		return $statusCode;
	}
	
	// Process request to approve user signup
	function processRequestApprove($serverScheme, $serverName, $base, $location, $fileName)
	{
		$this->action = "approve";
		$this->status = "ok";
		$email = $_REQUEST["email"];
		$this->status = $this->getUserRequest($email, $_REQUEST["action"], $_REQUEST["expire"], $_REQUEST["id"]);
		if($this->status=="ok")
		{
			$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
			$this->status = $this->users->update($fileNameUser, $email, "", "", "", "active") ? "ok" : "error";
			if($this->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
		}
		if($this->status=="ok")
		{
			$this->status = $this->sendMail($serverScheme, $serverName, $base, $email, "welcome") ? "done" : "error";
			if($this->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
		}
		$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
		return $statusCode;
	}
	
	// Process request to recover password
	function processRequestRecover($serverScheme, $serverName, $base, $location, $fileName)
	{
		$this->action = "recover";
		$this->status = "ok";
		$email = trim($_REQUEST["email"]);
		$password = trim($_REQUEST["password"]);
		if(empty($_REQUEST["id"]))
		{
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->status = "invalid";
			if($this->status=="ok" && !$this->users->isWebmaster()) $this->status = "next";
			if($this->status=="ok" && !$this->users->isExisting($email)) $this->status = "next";
			if($this->status=="ok")
			{
				$this->status = $this->sendMail($serverScheme, $serverName, $base, $email, "recover") ? "next" : "error";
				if($this->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
			}
		} else {
			$this->status = $this->getUserRequest($email, $_REQUEST["action"], $_REQUEST["expire"], $_REQUEST["id"]);
			if($this->status=="ok")
			{
				if(empty($password)) $this->status = "password";
				if($this->status=="ok") $this->status = $this->getUserAccount($email, $password, $this->action);
				if($this->status=="ok")
				{
					$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
					$this->status = $this->users->update($fileNameUser, $email, $password) ? "ok" : "error";
					if($this->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
				}
				if($this->status=="ok")
				{
					$this->userEmail = "";
					$this->users->destroyCookie("login");
					$this->status = $this->sendMail($serverScheme, $serverName, $base, $email, "information") ? "done" : "error";
					if($this->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
				}
			}
		}
		$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
		return $statusCode;
	}

	// Process request to change settings
	function processRequestSettings($serverScheme, $serverName, $base, $location, $fileName)
	{
		$this->action = "settings";
		$this->status = $this->getUserAccount($this->userEmail, "", $this->action);
		if($this->status=="ok")
		{
			$name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
			$language = trim($_REQUEST["language"]);
			$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
			$this->status = $this->users->update($fileNameUser, $this->userEmail, "", $name, $language) ? "done" : "error";
			if($this->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
		}
		if($this->status=="done")
		{
			$statusCode = 303;
			$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
			$this->yellow->sendStatus($statusCode, $location);
		} else {
			$statusCode = $this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
		}
		return $statusCode;
	}
	
	// Process request to create page
	function processRequestCreate($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if(!$this->userRestrictions && !empty($_POST["rawdataedit"]))
		{
			$this->rawDataSource = $this->rawDataEdit = rawurldecode($_POST["rawdatasource"]);
			$rawData = $this->normaliseText(rawurldecode($_POST["rawdataedit"]));
			$page = $this->getPageNew($serverScheme, $serverName, $base, $location, $fileName, $rawData);
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
				$this->yellow->processRequest($serverScheme, $serverName, $base, $location, $fileName, false);
				$this->yellow->page->error($statusCode, $page->get("pageError"));
			}
		}
		return $statusCode;
	}
	
	// Process request to edit page
	function processRequestEdit($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if(!$this->userRestrictions && !empty($_POST["rawdataedit"]))
		{
			$this->rawDataSource = rawurldecode($_POST["rawdatasource"]);
			$this->rawDataEdit = $this->normaliseText(rawurldecode($_POST["rawdataedit"]));
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
		if(!$this->userRestrictions)
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

	// Send mail to web interface user
	function sendMail($serverScheme, $serverName, $base, $email, $action)
	{
		if($action=="welcome" || $action=="information")
		{
			$url = "$serverScheme://$serverName$base/";
		} else {
			$expire = time()+60*60*24;
			$id = $this->users->createUserRequestId($email, $action, $expire);
			$url = "$serverScheme://$serverName$base"."/action:$action/email:$email/expire:$expire/id:$id/";
		}
		if($action=="approve")
		{
			$account = $email;
			$name = $this->yellow->config->get("author");
			$email = $this->yellow->config->get("email");
		} else {
			$account = $email;
			$name = $this->users->getName($email);
		}
		$language = $this->users->getLanguage($email);
		if(!$this->yellow->text->isLanguage($language)) $language = $this->yellow->config->get("language");
		$sitename = $this->yellow->config->get("sitename");
		$prefix = "webinterface".ucfirst($action);
		$message = $this->yellow->text->getText("{$prefix}Message", $language);
		$message = preg_replace("/@useraccount/i", $account, $message);
		$message = preg_replace("/@usershort/i", strtok($name, " "), $message);
		$message = preg_replace("/@username/i", $name, $message);
		$message = preg_replace("/@userlanguage/i", $language, $message);
		$mailTo = mb_encode_mimeheader("$name <$email>");
		$mailSubject = mb_encode_mimeheader($this->yellow->text->getText("{$prefix}Subject", $language));
		$mailHeaders = mb_encode_mimeheader("From: $sitename <noreply>")."\r\n";
		$mailHeaders .= mb_encode_mimeheader("X-Request-Url: $serverScheme://$serverName$base")."\r\n";
		$mailHeaders .= mb_encode_mimeheader("X-Remote-Addr: $_SERVER[REMOTE_ADDR]")."\r\n";
		$mailHeaders .= "Mime-Version: 1.0\r\n";
		$mailHeaders .= "Content-Type: text/plain; charset=utf-8\r\n";
		$mailMessage = "$message\r\n\r\n$url\r\n-- \r\n$sitename";
		return mail($mailTo, $mailSubject, $mailMessage, $mailHeaders);
	}

	// Check web interface request
	function checkRequest($location)
	{
		if($this->yellow->toolbox->getServerScheme()==$this->yellow->config->get("webinterfaceServerScheme") &&
		   $this->yellow->toolbox->getServerName()==$this->yellow->config->get("webinterfaceServerName"))
		{
			$locationLength = strlenu($this->yellow->config->get("webinterfaceLocation"));
			$this->active = substru($location, 0, $locationLength)==$this->yellow->config->get("webinterfaceLocation");
		}
		return $this->isActive();
	}
	
	// Check web interface user
	function checkUser($location, $fileName)
	{
		if($_POST["action"]=="login")
		{
			$email = $_POST["email"];
			$password = $_POST["password"];
			if($this->users->checkUser($email, $password))
			{
				$this->users->createCookie("login", $email);
				$this->userEmail = $email;
				$this->userLanguage = $this->getUserLanguage($email);
				$this->userRestrictions = $this->getUserRestrictions($email, $location, $fileName);
			} else {
				$this->action = "fail";
			}
		} else if(isset($_COOKIE["login"])) {
			list($email, $session) = explode(',', $_COOKIE["login"], 2);
			if($this->users->checkCookie($email, $session))
			{
				$this->userEmail = $email;
				$this->userLanguage = $this->getUserLanguage($email);
				$this->userRestrictions = $this->getUserRestrictions($email, $location, $fileName);
			} else {
				$this->action = "fail";
			}
		}
		return $this->isUser();
	}
	
	// Return user language
	function getUserLanguage($email)
	{
		$language = $this->users->getLanguage($email);
		if(!$this->yellow->text->isLanguage($language)) $language = $this->yellow->config->get("language");
		return $language;
	}

	// Return user account request
	function getUserRequest($email, $action, $expire, $id)
	{
		$status = $this->users->checkUserRequest($email, $action, $expire, $id) ? "ok" : "done";
		if($status=="done" && $expire<=time()) $status = "expire";
		return $status;
	}
	
	// Return user account changes
	function getUserAccount($email, $password, $action)
	{
		$status = null;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onUserAccount"))
			{
				$status = $value["obj"]->onUserAccount($email, $password, $action, $status, $this->users);
				if(!is_null($status)) break;
			}
		}
		if(is_null($status))
		{
			$status = "ok";
			if(!empty($password) && strlenu($password)<$this->yellow->config->get("webinterfaceUserPasswordMinLength")) $status = "weak";
			if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $status = "invalid";
		}
		return $status;
	}
	
	// Return user restrictions to change page
	function getUserRestrictions($email, $location, $fileName)
	{
		$userRestrictions = null;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onUserRestrictions"))
			{
				$userRestrictions = $value["obj"]->onUserRestrictions($email, $location, $fileName, $this->users);
				if(!is_null($userRestrictions)) break;
			}
		}
		if(is_null($userRestrictions))
		{
			$userRestrictions = !is_dir(dirname($fileName)) || strlenu(basename($fileName))>128;
			$userRestrictions |= substru($location, 0, strlenu($this->users->getHome($email)))!=$this->users->getHome($email);
		}
		return $userRestrictions;
	}
	
	// Update request information
	function updateRequestInformation()
	{
		if($this->isActive())
		{
			$serverScheme = $this->yellow->config->get("webinterfaceServerScheme");
			$serverName = $this->yellow->config->get("webinterfaceServerName");
			$base = rtrim($this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation"), '/');
			$this->yellow->page->base = $base;
		}
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
		if($this->getUserRestrictions($this->userEmail, $page->location, $page->fileName))
		{
			$page->error(500, "Page '".$page->get("title")."' is not allowed!");
		}
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
				if($pageSource->location!=$page->location)
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
		if($this->getUserRestrictions($this->userEmail, $page->location, $page->fileName))
		{
			$page->error(500, "Page '".$page->get("title")."' is not allowed!");
		}
		return $page;
	}
	
	// Return raw data for new page
	function getRawDataNew($location = "")
	{
		$fileName = $this->yellow->lookup->findFileFromLocation($this->yellow->page->location);
		$fileName = $this->yellow->lookup->findFileNew($fileName,
			$this->yellow->config->get("webinterfaceNewFile"), $this->yellow->config->get("configDir"),
			$this->yellow->config->get("template"));
		$rawData = $this->yellow->toolbox->readFile($fileName);
		$rawData = preg_replace("/@datetime/i", date("Y-m-d H:i:s"), $rawData);
		$rawData = preg_replace("/@date/i", date("Y-m-d"), $rawData);
		$rawData = preg_replace("/@usershort/i", strtok($this->users->getName($this->userEmail), " "), $rawData);
		$rawData = preg_replace("/@username/i", $this->users->getName($this->userEmail), $rawData);
		$rawData = preg_replace("/@userlanguage/i", $this->users->getLanguage($this->userEmail), $rawData);
		if(!empty($location))
		{
			$title = $this->yellow->toolbox->createTextTitle($location);
			$rawData = $this->updateDataTitle($rawData, $title);
		}
		return $rawData;
	}
	
	// Return page data including login information
	function getPageData()
	{
		$data = array();
		if($this->isUser())
		{
			$data["title"] = $this->getDataTitle($this->rawDataEdit);
			$data["rawDataSource"] = $this->rawDataSource;
			$data["rawDataEdit"] = $this->rawDataEdit;
			$data["rawDataNew"] = $this->getRawDataNew();
			$data["pageFile"] = $this->yellow->page->get("pageFile");
			$data["parserSafeMode"] = $this->yellow->page->parserSafeMode;
		}
		if($this->action!="none") $data = array_merge($data, $this->getRequestData());
		$data["action"] = $this->action;
		$data["status"] = $this->status;
		$data["statusCode"] = $this->yellow->page->statusCode;
		return $data;
	}
	
	// Return configuration data including user information
	function getConfigData()
	{
		$data = $this->yellow->config->getData("", "Location");
		if($this->isUser())
		{
			$data["userEmail"] = $this->userEmail;
			$data["userName"] = $this->users->getName($this->userEmail);
			$data["userLanguage"] = $this->users->getLanguage($this->userEmail);
			$data["userStatus"] = $this->users->getStatus($this->userEmail);
			$data["userHome"] = $this->users->getHome($this->userEmail);
			$data["userRestrictions"] = $this->userRestrictions;
			$data["serverScheme"] = $this->yellow->config->get("serverScheme");
			$data["serverName"] = $this->yellow->config->get("serverName");
			$data["serverBase"] = $this->yellow->config->get("serverBase");
			$data["serverTime"] = $this->yellow->config->get("serverTime");
			$data["serverLanguages"] = array();
			foreach($this->yellow->text->getLanguages() as $language)
			{
				$data["serverLanguages"][$language] = $this->yellow->text->getTextHtml("languageDescription", $language);
			}
			$data["serverVersion"] = "Yellow ".YellowCore::VERSION;
		} else {
			$data["loginEmail"] = $this->yellow->config->get("loginEmail");
			$data["loginPassword"] = $this->yellow->config->get("loginPassword");
			$data["loginButtons"] = intval($this->users->isWebmaster());
		}
		if(defined("DEBUG") && DEBUG>=1) $data["debug"] = DEBUG;
		return $data;
	}
	
	// Return request strings
	function getRequestData()
	{
		$data = array();
		foreach($_REQUEST as $key=>$value)
		{
			if($key=="login" || $key=="password") continue;
			$data["request".ucfirst($key)] = trim($value);
		}
		return $data;
	}
	
	// Return user data
	function getUserData()
	{
		$data = array();
		foreach($this->users->users as $key=>$value)
		{
			$data[$key] = "$value[email] password $value[name] $value[language] $value[status]";
			if($this->getUserRestrictions($value["email"], "/locationcheck/", "/filecheck")) $data[$key] .= " restrictions";
		}
		usort($data, strnatcasecmp);
		return $data;
	}
	
	// Return text strings
	function getTextData()
	{
		$textLanguage = array_merge($this->yellow->text->getData("language", $this->userLanguage));
		$textWebinterface = array_merge($this->yellow->text->getData("webinterface", $this->userLanguage));
		$textYellow = array_merge($this->yellow->text->getData("yellow", $this->userLanguage));
		return array_merge($textLanguage, $textWebinterface, $textYellow);
	}
	
	// Normlise text with special characters
	function normaliseText($text)
	{
		if($this->yellow->plugins->isExisting("emojiawesome"))
		{
			$text = $this->yellow->plugins->get("emojiawesome")->normaliseText($text, true, false);
		}
		return $text;
	}
	
	// Check if user is logged in
	function isUser()
	{
		return !empty($this->userEmail);
	}
	
	// Check if web interface request
	function isActive()
	{
		return $this->active;
	}
}

// Yellow users
class YellowUsers
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
		if(defined("DEBUG") && DEBUG>=2) echo "YellowUsers::load file:$fileName<br/>\n";
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			if(preg_match("/^\#/", $line)) continue;
			preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
			if(!empty($matches[1]) && !empty($matches[2]))
			{
				list($hash, $name, $language, $status, $pending, $home) = explode(',', $matches[2]);
				$home = empty($home) ? $pending : $home; //TODO: remove later, converts old file format
				$this->set($matches[1], $hash, $name, $language, $status, $pending, $home);
				if(defined("DEBUG") && DEBUG>=3) echo "YellowUsers::load email:$matches[1]<br/>\n";
			}
		}
	}
	
	// Clean users in file
	function clean($fileName)
	{
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
			if(!empty($matches[1]) && !empty($matches[2]))
			{
				list($hash, $name, $language, $status, $pending, $home) = explode(',', $matches[2]);
				if($status=="active" || $status=="inactive")
				{
					$home = empty($home) ? $pending : $home; //TODO: remove later, converts old file format
					$pending = $this->yellow->config->get("webinterfaceUserPending");
					$fileDataNew .= "$matches[1]: $hash,$name,$language,$status,$pending,$home\n";
				}
			} else {
				$fileDataNew .= $line;
			}
		}
		return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
	}
	
	// Update users in file
	function update($fileName, $email, $password = "", $name = "", $language = "", $status = "", $pending = "", $home = "")
	{
		if(!empty($password))
		{
			$algorithm = $this->yellow->config->get("webinterfaceUserHashAlgorithm");
			$cost = $this->yellow->config->get("webinterfaceUserHashCost");
			$hash = $this->yellow->toolbox->createHash($password, $algorithm, $cost);
			if(empty($hash)) $hash = "error-hash-algorithm-$algorithm";
		}
		if($this->isExisting($email))
		{
			$email = strreplaceu(',', '-', $email);
			$hash = strreplaceu(',', '-', empty($hash) ? $this->users[$email]["hash"] : $hash);
			$name = strreplaceu(',', '-', empty($name) ? $this->users[$email]["name"] : $name);
			$language = strreplaceu(',', '-', empty($language) ? $this->users[$email]["language"] : $language);
			$status = strreplaceu(',', '-', empty($status) ? $this->users[$email]["status"] : $status);
			$pending = strreplaceu(',', '-', empty($pending) ? $this->users[$email]["pending"] : $pending);
			$home = strreplaceu(',', '-', empty($home) ? $this->users[$email]["home"] : $home);
		} else {
			$email = strreplaceu(',', '-', empty($email) ? "none" : $email);
			$hash = strreplaceu(',', '-', empty($hash) ? "none" : $hash);
			$name = strreplaceu(',', '-', empty($name) ? $this->yellow->config->get("sitename") : $name);
			$language = strreplaceu(',', '-', empty($language) ? $this->yellow->config->get("language") : $language);
			$status = strreplaceu(',', '-', empty($status) ? $this->yellow->config->get("webinterfaceUserStatus") : $status);
			$pending = strreplaceu(',', '-', empty($pending) ? $this->yellow->config->get("webinterfaceUserPending") : $pending);
			$home = strreplaceu(',', '-', empty($home) ? $this->yellow->config->get("webinterfaceUserHome") : $home);
		}
		$this->set($email, $hash, $name, $language, $status, $pending, $home);
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
			if(!empty($matches[1]) && $matches[1]==$email)
			{
				$fileDataNew .= "$email: $hash,$name,$language,$status,$pending,$home\n";
				$found = true;
			} else {
				$fileDataNew .= $line;
			}
		}
		if(!$found) $fileDataNew .= "$email: $hash,$name,$language,$status,$pending,$home\n";
		return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
	}

	// Set user data
	function set($email, $hash, $name, $language, $status, $pending, $home)
	{
		$this->users[$email] = array();
		$this->users[$email]["email"] = $email;
		$this->users[$email]["hash"] = $hash;
		$this->users[$email]["name"] = $name;
		$this->users[$email]["language"] = $language;
		$this->users[$email]["status"] = $status;
		$this->users[$email]["pending"] = $pending;
		$this->users[$email]["home"] = $home;
	}
	
	// Check user login
	function checkUser($email, $password)
	{
		$algorithm = $this->yellow->config->get("webinterfaceUserHashAlgorithm");
		return $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
			$this->yellow->toolbox->verifyHash($password, $algorithm, $this->users[$email]["hash"]);
	}

	// Check user login from browser cookie
	function checkCookie($email, $session)
	{
		return $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
			$this->yellow->toolbox->verifyHash($this->users[$email]["hash"], "sha256", $session);
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
			setcookie($cookieName, "$email,$session", time()+60*60*24*365, $location, "", $serverScheme=="https");
		}
	}
	
	// Destroy browser cookie
	function destroyCookie($cookieName)
	{
		$serverScheme = $this->yellow->config->get("webinterfaceServerScheme");
		$location = $this->yellow->config->get("serverBase").$this->yellow->config->get("webinterfaceLocation");
		setcookie($cookieName, "", time()-3600, $location, "", $serverScheme=="https");
	}
	
	// Check user request
	function checkUserRequest($email, $action, $expire, $id)
	{
		switch($action)
		{
			case "confirm":	$status = "unconfirmed"; break;
			case "approve":	$status = "unapproved"; break;
			default:		$status = "active"; break;
		}
		return $this->isExisting($email) && $this->users[$email]["status"]==$status && $expire>time() &&
			$this->yellow->toolbox->verifyHash($this->users[$email]["hash"].$action.$expire, "sha256", $id);
	}
	
	// Create user request ID
	function createUserRequestId($email, $action, $expire)
	{
		return $this->yellow->toolbox->createHash($this->users[$email]["hash"].$action.$expire, "sha256");
	}
	
	// Return user hash
	function getHash($email = "")
	{
		return $this->isExisting($email) ? $this->users[$email]["hash"] : "";
	}
	
	// Return user name
	function getName($email = "")
	{
		return $this->isExisting($email) ? $this->users[$email]["name"] : "";
	}

	// Return user language
	function getLanguage($email = "")
	{
		return $this->isExisting($email) ? $this->users[$email]["language"] : "";
	}	
	
	// Return user status
	function getStatus($email = "")
	{
		return $this->isExisting($email) ? $this->users[$email]["status"] : "";
	}
	
	// Return user pending
	function getPending($email = "")
	{
		return $this->isExisting($email) ? $this->users[$email]["pending"] : "";
	}
	
	// Return user home
	function getHome($email = "")
	{
		return $this->isExisting($email) ? $this->users[$email]["home"] : "";
	}
	
	// Return number of users
	function getNumber()
	{
		return count($this->users);
	}

	// Check if web master exists
	function isWebmaster()
	{
		return substru($this->yellow->config->get("email"), 0, 7)!="noreply";
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
	const ADD = '+';	//merge types
	const MODIFY = '*';
	const REMOVE = '-';
	const SAME = ' ';
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
	}
	
	// Merge text, null if not possible
	function merge($textSource, $textMine, $textYours, $showDiff = false)
	{
		if($textMine!=$textYours)
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
		for($pos=0; $pos<$textStart; ++$pos) array_push($diff, array(YellowMerge::SAME, $textSource[$pos], false));
		$lcs = $this->buildDiffLCS($textSource, $textOther, $textStart, $sourceEnd-$textStart, $otherEnd-$textStart);
		for($x=0,$y=0,$xEnd=$otherEnd-$textStart,$yEnd=$sourceEnd-$textStart; $x<$xEnd || $y<$yEnd;)
		{
			$max = $lcs[$y][$x];
			if($y<$yEnd && $lcs[$y+1][$x]==$max)
			{
				array_push($diff, array(YellowMerge::REMOVE, $textSource[$textStart+$y], false));
				if($lastRemove==-1) $lastRemove = count($diff)-1;
				++$y;
				continue;
			}
			if($x<$xEnd && $lcs[$y][$x+1]==$max)
			{
				if($lastRemove==-1 || $diff[$lastRemove][0]!=YellowMerge::REMOVE)
				{
					array_push($diff, array(YellowMerge::ADD, $textOther[$textStart+$x], false));
					$lastRemove = -1;
				} else {
					$diff[$lastRemove] = array(YellowMerge::MODIFY, $textOther[$textStart+$x], false);
					++$lastRemove; if(count($diff)==$lastRemove) $lastRemove = -1;
				}
				++$x;
				continue;
			}
			array_push($diff, array(YellowMerge::SAME, $textSource[$textStart+$y], false));
			$lastRemove = -1;
			++$x;
			++$y;
		}
		for($pos=$sourceEnd;$pos<$sourceSize; ++$pos) array_push($diff, array(YellowMerge::SAME, $textSource[$pos], false));
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
				if($textSource[$textStart+$y]==$textOther[$textStart+$x])
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
			if($typeMine==YellowMerge::SAME)
			{
				array_push($diff, $diffYours[$posYours]);
			} else if($typeYours==YellowMerge::SAME) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeMine==YellowMerge::ADD && $typeYours==YellowMerge::ADD) {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
			} else if($typeMine==YellowMerge::MODIFY && $typeYours==YellowMerge::MODIFY) {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
			} else if($typeMine==YellowMerge::REMOVE && $typeYours==YellowMerge::REMOVE) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeMine==YellowMerge::ADD) {
				array_push($diff, $diffMine[$posMine]);
			} else if($typeYours==YellowMerge::ADD) {
				array_push($diff, $diffYours[$posYours]);
			} else {
				$this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], true);
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
			if($typeMine==YellowMerge::ADD || $typeYours==YellowMerge::ADD)
			{
				if($typeMine==YellowMerge::ADD) ++$posMine;
				if($typeYours==YellowMerge::ADD) ++$posYours;
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
	
	// Return merged text, null if not possible
	function getOutput($diff, $showDiff = false)
	{
		$output = "";
		if(!$showDiff)
		{
			for($i=0; $i<count($diff); ++$i)
			{
				if($diff[$i][0]!=YellowMerge::REMOVE) $output .= $diff[$i][1];
				$conflict |= $diff[$i][2];
			}
		} else {
			for($i=0; $i<count($diff); ++$i)
			{
				$output .= $diff[$i][2] ? "! " : $diff[$i][0].' ';
				$output .= $diff[$i][1];
			}
		}
		return !$conflict ? $output : null;
	}
}

$yellow->plugins->register("webinterface", "YellowWebinterface", YellowWebinterface::VERSION);
?>