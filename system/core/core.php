<?php
// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main class
class Yellow
{
	const Version = "0.4.17";
	var $page;				//current page
	var $pages;				//pages from file system
	var $config;			//configuration
	var $text;				//text strings
	var $toolbox;			//toolbox with helpers
	var $plugins;			//plugins

	function __construct()
	{
		$this->pages = new YellowPages($this);
		$this->config = new YellowConfig($this);
		$this->text = new YellowText($this);
		$this->toolbox = new YellowToolbox();
		$this->plugins = new YellowPlugins();
		$this->config->setDefault("sitename", "Yellow");
		$this->config->setDefault("author", "Yellow");
		$this->config->setDefault("theme", "default");
		$this->config->setDefault("template", "default");
		$this->config->setDefault("language", "en");
		$this->config->setDefault("serverScheme", $this->toolbox->getServerScheme());
		$this->config->setDefault("serverName", $this->toolbox->getServerName());
		$this->config->setDefault("serverBase", $this->toolbox->getServerBase());
		$this->config->setDefault("imageLocation", "/media/images/");
		$this->config->setDefault("pluginLocation", "/media/plugins/");
		$this->config->setDefault("themeLocation", "/media/themes/");
		$this->config->setDefault("systemDir", "system/");
		$this->config->setDefault("configDir", "system/config/");
		$this->config->setDefault("pluginDir", "system/plugins/");
		$this->config->setDefault("snippetDir", "system/snippets/");
		$this->config->setDefault("templateDir", "system/templates/");
		$this->config->setDefault("themeDir", "system/themes/");
		$this->config->setDefault("mediaDir", "media/");
		$this->config->setDefault("imageDir", "media/images/");
		$this->config->setDefault("contentDir", "content/");
		$this->config->setDefault("contentRootDir", "default/");
		$this->config->setDefault("contentHomeDir", "home/");
		$this->config->setDefault("contentDefaultFile", "page.txt");
		$this->config->setDefault("contentPagination", "page");
		$this->config->setDefault("contentExtension", ".txt");
		$this->config->setDefault("configExtension", ".ini");
		$this->config->setDefault("configFile", "config.ini");
		$this->config->setDefault("errorPageFile", "error(.*).txt");
		$this->config->setDefault("newPageFile", "new(.*).txt");
		$this->config->setDefault("textStringFile", "text(.*).ini");
		$this->config->setDefault("parser", "markdownextra");
		$this->config->setDefault("parserSafeMode", "0");
		$this->config->setDefault("multiLanguageMode", "0");
		$this->config->load($this->config->get("configDir").$this->config->get("configFile"));
		$this->text->load($this->config->get("configDir").$this->config->get("textStringFile"));
		$this->updateConfig();
	}
	
	// Handle request
	function request($statusCodeRequest = 0)
	{
		$this->toolbox->timerStart($time);
		ob_start();
		$statusCode = 0;
		list($serverScheme, $serverName, $base, $location, $fileName) = $this->getRequestInformation();
		$this->page = new YellowPage($this, $serverScheme, $serverName, $base, $location, $fileName);
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onRequest"))
			{
				$this->pages->requestHandler = $key;
				$statusCode = $value["obj"]->onRequest($serverScheme, $serverName, $base, $location, $fileName);
				if($statusCode != 0) break;
			}
		}
		if($statusCode == 0)
		{
			$this->pages->requestHandler = "core";
			$statusCode = $this->processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, true);
		}
		if($this->page->isError() || $statusCodeRequest>=400) $statusCode = $this->processRequestError($statusCodeRequest);
		ob_end_flush();
		$this->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::request status:$statusCode location:$location<br/>\n";
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::request time:$time ms<br/>\n";
		return $statusCode;
	}
	
	// Process request
	function processRequest($serverScheme, $serverName, $base, $location, $fileName, $statusCode, $cacheable)
	{
		$handler = $this->getRequestHandler();
		if($statusCode == 0)
		{
			if(is_readable($fileName))
			{
				if($this->toolbox->isRequestCleanUrl($location))
				{
					$statusCode = 303;
					$locationArgs = $this->toolbox->getLocationArgsCleanUrl($this->config->get("contentPagination"));
					$locationHeader = $this->toolbox->getLocationHeader($serverScheme, $serverName, $base, $location.$locationArgs);
					$this->sendStatus($statusCode, false, $locationHeader);
				} else {
					$statusCode = 200;
					$fileName = $this->readPage($serverScheme, $serverName, $base, $location, $fileName, $statusCode, $cacheable);
				}
			} else {
				if(($this->toolbox->isFileLocation($location) && $this->isContentDirectory("$location/")) ||
				   ($location=="/" && $this->config->get("multiLanguageMode")))
				{
					$statusCode = 301;
					$location = $this->toolbox->isFileLocation($location) ? "$location/" : "/".$this->getRequestLanguage()."/";
					$locationHeader = $this->toolbox->getLocationHeader($serverScheme, $serverName, $base, $location);
					$this->sendStatus($statusCode, false, $locationHeader);
				} else {
					$statusCode = 404;
					$fileName = $this->readPage($serverScheme, $serverName, $base, $location, $fileName, $statusCode, $cacheable);
				}
			}
		} else if($statusCode >= 400) {
			$fileName = $this->readPage($serverScheme, $serverName, $base, $location, $fileName, $statusCode, $cacheable);
		}
		if($this->page->statusCode != 0) $statusCode = $this->sendPage();
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequest handler:$handler file:$fileName<br/>\n";
		return $statusCode;
	}
	
	// Process request with error
	function processRequestError($statusCodeRequest)
	{
		ob_clean();
		$handler = $this->getRequestHandler();
		if($statusCodeRequest >= 400) $this->page->error($statusCodeRequest, "Request error");
		$fileName = $this->readPage($this->page->serverScheme, $this->page->serverName, $this->page->base, $this->page->location,
			$this->page->fileName, $this->page->statusCode, $this->page->cacheable, $this->page->get("pageError"));
		$statusCode = $this->sendPage();
		if(defined("DEBUG") && DEBUG>=1) echo "Yellow::processRequestError handler:$handler file:$fileName<br/>\n";
		return $statusCode;		
	}
	
	// Read page from file
	function readPage($serverScheme, $serverName, $base, $location, $fileName, $statusCode, $cacheable, $pageError = "")
	{
		if($statusCode >= 400)
		{
			$fileName = $this->config->get("configDir").$this->config->get("errorPageFile");
			$fileName = strreplaceu("(.*)", $statusCode, $fileName);
			$cacheable = false;
		}
		$this->page = new YellowPage($this, $serverScheme, $serverName, $base, $location, $fileName);
		$this->page->parseData($this->toolbox->getFileData($fileName), $statusCode, $cacheable, $pageError);
		$this->page->setHeader("Content-Type", "text/html; charset=UTF-8");
		$this->page->setHeader("Last-Modified", $this->page->getModified(true));
		if(!$this->page->isCacheable()) $this->page->setHeader("Cache-Control", "no-cache, must-revalidate");
		$this->text->setLanguage($this->page->get("language"));
		$this->page->parseContent();
		return $fileName;
	}
	
	// Send page response
	function sendPage()
	{
		$this->template($this->page->get("template"));
		$fileNameTheme = $this->config->get("themeDir").$this->page->get("theme").".css";
		if(!is_file($fileNameTheme))
		{
			$this->page->error(500, "Theme '".$this->page->get("theme")."' does not exist!");
		}
		if(!is_object($this->page->parser))
		{
			$this->page->error(500, "Parser '".$this->page->get("parser")."' does not exist!");
		}
		$statusCode = $this->page->statusCode;
		if($statusCode==200 && $this->getRequestHandler()=="core" && $this->page->isExisting("redirect"))
		{
			$statusCode = 301;
			$location = $this->toolbox->normaliseLocation($this->page->get("redirect"), $this->page->base, $this->page->location);
			$locationHeader = $this->toolbox->getLocationHeader($this->page->serverScheme, $this->page->serverName, "", $location);
			$this->page->clean($statusCode, $locationHeader);
			$this->page->setHeader("Last-Modified", $this->page->getModified(true));
			$this->page->setHeader("Cache-Control", "no-cache, must-revalidate");
		}
		if($statusCode==200 && $this->page->isCacheable() &&
		   $this->toolbox->isFileNotModified($this->page->getHeader("Last-Modified")))
		{
			$statusCode = 304;
			if($this->page->isHeader("Cache-Control")) $responseHeader = "Cache-Control: ".$this->page->getHeader("Cache-Control");
			$this->page->clean($statusCode, $responseHeader);
		}
		list($contentType) = explode(';', $this->page->getHeader("Content-Type"));
		if($statusCode==200 && !$this->toolbox->isValidContentType($contentType, $this->page->getLocation()))
		{
			$statusCode = 500;
			$this->page->error($statusCode, "Type '$contentType' does not match file name!");
		}
		if($this->page->isExisting("pageClean")) ob_clean();
		if(PHP_SAPI != "cli")
		{
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
			foreach($this->page->headerData as $key=>$value) @header("$key: $value");
		} else {
			if($statusCode>=301 && $statusCode<=303) $this->sendStaticRedirect();
		}
		if(defined("DEBUG") && DEBUG>=1)
		{
			foreach($this->page->headerData as $key=>$value) echo "Yellow::sendPage $key: $value<br/>\n";
			$fileNameTemplate = $this->config->get("templateDir").$this->page->get("template").".php";
			$parserName = $this->page->get("parser");
			echo "Yellow::sendPage template:$fileNameTemplate theme:$fileNameTheme parser:$parserName<br/>\n";
		}
		return $statusCode;
	}

	// Send status response
	function sendStatus($statusCode, $cacheable, $responseHeader = "")
	{
		if(PHP_SAPI != "cli")
		{
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
			if(!$cacheable) @header("Cache-Control: no-cache, must-revalidate");
			if(!empty($responseHeader)) @header($responseHeader);
		} else {
			if(!empty($responseHeader))
			{
				$this->page->header($responseHeader);
				$this->page->header("Last-Modified: ".$this->toolbox->getHttpTimeFormatted(time()));
				if($statusCode>=301 && $statusCode<=303) $this->sendStaticRedirect();
			}
		}
	}

	// Send static redirect response
	function sendStaticRedirect()
	{
		echo "<!DOCTYPE html><html>\n<head>\n";
		echo "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
		echo "<meta http-equiv=\"refresh\" content=\"0;url=".htmlspecialchars($this->page->getHeader("Location"))."\" />\n";
		echo "</head>\n</html>";
	}
	
	// Return request information
	function getRequestInformation($serverScheme = "", $serverName = "", $base = "")
	{
		$serverScheme = empty($serverScheme) ? $this->config->get("serverScheme") : $serverScheme;
		$serverName = empty($serverName) ? $this->config->get("serverName") : $serverName;
		$base = empty($base) ? $this->config->get("serverBase") : $base;
		$location = $this->toolbox->getLocationClean();
		$location = substru($location, strlenu($base));
		$fileName = $this->toolbox->findFileFromLocation($location, $this->config->get("contentDir"),
			$this->config->get("contentRootDir"), $this->config->get("contentHomeDir"),
			$this->config->get("contentDefaultFile"), $this->config->get("contentExtension"));
		return array($serverScheme, $serverName, $base, $location, $fileName);
	}
	
	// Return request language
	function getRequestLanguage()
	{
		$languages = $this->toolbox->findRootLanguages($this->config->get("contentDir"), $this->config->get("contentRootDir"));
		return $this->toolbox->detectBrowserLanguage($languages, $this->config->get("language"));
	}
	
	// Return request handler
	function getRequestHandler()
	{
		return $this->pages->requestHandler;
	}
	
	// Check if content directory exists
	function isContentDirectory($location)
	{
		$path = $this->toolbox->findFileFromLocation($location, $this->config->get("contentDir"),
			$this->config->get("contentRootDir"), $this->config->get("contentHomeDir"), "", "");
		return is_dir($path);
	}
	
	// Update content configuration
	function updateConfig()
	{
		list($pathRoot, $pathHome) = $this->toolbox->findRootConfig($this->config->get("contentDir"),
			$this->config->get("contentRootDir"), $this->config->get("contentHomeDir"),
			$this->config->get("multiLanguageMode"));
		$this->config->set("contentRootDir", $pathRoot);
		$this->config->set("contentHomeDir", $pathHome);
	}
	
	// Execute command
	function command($name, $args = NULL)
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
	
	// Execute template
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
	
	// Execute snippet
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
	
	// Return snippet arguments
	function getSnippetArgs()
	{
		return $this->pages->snippetArgs;
	}
}
	
// Yellow page
class YellowPage
{
	var $yellow;				//access to API
	var $serverScheme;			//server scheme
	var $serverName;			//server name
	var $base;					//base location
	var $location;				//page location
	var $fileName;				//content file name
	var $rawData;				//raw data of page
	var $metaDataOffsetBytes;	//meta data offset
	var $metaData;				//meta data
	var $headerData;			//response header
	var $parserData;			//content data of page
	var $parser;				//content parser
	var $parserSafeMode;		//page is parsed in safe mode? (boolean)
	var $active;				//page is active location? (boolean)
	var $visible;				//page is visible location? (boolean)
	var $cacheable;				//page is cacheable? (boolean)
	var $statusCode;			//status code

	function __construct($yellow, $serverScheme, $serverName, $base, $location, $fileName)
	{
		$this->yellow = $yellow;
		$this->serverScheme = $serverScheme;
		$this->serverName = $serverName;
		$this->base = $base;
		$this->location = $location;
		$this->fileName = $fileName;
		$this->metaData = array();
		$this->headerData = array();
		$this->statusCode = 0;
	}
	
	// Parse page data
	function parseData($rawData, $statusCode, $cacheable, $pageError = "")
	{
		$this->rawData = $rawData;
		$this->parserSafeMode = $this->yellow->config->get("parserSafeMode");
		$this->active = $this->yellow->toolbox->isActiveLocation($this->location, $this->yellow->page->location,
			$this->yellow->pages->getHomeLocation($this->yellow->page->location));
		$this->visible = $this->yellow->toolbox->isVisibleLocation($this->location, $this->fileName,
			$this->yellow->config->get("contentDir"));
		$this->cacheable = $cacheable;
		$this->statusCode = $statusCode;
		if(!empty($pageError)) $this->error($statusCode, $pageError);
		$this->parseMeta();
	}
	
	// Parse page data update
	function parseDataUpdate()
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
		}
	}
	
	// Parse page meta data
	function parseMeta()
	{
		$fileDate = date("c", is_readable($this->fileName) ? filemtime($this->fileName) : 0);
		$this->set("modified", $fileDate);
		$this->set("title", $this->yellow->toolbox->createTextTitle($this->location));
		$this->set("sitename", $this->yellow->config->get("sitename"));
		$this->set("author", $this->yellow->config->get("author"));
		$this->set("theme", $this->yellow->toolbox->findNameFromFile($this->fileName,
			$this->yellow->config->get("themeDir"), $this->yellow->config->get("theme"), ".css"));
		$this->set("template", $this->yellow->toolbox->findNameFromFile($this->fileName,
			$this->yellow->config->get("templateDir"), $this->yellow->config->get("template"), ".php"));
		$this->set("language", $this->yellow->toolbox->findLanguageFromFile($this->fileName,
			$this->yellow->config->get("contentDir"), $this->yellow->config->get("contentRootDir"),
			$this->yellow->config->get("language")));
		$this->set("parser", $this->yellow->config->get("parser"));
		
		if(preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)[\r\n]+\-\-\-[\r\n]+/s", $this->rawData, $parsed))
		{
			$this->metaDataOffsetBytes = strlenb($parsed[0]);
			foreach(preg_split("/[\r\n]+/", $parsed[2]) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !strempty($matches[2])) $this->set(lcfirst($matches[1]), $matches[2]);
			}
		} else if(preg_match("/^(\xEF\xBB\xBF)?([^\r\n]+)[\r\n]+=+[\r\n]+/", $this->rawData, $parsed)) {
			$this->metaDataOffsetBytes = strlenb($parsed[0]);
			$this->set("title", $parsed[2]);
		}
		
		$shortHeader = $this->location == $this->yellow->pages->getHomeLocation($this->location);
		$titleHeader = $shortHeader ? $this->get("sitename") : $this->get("title")." - ".$this->get("sitename");
		if(!$this->isExisting("titleHeader")) $this->set("titleHeader", $titleHeader);
		if(!$this->isExisting("titleNavigation")) $this->set("titleNavigation", $this->get("title"));
		if(!$this->isExisting("titleContent")) $this->set("titleContent", $this->get("title"));
		$this->set("pageRead", $this->yellow->toolbox->getUrl(
			$this->yellow->config->get("serverScheme"), $this->yellow->config->get("serverName"),
			$this->yellow->config->get("serverBase"), $this->location));
		$this->set("pageEdit", $this->yellow->toolbox->getUrl(
			$this->yellow->config->get("webinterfaceServerScheme"), $this->yellow->config->get("webinterfaceServerName"),
			$this->yellow->config->get("serverBase"), rtrim($this->yellow->config->get("webinterfaceLocation"), '/').$this->location));
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseMeta")) $value["obj"]->onParseMeta($this, $this->rawData);
		}
	}
	
	// Parse page content on demand
	function parseContent()
	{
		if(!is_object($this->parser))
		{
			if($this->yellow->plugins->isExisting($this->get("parser")))
			{
				$plugin = $this->yellow->plugins->plugins[$this->get("parser")];
				if(method_exists($plugin["obj"], "onParseContentText"))
				{
					$this->parser = $plugin["obj"];
					$this->parserData = $this->parser->onParseContentText($this, $this->getContent(true));
					foreach($this->yellow->plugins->plugins as $key=>$value)
					{
						if(method_exists($value["obj"], "onParseContent"))
						{
							$output = $value["obj"]->onParseContent($this, $this->parserData);
							if(!is_null($output)) { $this->parserData = $output; }
						}
					}
					if(!$this->isExisting("description"))
					{
						$this->set("description", $this->yellow->toolbox->createTextDescription($this->parserData, 150));
					}
					if(!$this->isExisting("keywords"))
					{
						$this->set("keywords", $this->yellow->toolbox->createTextKeywords($this->get("title"), 10));
					}
				}
			}
			if(defined("DEBUG") && DEBUG>=2) echo "YellowPage::parseContent location:".$this->location."<br/>\n";
		}
	}
	
	// Parse page custom type
	function parseType($name, $text, $typeShortcut)
	{
		$output = NULL;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseType"))
			{
				$output = $value["obj"]->onParseType($this, $name, $text, $typeShortcut);
				if(!is_null($output)) break;
			}
		}
		if(defined("DEBUG") && DEBUG>=3 && !empty($name)) echo "YellowPage::parseType name:$name shortcut:$typeShortcut<br/>\n";
		return $output;
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
			$this->parseDataUpdate();
			$text = substrb($this->rawData, $this->metaDataOffsetBytes);
		} else {
			$this->parseContent();
			$text = $this->parserData;
		}
		return $text;
	}
	
	// Return page extra header, HTML encoded
	function getHeaderExtra()
	{
		$header = "";
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onHeaderExtra")) $header .= $value["obj"]->onHeaderExtra($this);
		}
		return $header;
	}
	
	// Return parent page relative to current page, NULL if none
	function getParent()
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->find($parentLocation);
	}
	
	// Return top-level page for current page, NULL if none
	function getParentTop($homeFailback = true)
	{
		$parentTopLocation = $this->yellow->pages->getParentTopLocation($this->location);
		if(!$this->yellow->pages->find($parentTopLocation) && $homeFailback)
		{
			$parentTopLocation = $this->yellow->pages->getHomeLocation($this->location);
		}
		return $this->yellow->pages->find($parentTopLocation);
	}
	
	// Return pages on the same level as current page
	function getSiblings($showInvisible = false)
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->findChildren($parentLocation, $showInvisible);
	}
	
	// Return child pages relative to current page
	function getChildren($showInvisible = false)
	{
		return $this->yellow->pages->findChildren($this->location, $showInvisible);
	}
	
	// Return absolute page location
	function getLocation()
	{
		return $this->base.$this->location;
	}
	
	// Return page URL, with server scheme and server name
	function getUrl()
	{
		return $this->yellow->toolbox->getUrl($this->serverScheme, $this->serverName, $this->base, $this->location);
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
	
	// Respond with error page
	function error($statusCode, $pageError = "")
	{
		if(!$this->isExisting("pageError") && $statusCode>0)
		{
			$this->statusCode = $statusCode;
			$this->set("pageError", empty($pageError) ? "Template/snippet error!" : $pageError);
		}
	}
	
	// Respond with status code, no page content
	function clean($statusCode, $responseHeader = "")
	{
		if(!$this->isExisting("pageClean") && $statusCode>0)
		{
			$this->statusCode = $statusCode;
			$this->headerData = array();
			if(!empty($responseHeader)) $this->header($responseHeader);
			$this->set("pageClean", (string)$statusCode);
		}
	}
	
	// Add page response header, HTTP format
	function header($responseHeader)
	{
		$tokens = explode(':', $responseHeader, 2);
		$this->setHeader(trim($tokens[0]), trim($tokens[1]));
	}
	
	// Check if response header exists
	function isHeader($key)
	{
		return !is_null($this->headerData[$key]);
	}

	// Check if page meta data exists
	function isExisting($key)
	{
		return !is_null($this->metaData[$key]);
	}
	
	// Check if page with error
	function isError()
	{
		return $this->isExisting("pageError");
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
class YellowPageCollection extends ArrayObject
{
	var $yellow;				//access to API
	var $filterValue;			//current page filter value
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
			$value = strreplaceu(' ', '-', strtoloweru($value));
			$valueLength = strlenu($value);
			foreach($this->getArrayCopy() as $page)
			{
				if($page->isExisting($key))
				{
					foreach(preg_split("/,\s*/", $page->get($key)) as $pageValue)
					{
						$pageValueLength = $exactMatch ? strlenu($pageValue) : $valueLength;
						if($value == substru(strreplaceu(' ', '-', strtoloweru($pageValue)), 0, $pageValueLength))
						{
							$this->filterValue = substru($pageValue, 0, $pageValueLength);
							array_push($array, $page);
						}
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
		if($limit < $this->count())
		{
			$pagination = $this->yellow->config->get("contentPagination");
			if(isset($_REQUEST[$pagination])) $this->paginationPage = max(1, $_REQUEST[$pagination]);
		}
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
			$pagination = $this->yellow->config->get("contentPagination");
			$location = $this->yellow->page->getLocation();
			$locationArgs = $this->yellow->toolbox->getLocationArgsNew(
				$pageNumber>1 ? "$pagination:$pageNumber" : "$pagination:", $pagination);
		}
		return $location.$locationArgs;
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
	
	// Return current page filter
	function getFilter()
	{
		return $this->filterValue;
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

// Yellow pages from file system
class YellowPages
{
	var $yellow;			//access to API
	var $pages;				//scanned pages
	var $requestHandler;	//request handler name
	var $snippetArgs;		//requested snippet arguments
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->pages = array();
	}
	
	// Return page collection with all pages from file system
	function index($showInvisible = false, $showLanguages = false, $levelMax = 0)
	{
		$rootLocation = $showLanguages ? "" : $this->getRootLocation($this->yellow->page->location);
		return $this->findChildrenRecursive($rootLocation, $showInvisible, $levelMax);
	}
	
	// Return page collection with top-level navigation
	function top($showInvisible = false)
	{
		$rootLocation = $this->getRootLocation($this->yellow->page->location);
		return $this->findChildren($rootLocation, $showInvisible);
	}
	
	// Return page collection with path ancestry
	function path($location, $absoluteLocation = false)
	{
		$pages = new YellowPageCollection($this->yellow);
		if($page = $this->find($location, $absoluteLocation))
		{
			$pages->prepend($page);
			for(; $parent = $page->getParent(); $page=$parent) $pages->prepend($parent);
			$home = $this->find($this->getHomeLocation($page->location));
			if($home && $home->location!=$page->location) $pages->prepend($home);
		}
		return $pages;
	}
	
	// Return page collection with multiple languages
	function translation($location, $absoluteLocation = false, $showInvisible = false)
	{
		$pages = new YellowPageCollection($this->yellow);
		if($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
		$postfix = substru($location, strlenu($this->getRootLocation($location)) - 4);
		$this->scanChildren("");
		foreach($this->pages[""] as $page)
		{
			if($content = $this->find(substru($page->location, 4).$postfix))
			{
				if($content->isVisible() || $showInvisible) $pages->append($content);
			}
		}
		return $pages;
	}
	
	// Return empty page collection
	function create()
	{
		return new YellowPageCollection($this->yellow);
	}
	
	// Return one page from file system, NULL if not found
	function find($location, $absoluteLocation = false)
	{
		if(!$this->yellow->toolbox->isRootLocation($location))
		{
			if($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
			$parentLocation = $this->getParentLocation($location);
			$this->scanChildren($parentLocation);
			foreach($this->pages[$parentLocation] as $page) if($page->location == $location) { $found = true; break; }
		}
		return $found ? $page : NULL;
	}
	
	// Find child pages
	function findChildren($location, $showInvisible = false)
	{
		$pages = new YellowPageCollection($this->yellow);
		$this->scanChildren($location);
		foreach($this->pages[$location] as $page)
		{
			if($page->isVisible() || $showInvisible)
			{
				if(!$this->yellow->toolbox->isRootLocation($page->location)) $pages->append($page);
			}
		}
		return $pages;
	}
	
	// Find child pages recursively
	function findChildrenRecursive($location, $showInvisible = false, $levelMax = 0)
	{
		--$levelMax;
		$pages = new YellowPageCollection($this->yellow);
		$this->scanChildren($location);
		foreach($this->pages[$location] as $page)
		{
			if($page->isVisible() || $showInvisible)
			{
				if(!$this->yellow->toolbox->isRootLocation($page->location)) $pages->append($page);
				if(!$this->yellow->toolbox->isFileLocation($page->location) && $levelMax!=0)
				{
					$pages->merge($this->findChildrenRecursive($page->location, $showInvisible, $levelMax));
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
			if(defined("DEBUG") && DEBUG>=2) echo "YellowPages::scanChildren location:$location<br/>\n";
			$this->pages[$location] = array();
			if(empty($location))
			{
				$rootLocations = $this->yellow->toolbox->findRootLocations($this->yellow->config->get("contentDir"),
					$this->yellow->config->get("contentRootDir"));
				foreach($rootLocations as $rootLocation)
				{
					$page = new YellowPage($this->yellow,
						$this->yellow->page->serverScheme, $this->yellow->page->serverName, $this->yellow->page->base,
						$rootLocation, "");
					$page->parseData("", 0, false);
					array_push($this->pages[$location], $page);
				}
			} else {
				$fileNames = $this->yellow->toolbox->findChildrenFromLocation($location, $this->yellow->config->get("contentDir"),
					$this->yellow->config->get("contentRootDir"), $this->yellow->config->get("contentHomeDir"),
					$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension"));
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
					$page = new YellowPage($this->yellow,
						$this->yellow->page->serverScheme, $this->yellow->page->serverName, $this->yellow->page->base,
						$this->yellow->toolbox->findLocationFromFile($fileName, $this->yellow->config->get("contentDir"),
						$this->yellow->config->get("contentRootDir"), $this->yellow->config->get("contentHomeDir"),
						$this->yellow->config->get("contentDefaultFile"), $this->yellow->config->get("contentExtension")),
						$fileName);
					$page->parseData($fileData, $statusCode, false);
					array_push($this->pages[$location], $page);
				}
			}
		}
	}
	
	// Return root location
	function getRootLocation($location)
	{
		$rootLocation = "root/";
		if($this->yellow->config->get("multiLanguageMode"))
		{
			$this->scanChildren("");
			foreach($this->pages[""] as $page)
			{
				$token = substru($page->location, 4);
				if($token!="/" && substru($location, 0, strlenu($token))==$token) { $rootLocation = "root$token"; break; }
			}
		}
		return $rootLocation;
	}

	// Return home location
	function getHomeLocation($location)
	{
		return substru($this->getRootLocation($location), 4);
	}
	
	// Return parent location
	function getParentLocation($location)
	{
		$prefix = rtrim(substru($this->getRootLocation($location), 4), '/');
		if(preg_match("#^($prefix.*\/).+?$#", $location, $matches))
		{
			if($matches[1]!="$prefix/" || $this->yellow->toolbox->isFileLocation($location)) $parentLocation = $matches[1];
		}
		if(empty($parentLocation)) $parentLocation = "root$prefix/";
		return $parentLocation;
	}
	
	// Return top-level location
	function getParentTopLocation($location)
	{
		$prefix = rtrim(substru($this->getRootLocation($location), 4), '/');
		if(preg_match("#^($prefix.+?\/)#", $location, $matches)) $parentTopLocation = $matches[1];
		if(empty($parentTopLocation)) $parentTopLocation = "$prefix/";
		return $parentTopLocation;
	}
}

// Yellow configuration
class YellowConfig
{
	var $yellow;			//access to API
	var $modified;			//configuration modification time
	var $config;			//configuration
	var $configDefaults;	//configuration defaults
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->modified = 0;
		$this->config = array();
		$this->configDefaults = array();
	}
	
	// Load configuration from file
	function load($fileName)
	{
		$fileData = @file($fileName);
		if($fileData)
		{
			if(defined("DEBUG") && DEBUG>=2) echo "YellowConfig::load file:$fileName<br/>\n";
			$this->modified = filemtime($fileName);
			foreach($fileData as $line)
			{
				if(preg_match("/^\//", $line)) continue;
				preg_match("/^\s*(.*?)\s*=\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !strempty($matches[2]))
				{
					$this->set($matches[1], $matches[2]);
					if(defined("DEBUG") && DEBUG>=3) echo "YellowConfig::load key:$matches[1] $matches[2]<br/>\n";
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
		if(!is_null($this->config[$key]))
		{
			$value = $this->config[$key];
		} else {
			$value = !is_null($this->configDefaults[$key]) ? $this->configDefaults[$key] : "";
		}
		return $value;
	}
	
	// Return configuration, HTML encoded
	function getHtml($key)
	{
		return htmlspecialchars($this->get($key));
	}
	
	// Return configuration strings
	function getData($filterStart = "", $filterEnd = "")
	{
		$config = array();
		if(empty($filterStart) && empty($filterEnd))
		{
			$config = $this->config;
		} else {
			foreach($this->config as $key=>$value)
			{
				if(!empty($filterStart) && substru($key, 0, strlenu($filterStart))==$filterStart) $config[$key] = $value;
				if(!empty($filterEnd) && substru($key, -strlenu($filterEnd))==$filterEnd) $config[$key] = $value;
			}
		}
		return $config;
	}
	
	// Return configuration modification time, Unix time
	function getModified($httpFormat = false)
	{
		return $httpFormat ? $this->yellow->toolbox->getHttpTimeFormatted($this->modified) : $this->modified;
	}
	
	// Check if configuration exists
	function isExisting($key)
	{
		return !is_null($this->config[$key]);
	}
}

// Yellow text strings
class YellowText
{
	var $yellow;		//access to API
	var $modified;		//text modification time
	var $text;			//text strings
	var $language;		//current language
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->modified = 0;
		$this->text = array();
	}
	
	// Load text strings from file
	function load($fileName)
	{
		$path = dirname($fileName);
		$regex = "/^".basename($fileName)."$/";
		foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
		{
			$fileData = @file($entry);
			if($fileData)
			{
				if(defined("DEBUG") && DEBUG>=2) echo "YellowText::load file:$entry<br/>\n";
				$this->modified = max($this->modified, filemtime($entry));
				$language = "";
				foreach($fileData as $line)
				{
					preg_match("/^\s*(.*?)\s*=\s*(.*?)\s*$/", $line, $matches);
					if($matches[1]=="language" && !strempty($matches[2])) { $language = $matches[2]; break; }
				}
				foreach($fileData as $line)
				{
					if(preg_match("/^\//", $line)) continue;
					preg_match("/^\s*(.*?)\s*=\s*(.*?)\s*$/", $line, $matches);
					if(!empty($language) && !empty($matches[1]) && !strempty($matches[2]))
					{
						$this->setText($matches[1], $matches[2], $language);
						if(defined("DEBUG") && DEBUG>=3) echo "YellowText::load key:$matches[1] $matches[2]<br/>\n";
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
	function setText($key, $value, $language)
	{
		if(is_null($this->text[$language])) $this->text[$language] = array();
		$this->text[$language][$key] = $value;
	}
	
	// Return text string for specific language
	function getText($key, $language)
	{
		return ($this->isText($key, $language)) ? $this->text[$language][$key] : "[$key]";
	}
	
	// Return text string for specific language, HTML encoded
	function getTextHtml($key, $language)
	{
		return htmlspecialchars($this->getText($key, $language));
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
	function getData($filterStart = "", $language = "")
	{
		$text = array();
		if(empty($language)) $language = $this->language;
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
	
	// Return text modification time, Unix time
	function getModified($httpFormat = false)
	{
		return $httpFormat ? $this->yellow->toolbox->getHttpTimeFormatted($this->modified) : $this->modified;
	}
	
	// Check if text string for specific language exists
	function isText($key, $language)
	{
		return !is_null($this->text[$language]) && !is_null($this->text[$language][$key]);
	}
	
	// Check if text string exists
	function isExisting($key)
	{
		return !is_null($this->text[$this->language]) && !is_null($this->text[$this->language][$key]);
	}
}

// Yellow toolbox with helpers
class YellowToolbox
{
	// Return server scheme from current HTTP request
	function getServerScheme()
	{
		$serverScheme = "";
		if(preg_match("/^HTTP\//", $_SERVER["SERVER_PROTOCOL"]))
		{
			$secure = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]!="off";
			$serverScheme = $secure ? "https" : "http";
		}
		return $serverScheme;
	}
	
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
		return rawurldecode(($pos = strposu($uri, '?')) ? substru($uri, 0, $pos) : $uri);
	}
	
	// Return location from current HTTP request, remove unwanted path tokens
	function getLocationClean()
	{
		$string = $this->getLocation();
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
		if(preg_match("/^(.*?\/)([^\/]+:.*)$/", $location, $matches))
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
		if(!preg_match("/^HTTP\//", $_SERVER["SERVER_PROTOCOL"])) $_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
		return $location;
	}
	
	// Return location arguments from current HTTP request
	function getLocationArgs()
	{
		if(preg_match("/^(.*?\/)([^\/]+:.*)$/", $this->getLocation(), $matches)) $locationArgs = $matches[2];
		return $locationArgs;
	}
	
	// Return location arguments from current HTTP request, modify an argument
	function getLocationArgsNew($arg, $pagination)
	{
		preg_match("/^(.*?):(.*)$/", $arg, $args);
		if(preg_match("/^(.*?\/)([^\/]+:.*)$/", $this->getLocation(), $matches))
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
		if(!empty($locationArgs))
		{
			if(!$this->isPaginationLocation($locationArgs, $pagination)) $locationArgs .= '/';
			$locationArgs = $this->normaliseArgs($locationArgs, false, false);
		}
		return $locationArgs;
	}
	
	// Return location arguments from current HTTP request, convert form into clean URL
	function getLocationArgsCleanUrl($pagination)
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
		if(!empty($locationArgs))
		{
			if(!$this->isPaginationLocation($locationArgs, $pagination)) $locationArgs .= '/';
			$locationArgs = $this->normaliseArgs($locationArgs, false, false);
		}
		return $locationArgs;
	}
	
	// Return location header with URL
	function getLocationHeader($serverScheme, $serverName, $base, $location)
	{
		return "Location: ".$this->getUrl($serverScheme, $serverName, $base, $location);
	}
	
	// Check if location contains location arguments
	function isLocationArgs($location)
	{
		return preg_match("/[^\/]+:.*$/", $location);
	}
	
	// Check if location contains pagination
	function isPaginationLocation($location, $pagination)
	{
		return preg_match("/^(.*\/)?$pagination:\d*$/", $location);
	}

	// Check if location is specifying root
	function isRootLocation($location)
	{
		return $location[0] != "/";
	}

	// Check if location is specifying file or directory
	function isFileLocation($location)
	{
		return substru($location, -1, 1) != "/";
	}
	
	// Check if file is unmodified since last HTTP request
	function isFileNotModified($lastModified)
	{
		return isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && $_SERVER["HTTP_IF_MODIFIED_SINCE"]==$lastModified;
	}
	
	// Check if clean URL is requested
	function isRequestCleanUrl($location)
	{
		return (isset($_GET["clean-url"]) || isset($_POST["clean-url"])) && !$this->isFileLocation($location);
	}
	
	// Check if content type is valid for location
	function isValidContentType($contentType, $location)
	{
		$ok = false;
		$extension = ($pos = strrposu($location, '.')) ? substru($location, $pos) : "";
		if($contentType == "text/html")
		{
			if($this->isFileLocation($location))
			{
				if(empty($extension) || preg_match("/^\.(html|md)$/", $extension)) $ok = true;
			} else {
				$ok = true;
			}
		} else {
			if($this->isFileLocation($location))
			{
				if(!empty($extension) && preg_match("/^.*$extension$/", $contentType)) $ok = true;
			}
		}
		return $ok;
	}
	
	// Check if location is valid
	function isValidLocation($location)
	{
		$string = "";
		$tokens = explode('/', $location);
		for($i=1; $i<count($tokens); ++$i) $string .= '/'.$this->normaliseName($tokens[$i]);
		return $location == $string;
	}
	
	// Check if location is within current HTTP request
	function isActiveLocation($location, $currentLocation, $homeLocation)
	{
		if($location != $homeLocation)
		{
			$active = substru($currentLocation, 0, strlenu($location))==$location;
		} else {
			$active = $this->getDirectoryLocation($currentLocation)==$location;
		}
		return $active;
	}
	
	// Check if location is visible in navigation
	function isVisibleLocation($location, $fileName, $pathBase)
	{
		$visible = true;
		if(substru($fileName, 0, strlenu($pathBase)) == $pathBase)
		{
			$fileName = substru($fileName, strlenu($pathBase));
			$tokens = explode('/', $fileName);
			for($i=0; $i<count($tokens)-1; ++$i)
			{
				if(!preg_match("/^[\d\-\_\.]+(.*)$/", $tokens[$i])) { $visible = false; break; }
			}
		} else {
			$visible = false;
		}
		return $visible;
	}
	
	// Return root configuration
	function findRootConfig($pathBase, $pathRoot, $pathHome, $multiLanguageMode)
	{
		$path = $pathBase;
		if(!$multiLanguageMode) $pathRoot = "";
		if(!empty($pathRoot))
		{
			$token = $root = rtrim($pathRoot, '/');
			foreach($this->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				if(empty($firstRoot)) { $firstRoot = $token = $entry; }
				if($this->normaliseName($entry) == $root) { $token = $entry; break; }
			}
			$pathRoot = $this->normaliseName($token)."/";
			$path .= "$firstRoot/";
		}
		if(!empty($pathHome))
		{
			$token = $home = rtrim($pathHome, '/');
			foreach($this->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				if(empty($firstHome)) { $firstHome = $token = $entry; }
				if($this->normaliseName($entry) == $home) { $token = $entry; break; }
			}
			$pathHome = $this->normaliseName($token)."/";
		}
		return array($pathRoot, $pathHome);
	}
	
	// Return root languages
	function findRootLanguages($pathBase, $pathRoot, $includeInvisible = false)
	{
		$languages = array();
		if(!empty($pathRoot))
		{
			foreach($this->getDirectoryEntries($pathBase, "/.*/", true, true, false) as $entry)
			{
				if($includeInvisible || preg_match("/^[\d\-\_\.]+(.*)$/", $entry))
				{
					$token = $this->normaliseName($entry);
					if(strlenu($token) == 2) array_push($languages, $token);
				}
			}
		}
		return $languages;
	}
	
	// Return root locations
	function findRootLocations($pathBase, $pathRoot)
	{
		$locations = array("root/");
		if(!empty($pathRoot))
		{
			$root = rtrim($pathRoot, '/');
			foreach($this->getDirectoryEntries($pathBase, "/.*/", true, true, false) as $entry)
			{
				$token = $this->normaliseName($entry);
				if($token != $root) array_push($locations, "root/$token/");
			}
		}
		return $locations;
	}
	
	// Return location from file path
	function findLocationFromFile($fileName, $pathBase, $pathRoot, $pathHome, $fileDefault, $fileExtension)
	{
		$location = "/";
		if(substru($fileName, 0, strlenu($pathBase)) == $pathBase)
		{
			$fileName = substru($fileName, strlenu($pathBase));
			$tokens = explode('/', $fileName);
			if(!empty($pathRoot))
			{
				$token = $this->normaliseName($tokens[0]).'/';
				if($token!=$pathRoot) $location .= $token;
				array_shift($tokens);
			}
			for($i=0; $i<count($tokens)-1; ++$i)
			{
				$token = $this->normaliseName($tokens[$i]).'/';
				if($i || $token!=$pathHome) $location .= $token;
			}
			$token = $this->normaliseName($tokens[$i]);
			$fileFolder = $this->normaliseName($tokens[$i-1]).$fileExtension;
			if($token!=$fileDefault && $token!=$fileFolder) $location .= $this->normaliseName($tokens[$i], true, true);
			$extension = ($pos = strrposu($fileName, '.')) ? substru($fileName, $pos) : "";
			if($extension != $fileExtension) $invalid = true;
		} else {
			$invalid = true;
		}
		if(defined("DEBUG") && DEBUG>=2)
		{
			$debug = ($invalid ? "INVALID" : $location)." <- $pathBase$fileName";
			echo "YellowToolbox::findLocationFromFile $debug<br/>\n";
		}
		return $invalid ? "" : $location;
	}
	
	// Return file path from location
	function findFileFromLocation($location, $pathBase, $pathRoot, $pathHome, $fileDefault, $fileExtension)
	{
		$path = $pathBase;
		$tokens = explode('/', $location);
		if($this->isRootLocation($location))
		{
			if(!empty($pathRoot))
			{
				$token = (count($tokens) > 2) ? $tokens[1] : rtrim($pathRoot, '/');
				$path .= $this->findFileDirectory($path, $token, true, true, $found, $invalid);
			}
		} else {
			if(!empty($pathRoot))
			{
				if(count($tokens) > 2)
				{
					if($this->normaliseName($tokens[1]) == $this->normaliseName($pathRoot)) $invalid = true;
					$path .= $this->findFileDirectory($path, $tokens[1], false, true, $found, $invalid);
					if($found) array_shift($tokens);
				}
				if(!$found) $path .= $this->findFileDirectory($path, rtrim($pathRoot, '/'), true, true, $found, $invalid);
				
			}
			if(count($tokens) > 2)
			{
				if($this->normaliseName($tokens[1]) == $this->normaliseName($pathHome)) $invalid = true;
				for($i=1; $i<count($tokens)-1; ++$i)
				{
					$path .= $this->findFileDirectory($path, $tokens[$i], true, true, $found, $invalid);
				}
			} else {
				$i = 1;
				$tokens[0] = rtrim($pathHome, '/');
				$path .= $this->findFileDirectory($path, $tokens[0], true, true, $found, $invalid);
			}
			if(!empty($fileDefault) && !empty($fileExtension))
			{
				$fileFolder = $tokens[$i-1].$fileExtension;
				if(!empty($tokens[$i]))
				{
					$token = $tokens[$i].$fileExtension;
					if($token==$fileDefault || $token==$fileFolder) $invalid = true;
					$path .= $this->findFileDirectory($path, $token, true, false, $found, $invalid);
				} else {
					$path .= $this->findFileDefault($path, $fileDefault, $fileFolder);
				}
				if(defined("DEBUG") && DEBUG>=2)
				{
					$debug = "$location -> ".($invalid ? "INVALID" : $path);
					echo "YellowToolbox::findFileFromLocation $debug<br/>\n";
				}
			}
		}
		return $invalid ? "" : $path;
	}

	// Return file or directory that matches token
	function findFileDirectory($path, $token, $tokenFailback, $directory, &$found, &$invalid)
	{
		if($this->normaliseName($token) != $token) $invalid = true;
		if(!$invalid)
		{
			$regex = "/^[\d\-\_\.]*".strreplaceu('-', '.', $token)."$/";
			foreach($this->getDirectoryEntries($path, $regex, false, $directory, false) as $entry)
			{
				if($this->normaliseName($entry) == $token) { $token = $entry; $found = true; break; }
			}
		}
		if($directory) $token .= '/';
		return ($tokenFailback || $found) ? $token : "";
	}
	
	// Return default file in directory
	function findFileDefault($path, $fileDefault, $fileFolder)
	{
		$token = $fileDefault;
		if(!is_file($path."/".$fileDefault))
		{
			$regex = "/^[\d\-\_\.]*($fileDefault|$fileFolder)$/";
			foreach($this->getDirectoryEntries($path, $regex, true, false, false) as $entry)
			{
				if($this->normaliseName($entry) == $fileDefault) { $token = $entry; break; }
				if($this->normaliseName($entry) == $fileFolder) { $token = $entry; break; }
			}
		}
		return $token;
	}
	
	// Return children from location
	function findChildrenFromLocation($location, $pathBase, $pathRoot, $pathHome, $fileDefault, $fileExtension)
	{
		$fileNames = array();
		if(!$this->isFileLocation($location))
		{
			$path = $this->findFileFromLocation($location, $pathBase, $pathRoot, $pathHome, "", "");
			foreach($this->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				$fileFolder = $this->normaliseName($entry).$fileExtension;
				$token = $this->findFileDefault($path.$entry, $fileDefault, $fileFolder);
				array_push($fileNames, $path.$entry."/".$token);
			}
			if(!$this->isRootLocation($location))
			{
				$fileFolder = $this->normaliseName(basename($path)).$fileExtension;
				$regex = "/^.*\\".$fileExtension."$/";
				foreach($this->getDirectoryEntries($path, $regex, true, false, false) as $entry)
				{
					if($this->normaliseName($entry) == $fileDefault) continue;
					if($this->normaliseName($entry) == $fileFolder) continue;
					if($this->normaliseName($entry, true, true) == "") continue;
					array_push($fileNames, $path.$entry);
				}
			}
		}
		return $fileNames;
	}
	
	// Return theme/template name from file path
	function findNameFromFile($fileName, $pathBase, $nameDefault, $fileExtension)
	{
		$name = "";
		if(preg_match("/^.*\/(.+?)$/", dirname($fileName), $matches)) $name = $this->normaliseName($matches[1]);
		if(!is_file("$pathBase$name$fileExtension")) $name = $this->normaliseName($nameDefault);
		return $name;
	}

	// Return language from file path
	function findLanguageFromFile($fileName, $pathBase, $pathRoot, $languageDefault)
	{
		$language = $languageDefault;
		if(!empty($pathRoot))
		{
			$fileName = substru($fileName, strlenu($pathBase));
			if(preg_match("/^(.+?)\//", $fileName, $matches)) $name = $this->normaliseName($matches[1]);
			if(strlenu($name) == 2) $language = $name;
		}
		return $language;
	}
	
	// Return file path for new page
	function findFileNew($fileName, $pathBase, $fileNew, $fileDefault)
	{
		if(preg_match("/^.*\/(.+?)$/", dirname($fileName), $matches)) $name = $this->normaliseName($matches[1]);
		$fileName = strreplaceu("(.*)", $name, $pathBase.$fileNew);
		if(!is_file($fileName))
		{
			$name = $this->normaliseName($fileDefault, true, true);
			$fileName = strreplaceu("(.*)", $name, $pathBase.$fileNew);
		}
		return $fileName;
	}
	
	// Return file path from title
	function findFileFromTitle($titlePrefix, $titleText, $fileName, $fileDefault, $fileExtension)
	{
		preg_match("/^([\d\-\_\.]*)(.*)$/", $titlePrefix, $matches);
		if(preg_match("/\d$/", $matches[1])) $matches[1] .= '-';
		$titleText = $this->normaliseName($titleText, false, false, true);
		preg_match("/^([\d\-\_\.]*)(.*)$/", $matches[1].$titleText, $matches);
		$fileNamePrefix = $matches[1];
		$fileNameText = empty($matches[2]) ? $fileDefault : $matches[2].$fileExtension;
		return dirname($fileName)."/".$fileNamePrefix.$fileNameText;
	}
	
	// Normalise location, make absolute location
	function normaliseLocation($location, $pageBase, $pageLocation, $filterStrict = true)
	{
		if(!preg_match("/^\w+:/", trim(html_entity_decode($location, ENT_QUOTES, "UTF-8"))))
		{
			if(!preg_match("/^\//", $location))
			{
				$location = $this->getDirectoryLocation($pageBase.$pageLocation).$location;
			}
			else if(!preg_match("#^$pageBase#", $location))
			{
				$location = $pageBase.$location;
			}
		} else {
			if($filterStrict && !preg_match("/^(http|https|ftp|mailto):/", $location)) $location = "error-xss-filter";
		}
		return $location;
	}
	
	// Normalise location arguments
	function normaliseArgs($text, $appendSlash = true, $filterStrict = true)
	{
		if($appendSlash) $text .= '/';
		if($filterStrict) $text = strreplaceu(' ', '-', strtoloweru($text));
		return strreplaceu(array('%3A','%2F'), array(':','/'), rawurlencode($text));
	}
	
	// Normalise file/directory/other name
	function normaliseName($text, $removePrefix = true, $removeExtension = false, $filterStrict = false)
	{
		if($removeExtension) $text = ($pos = strrposu($text, '.')) ? substru($text, 0, $pos) : $text;
		if($removePrefix) if(preg_match("/^[\d\-\_\.]+(.*)$/", $text, $matches)) $text = $matches[1];
		if($filterStrict) $text = strreplaceu('.', '-', strtoloweru($text));
		return preg_replace("/[^\pL\d\-\_\.]/u", "-", rtrim($text, '/'));
	}
		
	// Normalise text into UTF-8 NFC
	function normaliseUnicode($text)
	{
		if(PHP_OS=="Darwin" && !mb_check_encoding($text, "ASCII"))
		{
			$utf8nfc = preg_match("//u", $text) && !preg_match('/[^\x00-\x{2FF}]/u', $text);
			if(!$utf8nfc) $text = iconv("UTF-8-MAC", "UTF-8", $text);
		}
		return $text;
	}
	
	// Return human readable HTTP server status
	function getHttpStatusFormatted($statusCode)
	{
		switch($statusCode)
		{
			case 0:		$text = "$_SERVER[SERVER_PROTOCOL] $statusCode No data"; break;
			case 200:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode OK"; break;
			case 301:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Moved permanently"; break;
			case 302:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Moved temporarily"; break;
			case 303:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Reload please"; break;
			case 304:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Not modified"; break;
			case 400:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Bad request"; break;
			case 401:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Unauthorised"; break;
			case 404:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Not found"; break;
			case 424:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Not existing"; break;
			case 500:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Server error"; break;
			default:	$text = "$_SERVER[SERVER_PROTOCOL] $statusCode Unknown status";
		}
		return $text;
	}
							  
	// Return human readable HTTP time
	function getHttpTimeFormatted($timestamp)
	{
		return gmdate("D, d M Y H:i:s", $timestamp)." GMT";
	}
	
	// Return URL
	function getUrl($serverScheme, $serverName, $base, $location)
	{
		if(!preg_match("/^\w+:/", $location))
		{
			$url = "$serverScheme://$serverName$base$location";
		} else {
			$url = $location;
		}
		return $url;
	}
	
	// Return directory location
	function getDirectoryLocation($location)
	{
		return ($pos = strrposu($location, '/')) ? substru($location, 0, $pos+1) : "/";
	}
	
	// Return files and directories
	function getDirectoryEntries($path, $regex = "/.*/", $sort = true, $directories = true, $includePath = true)
	{
		$entries = array();
		$dirHandle = @opendir($path);
		if($dirHandle)
		{
			$path = rtrim($path, '/');
			while(($entry = readdir($dirHandle)) !== false)
			{
				if(substru($entry, 0, 1) == ".") continue;
				$entry = $this->normaliseUnicode($entry);
				if(preg_match($regex, $entry))
				{
					if($directories)
					{
						if(is_dir("$path/$entry")) array_push($entries, $includePath ? "$path/$entry" : $entry);
					} else {
						if(is_file("$path/$entry")) array_push($entries, $includePath ? "$path/$entry" : $entry);
					}
				}
			}
			if($sort) natsort($entries);
			closedir($dirHandle);
		}
		return $entries;
	}
	
	// Return files and directories recursively
	function getDirectoryEntriesRecursive($path, $regex = "/.*/", $sort = true, $directories = true, $levelMax = 0)
	{
		--$levelMax;
		$entries = $this->getDirectoryEntries($path, $regex, $sort, $directories);
		if($levelMax != 0)
		{
			foreach($this->getDirectoryEntries($path, "/.*/", $sort, true) as $entry)
			{
				$entries = array_merge($entries, $this->getDirectoryEntriesRecursive($entry, $regex, $sort, $directories, $levelMax));
			}
		}
		return $entries;
	}
	
	// Delete directory
	function deleteDirectory($path, $recursive = false)
	{
		if($recursive)
		{
			$iterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
			$files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
			foreach($files as $file)
			{
				if($file->isDir())
				{
					@rmdir($file->getRealPath());
				} else {
					@unlink($file->getRealPath());
				}
			}
		}
		return @rmdir($path);
	}
	
	// Return file data
	function getFileData($fileName)
	{
		$fileData = "";
		$fileHandle = @fopen($fileName, "r");
		if($fileHandle)
		{
			$fileData = fread($fileHandle, filesize($fileName));
			fclose($fileHandle);
		}
		return $fileData;
	}

	// Create file
	function createFile($fileName, $fileData, $mkdir = false)
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
	
	// Rename file
	function renameFile($fileNameSource, $fileNameDest, $mkdir = false)
	{
		if($mkdir)
		{
			$path = dirname($fileNameDest);
			if(!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
		}
		return @rename($fileNameSource, $fileNameDest);
	}
	
	// Set file modification time, Unix time
	function modifyFile($fileName, $modified)
	{
		return @touch($fileName, $modified);
	}

	// Delete file
	function deleteFile($fileName)
	{
		return @unlink($fileName);
	}
		
	// Return arguments from text string
	function getTextArgs($text, $optional = "-")
	{
		$tokens = str_getcsv(trim($text), ' ', '"');
		foreach($tokens as $key=>$value) if($value == $optional) $tokens[$key] = "";
		return $tokens;
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
				if(preg_match("/^\/(code|pre)/i", $elementName)) $string = preg_replace("/^(\d+\n){2,}$/", "", $string);
				$string = preg_replace("/\s+/s", " ", $string);
				if(substru($string, 0, 1)==" " && (empty($output) || substru($output, -1)==' ')) $string = substru($string, 1);
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
			for($i=count($elementsOpen)-1; $i>=0; --$i)
			{
				if(!preg_match("/^(dl|ol|ul|table|tbody|thead|tfoot|tr)/i", $elementsOpen[$i])) break;
				$output .= "</".$elementsOpen[$i].">";
			}
			if($lengthMax <= 0) $output .= $endMarkerFound ? $endMarkerText : "…";
			for(; $i>=0; --$i) $output .= "</".$elementsOpen[$i].">";
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
	
	// Return lines from text string
	function getTextLines($text)
	{
		$lines = array();
		$split = preg_split("/(\R)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		for($i=0; $i<count($split)-1; $i+=2) array_push($lines, $split[$i].$split[$i+1]);
		if($split[$i] != '') array_push($lines, $split[$i]);
		return $lines;
	}
	
	// Create title from text string
	function createTextTitle($text)
	{
		if(preg_match("/^.*\/([\w\-]+)/", $text, $matches)) $text = strreplaceu('-', ' ', ucfirst($matches[1]));
		return $text;
	}

	// Create random text for cryptography
	function createSalt($length, $bcryptFormat = false)
	{
		$dataBuffer = $salt = "";
		$dataBufferSize = $bcryptFormat ? intval(ceil($length/4) * 3) : intval(ceil($length/2));
		if(empty($dataBuffer) && function_exists("mcrypt_create_iv"))
		{
			$dataBuffer = @mcrypt_create_iv($dataBufferSize, MCRYPT_DEV_URANDOM);
		}
		if(empty($dataBuffer) && function_exists("openssl_random_pseudo_bytes"))
		{
			$dataBuffer = @openssl_random_pseudo_bytes($dataBufferSize);
		}
		if(strlenb($dataBuffer) == $dataBufferSize)
		{
			if($bcryptFormat)
			{
				$salt = substrb(base64_encode($dataBuffer), 0, $length);
				$base64Chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
				$bcrypt64Chars = "./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
				$salt = strtr($salt, $base64Chars, $bcrypt64Chars);
			} else {
				$salt = substrb(bin2hex($dataBuffer), 0, $length);
			}
		}
		return $salt;
	}
	
	// Create hash with random salt, bcrypt or sha256
	function createHash($text, $algorithm, $cost = 0)
	{
		$hash = "";
		switch($algorithm)
		{
			case "bcrypt":	$prefix = sprintf("$2y$%02d$", $cost);
							$salt = $this->createSalt(22, true);
							$hash = crypt($text, $prefix.$salt);
							if(empty($salt) || strlenb($hash)!=60) $hash = "";
							break;
			case "sha256":	$prefix = "$5y$";
							$salt = $this->createSalt(32);
							$hash = "$prefix$salt".hash("sha256", $salt.$text);
							if(empty($salt) || strlenb($hash)!=100) $hash = "";
							break;
		}
		return $hash;
	}
	
	// Verify that text matches hash
	function verifyHash($text, $algorithm, $hash)
	{
		$hashCalculated = "";
		switch($algorithm)
		{
			case "bcrypt":	if(substrb($hash, 0, 4) == "$2y$") $hashCalculated = crypt($text, $hash); break;
			case "sha256":	if(substrb($hash, 0, 4) == "$5y$")
							{
								$prefix = substrb($hash, 0, 4);
								$salt = substrb($hash, 4, 32);
								$hashCalculated = "$prefix$salt".hash("sha256", $salt.$text);
							}
							break;
		}
		$ok = !empty($hashCalculated) && strlenb($hashCalculated)==strlenb($hash);
		if($ok) for($i=0; $i<strlenb($hashCalculated); ++$i) $ok &= $hashCalculated[$i] == $hash[$i];
		return $ok;
	}
	
	// Detect web browser language
	function detectBrowserLanguage($languages, $languageDefault)
	{
		$language = $languageDefault;
		if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
		{
			foreach(preg_split("/,\s*/", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $string)
			{
				$tokens = explode(';', $string);
				if(in_array($tokens[0], $languages)) { $language = $tokens[0]; break; }
			}
		}
		return $language;
	}
	
	// Detect image dimensions and type, png or jpg
	function detectImageInfo($fileName)
	{
		$width = $height = 0;
		$type = "";
		$fileHandle = @fopen($fileName, "rb");
		if($fileHandle)
		{
			if(substru(strtoloweru($fileName), -3) == "png")
			{
				$dataSignature = fread($fileHandle, 8);
				$dataHeader = fread($fileHandle, 16);
				if(!feof($fileHandle) && $dataSignature=="\x89PNG\r\n\x1a\n")
				{
					$width = (ord($dataHeader[10])<<8) + ord($dataHeader[11]);
					$height = (ord($dataHeader[14])<<8) + ord($dataHeader[15]);
					$type = "png";
				}
			} else if(substru(strtoloweru($fileName), -3) == "jpg") {
				$dataBufferSizeMax = filesize($fileName);
				$dataBufferSize = min($dataBufferSizeMax, 4096);
				$dataBuffer = fread($fileHandle, $dataBufferSize);
				$dataSignature = substrb($dataBuffer, 0, 4);
				if(!feof($fileHandle) && ($dataSignature=="\xff\xd8\xff\xe0" || $dataSignature=="\xff\xd8\xff\xe1"))
				{
					for($pos=2; $pos+8<$dataBufferSize; $pos+=$length)
					{
						if($dataBuffer[$pos] != "\xff") break;
						if($dataBuffer[$pos+1]=="\xc0" || $dataBuffer[$pos+1]=="\xc2")
						{
							$width = (ord($dataBuffer[$pos+7])<<8) + ord($dataBuffer[$pos+8]);
							$height = (ord($dataBuffer[$pos+5])<<8) + ord($dataBuffer[$pos+6]);
							$type = "jpg";
							break;
						}
						$length = (ord($dataBuffer[$pos+2])<<8) + ord($dataBuffer[$pos+3]) + 2;
						while($pos+$length+8 >= $dataBufferSize)
						{
							if($dataBufferSize == $dataBufferSizeMax) break;
							$dataBufferDiff = min($dataBufferSizeMax, $dataBufferSize*2) - $dataBufferSize;
							$dataBufferSize += $dataBufferDiff;
							$dataBuffer .= fread($fileHandle, $dataBufferDiff);
							if(feof($fileHandle)) { $dataBufferSize = 0; break; }
						}
					}
				}
			}
			fclose($fileHandle);
		}
		return array($width, $height, $type);
	}
	
	// Start timer
	function timerStart(&$time)
	{
		$time = microtime(true);
	}
	
	// Stop timer and calculate elapsed time in milliseconds
	function timerStop(&$time)
	{
		$time = intval((microtime(true)-$time) * 1000);
	}
}

// Yellow plugins
class YellowPlugins
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
		$path = dirname(__FILE__);
		foreach($yellow->toolbox->getDirectoryEntries($path, "/^core-.*\.php$/", true, false) as $entry) require_once($entry);
		$path = $yellow->config->get("pluginDir");
		foreach($yellow->toolbox->getDirectoryEntries($path, "/^.*\.php$/", true, false) as $entry) require_once($entry);
		foreach($this->plugins as $key=>$value)
		{
			$this->plugins[$key]["obj"] = new $value["class"];
			if(defined("DEBUG") && DEBUG>=3) echo "YellowPlugins::load class:$value[class] $value[version]<br/>\n";
			if(method_exists($this->plugins[$key]["obj"], "onLoad")) $this->plugins[$key]["obj"]->onLoad($yellow);
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

// Unicode support for PHP
mb_internal_encoding("UTF-8");
function strempty($string) { return is_null($string) || $string===""; }
function strreplaceu() { return call_user_func_array("str_replace", func_get_args()); }
function strtoloweru() { return call_user_func_array("mb_strtolower", func_get_args()); }
function strtoupperu() { return call_user_func_array("mb_strtoupper", func_get_args()); }
function strlenu() { return call_user_func_array("mb_strlen", func_get_args()); }
function strlenb() { return call_user_func_array("strlen", func_get_args()); }
function strposu() { return call_user_func_array("mb_strpos", func_get_args()); }
function strposb() { return call_user_func_array("strpos", func_get_args()); }
function strrposu() { return call_user_func_array("mb_strrpos", func_get_args()); }
function strrposb() { return call_user_func_array("strrpos", func_get_args()); }
function substru() { return call_user_func_array("mb_substr", func_get_args()); }
function substrb() { return call_user_func_array("substr", func_get_args()); }

// Default timezone for PHP
date_default_timezone_set(@date_default_timezone_get());
	
// Error reporting for PHP
error_reporting(E_ALL ^ E_NOTICE);
?>