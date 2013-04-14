<?php
// Copyright (c) 2013 Datenstrom, http://www.datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main class
class Yellow
{
	const Version = "0.1.0";
	var $page;				//current page data
	var $pages;				//current page tree, top level
	var $toolbox;			//toolbox with helpers
	var $config;			//site configuration
	var $text;				//site text strings
	var $plugins;			//site plugins

	function __construct()
	{
		$this->toolbox = new Yellow_Toolbox();
		$this->config = new Yellow_Config();
		$this->text = new Yellow_Text();
		$this->plugins = new Yellow_Plugins();
		$this->config->setDefault("sitename", "Yellow");
		$this->config->setDefault("author", "Yellow");
		$this->config->setDefault("language", "en");
		$this->config->setDefault("parser", "markdown");
		$this->config->setDefault("template", "default");
		$this->config->setDefault("yellowVersion", Yellow::Version);
		$this->config->setDefault("baseLocation", $this->toolbox->getBaseLocation());
		$this->config->setDefault("stylesLocation", "/media/styles/");
		$this->config->setDefault("imagesLocation", "/media/images/");
		$this->config->setDefault("pluginsLocation", "media/plugins/");
		$this->config->setDefault("systemDir", "system/");
		$this->config->setDefault("configDir", "system/config/");
		$this->config->setDefault("pluginDir", "system/plugins/");
		$this->config->setDefault("snippetDir", "system/snippets/");
		$this->config->setDefault("templateDir", "system/templates/");
		$this->config->setDefault("contentDir", "content/");
		$this->config->setDefault("contentHomeDir", "1-home/");
		$this->config->setDefault("contentDefaultFile", "page.txt");
		$this->config->setDefault("contentExtension", ".txt");
		$this->config->setDefault("configExtension", ".ini");
		$this->config->setDefault("systemExtension", ".php");
		$this->config->setDefault("configFile", "config.ini");
		$this->config->setDefault("errorPageFile", "error(.*).txt");
		$this->config->setDefault("textStringFile", "text_(.*).ini");
		$this->config->load($this->config->get("configDir").$this->config->get("configFile"));
		$this->text->load($this->config->get("configDir").$this->config->get("textStringFile"), $this->toolbox);
	}

	// Start and handle request
	function request()
	{
		$this->toolbox->timerStart($time);
		$this->plugins->load();
		$this->processRequest();
		$this->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::request time:$time ms<br>\n";
	}

	// Process request
	function processRequest()
	{
		$statusCode = 0;
		$baseLocation = $this->config->get("baseLocation");
		$location = $this->getRelativeLocation($baseLocation);
		$fileName = $this->getContentFileName($location);
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onRequest"))
			{
				$statusCode = $value["obj"]->onRequest($baseLocation, $location, $fileName);
				if($statusCode) break;
			}
		}
		if($statusCode == 0) $statusCode = $this->processRequestFile($baseLocation, $location, $fileName, $statusCode);
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequest status:$statusCode location:$location<br>\n";
	}

	// Process request for a file
	function processRequestFile($baseLocation, $location, $fileName, $statusCode, $cache = true)
	{
		if($statusCode == 0)
		{
			if(is_readable($fileName))
			{
				$time = gmdate("D, d M Y H:i:s", filemtime($fileName))." GMT";
				if(isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && $_SERVER["HTTP_IF_MODIFIED_SINCE"]==$time && $cache)
				{
					$statusCode = 304;
					$this->sendStatus($statusCode);
				} else {
					$statusCode = 200;
					header("Content-Type: text/html; charset=UTF-8");
					if($cache)
					{
						header("Last-Modified: ".$time);
					} else {
						header("Cache-Control: no-cache");
						header("Pragma: no-cache");
						header("Expires: 0");
					}
					$fileHandle = @fopen($fileName, "r");
					if($fileHandle)
					{
						$fileData = fread($fileHandle, filesize($fileName));
						fclose($fileHandle);
					} else {
						die("Server problem: Can't read file '$fileName'!");
					}
				}
			} else {
				if($this->toolbox->isFileLocation($location) && is_dir($this->getContentDirectory("$location/")))
				{
					$statusCode = 301;
					$this->sendStatus($statusCode, "Location: http://$_SERVER[SERVER_NAME]$baseLocation$location/");
				} else {
					$statusCode = 404;
				}
			}
		}
		if($statusCode >= 400)
		{
			header($this->toolbox->getHttpStatusFormated($statusCode));
			header("Content-Type: text/html; charset=UTF-8");
			$fileName = str_replace("(.*)", $statusCode, $this->config->get("configDir").$this->config->get("errorPageFile"));
			$fileHandle = @fopen($fileName, "r");
			if($fileHandle)
			{
				$fileData = fread($fileHandle, filesize($fileName));
				fclose($fileHandle);
			} else {
				die("Configuration problem: Can't open file '$fileName'!");
			}
		}
		if($fileData != "") $this->sendPage($baseLocation, $location, $fileName, $fileData, $statusCode);
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequestFile base:$baseLocation file:$fileName<br>\n";
		return $statusCode;
	}
	
	// Send status response
	function sendStatus($statusCode, $text = "")
	{
		header($this->toolbox->getHttpStatusFormated($statusCode));
		if($text != "") header($text);
	}
	
	// Send page response
	function sendPage($baseLocation, $location, $fileName, $fileData, $statusCode)
	{
		$this->pages = new Yellow_Pages($baseLocation, $this->toolbox, $this->config);
		$this->page = new Yellow_Page($baseLocation, $location, $fileName, $fileData, $this->toolbox, $this->config);
		$this->text->setLanguage($this->page->get("language"));
		
		$text = $this->page->getContentRawText();
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseBefore"))
			{
				$text = $value["obj"]->onParseBefore($text, $statusCode);
			}
		}
		if(!$this->plugins->isExisting($this->page->get("parser"))) die("Parser '".$this->page->get("parser")."' does not exist!");
		$this->page->parser = $this->plugins->plugins[$this->page->get("parser")]["obj"];
		$text = $this->page->parser->parse($text);
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseAfter"))
			{
				$text = $value["obj"]->onParseAfter($text, $statusCode);
			}
		}
		$this->page->setContent($text);

		if(!$this->page->isExisting("description"))
		{
			$this->page->set("description", $this->toolbox->createTextDescription($this->page->getContent(), 150));
		}
		if(!$this->page->isExisting("keywords"))
		{
			$this->page->set("keywords", $this->toolbox->createTextKeywords($this->page->get("title"), 10));
		}
		
		$fileName = $this->config->get("templateDir").$this->page->get("template").$this->config->get("systemExtension");
		if(!is_file($fileName)) die("Template '".$this->page->get("template")."' does not exist!");
		global $yellow;
		require($fileName);
	}

	// Execute a template snippet
	function snippet($snippet)
	{
		$fileName = $this->config->get("snippetDir").$snippet.$this->config->get("systemExtension");
		if(!is_file($fileName)) die("Snippet '$snippet' does not exist!");
		global $yellow;
		require($fileName);
	}
	
	// Return extra HTML header lines generated from plugins
	function getHeaderExtra()
	{
		$header = "";
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onHeaderExtra")) $header .= $value["obj"]->onHeaderExtra();
		}
		return $header;
	}
	
	// Return content location for current HTTP request, without base location
	function getRelativeLocation($baseLocation)
	{
		$location = $this->toolbox->getRequestLocation();
		$location = $this->toolbox->normaliseLocation($location);
		$position = strlen($baseLocation);
		return substr($location, $position);
	}

	// Return content file name from location
	function getContentFileName($location)
	{
		return $this->toolbox->findFileFromLocation($location,
					$this->config->get("contentDir"), $this->config->get("contentHomeDir"),
					$this->config->get("contentDefaultFile"), $this->config->get("contentExtension"));
	}
	
	// Return content directory from location
	function getContentDirectory($location)
	{
		return $this->toolbox->findFileFromLocation($location,
					$this->config->get("contentDir"), $this->config->get("contentHomeDir"), "", "");
	}
	
	// Register plugin
	function registerPlugin($name, $class, $version)
	{
		$this->plugins->register($name, $class, $version);
	}
}

// Yellow page data
class Yellow_Page
{
	var $baseLocation;		//base location
	var $location;			//page location
	var $fileName;			//content file name
	var $parser;			//content parser
	var $metaData;			//meta data of page
	var $rawData;			//raw data of page (unparsed)
	var $rawTextPos;		//raw text of page (unparsed)
	var $active;			//page is active?
	var $hidden;			//page is hidden?

	function __construct($baseLocation, $location, $fileName, $rawData, $toolbox, $config)
	{
		$this->baseLocation = $baseLocation;
		$this->location = $location;
		$this->fileName = $fileName;
		$this->setRawData($rawData, $toolbox, $config);
		$this->active = $toolbox->isActiveLocation($baseLocation, $location);
		$this->hidden = $toolbox->isHiddenLocation($baseLocation, $location, $fileName, $config->get("contentDir"));
	}
	
	// Set page raw data
	function setRawData($rawData, $toolbox, $config)
	{
		$this->metaData = array();
		$this->rawData = $rawData;
		$this->rawTextPos = 0;
		$this->set("title", $toolbox->createTextTitle($this->location));
		$this->set("author", $config->get("author"));
		$this->set("language", $config->get("language"));
		$this->set("parser", $config->get("parser"));
		$this->set("template", $config->get("template"));
		
		if(preg_match("/^(\-\-\-[\r\n]+)(.+?)([\r\n]+\-\-\-[\r\n]+)/s", $rawData, $parsed))
		{
			$this->rawTextPos = strlen($parsed[1]) + strlen($parsed[2]) + strlen($parsed[3]);
			preg_match_all("/([^\:\r\n]+)\s*\:\s*([^\r\n]+)/s", $parsed[2], $matches, PREG_SET_ORDER);
			foreach($matches as $match)
			{
				$this->set(strtolower($match[1]), $match[2]);
			}
		} else if(preg_match("/^([^\r\n]+)([\r\n]+=+[\r\n]+)/", $rawData, $parsed)) {
			$this->rawTextPos = strlen($parsed[1]) + strlen($parsed[2]);
			$this->set("title", $parsed[1]);
		}
	}

	// Set page meta data
	function set($key, $value)
	{
		$this->metaData[$key] = $value;
	}

	// Return page meta data
	function get($key)
	{
		return $this->IsExisting($key) ? $this->metaData[$key] : "";
	}

	// Return page meta data, HTML encoded
	function getHtml($key)
	{
		return htmlspecialchars($this->get($key));
	}

	// Return page title, HTML encoded
	function getTitle()
	{
		return $this->getHtml("title");
	}
	
	// Set page content, HTML encoded
	function setContent($html)
	{
		$this->parser->html = $html;
	}
	
	// Return page content, HTML encoded
	function getContent()
	{
		return $this->parser->html;
	}
	
	// Return page content, raw text
	function getContentRawText()
	{
		return substr($this->rawData, $this->rawTextPos);
	}
	
	// Return absolut page location
	function getLocation()
	{
		return $this->baseLocation.$this->location;
	}
	
	// Check if meta data exists
	function isExisting($key)
	{
		return !is_null($this->metaData[$key]);
	}
	
	// Check if page is active
	function isActive()
	{
		return $this->active;
	}

	// Check if page is active
	function isHidden()
	{
		return $this->hidden;
	}
}

// Yellow page tree from file system
class Yellow_Pages
{
	var $pages;		//scanned pages
	
	function __construct($baseLocation, $toolbox, $config)
	{
		$this->scan($baseLocation, $toolbox, $config);
	}
	
	// Scan top-level pages
	function scan($baseLocation, $toolbox, $config)
	{
		$this->pages = array();
		foreach($toolbox->getDirectoryEntries($config->get("contentDir"), "/.*/", true) as $entry)
		{
			$fileName = $config->get("contentDir").$entry."/".$config->get("contentDefaultFile");
			$location = $toolbox->findLocationFromFile($fileName, $config->get("contentDir"), $config->get("contentHomeDir"),
													   $config->get("contentDefaultFile"), $config->get("contentExtension"));
			$fileHandle = @fopen($fileName, "r");
			if($fileHandle)
			{
				$fileData = fread($fileHandle, 4096);
				fclose($fileHandle);
			} else {
				$fileData = "";
			}
			$page = new Yellow_Page($baseLocation, $location, $fileName, $fileData, $toolbox, $config);
			array_push($this->pages, $page);
		}
	}
	
	// Return top-level pages
	function root($showHidden = false)
	{
		$pages = array();
		foreach($this->pages as $page)
		{
			if($showHidden || !$page->isHidden()) array_push($pages, $page);
		}
		return $pages;
	}
}
	
// Yellow toolbox with helpers
class Yellow_Toolbox
{
	// Return location from current HTTP request
	static function getRequestLocation()
	{
		$uri = $_SERVER["REQUEST_URI"];
		return ($pos = strpos($uri, '?')) ? substr($uri, 0, $pos) : $uri;
	}

	// Return arguments from current HTTP request
	static function getRequestLocationArguments()
	{
		$uri = $_SERVER["REQUEST_URI"];
		return ($pos = strpos($uri, '?')) ? substr($uri, $pos+1) : "";
	}
	
	// Return base location
	static function getBaseLocation()
	{
		$baseLocation = "/";
		if(preg_match("/^(.*)\//", $_SERVER["SCRIPT_NAME"], $matches)) $baseLocation = $matches[1];
		return $baseLocation;
	}
	
	// Normalise location and remove incorrect path tokens
	static function normaliseLocation($location)
	{
		$str = str_replace('\\', '/', rawurldecode($location));
		$location = ($str[0]=='/') ? '' : '/';
		for($pos=0; $pos<strlen($str); ++$pos)
		{
			if($str[$pos] == '/')
			{
				if($str[$pos+1] == '/') continue;
				if($str[$pos+1] == '.')
				{
					$posNew = $pos+1; while($str[$posNew] == '.') ++$posNew;
					if($str[$posNew]=='/' || $str[$posNew]=='')
					{
						$pos = $posNew-1; 
						continue;
					}
				}
			}
			$location .= $str[$pos];
		}
		return $location;
	}
	
	// Check if location is specifying file or directory
	static function isFileLocation($location)
	{
		return substr($location,-1,1) != "/";
	}
	
	// Check if location is within current HTTP request
	static function isActiveLocation($baseLocation, $location)
	{
		$currentLocation = substr(self::getRequestLocation(), strlen($baseLocation));
		if($location != "/")
		{
			$active = substr($currentLocation, 0, strlen($location))==$location;
		} else {
			$active = $currentLocation==$location;
		}
		return $active;
	}
	
	// Check if location is within visible collection
	static function isHiddenLocation($baseLocation, $location, $fileName, $pathBase)
	{
		$hidden = false;
		if(substr($fileName, 0, strlen($pathBase)) == $pathBase) $fileName = substr($fileName, strlen($pathBase));
		$tokens = explode('/', $fileName);
		for($i=0; $i<count($tokens)-1; ++$i)
		{
			if(!preg_match("/^[\d\-\.]+(.*)$/", $tokens[$i]))
			{
				$hidden = true;
				break;
			}
		}
		return $hidden;
	}
	
	// Find file path from location
	static function findFileFromLocation($location, $pathBase, $pathHome, $fileDefault, $fileExtension)
	{
		$path = $pathBase;
		if($location != "/")
		{
			$tokens = explode('/', $location);
			for($i=1; $i<count($tokens)-1; ++$i)
			{
				$entries = self::getDirectoryEntries($path, "/^[\d\-\.]+".$tokens[$i]."$/");
				if(!empty($entries)) $tokens[$i] = $entries[0];
				$path .= "$tokens[$i]/";
			}
			if($tokens[$i] != "")
			{
				$path .= $tokens[$i].$fileExtension;
			} else {
				$path .= $fileDefault;
			}
		} else {
			$path .= $pathHome.$fileDefault;
		}
		return $path;
	}
	
	// Find location from file path
	static function findLocationFromFile($fileName, $pathBase, $pathHome, $fileDefault, $fileExtension)
	{
		$location = "/";
		if(substr($fileName, 0, strlen($pathBase)) == $pathBase) $fileName = substr($fileName, strlen($pathBase));
		if(substr($fileName, 0, strlen($pathHome)) != $pathHome)
		{
			$tokens = explode('/', $fileName);
			for($i=0; $i<count($tokens)-1; ++$i)
			{
				if(preg_match("/^[\d\-\.]+(.*)$/", $tokens[$i], $matches)) $tokens[$i] = $matches[1];
				$location .= "$tokens[$i]/";
			}
			if($tokens[$i] != $fileDefault)
			{
				$location .= substr($tokens[$i], 0, -strlen($fileExtension)-1);
			}
		} else {
			if($fileName != $pathHome.$fileDefault)
			{
				$location .= substr($fileName, $pathHome, -strlen($fileExtension)-1);
			}
		}
		return $location;
	}
	
	// Return human readable HTTP server status
	static function getHttpStatusFormated($statusCode)
	{
		switch($statusCode)
		{
			case 301: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Moved permanently"; break;
			case 302: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Moved temporarily"; break;
			case 304: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Not modified"; break;
			case 401: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Unauthorised"; break;
			case 404: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Not found"; break;
			case 424: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Does not exist"; break;
			default: die("Unknown HTTP status $statusCode!");
		}
		return $text;
	}
	
	// Return files and directories
	static function getDirectoryEntries($path, $regex = "/.*/", $sort = false, $directories = true)
	{
		$entries = array();
		$dirHandle = @opendir($path);
		if($dirHandle)
		{
			while(($entry = readdir($dirHandle)) !== false)
			{
				if(substr($entry, 0, 1) == ".") continue;
				if(preg_match($regex, $entry))
				{
					if($directories)
					{
						if(is_dir("$path/$entry")) array_push($entries, $entry);
					} else {
						if(is_file("$path/$entry")) array_push($entries, $entry);
					}
				}
			}
			if($sort) natsort($entries);
			closedir($dirHandle);
		}
		return $entries;
	}

	// Create description from text
	static function createTextDescription($text, $lengthMax)
	{
		$description = "";
		preg_match_all("/\<p\>(.+?\<\/p\>)/s", $text, $parsedDescription, PREG_SET_ORDER);
		foreach($parsedDescription as $matches)
		{
			preg_match_all("/([^\<]*)[^\>]+./s", $matches[1], $parsedUndoTag, PREG_SET_ORDER);
			if(count($parsedUndoTag) > 0)
			{
				if(!empty($description)) $description .= " ";
				foreach($parsedUndoTag as $matchTag)
				{
					$description .= preg_replace("/[\\x00-\\x1f]+/s", " ", $matchTag[1]);
				}
			}
			if(strlen($description) > $lengthMax)
			{
				$description = substr($description, 0, $lengthMax-3)."...";
				break;
			}
		}
		return $description;
	}

	// Create keywords from text string
	static function createTextKeywords($text, $keywordsMax)
	{
		$tokens = preg_split("/[,\s\(\)]/", strtolower($text));
		foreach($tokens as $key => $value) if(strlen($value) < 3) unset($tokens[$key]);
		return implode(", ", array_slice(array_unique($tokens), 0, $keywordsMax));
	}
	
	// Create title from text string
	static function createTextTitle($text)
	{
		if(preg_match("/^.*\/(\w*)/", $text, $matches)) $text = ucfirst($matches[1]);
		return $text;
	}
	
	// Detect web browser language
	function detectBrowserLanguage($languagesAllowed, $languageDefault)
	{
		$language = $languageDefault;
		if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
		{
			foreach(preg_split("/,\s*/", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $string)
			{
				$tokens = split(";", $string, 2);
				if(in_array($tokens[0], $languagesAllowed))
				{
					$language = $tokens[0];
					break;
				}
			}
		}
		return $language;
	}

	// Detect PNG and JPG image dimensions
	static function detectImageDimensions($fileName)
	{
		$width = $height = 0;
		$fileHandle = @fopen($fileName, "rb");
		if($fileHandle)
		{
			if(substr($fileName, -3) == "png")
			{
				$dataSignature = fread($fileHandle, 8);
				$dataHeader = fread($fileHandle, 25);
				if(!feof($fileHandle) && $dataSignature=="\x89PNG\r\n\x1a\n")
				{
					$width = (ord($dataHeader[10])<<8) + ord($dataHeader[11]);
					$height = (ord($dataHeader[14])<<8) + ord($dataHeader[15]);
				}
			} else if(substr($fileName, -3) == "jpg") {
				$dataSignature = fread($fileHandle, 11);
				$dataHeader = fread($fileHandle, 147);
				$dataHeader = fread($fileHandle, 16);
				if(!feof($fileHandle) && $dataSignature=="\xff\xd8\xff\xe0\x00\x10JFIF\0")
				{
					$width = (ord($dataHeader[7])<<8) + ord($dataHeader[8]);
					$height = (ord($dataHeader[5])<<8) + ord($dataHeader[6]);
				}
			}
			fclose($fileHandle);
		}
		return array($width, $height);
	}

	// Start timer
	static function timerStart(&$time)
	{
		$time = microtime(true);
	}
	
	// Stop timer and calcuate elapsed time (milliseconds)
	static function timerStop(&$time)
	{
		$time = intval((microtime(true)-$time) * 1000);
	}
}

// Yellow configuration
class Yellow_Config
{
	var $config;			//configuration
	var $configDefaults;	//configuration defaults
	
	function __construct()
	{
		$this->config = array();
		$this->configDefaults = array();
	}
	
	// Load configuration from file
	function load($fileName)
	{
		$fileData = @file($fileName);
		if($fileData)
		{
			if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Config::load file:$fileName<br/>\n";
			foreach($fileData as $line)
			{
				if(preg_match("/^\//", $line)) continue;
				preg_match("/^\s*(.*?)\s*=\s*(.*?)\s*$/", $line, $matches);
				if($matches[1]!="" && $matches[2]!="")
				{
					$this->set($matches[1], $matches[2]);
					if(defined("DEBUG") && DEBUG>=3) echo "Yellow_Config::load key:$matches[1] $matches[2]<br/>\n";
				}
			}
		}
	}
	
	// Set default configuration
	function setDefault($key, $value)
	{
		$this->configDefaults[$key] = $value;
	}
	
	// Set configuration
	function set($key, $value)
	{
		$this->config[$key] = $value;
	}
	
	// Return configuration
	function get($key)
	{
		return $this->isExisting($key) ? $this->config[$key] : $this->configDefaults[$key];
	}
	
	// Return configuration, HTML encoded
	function getHtml($key)
	{
		return htmlspecialchars($this->get($key));
	}
	
	// Return configuration strings
	function getData($filterEnd = "")
	{
		$config = array();
		if($filterEnd == "")
		{
			$config = $this->config;
		} else {
			foreach($this->config as $key=>$value)
			{
				if(substr($key, -strlen($filterEnd)) == $filterEnd) $config[$key] = $value;
			}
		}
		return $config;
	}
	
	// Check if configuration exists
	function isExisting($key)
	{
		return !is_null($this->config[$key]);
	}
}
	
// Yellow text strings
class Yellow_Text
{
	var $text;			//text strings
	var $language;		//current language

	function __construct()
	{
		$this->text = array();
	}
	
	// Load text strings from file
	function load($fileName, $toolbox)
	{
		$path = dirname($fileName);
		$regex = basename($fileName);
		foreach($toolbox->getDirectoryEntries($path, "/$regex/", true, false) as $entry)
		{
			$fileData = @file("$path/$entry");
			if($fileData)
			{
				if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Text::load file:$path/$entry<br/>\n";
				$language = "";
				foreach($fileData as $line)
				{
					preg_match("/^\s*(.*?)\s*=\s*(.*?)\s*$/", $line, $matches);
					if($matches[1]=="language" && $matches[2]!="") { $language = $matches[2]; break; }
				}
				foreach($fileData as $line)
				{
					if(preg_match("/^\//", $line)) continue;
					preg_match("/^\s*(.*?)\s*=\s*(.*?)\s*$/", $line, $matches);
					if($language!="" && $matches[1]!="" && $matches[2]!="")
					{
						$this->setLanguageText($language, $matches[1], $matches[2]);
						if(defined("DEBUG") && DEBUG>=3) echo "Yellow_Text::load key:$matches[1] $matches[2]<br/>\n";
					}
				}
			}
		}
	}
	
	// Set current language
	function setLanguage($language)
	{
		$this->language = $language;
	}
	
	// Set text string
	function setLanguageText($language, $key, $value)
	{
		if(is_null($this->text[$language])) $this->text[$language] = array();
		$this->text[$language][$key] = $value;
	}
	
	// Return text string
	function get($key)
	{
		return $this->isExisting($key) ? $this->text[$this->language][$key] : "[$key]";
	}
	
	// Return text string, HTML encoded
	function getHtml($key)
	{
		return htmlspecialchars($this->get($key)); 
	}
	
	// Return text strings
	function getData($language, $filterStart = "")
	{
		$text = array();
		if(!is_null($this->text[$language]))
		{
			if($filterStart == "")
			{
				$text = $this->text[$language];
			} else {
				foreach($this->text[$language] as $key=>$value)
				{
					if(substr($key, 0, strlen("language")) == "language") $text[$key] = $value;
					if(substr($key, 0, strlen($filterStart)) == $filterStart) $text[$key] = $value;
				}
			}
		}
		return $text;
	}
	
	// Check if text string exists
	function isExisting($key)
	{
		return !is_null($this->text[$this->language]) && !is_null($this->text[$this->language][$key]);
	}
}

// Yellow plugins
class Yellow_Plugins
{
	var $plugins;		//registered plugins

	function __construct()
	{
		$this->plugins = array();
	}
	
	// Load plugins
	function load()
	{
		global $yellow;
		require_once("core_markdown.php");
		require_once("core_rawhtml.php");
		require_once("core_webinterface.php");
		foreach($yellow->toolbox->getDirectoryEntries($yellow->config->get("pluginDir"), "/.*\.php/", true, false) as $entry)
		{
			$fileName = $yellow->config->get("pluginDir")."/$entry";
			require_once($fileName);
		}
		foreach($this->plugins as $key=>$value)
		{
			$this->plugins[$key]["obj"] = new $value["class"];
			if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Plugins::load class:$value[class] $value[version]<br/>\n";
			if(method_exists($this->plugins[$key]["obj"], "initPlugin"))
			{
				$this->plugins[$key]["obj"]->initPlugin($yellow);
			}
		}
	}
	
	// Register plugin
	function register($name, $class, $version)
	{
		if(!$this->isExisting($name))
		{
			$this->plugins[$name] = array();
			$this->plugins[$name]["class"] = $class;
			$this->plugins[$name]["version"] = $version;
		}
	}
	
	// Check if plugin exists
	function isExisting($name)
	{
		return !is_null($this->plugins[$name]);
	}
}
?>