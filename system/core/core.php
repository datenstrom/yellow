<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main class
class Yellow
{
	const Version = "0.1.8";
	var $page;				//current page data
	var $pages;				//current page tree from file system
	var $toolbox;			//toolbox with helpers
	var $config;			//configuration
	var $text;				//text strings
	var $plugins;			//plugins

	function __construct()
	{
		$this->pages = new Yellow_Pages($this);
		$this->toolbox = new Yellow_Toolbox();
		$this->config = new Yellow_Config();
		$this->text = new Yellow_Text();
		$this->plugins = new Yellow_Plugins();
		$this->config->setDefault("sitename", "Yellow");
		$this->config->setDefault("author", "Yellow");
		$this->config->setDefault("language", "en");
		$this->config->setDefault("template", "default");
		$this->config->setDefault("style", "default");
		$this->config->setDefault("parser", "markdown");
		$this->config->setDefault("yellowVersion", Yellow::Version);
		$this->config->setDefault("serverName", $this->toolbox->getServerName());
		$this->config->setDefault("baseLocation", $this->toolbox->getServerBase());
		$this->config->setDefault("styleLocation", "/media/styles/");
		$this->config->setDefault("imageLocation", "/media/images/");
		$this->config->setDefault("pluginLocation", "media/plugins/");
		$this->config->setDefault("systemDir", "system/");
		$this->config->setDefault("configDir", "system/config/");
		$this->config->setDefault("pluginDir", "system/plugins/");
		$this->config->setDefault("snippetDir", "system/snippets/");
		$this->config->setDefault("templateDir", "system/templates/");
		$this->config->setDefault("mediaDir", "media/");
		$this->config->setDefault("styleDir", "media/styles/");
		$this->config->setDefault("imageDir", "media/images/");
		$this->config->setDefault("contentDir", "content/");
		$this->config->setDefault("contentHomeDir", "1-home/");
		$this->config->setDefault("contentDefaultFile", "page.txt");
		$this->config->setDefault("contentExtension", ".txt");
		$this->config->setDefault("configExtension", ".ini");
		$this->config->setDefault("configFile", "config.ini");
		$this->config->setDefault("errorPageFile", "error(.*).txt");
		$this->config->setDefault("textStringFile", "text_(.*).ini");
		$this->config->load($this->config->get("configDir").$this->config->get("configFile"));
		$this->text->load($this->config->get("configDir").$this->config->get("textStringFile"), $this->toolbox);
	}
	
	// Handle request
	function request()
	{
		ob_start();
		$this->toolbox->timerStart($time);
		$baseLocation = $this->config->get("baseLocation");
		$location = $this->getRelativeLocation($baseLocation);
		$fileName = $this->getContentFileName($location);
		$statusCode = 0;
		$this->page = new Yellow_Page($this, $location);
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onRequest"))
			{
				$statusCode = $value["obj"]->onRequest($baseLocation, $location, $fileName);
				if($statusCode) break;
			}
		}
		if($statusCode == 0) $statusCode = $this->processRequest($baseLocation, $location, $fileName, true, $statusCode);
		if($this->page->isExisting("pageError"))
		{
			ob_clean();
			$statusCode = $this->processRequestError();
		}
		$this->toolbox->timerStop($time);
		ob_end_flush();
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::request status:$statusCode location:$location<br>\n";
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::request time:$time ms<br>\n";
		return $statusCode;
	}
	
	// Process request
	function processRequest($baseLocation, $location, $fileName, $cacheable, $statusCode)
	{
		if($statusCode == 0)
		{
			if(is_readable($fileName))
			{
				$statusCode = 200;
				$fileName = $this->readPage($baseLocation, $location, $fileName, $cacheable, $statusCode);
			} else {
				if($this->toolbox->isFileLocation($location) && is_dir($this->getContentDirectory("$location/")))
				{
					$statusCode = 301;
					$serverName = $this->config->get("serverName");
					$this->sendStatus($statusCode, "Location: http://$serverName$baseLocation$location/");
				} else {
					$statusCode = 404;
					$fileName = $this->readPage($baseLocation, $location, $fileName, $cacheable, $statusCode);
				}
			}
		} else if($statusCode >= 400) {
			$fileName = $this->readPage($baseLocation, $location, $fileName, $cacheable, $statusCode);
		}
		if($this->page->statusCode != 0) $statusCode = $this->sendPage();
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequest base:$baseLocation file:$fileName<br>\n";
		return $statusCode;
	}
	
	// Process request with error
	function processRequestError()
	{
		$baseLocation = $this->pages->baseLocation;
		$fileName = $this->readPage($baseLocation, $this->page->location, $this->page->fileName, $this->page->cacheable,
			$this->page->statusCode, $this->page->get("pageError"));
		$statusCode = $this->sendPage();
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequestError base:$baseLocation file:$fileName<br>\n";
		return $statusCode;		
	}
	
	// Read page from file
	function readPage($baseLocation, $location, $fileName, $cacheable, $statusCode, $pageError = "")
	{
		if($statusCode >= 400)
		{
			$fileName = $this->config->get("configDir").$this->config->get("errorPageFile");
			$fileName = strreplaceu("(.*)", $statusCode, $fileName);
			$cacheable = false;
		}
		$fileHandle = @fopen($fileName, "r");
		if($fileHandle)
		{
			$fileData = fread($fileHandle, filesize($fileName));
			fclose($fileHandle);
		}
		$this->pages->baseLocation = $baseLocation;
		$this->page = new Yellow_Page($this, $location);
		$this->page->parseData($fileName, $fileData, $cacheable, $statusCode, $pageError);
		$this->page->parseContent();
		$this->text->setLanguage($this->page->get("language"));
		return $fileName;
	}
	
	// Send page response
	function sendPage()
	{
		$this->template($this->page->get("template"));
		$fileNameTemplate = $this->config->get("templateDir").$this->page->get("template").".php";
		$fileNameStyle = $this->config->get("styleDir").$this->page->get("style").".css";
		if(!is_file($fileNameStyle))
		{
			$this->page->error(500, "Style '".$this->page->get("style")."' does not exist!");
		}
		if(!$this->plugins->isExisting($this->page->get("parser")))
		{
			$this->page->error(500, "Parser '".$this->page->get("parser")."' does not exist!");
		}

		$statusCode = $this->page->statusCode;
		if($statusCode==200 && $this->page->isCacheable() &&
		   $this->toolbox->isFileNotModified($this->page->getHeader("Last-Modified")))
		{
			ob_clean();
			$statusCode = 304;
		}
		if(PHP_SAPI != "cli")
		{
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
			if($statusCode != 304) foreach($this->page->headerData as $key=>$value) @header("$key: $value");
		}
		if(defined("DEBUG") && DEBUG>=1)
		{
			foreach($this->page->headerData as $key=>$value) echo "Yellow::sendPage $key: $value<br>\n";
			echo "Yellow::sendPage template:$fileNameTemplate style:$fileNameStyle<br>\n";
		}
		return $statusCode;
	}

	// Send status response
	function sendStatus($statusCode, $text = "")
	{
		@header($this->toolbox->getHttpStatusFormatted($statusCode));
		if(!empty($text)) @header($text);
	}
	
	// Execute a template
	function template($name)
	{
		$fileNameTemplate = $this->config->get("templateDir")."$name.php";
		if(is_file($fileNameTemplate))
		{
			global $yellow;
			require($fileNameTemplate);
		} else {
			$this->page->error(500, "Template '$name' does not exist!");
		}
	}
	
	// Execute a template snippet
	function snippet($name, $args = NULL)
	{
		$this->pages->snippetArgs = func_get_args();
		$fileNameSnippet = $this->config->get("snippetDir")."$name.php";
		if(is_file($fileNameSnippet))
		{
			global $yellow;
			require($fileNameSnippet);
		} else {
			$this->page->error(500, "Snippet '$name' does not exist!");
		}
	}
	
	// Return template snippet arguments
	function getSnippetArgs()
	{
		return $this->pages->snippetArgs;
	}
	
	// Return extra HTML header lines
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
		return substru($location, strlenu($baseLocation));
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
		
	// Execute a plugin command
	function plugin($name, $args = NULL)
	{
		$statusCode = 0;
		if($this->plugins->isExisting($name))
		{
			$plugin = $this->plugins->plugins[$name];
			if(method_exists($plugin["obj"], "onCommand")) $statusCode = $plugin["obj"]->onCommand(func_get_args());
		} else {
			$statusCode = 500;
			$this->page->error($statusCode, "Plugin '$name' does not exist!");
		}
		return $statusCode;
	}
	
	// Register plugin
	function registerPlugin($name, $class, $version)
	{
		$this->plugins->register($name, $class, $version);
	}
	
	// Set a response header
	function header($text)
	{
		$tokens = explode(':', $text, 2);
		$this->page->setHeader(trim($tokens[0]), trim($tokens[1]));
	}
}
	
// Yellow page data
class Yellow_Page
{
	var $yellow;				//access to API
	var $location;				//page location
	var $fileName;				//content file name
	var $rawData;				//raw data of page
	var $metaDataOffsetBytes;	//meta data offset
	var $metaData;				//meta data of page
	var $headerData;			//response header of page
	var $parser;				//parser for page content
	var $active;				//page is active location? (boolean)
	var $visible;				//page is visible location? (boolean)
	var $cacheable;				//page is cacheable? (boolean)
	var $statusCode;			//status code of page

	function __construct($yellow, $location)
	{
		$this->yellow = $yellow;
		$this->location = $location;
		$this->metaData = array();
		$this->headerData = array();
		$this->statusCode = 0;
	}
	
	// Parse page data
	function parseData($fileName, $rawData, $cacheable, $statusCode, $pageError = "")
	{
		$this->fileName = $fileName;
		$this->rawData = $rawData;
		$this->active = $this->yellow->toolbox->isActiveLocation($this->yellow->pages->baseLocation, $this->location);
		$this->visible = $this->yellow->toolbox->isVisibleLocation($this->yellow->pages->baseLocation, $this->location,
							$fileName, $this->yellow->config->get("contentDir"));
		$this->cacheable = $cacheable;
		$this->statusCode = $statusCode;
		if(!empty($pageError)) $this->error($statusCode, $pageError);
		$this->parseMeta();
	}
	
	// Parse page meta data
	function parseMeta()
	{
		$this->set("title", $this->yellow->toolbox->createTextTitle($this->location));
		$this->set("author", $this->yellow->config->get("author"));
		$this->set("language", $this->yellow->config->get("language"));
		$this->set("template", $this->yellow->config->get("template"));
		$this->set("style", $this->yellow->config->get("style"));
		$this->set("parser", $this->yellow->config->get("parser"));
		$location = $this->yellow->config->get("baseLocation").rtrim($this->yellow->config->get("webinterfaceLocation"), '/').$this->location;
		$this->set("pageEdit", $location);

		if(preg_match("/^(\-\-\-[\r\n]+)(.+?)([\r\n]+\-\-\-[\r\n]+)/s", $this->rawData, $parsed))
		{
			$this->metaDataOffsetBytes = strlenb($parsed[0]);
			preg_match_all("/([^\:\r\n]+)\s*\:\s*([^\r\n]+)/s", $parsed[2], $matches, PREG_SET_ORDER);
			foreach($matches as $match) $this->set(strtoloweru($match[1]), $match[2]);
		} else if(preg_match("/^([^\r\n]+)([\r\n]+=+[\r\n]+)/", $this->rawData, $parsed)) {
			$this->metaDataOffsetBytes = strlenb($parsed[0]);
			$this->set("title", $parsed[1]);
		}

		if($this == $this->yellow->page)
		{
			$this->setHeader("Content-Type", "text/html; charset=UTF-8");
			$this->setHeader("Last-Modified", $this->getModified(true));
			if(!$this->isCacheable()) $this->setHeader("Cache-Control", "no-cache, must-revalidate");
		}
	}
	
	// Parse page content
	function parseContent()
	{
		if(!is_object($this->parser))
		{
			$text = substrb($this->rawData, $this->metaDataOffsetBytes);
			if($this->yellow->plugins->isExisting($this->get("parser")))
			{
				$this->parser = $this->yellow->plugins->plugins[$this->get("parser")]["obj"];
				$text = $this->parser->parse($text);
			}
			foreach($this->yellow->plugins->plugins as $key=>$value)
			{
				if(method_exists($value["obj"], "onParseContent")) $text = $value["obj"]->onParseContent($text, $this->statusCode);
			}
			$this->setContent($text);
			if(!$this->isExisting("description"))
			{
				$this->set("description", $this->yellow->toolbox->createTextDescription($this->getContent(), 150));
			}
			if(!$this->isExisting("keywords"))
			{
				$this->set("keywords", $this->yellow->toolbox->createTextKeywords($this->get("title"), 10));
			}
			if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Page::parseContent location:".$this->location."<br/>\n";
		}
	}
	
	// Respond with error page
	function error($statusCode, $pageError = "")
	{
		if(!$this->isExisting("pageError"))
		{
			$this->statusCode = $statusCode;
			$this->set("pageError", empty($pageError) ? "Template/snippet error" : $pageError);
		}
	}
	
	// Set page response header
	function setHeader($key, $value)
	{
		$this->headerData[$key] = $value;
	}
	
	// Return page response header
	function getHeader($key)
	{
		return $this->isHeader($key) ? $this->headerData[$key] : "";
	}
	
	// Set page meta data
	function set($key, $value)
	{
		$this->metaData[$key] = $value;
	}

	// Return page meta data
	function get($key)
	{
		return $this->isExisting($key) ? $this->metaData[$key] : "";
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
		$this->parseContent();
		return $this->parser->html;
	}
	
	// Return absolute page location
	function getLocation()
	{
		return $this->yellow->pages->baseLocation.$this->location;
	}
	
	// Return page modification time, Unix time
	function getModified($httpFormat = false)
	{
		$modified = is_readable($this->fileName) ? filemtime($this->fileName) : "";
		if($this->isExisting("modified")) $modified = strtotime($this->get("modified"));
		return $httpFormat ? $this->yellow->toolbox->getHttpTimeFormatted($modified) : $modified;
	}
	
	// Return page status code
	function getStatusCode($httpFormat = false)
	{
		$statusCode = $this->statusCode;
		if($httpFormat)
		{
			$statusCode = $this->yellow->toolbox->getHttpStatusFormatted($statusCode);
			if($this->isExisting("pageError")) $statusCode .= ": ".$this->get("pageError");
		}
		return $statusCode;
	}
	
	// Return child pages relative to current page
	function getChildren($showHidden = false)
	{
		return $this->yellow->pages->findChildren($this->location, $showHidden);
	}

	// Return pages on the same level as current page
	function getSiblings($showHidden = false)
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->findChildren($parentLocation, $showHidden);
	}

	// Return parent page relative to current page
	function getParent()
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->find($parentLocation, false);
	}
	
	// Check if response header exists
	function isHeader($key)
	{
		return !is_null($this->headerData[$key]);
	}

	// Check if meta data exists
	function isExisting($key)
	{
		return !is_null($this->metaData[$key]);
	}
	
	// Check if page is within current HTTP request
	function isActive()
	{
		return $this->active;
	}

	// Check if page is visible in navigation
	function isVisible()
	{
		return $this->visible;
	}
	
	// Check if page is cacheable
	function isCacheable()
	{
		return $this->cacheable;
	}
}

// Yellow page collection as array
class Yellow_PageCollection extends ArrayObject
{
	var $yellow;				//access to API
	var $location;				//common location
	var $paginationPage;		//current page number in pagination
	var $paginationCount;		//highest page number in pagination
	
	function __construct($yellow, $location)
	{
		parent::__construct(array());
		$this->yellow = $yellow;
		$this->location = $location;
	}
	
	// Filter page collection by meta data
	function filter($key, $value, $exactMatch = true)
	{
		if(!empty($key))
		{
			$array = array();
			$value = strtoloweru($value);
			$valueLength = strlenu($value);
			foreach($this->getArrayCopy() as $page)
			{
				if($page->isExisting($key))
				{
					foreach(preg_split("/,\s*/", strtoloweru($page->get($key))) as $valuePage)
					{
						$length = $exactMatch ? strlenu($valuePage) : $valueLength;
						if($value == substru($valuePage, 0, $length)) array_push($array, $page);
					}
				}
			}
			$this->exchangeArray($array);
		}
		return $this;
	}

	// Merge page collection
	function merge($input)
	{
		$this->exchangeArray(array_merge($this->getArrayCopy(), (array)$input));
		return $this;
	}
	
	// Reverse page collection
	function reverse($entriesMax = 0)
	{
		$this->exchangeArray(array_reverse($this->getArrayCopy()));
		return $this;
	}

	// Paginate page collection
	function pagination($limit, $reverse = true)
	{
		$array = $this->getArrayCopy();
		if($reverse) $array = array_reverse($array);
		$this->paginationPage = 1;
		$this->paginationCount = ceil($this->count() / $limit);
		if($limit < $this->count() && isset($_REQUEST["page"])) $this->paginationPage = max(1, $_REQUEST["page"]);
		$this->exchangeArray(array_slice($array, ($this->paginationPage - 1) * $limit, $limit));
		return $this;
	}
	
	// Return current page number in pagination 
	function getPaginationPage()
	{
		return $this->paginationPage;
	}
	
	// Return highest page number in pagination
	function getPaginationCount()
	{
		return $this->paginationCount;
	}
	
	// Return absolute location for a page in pagination
	function getLocationPage($pageNumber)
	{
		if($pageNumber>=1 && $pageNumber<=$this->paginationCount)
		{
			$locationArgs = $this->yellow->toolbox->getRequestLocationArgs($pageNumber>1 ? "page:$pageNumber" : "page:");
			$location = $this->yellow->pages->baseLocation.$this->location.$locationArgs;
		}
		return $location;
	}
	
	// Return absolute location for previous page in pagination
	function getLocationPrevious()
	{
		$pageNumber = $this->paginationPage;
		$pageNumber = ($pageNumber>1 && $pageNumber<=$this->paginationCount) ? $pageNumber-1 : 0;
		return $this->getLocationPage($pageNumber);
	}
	
	// Return absolute location for next page in pagination
	function getLocationNext()
	{
		$pageNumber = $this->paginationPage;
		$pageNumber = ($pageNumber>=1 && $pageNumber<$this->paginationCount) ? $pageNumber+1 : 0;
		return $this->getLocationPage($pageNumber);
	}
	
	// Return last modification time for page collection, Unix time
	function getModified($httpFormat = false)
	{
		$modified = 0;
		foreach($this->getIterator() as $page) $modified = max($modified, $page->getModified());
		return $httpFormat ? $this->yellow->toolbox->getHttpTimeFormatted($modified) : $modified;
	}

	// Check if there is an active pagination
	function isPagination()
	{
		return $this->paginationCount > 1;
	}
}

// Yellow page tree from file system
class Yellow_Pages
{
	var $yellow;		//access to API
	var $pages;			//scanned pages
	var $baseLocation;	//requested base location
	var $snippetArgs;	//requested snippet arguments
	
	function __construct($yellow)
	{
		$this->pages = array();
		$this->yellow = $yellow;
	}
		
	// Return pages from file system
	function index($showHidden = false, $levelMax = 0)
	{
		return $this->findChildrenRecursive("", $showHidden, $levelMax);
	}
	
	// Return top-level navigation pages
	function root($showHidden = false)
	{
		return $this->findChildren("", $showHidden);
	}

	// Find a specific page
	function find($location, $absoluteLocation = false)
	{
		if($absoluteLocation) $location = substru($location, strlenu($this->baseLocation));
		$parentLocation = $this->getParentLocation($location);
		$this->scanChildren($parentLocation);
		foreach($this->pages[$parentLocation] as $page) if($page->location == $location) return $page;
		return false;
	}
	
	// Find child pages
	function findChildren($location, $showHidden = false)
	{
		$pages = new Yellow_PageCollection($this->yellow, $location);
		$this->scanChildren($location);
		foreach($this->pages[$location] as $page) if($page->isVisible() || $showHidden) $pages->append($page);
		return $pages;
	}
	
	// Find child pages recursively
	function findChildrenRecursive($location, $showHidden = false, $levelMax = 0)
	{
		--$levelMax;
		$pages = new Yellow_PageCollection($this->yellow, $location);
		$this->scanChildren($location);
		foreach($this->pages[$location] as $page)
		{
			if($page->isVisible() || $showHidden)
			{
				$pages->append($page);
				if(!$this->yellow->toolbox->isFileLocation($page->location) && $levelMax!=0)
				{
					$pages->merge($this->findChildrenRecursive($page->location, $showHidden, $levelMax));
				}
			}
		}
		return $pages;
	}
	
	// Scan child pages on demand
	function scanChildren($location)
	{
		if(is_null($this->pages[$location]))
		{
			if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Pages::scanChildren location:$location<br/>\n";
			$this->pages[$location] = array();
			$path = $this->yellow->config->get("contentDir");
			if(!empty($location))
			{
				$path = $this->yellow->toolbox->findFileFromLocation($location,
					$this->yellow->config->get("contentDir"), $this->yellow->config->get("contentHomeDir"), "", "");
			}
			$fileNames = array();
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true) as $entry)
			{
				array_push($fileNames, $path.$entry."/".$this->yellow->config->get("contentDefaultFile"));
			}
			$fileRegex = "/.*\\".$this->yellow->config->get("contentExtension")."/";
			foreach($this->yellow->toolbox->getDirectoryEntries($path, $fileRegex, true, false) as $entry)
			{
				if($entry != $this->yellow->config->get("contentDefaultFile")) array_push($fileNames, $path.$entry);
			}
			foreach($fileNames as $fileName)
			{
				$childLocation = $this->yellow->toolbox->findLocationFromFile($fileName,
					$this->yellow->config->get("contentDir"), $this->yellow->config->get("contentHomeDir"),
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
				$fileHandle = @fopen($fileName, "r");
				if($fileHandle)
				{
					$fileData = fread($fileHandle, 4096);
					fclose($fileHandle);
				} else {
					$fileData = "";
				}
				$page = new Yellow_Page($this->yellow, $childLocation);
				$page->parseData($fileName, $fileData, false, 0);
				array_push($this->pages[$location], $page);
			}
		}
	}
	
	// Return parent location
	function getParentLocation($location)
	{
		$parentLocation = "";
		if(preg_match("/^(.*\/)(.+?)$/", $location, $matches)) $parentLocation = $matches[1]!="/" ? $matches[1] : "";
		return $parentLocation;
	}
}

// Yellow toolbox with helpers
class Yellow_Toolbox
{
	// Return server name from current HTTP request
	static function getServerName()
	{
		return $_SERVER["SERVER_NAME"];
	}
	
	// Return server base from current HTTP request
	static function getServerBase()
	{
		$serverBase = "";
		if(preg_match("/^(.*)\//", $_SERVER["SCRIPT_NAME"], $matches)) $serverBase = $matches[1];
		return $serverBase;
	}
	
	// Return location from current HTTP request
	static function getRequestLocation()
	{
		$uri = $_SERVER["REQUEST_URI"];
		return ($pos = strposu($uri, '?')) ? substru($uri, 0, $pos) : $uri;
	}
	
	// Return arguments from current HTTP request
	static function getRequestLocationArgs($arg = "", $encodeArgs = true)
	{		
		preg_match("/^(.*?):(.*)$/", $arg, $args);
		if(preg_match("/^(.*?\/)(\w+:.*)$/", rawurldecode(self::getRequestLocation()), $matches))
		{
			foreach(explode('/', $matches[2]) as $token)
			{
				preg_match("/^(.*?):(.*)$/", $token, $matches);
				if($matches[1] == $args[1]) { $matches[2] = $args[2]; $found = true; }
				if(!empty($matches[1]) && !empty($matches[2]))
				{
					if(!empty($locationArgs)) $locationArgs .= '/';
					$locationArgs .= "$matches[1]:$matches[2]";
				}
			}
		}
		if(!$found && !empty($args[1]) && !empty($args[2]))
		{
			if(!empty($locationArgs)) $locationArgs .= '/';
			$locationArgs .= "$args[1]:$args[2]";
		}
		if($encodeArgs)
		{
			$locationArgs = rawurlencode($locationArgs);
			$locationArgs = strreplaceu(array('%3A','%2F'), array(':','/'), $locationArgs);
		}
		return $locationArgs;
	}

	// Normalise location and remove unwanted path tokens
	static function normaliseLocation($location, $removeArgs = true)
	{
		$string = strreplaceu('\\', '/', rawurldecode($location));
		$location = ($string[0]=='/') ? '' : '/';
		for($pos=0; $pos<strlenb($string); ++$pos)
		{
			if($string[$pos] == '/')
			{
				if($string[$pos+1] == '/') continue;
				if($string[$pos+1] == '.')
				{
					$posNew = $pos+1; while($string[$posNew] == '.') ++$posNew;
					if($string[$posNew]=='/' || $string[$posNew]=='')
					{
						$pos = $posNew-1; 
						continue;
					}
				}
			}
			$location .= $string[$pos];
		}
		if($removeArgs && preg_match("/^(.*?\/)(\w+:.*)$/", $location, $matches))
		{
			$location = $matches[1];
			foreach(explode('/', $matches[2]) as $token)
			{
				preg_match("/^(.*?):(.*)$/", $token, $matches);
				if(!empty($matches[1]) && !empty($matches[2])) $_REQUEST[$matches[1]] = $matches[2];
			}
		}
		return $location;
	}
	
	// Check if location is specifying file or directory
	static function isFileLocation($location)
	{
		return substru($location, -1, 1) != "/";
	}
	
	// Check if file has been unmodified since last HTTP request
	static function isFileNotModified($lastModified)
	{
		return isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && $_SERVER["HTTP_IF_MODIFIED_SINCE"]==$lastModified;
	}
	
	// Check if location is within current HTTP request
	static function isActiveLocation($baseLocation, $location)
	{
		$currentLocation = substru(self::getRequestLocation(), strlenu($baseLocation));
		if($location != "/")
		{
			$active = substru($currentLocation, 0, strlenu($location))==$location;
		} else {
			$active = $currentLocation==$location;
		}
		return $active;
	}
	
	// Check if location is visible in navigation
	static function isVisibleLocation($baseLocation, $location, $fileName, $pathBase)
	{
		$visible = true;
		if(substru($fileName, 0, strlenu($pathBase)) == $pathBase) $fileName = substru($fileName, strlenu($pathBase));
		$tokens = explode('/', $fileName);
		for($i=0; $i<count($tokens)-1; ++$i)
		{
			if(!preg_match("/^[\d\-\.]+(.*)$/", $tokens[$i]))
			{
				$visible = false;
				break;
			}
		}
		return $visible;
	}

	// Find file path from location
	static function findFileFromLocation($location, $pathBase, $pathHome, $fileDefault, $fileExtension)
	{
		$path = $pathBase;
		$tokens = explode('/', $location);
		if(count($tokens) > 2)
		{
			for($i=1; $i<count($tokens)-1; ++$i)
			{
				if(preg_match("/^[\d\-\.]+/", $tokens[$i])) $duplicate = true;
				$entries = self::getDirectoryEntries($path, "/^[\d\-\.]+".$tokens[$i]."$/");
				$path .= empty($entries) ? "$tokens[$i]/" : "$entries[0]/";
			}
			if($path == $pathBase.$pathHome) $duplicate = true;
		} else {
			$i = 1;
			$path .= $pathHome;
		}
		if($tokens[$i] != "")
		{
			if(preg_match("/^[\d\-\.]+/", $tokens[$i])) $duplicate = true;
			$entries = self::getDirectoryEntries($path, "/^[\d\-\.]+".$tokens[$i].$fileExtension."$/", false, false);
			$path .= empty($entries) ? $tokens[$i].$fileExtension : $entries[0];
		} else {
			$path .= $fileDefault;
		}
		return $duplicate ? "" : $path;
	}
	
	// Find location from file path
	static function findLocationFromFile($fileName, $pathBase, $pathHome, $fileDefault, $fileExtension)
	{
		$location = "/";
		if(substru($fileName, 0, strlenu($pathBase)) == $pathBase) $fileName = substru($fileName, strlenu($pathBase));
		if(substru($fileName, 0, strlenu($pathHome)) == $pathHome) $fileName = substru($fileName, strlenu($pathHome));
		$tokens = explode('/', $fileName);
		for($i=0; $i<count($tokens)-1; ++$i)
		{
			if(preg_match("/^[\d\-\.]+(.*)$/", $tokens[$i], $matches)) $tokens[$i] = $matches[1];
			$location .= "$tokens[$i]/";
		}
		if($tokens[$i] != $fileDefault)
		{
			if(preg_match("/^[\d\-\.]+(.*)$/", $tokens[$i], $matches)) $tokens[$i] = $matches[1];
			$location .= substru($tokens[$i], 0, -strlenu($fileExtension));
		}
		return $location;
	}
	
	// Return human readable HTTP server status
	static function getHttpStatusFormatted($statusCode)
	{
		switch($statusCode)
		{
			case 0:   $text = "$_SERVER[SERVER_PROTOCOL] $statusCode No data"; break;
			case 200: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode OK"; break;
			case 301: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Moved permanently"; break;
			case 302: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Moved temporarily"; break;
			case 303: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Reload please"; break;
			case 304: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Not modified"; break;
			case 401: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Unauthorised"; break;
			case 404: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Not found"; break;
			case 424: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Does not exist"; break;
			case 500: $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Server error"; break;
			default:  $text = "$_SERVER[SERVER_PROTOCOL] $statusCode Unknown status";
		}
		return $text;
	}
							  
	// Return human readable HTTP time
	static function getHttpTimeFormatted($timestamp)
	{
		return gmdate("D, d M Y H:i:s", $timestamp)." GMT";
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
				if(substru($entry, 0, 1) == ".") continue;
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
	
	// Return files and directories recursively
	static function getDirectoryEntriesRecursive($path, $regex = "/.*/", $sort = false, $directories = true, $levelMax = 0)
	{
		$entries = array();
		foreach(self::getDirectoryEntries($path, $regex, $sort, $directories) as $entry) array_push($entries, "$path/$entry");
		--$levelMax;
		if($levelMax != 0)
		{
			foreach(self::getDirectoryEntries($path, "/.*/", $sort, true) as $entry)
			{
				$entries = array_merge($entries, self::getDirectoryEntriesRecursive("$path/$entry", $regex, $sort, $directories, $levelMax));
			}
		}
		return $entries;
	}

	// Create new file
	function makeFile($fileName, $fileData, $mkdir = false)
	{
		$ok = false;
		if($mkdir)
		{
			$path = dirname($fileName);
			if(!empty($path) && !is_dir($path)) mkdir($path, 0777, true);
		}
		$fileHandle = @fopen($fileName, "w");
		if($fileHandle)
		{
			fwrite($fileHandle, $fileData);
			fclose($fileHandle);
			$ok = true;
		}
		return $ok;
	}
	
	// Copy file
	function copyFile($fileNameSource, $fileNameDest, $mkdir = false)
	{
		if($mkdir)
		{
			$path = dirname($fileNameDest);
			if(!empty($path) && !is_dir($path)) mkdir($path, 0777, true);
		}
		return @copy($fileNameSource, $fileNameDest);
	}

	// Set file modification time, Unix time
	function modifyFile($fileName, $modified)
	{
		return @touch($fileName, $modified);
	}
	
	// Create description from text string
	static function createTextDescription($text, $lengthMax, $removeHtml = true)
	{
		if(preg_match("/<h1>.*<\/h1>(.*)/si", $text, $matches)) $text = $matches[1];
		if($removeHtml)
		{
			while(true)
			{
				$elementFound = preg_match("/<\s*?(\/?\w*).*?\>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
				$element = $matches[0][0];
				$elementName = $matches[1][0];
				$elementOffsetBytes = $elementFound ? $matches[0][1] : strlenb($text);
				$string = html_entity_decode(substrb($text, $offsetBytes, $elementOffsetBytes - $offsetBytes), ENT_QUOTES, "UTF-8");
				if(preg_match("/^(blockquote|br|div|h\d|hr|li|ol|p|pre|ul)/i", $elementName)) $string .= ' ';
				$string = preg_replace("/\s+/s", " ", $string);
				if(substru($string, 0 , 1)==" " && (empty($output) || substru($output, -1)==' ')) $string = substru($string, 1);
				$length = strlenu($string);
				$output .= substru($string, 0, $length < $lengthMax ? $length : $lengthMax-1);
				$lengthMax -= $length;
				if($lengthMax<=0 || !$elementFound) break;
				$offsetBytes = $elementOffsetBytes + strlenb($element);
			}
			$output = rtrim($output);
			if($lengthMax <= 0) $output .= '…';
		} else {
			$elementsOpen = array();
			while(true)
			{
				$elementFound = preg_match("/&.*?\;|<\s*?(\/?\w*)\s*?(.*?)\s*?\>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
				$element = $matches[0][0];
				$elementName = $matches[1][0];
				$elementOffsetBytes = $elementFound ? $matches[0][1] : strlenb($text);
				$string = substrb($text, $offsetBytes, $elementOffsetBytes - $offsetBytes);
				$length = strlenu($string);
				$output .= substru($string, 0, $length < $lengthMax ? $length : $lengthMax-1);
				$lengthMax -= $length + ($element[0]=='&' ? 1 : 0);
				if($lengthMax<=0 || !$elementFound) break;
				if(!empty($elementName))
				{
					if(!preg_match("/^(\/|area|br|col|hr|img|input|col|param)/i", $elementName))
					{
						if(substru($matches[2][0], -1) != '/') array_push($elementsOpen, $elementName);
					} else {
						array_pop($elementsOpen);
					}
				}
				$output .= $element;
				$offsetBytes = $elementOffsetBytes + strlenb($element);
			}
			$output = rtrim($output);
			if($lengthMax <= 0) $output .= '…';
			for($t=count($elementsOpen)-1; $t>=0; --$t) $output .= "</".$elementsOpen[$t].">";
		}
		return $output;
	}

	// Create keywords from text string
	static function createTextKeywords($text, $keywordsMax)
	{
		$tokens = preg_split("/[,\s\(\)]/", strtoloweru($text));
		foreach($tokens as $key=>$value) if(strlenu($value) < 3) unset($tokens[$key]);
		return implode(", ", array_slice(array_unique($tokens), 0, $keywordsMax));
	}
	
	// Create title from text string
	static function createTextTitle($text)
	{
		if(preg_match("/^.*\/([\w\-]+)/", $text, $matches)) $text = ucfirst($matches[1]);
		return $text;
	}
	
	// Detect web browser language
	static function detectBrowserLanguage($languagesAllowed, $languageDefault)
	{
		$language = $languageDefault;
		if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
		{
			foreach(preg_split("/,\s*/", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $string)
			{
				$tokens = explode(';', $string, 2);
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
			if(substru($fileName, -3) == "png")
			{
				$dataSignature = fread($fileHandle, 8);
				$dataHeader = fread($fileHandle, 25);
				if(!feof($fileHandle) && $dataSignature=="\x89PNG\r\n\x1a\n")
				{
					$width = (ord($dataHeader[10])<<8) + ord($dataHeader[11]);
					$height = (ord($dataHeader[14])<<8) + ord($dataHeader[15]);
				}
			} else if(substru($fileName, -3) == "jpg") {
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
				if(!empty($matches[1]) && !empty($matches[2]))
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
				if(substru($key, -strlenu($filterEnd)) == $filterEnd) $config[$key] = $value;
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
					if($matches[1]=="language" && !empty($matches[2])) { $language = $matches[2]; break; }
				}
				foreach($fileData as $line)
				{
					if(preg_match("/^\//", $line)) continue;
					preg_match("/^\s*(.*?)\s*=\s*(.*?)\s*$/", $line, $matches);
					if(!empty($language) && !empty($matches[1]) && !empty($matches[2]))
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
	
	// Set text string for specific language
	function setLanguageText($language, $key, $value)
	{
		if(is_null($this->text[$language])) $this->text[$language] = array();
		$this->text[$language][$key] = $value;
	}
	
	// Return text string for specific language
	function getLanguageText($language, $key)
	{
		return ($this->isLanguageText($language, $key)) ? $this->text[$language][$key] : "[$key]";
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
	
	// Return text strings for specific language
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
					if(substru($key, 0, strlenu("language")) == "language") $text[$key] = $value;
					if(substru($key, 0, strlenu($filterStart)) == $filterStart) $text[$key] = $value;
				}
			}
		}
		return $text;
	}
	
	// Check if text string for specific language exists
	function isLanguageText($language, $key)
	{
		return !is_null($this->text[$language]) && !is_null($this->text[$language][$key]);
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
		require_once("core_commandline.php");
		require_once("core_webinterface.php");
		foreach($yellow->toolbox->getDirectoryEntries($yellow->config->get("pluginDir"), "/.*\.php/", true, false) as $entry)
		{
			$fileNamePlugin = $yellow->config->get("pluginDir")."/$entry";
			require_once($fileNamePlugin);
		}
		foreach($this->plugins as $key=>$value)
		{
			$this->plugins[$key]["obj"] = new $value["class"];
			if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Plugins::load class:$value[class] $value[version]<br/>\n";
			if(method_exists($this->plugins[$key]["obj"], "initPlugin")) $this->plugins[$key]["obj"]->initPlugin($yellow);
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

// Unicode support for PHP 5
mb_internal_encoding("UTF-8");
function strlenu() { return call_user_func_array("mb_strlen", func_get_args()); }
function strposu() { return call_user_func_array("mb_strpos", func_get_args()); }
function strreplaceu() { return call_user_func_array("str_replace", func_get_args()); }
function strtoloweru() { return call_user_func_array("mb_strtolower", func_get_args()); }
function strtoupperu() { return call_user_func_array("mb_strtoupper", func_get_args()); }
function substru() { return call_user_func_array("mb_substr", func_get_args()); }
function strlenb() { return call_user_func_array("strlen", func_get_args()); }
function strposb() { return call_user_func_array("strpos", func_get_args()); }
function substrb() { return call_user_func_array("substr", func_get_args()); }

// Error reporting for PHP 5
error_reporting(E_ALL ^ E_NOTICE);
?>