<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main class
class Yellow
{
	const Version = "0.1.20";
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
		$this->config->setDefault("serverName", $this->toolbox->getServerName());
		$this->config->setDefault("serverBase", $this->toolbox->getServerBase());
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
		$this->config->setDefault("contentHomeDir", "home/");
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
	function request($statusCodeRequest = 200)
	{
		$this->toolbox->timerStart($time);
		ob_start();
		$statusCode = 0;
		$serverName = $this->config->get("serverName");
		$serverBase = $this->config->get("serverBase");
		$location = $this->getRelativeLocation($serverBase);
		$fileName = $this->getContentFileName($location);
		$this->page = new Yellow_Page($this, $location);
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onRequest"))
			{
				$this->pages->requestHandler = $key;
				$statusCode = $value["obj"]->onRequest($serverName, $serverBase, $location, $fileName);
				if($statusCode != 0) break;
			}
		}
		if($statusCode == 0)
		{
			$this->pages->requestHandler = "core";
			$statusCode = $this->processRequest($serverName, $serverBase, $location, $fileName, true, $statusCode);
		}
		if($statusCodeRequest > 200) $this->page->error($statusCodeRequest, "Request error");
		if($this->isRequestError())
		{
			ob_clean();
			$statusCode = $this->processRequestError();
		}
		ob_end_flush();
		$this->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::request status:$statusCode location:$location<br>\n";
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::request time:$time ms<br>\n";
		return $statusCode;
	}
	
	// Process request
	function processRequest($serverName, $serverBase, $location, $fileName, $cacheable, $statusCode)
	{
		$handler = $this->getRequestHandler();
		if($statusCode == 0)
		{
			if(is_readable($fileName))
			{
				if(!$this->isRequestCleanUrl())
				{
					$statusCode = 200;
					$fileName = $this->readPage($serverBase, $location, $fileName, $cacheable, $statusCode);
					if($this->page->isExisting("redirect") && $handler=="core")
					{
						$statusCode = 301;
						$locationHeader = $this->toolbox->getHttpLocationHeader($serverName, $serverBase, $this->page->get("redirect"));
						$this->page->statusCode = 0;
						$this->header($locationHeader);
						$this->sendStatus($statusCode, $locationHeader);
					}
				} else {
					$statusCode = 303;
					$locationArgs = $this->toolbox->getLocationArgsCleanUrl();
					$this->sendStatus($statusCode, $this->toolbox->getHttpLocationHeader($serverName, $serverBase, $location.$locationArgs));
				}
			} else {
				if($this->toolbox->isFileLocation($location) && is_dir($this->getContentDirectory("$location/")))
				{
					$statusCode = 301;
					$this->sendStatus($statusCode, $this->toolbox->getHttpLocationHeader($serverName, $serverBase, "$location/"));
				} else {
					$statusCode = 404;
					$fileName = $this->readPage($serverBase, $location, $fileName, $cacheable, $statusCode);
				}
			}
		} else if($statusCode >= 400) {
			$fileName = $this->readPage($serverBase, $location, $fileName, $cacheable, $statusCode);
		}
		if($this->page->statusCode != 0) $statusCode = $this->sendPage();
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequest handler:$handler base:$serverBase file:$fileName<br>\n";
		return $statusCode;
	}
	
	// Process request with error
	function processRequestError()
	{
		$handler = $this->getRequestHandler();
		$serverBase = $this->pages->serverBase;
		$fileName = $this->readPage($serverBase, $this->page->location, $this->page->fileName, $this->page->cacheable,
			$this->page->statusCode, $this->page->get("pageError"));
		$statusCode = $this->sendPage();
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequestError handler:$handler base:$serverBase file:$fileName<br>\n";
		return $statusCode;		
	}
	
	// Read page from file
	function readPage($serverBase, $location, $fileName, $cacheable, $statusCode, $pageError = "")
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
		$this->pages->serverBase = $serverBase;
		$this->page = new Yellow_Page($this, $location);
		$this->page->parseData($fileName, $fileData, $cacheable, $statusCode, $pageError);
		$this->page->parseContent();
		$this->page->setHeader("Content-Type", "text/html; charset=UTF-8");
		$this->page->setHeader("Last-Modified", $this->page->getModified(true));
		if(!$this->page->isCacheable()) $this->page->setHeader("Cache-Control", "no-cache, must-revalidate");
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
		if(PHP_SAPI != "cli")
		{
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
			if(!empty($text)) @header($text);
		}
	}
	
	// Return name of request handler
	function getRequestHandler()
	{
		return $this->pages->requestHandler;
	}
	
	// Check if clean URL is requested
	function isRequestCleanUrl()
	{
		return isset($_GET["clean-url"]) || isset($_POST["clean-url"]);
	}
	
	// Check for request error
	function isRequestError()
	{
		$serverBase = $this->config->get("serverBase");
		if(!empty($serverBase) && !$this->toolbox->isValidLocation($serverBase))
		{
			$this->page->error(500, "Server base '$serverBase' not supported!");
		}
		return $this->page->isExisting("pageError");
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
	
	// Return content location for current HTTP request, without server base
	function getRelativeLocation($serverBase)
	{
		$location = $this->toolbox->getLocation();
		$location = $this->toolbox->normaliseLocation($location);
		return substru($location, strlenu($serverBase));
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
	var $metaData;				//meta data
	var $headerData;			//response header
	var $parser;				//content parser
	var $active;				//page is active location? (boolean)
	var $visible;				//page is visible location? (boolean)
	var $cacheable;				//page is cacheable? (boolean)
	var $statusCode;			//status code

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
		$this->active = $this->yellow->toolbox->isActiveLocation($this->yellow->pages->serverBase, $this->location,
							$this->yellow->page->location);
		$this->visible = $this->yellow->toolbox->isVisibleLocation($this->yellow->pages->serverBase, $this->location,
							$fileName, $this->yellow->config->get("contentDir"));
		$this->cacheable = $cacheable;
		$this->statusCode = $statusCode;
		if(!empty($pageError)) $this->error($statusCode, $pageError);
		$this->parseMeta();
	}
	
	// Parse page meta data
	function parseMeta()
	{
		$fileDate = date("c", is_readable($this->fileName) ? filemtime($this->fileName) : 0);
		$this->set("modified", $fileDate);
		$this->set("published", $fileDate);
		$this->set("title", $this->yellow->toolbox->createTextTitle($this->location));
		$this->set("sitename", $this->yellow->config->get("sitename"));
		$this->set("author", $this->yellow->config->get("author"));
		$this->set("language", $this->yellow->config->get("language"));
		$this->set("template", $this->yellow->config->get("template"));
		$this->set("style", $this->yellow->config->get("style"));
		$this->set("parser", $this->yellow->config->get("parser"));
		
		if(preg_match("/^(\-\-\-[\r\n]+)(.+?)([\r\n]+\-\-\-[\r\n]+)/s", $this->rawData, $parsed))
		{
			$this->metaDataOffsetBytes = strlenb($parsed[0]);
			foreach(preg_split("/[\r\n]+/", $parsed[2]) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !empty($matches[2])) $this->set(lcfirst($matches[1]), $matches[2]);
			}
		} else if(preg_match("/^([^\r\n]+)([\r\n]+=+[\r\n]+)/", $this->rawData, $parsed)) {
			$this->metaDataOffsetBytes = strlenb($parsed[0]);
			$this->set("title", $parsed[1]);
		}
		
		$titleHeader = $this->location!="/" ? $this->get("title")." - ".$this->get("sitename") : $this->get("sitename");
		if(!$this->isExisting("titleHeader")) $this->set("titleHeader", $titleHeader);
		if(!$this->isExisting("titleNavigation")) $this->set("titleNavigation", $this->get("title"));
		$this->set("pageRead", $this->yellow->config->get("serverBase").$this->location);
		$this->set("pageEdit", $this->yellow->config->get("serverBase").
			rtrim($this->yellow->config->get("webinterfaceLocation"), '/').$this->location);
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseMeta"))
			{
				$output = $value["obj"]->onParseMeta($this, $this->rawData);
				if(!is_null($output)) break;
			}
		}
	}
	
	// Parse page update if necessary
	function parseUpdate()
	{
		if($this->statusCode == 0)
		{
			$fileHandle = @fopen($this->fileName, "r");
			if($fileHandle)
			{
				$this->statusCode = 200;
				$this->rawData = fread($fileHandle, filesize($this->fileName));
				$this->metaData = array();
				fclose($fileHandle);
				$this->parseMeta();
			}
			if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Page::parseUpdate location:".$this->location."<br/>\n";
		}
	}
	
	// Parse page content
	function parseContent()
	{
		if(!is_object($this->parser))
		{
			$this->parser = new stdClass;
			if($this->yellow->plugins->isExisting($this->get("parser")))
			{
				$this->parser = $this->yellow->plugins->plugins[$this->get("parser")]["obj"];
				$this->parser->parse($this->getContent(true));
				$location = $this->yellow->toolbox->getDirectoryLocation($this->getLocation());
				$this->parser->textHtml = preg_replace("#<a(.*?)href=\"(?!javascript:)([^\/\"]+)\"(.*?)>#",
													   "<a$1href=\"$location$2\"$3>", $this->parser->textHtml);
			}
			foreach($this->yellow->plugins->plugins as $key=>$value)
			{
				if(method_exists($value["obj"], "onParseContent"))
				{
					$output = $value["obj"]->onParseContent($this, $this->parser->textHtml);
					if(!is_null($output)) { $this->parser->textHtml = $output; break; }
				}
			}
			if(!$this->isExisting("description"))
			{
				$this->set("description", $this->yellow->toolbox->createTextDescription($this->parser->textHtml, 150));
			}
			if(!$this->isExisting("keywords"))
			{
				$this->set("keywords", $this->yellow->toolbox->createTextKeywords($this->get("title"), 10));
			}
			if(defined("DEBUG") && DEBUG>=2) echo "Yellow_Page::parseContent location:".$this->location."<br/>\n";
		}
	}
	
	// Parse custom type
	function parseType($name, $text, $typeShortcut)
	{
		$output = NULL;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseType"))
			{
				$output = $value["obj"]->onParseType($name, $text, $typeShortcut);
				if(!is_null($output)) break;
			}
		}
		return $output;
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

	// Return page content, HTML encoded or raw format
	function getContent($rawFormat = false)
	{
		if($rawFormat)
		{
			$this->parseUpdate();
			$text = substrb($this->rawData, $this->metaDataOffsetBytes);
		} else {
			$this->parseContent();
			$text = $this->parser->textHtml;
		}
		return $text;
	}
	
	// Return absolute page location
	function getLocation()
	{
		return $this->yellow->pages->serverBase.$this->location;
	}
	
	// Return page modification time, Unix time
	function getModified($httpFormat = false)
	{
		$modified = strtotime($this->get("modified"));
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
	
	// Return parent page relative to current page
	function getParent()
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->find($parentLocation, false)->first();
	}

	// Return top-level parent page of current page
	function getParentTop()
	{
		$parentTopLocation = $this->yellow->pages->getParentTopLocation($this->location);
		return $this->yellow->pages->find($parentTopLocation, false)->first();
	}

	// Return pages on the same level as current page
	function getSiblings($showHidden = false)
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->findChildren($parentLocation, $showHidden);
	}

	// Return child pages relative to current page
	function getChildren($showHidden = false)
	{
		return $this->yellow->pages->findChildren($this->location, $showHidden);
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
	var $paginationPage;		//current page number in pagination
	var $paginationCount;		//highest page number in pagination
	
	function __construct($yellow)
	{
		parent::__construct(array());
		$this->yellow = $yellow;
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
	
	// Sort page collection by meta data
	function sort($key, $ascendingOrder = true)
	{
		$callback = function($a, $b) use ($key, $ascendingOrder)
		{
			return $ascendingOrder ?
				strnatcasecmp($a->get($key), $b->get($key)) :
				strnatcasecmp($b->get($key), $a->get($key));
		};
		$array = $this->getArrayCopy();
		usort($array, $callback);
		$this->exchangeArray($array);
		return $this;
	}

	// Merge page collection
	function merge($input)
	{
		$this->exchangeArray(array_merge($this->getArrayCopy(), (array)$input));
		return $this;
	}
	
	// Append to end of page collection
	function append($page)
	{
		parent::append($page);
		return $this;
	}
	
	// Prepend to start of page collection
	function prepend($page)
	{
		$array = $this->getArrayCopy();
		array_unshift($array, $page);
		$this->exchangeArray($array);
		return $this;
	}
	
	// Limit the number of pages in page collection
	function limit($pagesMax)
	{
		$this->exchangeArray(array_slice($this->getArrayCopy(), 0, $pagesMax));
		return $this;
	}
	
	// Reverse page collection
	function reverse()
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
			$locationArgs = $this->yellow->toolbox->getLocationArgs($pageNumber>1 ? "page:$pageNumber" : "page:");
			$location = $this->yellow->page->getLocation().$locationArgs;
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
	
	// Return first page in page collection
	function first()
	{
		return $this->offsetGet(0);
	}

	// Return last page in page collection
	function last()
	{
		return $this->offsetGet($this->count()-1);
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
	var $yellow;			//access to API
	var $pages;				//scanned pages
	var $requestHandler;	//request handler
	var $serverBase;		//requested server base
	var $snippetArgs;		//requested snippet arguments
	
	function __construct($yellow)
	{
		$this->pages = array();
		$this->yellow = $yellow;
	}
	
	// Return empty page collection
	function create()
	{
		return new Yellow_PageCollection($this->yellow);
	}
	
	// Return pages from file system
	function index($showHidden = false, $levelMax = 0)
	{
		return $this->findChildrenRecursive("", $showHidden, $levelMax);
	}
	
	// Return page collection with top-level navigation
	function top($showHidden = false)
	{
		return $this->findChildren("", $showHidden);
	}
	
	// Return page collection with path ancestry
	function path($location, $absoluteLocation = false)
	{
		if($absoluteLocation) $location = substru($location, strlenu($this->serverBase));
		$pages = $this->find($location, false);
		for($page=$pages->first(); $page; $page=$parent)
		{
			if($parent = $page->getParent()) $pages->prepend($parent);
			else if($page->location!="/" && $home = $this->find("/", false)->first()) $pages->prepend($home);
		}
		return $pages;
	}
	
	// Return page collection with a specific page
	function find($location, $absoluteLocation = false)
	{
		if($absoluteLocation) $location = substru($location, strlenu($this->serverBase));
		$parentLocation = $this->getParentLocation($location);
		$this->scanChildren($parentLocation);
		$pages = new Yellow_PageCollection($this->yellow);
		foreach($this->pages[$parentLocation] as $page) if($page->location == $location) { $pages->append($page); break; }
		return $pages;
	}
	
	// Find child pages
	function findChildren($location, $showHidden = false)
	{
		$this->scanChildren($location);
		$pages = new Yellow_PageCollection($this->yellow);
		foreach($this->pages[$location] as $page) if($page->isVisible() || $showHidden) $pages->append($page);
		return $pages;
	}
	
	// Find child pages recursively
	function findChildrenRecursive($location, $showHidden = false, $levelMax = 0)
	{
		--$levelMax;
		$this->scanChildren($location);
		$pages = new Yellow_PageCollection($this->yellow);
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
				$fileDefault = $this->yellow->config->get("contentDefaultFile");
				if(!is_file($path.$entry."/".$fileDefault))
				{
					$regex = "/^[\d\-\_\.]*".strreplaceu('-', '.', $fileDefault)."$/";
					foreach($this->yellow->toolbox->getDirectoryEntries($path.$entry, $regex, false, false) as $entry2)
					{
						if($this->yellow->toolbox->normaliseName($entry2) == $fileDefault) { $fileDefault = $entry2; break; }
					}
				}
				array_push($fileNames, $path.$entry."/".$fileDefault);
			}
			$regex = "/.*\\".$this->yellow->config->get("contentExtension")."/";
			foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
			{
				$token = $this->yellow->toolbox->normaliseName($entry);
				if($token != $this->yellow->config->get("contentDefaultFile")) array_push($fileNames, $path.$entry);
			}
			foreach($fileNames as $fileName)
			{
				$fileHandle = @fopen($fileName, "r");
				if($fileHandle)
				{
					$fileData = fread($fileHandle, 4096);
					$statusCode = filesize($fileName) <= 4096 ? 200 : 0;
					fclose($fileHandle);
				} else {
					$fileData = "";
					$statusCode = 0;
				}
				$page = new Yellow_Page($this->yellow, $this->yellow->toolbox->findLocationFromFile($fileName,
					$this->yellow->config->get("contentDir"), $this->yellow->config->get("contentHomeDir"),
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension")));
				$page->parseData($fileName, $fileData, false, $statusCode);
				array_push($this->pages[$location], $page);
			}
		}
	}
	
	// Return parent location
	function getParentLocation($location)
	{
		$parentLocation = "";
		if(preg_match("/^(.*\/).+?$/", $location, $matches))
		{
			if($matches[1]!="/" || $this->yellow->toolbox->isFileLocation($location)) $parentLocation = $matches[1];
		}
		return $parentLocation;
	}
	
	// Return top-level parent location
	function getParentTopLocation($location)
	{
		$parentTopLocation = "/";
		if(preg_match("/^(.+?\/)/", $location, $matches)) $parentTopLocation = $matches[1];
		return $parentTopLocation;
	}
}

// Yellow toolbox with helpers
class Yellow_Toolbox
{
	// Return server name from current HTTP request
	function getServerName()
	{
		return $_SERVER["SERVER_NAME"];
	}
	
	// Return server base from current HTTP request
	function getServerBase()
	{
		$serverBase = "";
		if(preg_match("/^(.*)\//", $_SERVER["SCRIPT_NAME"], $matches)) $serverBase = $matches[1];
		return $serverBase;
	}
	
	// Return location from current HTTP request
	function getLocation()
	{
		$uri = $_SERVER["REQUEST_URI"];
		return ($pos = strposu($uri, '?')) ? substru($uri, 0, $pos) : $uri;
	}
	
	// Return location arguments from current HTTP request
	function getLocationArgs($arg = "")
	{		
		preg_match("/^(.*?):(.*)$/", $arg, $args);
		if(preg_match("/^(.*?\/)(\w+:.*)$/", rawurldecode(self::getLocation()), $matches))
		{
			foreach(explode('/', $matches[2]) as $token)
			{
				preg_match("/^(.*?):(.*)$/", $token, $matches);
				if($matches[1] == $args[1]) { $matches[2] = $args[2]; $found = true; }
				if(!empty($matches[1]) && !strempty($matches[2]))
				{
					if(!empty($locationArgs)) $locationArgs .= '/';
					$locationArgs .= "$matches[1]:$matches[2]";
				}
			}
		}
		if(!$found && !empty($args[1]) && !strempty($args[2]))
		{
			if(!empty($locationArgs)) $locationArgs .= '/';
			$locationArgs .= "$args[1]:$args[2]";
		}
		$locationArgs = rawurlencode($locationArgs);
		$locationArgs = strreplaceu(array('%3A','%2F'), array(':','/'), $locationArgs);
		return $locationArgs;
	}
	
	// Return location arguments from current HTTP request, convert form into clean URL
	function getLocationArgsCleanUrl()
	{
		foreach(array_merge($_GET, $_POST) as $key=>$value)
		{
			if(!empty($key) && !strempty($value))
			{
				if(!empty($locationArgs)) $locationArgs .= '/';
				$key = strreplaceu(array('/', ':'), array("\x1c", "\x1d"), $key);
				$value = strreplaceu(array('/', ':'), array("\x1c", "\x1d"), $value);
				$locationArgs .= "$key:$value";
			}
		}
		$locationArgs = rawurlencode($locationArgs);
		$locationArgs = strreplaceu(array('%3A','%2F'), array(':','/'), $locationArgs);
		return $locationArgs;
	}

	// Normalise location and remove unwanted path tokens
	function normaliseLocation($location, $convertArgs = true)
	{
		$string = rawurldecode($location);
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
		if($convertArgs && preg_match("/^(.*?\/)(\w+:.*)$/", $location, $matches))
		{
			$location = $matches[1];
			foreach(explode('/', $matches[2]) as $token)
			{
				preg_match("/^(.*?):(.*)$/", $token, $matches);
				if(!empty($matches[1]) && !strempty($matches[2]))
				{
					$matches[1] = strreplaceu(array("\x1c", "\x1d"), array('/', ':'), $matches[1]);
					$matches[2] = strreplaceu(array("\x1c", "\x1d"), array('/', ':'), $matches[2]);
					$_REQUEST[$matches[1]] = $matches[2];
				}
			}
		}
		return $location;
	}
	
	// Check if file is unmodified since last HTTP request
	function isFileNotModified($lastModified)
	{
		return isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && $_SERVER["HTTP_IF_MODIFIED_SINCE"]==$lastModified;
	}
		
	// Check if location is specifying file or directory
	function isFileLocation($location)
	{
		return substru($location, -1, 1) != "/";
	}
	
	// Check if location is valid
	function isValidLocation($location)
	{
		$string = "";
		$tokens = explode('/', $location);
		for($i=1; $i<count($tokens); ++$i) $string .= '/'.self::normaliseName($tokens[$i]);
		return $location == $string;
	}
	
	// Check if location is within current HTTP request
	function isActiveLocation($serverBase, $location, $currentLocation)
	{
		if($location != "/")
		{
			$active = substru($currentLocation, 0, strlenu($location))==$location;
		} else {
			$active = $currentLocation==$location;
		}
		return $active;
	}
	
	// Check if location is visible in navigation
	function isVisibleLocation($serverBase, $location, $fileName, $pathBase)
	{
		$visible = true;
		if(substru($fileName, 0, strlenu($pathBase)) == $pathBase) $fileName = substru($fileName, strlenu($pathBase));
		$tokens = explode('/', $fileName);
		for($i=0; $i<count($tokens)-1; ++$i)
		{
			if(!preg_match("/^[\d\-\_\.]+(.*)$/", $tokens[$i])) { $visible = false; break; }
		}
		return $visible;
	}
		
	// Find file path from location
	function findFileFromLocation($location, $pathBase, $pathHome, $fileDefault, $fileExtension)
	{
		$path = $pathBase;
		$tokens = explode('/', $location);
		if(count($tokens) > 2)
		{
			if($tokens[1]."/" == $pathHome) $invalid = true;
			for($i=1; $i<count($tokens)-1; ++$i)
			{
				$token = $tokens[$i];
				if(self::normaliseName($token) != $token) $invalid = true;
				$regex = "/^[\d\-\_\.]*".strreplaceu('-', '.', $token)."$/";
				foreach(self::getDirectoryEntries($path, $regex) as $entry)
				{
					if(self::normaliseName($entry) == $token) { $token = $entry; break; }
				}
				$path .= "$token/";
			}
		} else {
			$i = 1;
			$token = rtrim($pathHome, '/');
			if(self::normaliseName($token) != $token) $invalid = true;
			$regex = "/^[\d\-\_\.]*".strreplaceu('-', '.', $token)."$/";
			foreach(self::getDirectoryEntries($path, $regex) as $entry)
			{
				if(self::normaliseName($entry) == $token) { $token = $entry; break; }
			}
			$path .= "$token/";
		}
		$token = !empty($tokens[$i]) ? $tokens[$i].$fileExtension : $fileDefault;
		if(!empty($tokens[$i]) && $tokens[$i].$fileExtension==$fileDefault) $invalid = true;
		if(self::normaliseName($token) != $token) $invalid = true;
		$regex = "/^[\d\-\_\.]*".strreplaceu('-', '.', $token)."$/";
		foreach(self::getDirectoryEntries($path, $regex, false, false) as $entry)
		{
			if(self::normaliseName($entry) == $token) { $token = $entry; break; }
		}
		$path .= $token;
		return $invalid ? "" : $path;
	}
	
	// Find location from file path
	function findLocationFromFile($fileName, $pathBase, $pathHome, $fileDefault, $fileExtension)
	{
		$location = "/";
		if(substru($fileName, 0, strlenu($pathBase)) == $pathBase) $fileName = substru($fileName, strlenu($pathBase));
		$tokens = explode('/', $fileName);
		for($i=0; $i<count($tokens)-1; ++$i)
		{
			$token = self::normaliseName($tokens[$i]).'/';
			if($i || $token!=$pathHome) $location .= $token;
		}
		$token = self::normaliseName($tokens[$i]);
		if($token != $fileDefault) $location .= self::normaliseName($tokens[$i], true);
		return $location;
	}
	
	// Normalise directory/file name and convert unwanted characters
	function normaliseName($text, $removeExtension = false)
	{
		if(preg_match("/^[\d\-\_\.]+(.*)$/", $text, $matches)) $text = $matches[1];
		if($removeExtension) $text = ($pos = strrposu($text, '.')) ? substru($text, 0, $pos) : $text;
		$text = preg_replace("/[^\pL\d\-\_\.]/u", "-", $text);
		return $text;
	}
	
	// Return human readable HTTP server status
	function getHttpStatusFormatted($statusCode)
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
	function getHttpTimeFormatted($timestamp)
	{
		return gmdate("D, d M Y H:i:s", $timestamp)." GMT";
	}
	
	// Return HTTP location header
	function getHttpLocationHeader($serverName, $serverBase, $location)
	{
		if(preg_match("/^(http|https):\/\//", $location))
		{
			$locationHeader = "Location: $location";
		} else {
			$locationHeader = "Location: http://$serverName$serverBase$location";
		}
		return $locationHeader;
	}
	
	// Return directory location
	function getDirectoryLocation($location)
	{
		return ($pos = strrposu($location, '/')) ? substru($location, 0, $pos+1) : "/";
	}
	
	// Return files and directories
	function getDirectoryEntries($path, $regex = "/.*/", $sort = false, $directories = true)
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
	function getDirectoryEntriesRecursive($path, $regex = "/.*/", $sort = false, $directories = true, $levelMax = 0)
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

	// Create file
	function makeFile($fileName, $fileData, $mkdir = false)
	{
		$ok = false;
		if($mkdir)
		{
			$path = dirname($fileName);
			if(!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
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
			if(!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
		}
		return @copy($fileNameSource, $fileNameDest);
	}

	// Set file modification time, Unix time
	function modifyFile($fileName, $modified)
	{
		return @touch($fileName, $modified);
	}
	
	// Create description from text string
	function createTextDescription($text, $lengthMax, $removeHtml = true, $endMarker = "", $endMarkerText = "")
	{
		if(preg_match("/<h1>.*<\/h1>(.*)/si", $text, $matches)) $text = $matches[1];
		if($removeHtml)
		{
			while(true)
			{
				$elementFound = preg_match("/<\s*?([\/!]?\w*)(.*?)\s*?\>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
				$element = $matches[0][0];
				$elementName = $matches[1][0];
				$elementText = $matches[2][0];
				$elementOffsetBytes = $elementFound ? $matches[0][1] : strlenb($text);
				$string = html_entity_decode(substrb($text, $offsetBytes, $elementOffsetBytes - $offsetBytes), ENT_QUOTES, "UTF-8");
				if(preg_match("/^(blockquote|br|div|h\d|hr|li|ol|p|pre|ul)/i", $elementName)) $string .= ' ';
				$string = preg_replace("/\s+/s", " ", $string);
				if(substru($string, 0 , 1)==" " && (empty($output) || substru($output, -1)==' ')) $string = substru($string, 1);
				$length = strlenu($string);
				$output .= substru($string, 0, $length < $lengthMax ? $length : $lengthMax-1);
				$lengthMax -= $length;
				if(!empty($element) && $element==$endMarker) { $lengthMax = 0; $endMarkerFound = true; }
				if($lengthMax<=0 || !$elementFound) break;
				$offsetBytes = $elementOffsetBytes + strlenb($element);
			}
			$output = rtrim($output);
			if($lengthMax <= 0) $output .= $endMarkerFound ? $endMarkerText : "…";
		} else {
			$elementsOpen = array();
			while(true)
			{
				$elementFound = preg_match("/&.*?\;|<\s*?([\/!]?\w*)(.*?)\s*?\>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
				$element = $matches[0][0];
				$elementName = $matches[1][0];
				$elementText = $matches[2][0];
				$elementOffsetBytes = $elementFound ? $matches[0][1] : strlenb($text);
				$string = substrb($text, $offsetBytes, $elementOffsetBytes - $offsetBytes);
				$length = strlenu($string);
				$output .= substru($string, 0, $length < $lengthMax ? $length : $lengthMax-1);
				$lengthMax -= $length + ($element[0]=='&' ? 1 : 0);
				if(!empty($element) && $element==$endMarker) { $lengthMax = 0; $endMarkerFound = true; }
				if($lengthMax<=0 || !$elementFound) break;
				if(!empty($elementName) && substru($elementText, -1)!='/' &&
				   !preg_match("/^(area|br|col|hr|img|input|col|param|!)/i", $elementName))
				{
					if($elementName[0] != '/')
					{
						array_push($elementsOpen, $elementName);
					} else {
						array_pop($elementsOpen);
					}
				}
				$output .= $element;
				$offsetBytes = $elementOffsetBytes + strlenb($element);
			}
			$output = rtrim($output);
			if($lengthMax <= 0) $output .= $endMarkerFound ? $endMarkerText : "…";
			for($t=count($elementsOpen)-1; $t>=0; --$t) $output .= "</".$elementsOpen[$t].">";
		}
		return $output;
	}

	// Create keywords from text string
	function createTextKeywords($text, $keywordsMax)
	{
		$tokens = preg_split("/[,\s\(\)]/", strtoloweru($text));
		foreach($tokens as $key=>$value) if(strlenu($value) < 3) unset($tokens[$key]);
		return implode(", ", array_slice(array_unique($tokens), 0, $keywordsMax));
	}
	
	// Create title from text string
	function createTextTitle($text)
	{
		if(preg_match("/^.*\/([\w\-]+)/", $text, $matches)) $text = ucfirst($matches[1]);
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
				$tokens = explode(';', $string, 2);
				if(in_array($tokens[0], $languagesAllowed)) { $language = $tokens[0]; break; }
			}
		}
		return $language;
	}

	// Detect PNG and JPG image dimensions
	function detectImageDimensions($fileName)
	{
		$width = $height = 0;
		$fileHandle = @fopen($fileName, "rb");
		if($fileHandle)
		{
			if(substru($fileName, -3) == "png")
			{
				$dataSignature = fread($fileHandle, 8);
				$dataHeader = fread($fileHandle, 16);
				if(!feof($fileHandle) && $dataSignature=="\x89PNG\r\n\x1a\n")
				{
					$width = (ord($dataHeader[10])<<8) + ord($dataHeader[11]);
					$height = (ord($dataHeader[14])<<8) + ord($dataHeader[15]);
				}
			} else if(substru($fileName, -3) == "jpg") {
				$dataBuffer = fread($fileHandle, 2048);
				$dataSignature = substrb($dataBuffer, 0, 11);
				if(!feof($fileHandle) && $dataSignature=="\xff\xd8\xff\xe0\x00\x10JFIF\0")
				{
					$marker = substrb($dataBuffer, 20, 2);
					$length = (ord($dataBuffer[22])<<8) + ord($dataBuffer[23]) + 2;
					$pos = 158 + ($marker=="\xff\xe1" ? $length : 0);
					if($pos+8 < 2048)
					{
						$width = (ord($dataBuffer[$pos+7])<<8) + ord($dataBuffer[$pos+8]);
						$height = (ord($dataBuffer[$pos+5])<<8) + ord($dataBuffer[$pos+6]);
					}
				}
				
			}
			fclose($fileHandle);
		}
		return array($width, $height);
	}

	// Start timer
	function timerStart(&$time)
	{
		$time = microtime(true);
	}
	
	// Stop timer and calcuate elapsed time (milliseconds)
	function timerStop(&$time)
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
				if(!empty($matches[1]) && !strempty($matches[2]))
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
		if(empty($filterEnd))
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
		$regex = "/".basename($fileName)."/";
		foreach($toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
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
					if(!empty($language) && !empty($matches[1]) && !strempty($matches[2]))
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
			if(empty($filterStart))
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
function strempty($string) { return is_null($string) || $string===""; }
function strlenu() { return call_user_func_array("mb_strlen", func_get_args()); }
function strposu() { return call_user_func_array("mb_strpos", func_get_args()); }
function strrposu() { return call_user_func_array("mb_strrpos", func_get_args()); }
function strreplaceu() { return call_user_func_array("str_replace", func_get_args()); }
function strtoloweru() { return call_user_func_array("mb_strtolower", func_get_args()); }
function strtoupperu() { return call_user_func_array("mb_strtoupper", func_get_args()); }
function substru() { return call_user_func_array("mb_substr", func_get_args()); }
function strlenb() { return call_user_func_array("strlen", func_get_args()); }
function strposb() { return call_user_func_array("strpos", func_get_args()); }
function strrposb() { return call_user_func_array("strrpos", func_get_args()); }
function substrb() { return call_user_func_array("substr", func_get_args()); }

// Error reporting for PHP 5
error_reporting(E_ALL ^ E_NOTICE);
?>