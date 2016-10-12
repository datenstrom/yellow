<?php
// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow core
class YellowCore
{
	const VERSION = "0.6.5";
	var $page;				//current page
	var $pages;				//pages from file system
	var $files;				//files from file system
	var $plugins;			//plugins
	var $themes;			//themes
	var $config;			//configuration
	var $text;				//text strings
	var $lookup;			//location and file lookup
	var $toolbox;			//toolbox with helpers

	function __construct()
	{
		$this->page = new YellowPage($this);
		$this->pages = new YellowPages($this);
		$this->files = new YellowFiles($this);
		$this->plugins = new YellowPlugins($this);
		$this->themes = new YellowThemes($this);
		$this->config = new YellowConfig($this);
		$this->text = new YellowText($this);
		$this->lookup = new YellowLookup($this);
		$this->toolbox = new YellowToolbox();
		$this->config->setDefault("sitename", "Yellow");
		$this->config->setDefault("author", "Yellow");
		$this->config->setDefault("email", "webmaster");
		$this->config->setDefault("language", "en");
		$this->config->setDefault("theme", "default");
		$this->config->setDefault("serverScheme", $this->toolbox->getServerScheme());
		$this->config->setDefault("serverName", $this->toolbox->getServerName());
		$this->config->setDefault("serverBase", $this->toolbox->getServerBase());
		$this->config->setDefault("serverTime", $this->toolbox->getServerTime());
		$this->config->setDefault("imageLocation", "/media/images/");
		$this->config->setDefault("pluginLocation", "/media/plugins/");
		$this->config->setDefault("themeLocation", "/media/themes/");
		$this->config->setDefault("assetLocation", "/media/themes/assets/");
		$this->config->setDefault("systemDir", "system/");
		$this->config->setDefault("configDir", "system/config/");
		$this->config->setDefault("pluginDir", "system/plugins/");
		$this->config->setDefault("themeDir", "system/themes/");
		$this->config->setDefault("assetDir", "system/themes/assets/");
		$this->config->setDefault("snippetDir", "system/themes/snippets/");
		$this->config->setDefault("templateDir", "system/themes/templates/");
		$this->config->setDefault("trashDir", "system/trash/");
		$this->config->setDefault("mediaDir", "media/");
		$this->config->setDefault("imageDir", "media/images/");
		$this->config->setDefault("staticDir", "cache/");
		$this->config->setDefault("staticDefaultFile", "index.html");
		$this->config->setDefault("staticErrorFile", "404.html");
		$this->config->setDefault("contentPagination", "page");
		$this->config->setDefault("contentDir", "content/");
		$this->config->setDefault("contentRootDir", "default/");
		$this->config->setDefault("contentHomeDir", "home/");
		$this->config->setDefault("contentDefaultFile", "page.txt");
		$this->config->setDefault("contentExtension", ".txt");
		$this->config->setDefault("configExtension", ".ini");
		$this->config->setDefault("downloadExtension", ".download");
		$this->config->setDefault("configFile", "config.ini");
		$this->config->setDefault("textFile", "language-(.*).txt");
		$this->config->setDefault("errorFile", "page-error-(.*).txt");
		$this->config->setDefault("robotsFile", "robots.txt");
		$this->config->setDefault("faviconFile", "favicon.ico");
		$this->config->setDefault("template", "default");
		$this->config->setDefault("navigation", "navigation");
		$this->config->setDefault("sidebar", "sidebar");
		$this->config->setDefault("siteicon", "icon");
		$this->config->setDefault("tagline", "");
		$this->config->setDefault("parser", "markdown");
		$this->config->setDefault("parserSafeMode", "0");
		$this->config->setDefault("multiLanguageMode", "0");
		$this->config->setDefault("installationMode", "0");
		$this->load();
	}
	
	// Initialise configuration
	function load()
	{
		if(defined("DEBUG") && DEBUG>=3)
		{
			$serverSoftware = $this->toolbox->getServerSoftware();
			echo "Yellow ".YellowCore::VERSION.", PHP ".PHP_VERSION.", $serverSoftware<br>\n";
		}
		$this->config->load($this->config->get("configDir").$this->config->get("configFile"));
		$this->text->load($this->config->get("pluginDir").$this->config->get("textFile"));
		$this->lookup->load();
		$this->themes->load();
	}
	
	// Handle request
	function request()
	{
		ob_start();
		$statusCode = 0;
		$this->toolbox->timerStart($time);
		$this->toolbox->normaliseRequest();
		list($serverScheme, $serverName, $base, $location, $fileName) = $this->getRequestInformation();
		$this->page->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onRequest"))
			{
				$this->pages->requestHandler = $key;
				$statusCode = $value["obj"]->onRequest($serverScheme, $serverName, $base, $location, $fileName);
				if($statusCode!=0) break;
			}
		}
		if($statusCode==0)
		{
			$this->pages->requestHandler = "core";
			$statusCode = $this->processRequest($serverScheme, $serverName, $base, $location, $fileName, true);
		}
		if($this->page->isError()) $statusCode = $this->processRequestError();
		$this->toolbox->timerStop($time);
		ob_end_flush();
		if(defined("DEBUG") && DEBUG>=1)
		{
			$handler = $this->getRequestHandler();
			echo "YellowCore::request status:$statusCode location:$location handler:$handler<br/>\n";
			echo "YellowCore::request time:$time ms<br/>\n";
		}
		return $statusCode;
	}
	
	// Process request
	function processRequest($serverScheme, $serverName, $base, $location, $fileName, $cacheable)
	{
		$statusCode = 0;
		if(is_readable($fileName))
		{
			if($this->toolbox->isRequestCleanUrl($location))
			{
				$statusCode = 303;
				$location = $location.$this->getRequestLocationArgsClean();
				$location = $this->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->sendStatus($statusCode, $location);
			}
		} else {
			if($this->isRequestContentDirectory($location))
			{
				$statusCode = 301;
				$location = $this->lookup->isFileLocation($location) ? "$location/" : "/".$this->getRequestLanguage(true)."/";
				$location = $this->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->sendStatus($statusCode, $location);
			}
		}
		if($statusCode==0)
		{
			if($this->isStaticFile($location, $fileName, $cacheable))
			{
				$fileName = $this->getStaticFileFromCache($location, $fileName, $cacheable);
				$statusCode = $this->sendFile(200, $fileName, $cacheable);
			} else {
				$fileName = $this->readPage($serverScheme, $serverName, $base, $location, $fileName, $cacheable,
					max(is_readable($fileName) ? 200 : 404, $this->page->statusCode), $this->page->get("pageError"));
				$statusCode = $this->sendPage();
			}
		}
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCore::processRequest file:$fileName<br/>\n";
		return $statusCode;
	}
	
	// Process request with error
	function processRequestError()
	{
		ob_clean();
		$fileName = $this->readPage($this->page->serverScheme, $this->page->serverName, $this->page->base,
			$this->page->location, $this->page->fileName, $this->page->cacheable, $this->page->statusCode,
			$this->page->get("pageError"));
		$statusCode = $this->sendPage();
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCore::processRequestError file:$fileName<br/>\n";
		return $statusCode;
	}
	
	// Read page
	function readPage($serverScheme, $serverName, $base, $location, $fileName, $cacheable, $statusCode, $pageError)
	{
		if($statusCode>=400)
		{
			$fileName = $this->config->get("configDir").$this->config->get("errorFile");
			$fileName = strreplaceu("(.*)", $statusCode, $fileName);
			$cacheable = false;
		}
		$this->page = new YellowPage($this);
		$this->page->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
		$this->page->parseData($this->toolbox->readFile($fileName), $cacheable, $statusCode, $pageError);
		$this->text->setLanguage($this->page->get("language"));
		$this->page->parseContent();
		return $fileName;
	}
	
	// Send page response
	function sendPage()
	{
		$this->page->parsePage();
		$statusCode = $this->page->statusCode;
		$lastModifiedFormatted = $this->page->getHeader("Last-Modified");
		if($statusCode==200 && $this->page->isCacheable() && $this->toolbox->isRequestNotModified($lastModifiedFormatted))
		{
			$statusCode = 304;
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
		} else {
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
			foreach($this->page->headerData as $key=>$value) @header("$key: $value");
			if(!is_null($this->page->outputData)) echo $this->page->outputData;
		}
		if(defined("DEBUG") && DEBUG>=1)
		{
			foreach($this->page->headerData as $key=>$value) echo "YellowCore::sendPage $key: $value<br/>\n";
			$theme = $this->page->get("theme");
			$template = $this->page->get("template");
			$parser = $this->page->get("parser");
			echo "YellowCore::sendPage theme:$theme template:$template parser:$parser<br/>\n";
		}
		return $statusCode;
	}
	
	// Send file response
	function sendFile($statusCode, $fileName, $cacheable)
	{
		$lastModifiedFormatted = $this->toolbox->getHttpDateFormatted($this->toolbox->getFileModified($fileName));
		if($statusCode==200 && $cacheable && $this->toolbox->isRequestNotModified($lastModifiedFormatted))
		{
			$statusCode = 304;
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
		} else {
			@header($this->toolbox->getHttpStatusFormatted($statusCode));
			if(!$cacheable) @header("Cache-Control: no-cache, must-revalidate");
			@header("Content-Type: ".$this->toolbox->getMimeContentType($fileName));
			@header("Last-Modified: ".$lastModifiedFormatted);
			echo $this->toolbox->readFile($fileName);
		}
		return $statusCode;
	}

	// Send status response
	function sendStatus($statusCode, $location = "")
	{
		if(!empty($location)) $this->page->clean($statusCode, $location);
		@header($this->toolbox->getHttpStatusFormatted($statusCode));
		foreach($this->page->headerData as $key=>$value) @header("$key: $value");
		if(defined("DEBUG") && DEBUG>=1)
		{
			foreach($this->page->headerData as $key=>$value) echo "YellowCore::sendStatus $key: $value<br/>\n";
		}
	}
	
	// Handle command
	function command($args = null)
	{
		$statusCode = 0;
		$this->toolbox->timerStart($time);
		foreach($this->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onCommand"))
			{
				$statusCode = $value["obj"]->onCommand(func_get_args());
				if($statusCode!=0) break;
			}
		}
		if($statusCode==0)
		{
			$statusCode = 400;
			list($command) = func_get_args();
			echo "Yellow $command: Command not found\n";
		}
		$this->toolbox->timerStop($time);
		if(defined("DEBUG") && DEBUG>=1) echo "YellowCore::command time:$time ms<br/>\n";
		return $statusCode;
	}
	
	// Parse snippet
	function snippet($name, $args = null)
	{
		$this->pages->snippetArgs = func_get_args();
		$this->page->parseSnippet($name);
	}
	
	// Return request information
	function getRequestInformation($serverScheme = "", $serverName = "", $base = "")
	{
		$serverScheme = empty($serverScheme) ? $this->config->get("serverScheme") : $serverScheme;
		$serverName = empty($serverName) ? $this->config->get("serverName") : $serverName;
		$base = empty($base) ? $this->config->get("serverBase") : $base;
		$location = $this->toolbox->getLocation();
		$location = substru($location, strlenu($base));
		if(preg_match("/\.(css|ico|js|jpg|png|svg|txt|woff)$/", $location))
		{
			$pluginLocationLength = strlenu($this->config->get("pluginLocation"));
			$themeLocationLength = strlenu($this->config->get("themeLocation"));
			if(substru($location, 0, $pluginLocationLength)==$this->config->get("pluginLocation")) {
				$fileName = $this->config->get("pluginDir").substru($location, $pluginLocationLength);
			} else if(substru($location, 0, $themeLocationLength)==$this->config->get("themeLocation")) {
				$fileName = $this->config->get("themeDir").substru($location, $themeLocationLength);
			} else if($location=="/".$this->config->get("robotsFile")) {
				$fileName = $this->config->get("configDir").$this->config->get("robotsFile");
			} else if($location=="/".$this->config->get("faviconFile")) {
				$fileName = $this->config->get("assetDir").$this->config->get("siteicon").".png";
			}
		}
		if(empty($fileName)) $fileName = $this->lookup->findFileFromLocation($location);
		return array($serverScheme, $serverName, $base, $location, $fileName);
	}
	
	// Return request location
	function getRequestLocationArgsClean()
	{
		return $this->toolbox->getLocationArgsClean($this->config->get("contentPagination"));
	}
	
	// Return request language
	function getRequestLanguage($multiLanguage = false)
	{
		$languages = $multiLanguage ? $this->pages->getLanguages() : $this->text->getLanguages();
		return $this->toolbox->detectBrowserLanguage($languages, $this->config->get("language"));
	}
	
	// Return request handler
	function getRequestHandler()
	{
		return $this->pages->requestHandler;
	}
	
	// Return snippet arguments
	function getSnippetArgs()
	{
		return $this->pages->snippetArgs;
	}
	
	// Return static file from cache if available
	function getStaticFileFromCache($location, $fileName, $cacheable)
	{
		if($cacheable && PHP_SAPI!="cli")
		{
			$location .= $this->toolbox->getLocationArgs();
			$fileNameStatic = rtrim($this->config->get("staticDir"), '/').$location;
			if(!$this->lookup->isFileLocation($location)) $fileNameStatic .= $this->config->get("staticDefaultFile");
			if(is_readable($fileNameStatic)) $fileName = $fileNameStatic;
		}
		return $fileName;
	}
	
	// Check if static file
	function isStaticFile($location, $fileName, $cacheable)
	{
		$ok = false;
		if(is_readable($fileName))
		{
			$fileName = $this->getStaticFileFromCache($location, $fileName, $cacheable);
			$staticDirLength = strlenu($this->config->get("staticDir"));
			$systemDirLength = strlenu($this->config->get("systemDir"));
			$ok = substru($fileName, 0, $staticDirLength)==$this->config->get("staticDir") ||
				substru($fileName, 0, $systemDirLength)==$this->config->get("systemDir");
		}
		return $ok;
	}
	
	// Check if request can be redirected into content directory
	function isRequestContentDirectory($location)
	{
		$ok = false;
		if($this->lookup->isFileLocation($location))
		{
			$path = $this->lookup->findFileFromLocation("$location/", true);
			$ok = is_dir($path);
		} else if($location=="/") {
			$ok = $this->config->get("multiLanguageMode");
		}
		return $ok;
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
	var $lastModified;			//last modification date
	var $rawData;				//raw data of page
	var $metaDataOffsetBytes;	//meta data offset
	var $metaData;				//meta data
	var $pageCollection;		//page collection
	var $pageRelations;			//page relations
	var $headerData;			//response header
	var $outputData;			//response output
	var $parser;				//content parser
	var $parserData;			//content data of page
	var $parserSafeMode;		//page is parsed in safe mode? (boolean)
	var $available;				//page is available? (boolean)
	var $visible;				//page is visible location? (boolean)
	var $active;				//page is active location? (boolean)
	var $cacheable;				//page is cacheable? (boolean)
	var $statusCode;			//status code

	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->metaData = new YellowDataCollection();
		$this->pageCollection = new YellowPageCollection($yellow);
		$this->pageRelations = array();
		$this->headerData = array();
	}

	// Set request information
	function setRequestInformation($serverScheme, $serverName, $base, $location, $fileName)
	{
		$this->serverScheme = $serverScheme;
		$this->serverName = $serverName;
		$this->base = $base;
		$this->location = $location;
		$this->fileName = $fileName;
	}
	
	// Parse page data
	function parseData($rawData, $cacheable, $statusCode, $pageError = "")
	{
		$this->lastModified = 0;
		$this->rawData = $rawData;
		$this->parser = null;
		$this->parserData = "";
		$this->parserSafeMode = intval($this->yellow->config->get("parserSafeMode"));
		$this->available = true;
		$this->visible = $this->yellow->lookup->isVisibleLocation($this->location, $this->fileName);
		$this->active = $this->yellow->lookup->isActiveLocation($this->location, $this->yellow->page->location);
		$this->cacheable = $cacheable;
		$this->statusCode = $statusCode;
		$this->parseMeta($pageError);
	}
	
	// Parse page data update
	function parseDataUpdate()
	{
		if($this->statusCode==0)
		{
			$this->rawData = $this->yellow->toolbox->readFile($this->fileName);
			$this->statusCode = 200;
			$this->parseMeta();
		}
	}
	
	// Parse page meta data
	function parseMeta($pageError = "")
	{
		$this->metaData = new YellowDataCollection();
		if(!is_null($this->rawData))
		{
			$this->set("title", $this->yellow->toolbox->createTextTitle($this->location));
			$this->set("language", $this->yellow->lookup->findLanguageFromFile($this->fileName,
				$this->yellow->config->get("language")));
			$this->set("theme", $this->yellow->lookup->findNameFromFile($this->fileName,
				$this->yellow->config->get("themeDir"), $this->yellow->config->get("theme"), ".css"));
			$this->set("template", $this->yellow->lookup->findNameFromFile($this->fileName,
				$this->yellow->config->get("templateDir"), $this->yellow->config->get("template"), ".html"));
			$this->set("modified", date("Y-m-d H:i:s", $this->yellow->toolbox->getFileModified($this->fileName)));
			$this->parseMetaData(array("sitename", "siteicon", "tagline", "author", "navigation", "sidebar", "parser"));
			$titleHeader = ($this->location==$this->yellow->pages->getHomeLocation($this->location)) ?
				$this->get("sitename") : $this->get("title")." - ".$this->get("sitename");
			if(!$this->isExisting("titleContent")) $this->set("titleContent", $this->get("title"));
			if(!$this->isExisting("titleNavigation")) $this->set("titleNavigation", $this->get("title"));
			if(!$this->isExisting("titleHeader")) $this->set("titleHeader", $titleHeader);
			if($this->get("status")=="hidden") $this->available = false;
			$this->set("pageRead", $this->yellow->lookup->normaliseUrl(
				$this->yellow->config->get("serverScheme"),
				$this->yellow->config->get("serverName"),
				$this->yellow->config->get("serverBase"), $this->location));
			$this->set("pageEdit", $this->yellow->lookup->normaliseUrl(
				$this->yellow->config->get("webinterfaceServerScheme"),
				$this->yellow->config->get("webinterfaceServerName"),
				$this->yellow->config->get("serverBase"),
				rtrim($this->yellow->config->get("webinterfaceLocation"), '/').$this->location));
			$this->set("pageFile", $this->yellow->lookup->normaliseFile($this->fileName));
		} else {
			$this->set("type", $this->yellow->toolbox->getFileExtension($this->fileName));
			$this->set("modified", date("Y-m-d H:i:s", $this->yellow->toolbox->getFileModified($this->fileName)));
			$this->set("pageFile", $this->yellow->lookup->normaliseFile($this->fileName, true));
		}
		if(!empty($pageError)) $this->set("pageError", $pageError);
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseMeta")) $value["obj"]->onParseMeta($this);
		}
	}
	
	// Parse page meta data from configuration and raw data
	function parseMetaData($defaultKeys)
	{
		foreach($defaultKeys as $key)
		{
			$value = $this->yellow->config->get($key);
			if(!empty($key) && !strempty($value)) $this->set($key, $value);
		}
		if(preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)[\r\n]+\-\-\-[\r\n]+/s", $this->rawData, $parts))
		{
			$this->metaDataOffsetBytes = strlenb($parts[0]);
			foreach(preg_split("/[\r\n]+/", $parts[2]) as $line)
			{
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(!empty($matches[1]) && !strempty($matches[2])) $this->set($matches[1], $matches[2]);
			}
		} else if(preg_match("/^(\xEF\xBB\xBF)?([^\r\n]+)[\r\n]+=+[\r\n]+/", $this->rawData, $parts)) {
			$this->metaDataOffsetBytes = strlenb($parts[0]);
			$this->set("title", $parts[2]);
		}
	}
	
	// Parse page content on demand
	function parseContent($sizeMax = 0)
	{
		if(!is_object($this->parser))
		{
			if($this->yellow->plugins->isExisting($this->get("parser")))
			{
				$plugin = $this->yellow->plugins->plugins[$this->get("parser")];
				if(method_exists($plugin["obj"], "onParseContentRaw"))
				{
					$this->parser = $plugin["obj"];
					$this->parserData = $this->getContent(true, $sizeMax);
					$this->parserData = preg_replace("/@pageRead/i", $this->get("pageRead"), $this->parserData);
					$this->parserData = preg_replace("/@pageEdit/i", $this->get("pageEdit"), $this->parserData);
					$this->parserData = preg_replace("/@pageError/i", $this->get("pageError"), $this->parserData);
					$this->parserData = $this->parser->onParseContentRaw($this, $this->parserData);
					foreach($this->yellow->plugins->plugins as $key=>$value)
					{
						if(method_exists($value["obj"], "onParseContentText"))
						{
							$output = $value["obj"]->onParseContentText($this, $this->parserData);
							if(!is_null($output)) $this->parserData = $output;
						}
					}
				}
			} else {
				$this->parserData = $this->getContent(true, $sizeMax);
				$this->parserData = preg_replace("/@pageError/i", $this->get("pageError"), $this->parserData);
			}
			if(!$this->isExisting("description"))
			{
				$this->set("description", $this->yellow->toolbox->createTextDescription($this->parserData, 150));
			}
			if(!$this->isExisting("keywords"))
			{
				$this->set("keywords", $this->yellow->toolbox->createTextKeywords($this->get("title"), 10));
			}
			if(defined("DEBUG") && DEBUG>=3) echo "YellowPage::parseContent location:".$this->location."<br/>\n";
		}
	}
	
	// Parse page content block
	function parseContentBlock($name, $text, $shortcut)
	{
		$output = null;
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onParseContentBlock"))
			{
				$output = $value["obj"]->onParseContentBlock($this, $name, $text, $shortcut);
				if(!is_null($output)) break;
			}
		}
		if(is_null($output))
		{
			if($name=="yellow" && $shortcut)
			{
				$output = "Yellow ".YellowCore::VERSION;
				if(!empty($text))
				{
					$output = "<span class=\"".htmlspecialchars($name)."\">\n";
					if($text=="version")
					{
						$serverSoftware = $this->yellow->toolbox->getServerSoftware();
						$output .= "Yellow ".YellowCore::VERSION.", PHP ".PHP_VERSION.", $serverSoftware<br />\n";
						foreach($this->yellow->plugins->getData() as $key=>$value)
						{
							$output .= htmlspecialchars("$key $value")."<br />\n";
						}
						foreach($this->yellow->themes->getData() as $key=>$value)
						{
							$output .= htmlspecialchars("$key $value")."<br />\n";
						}
					} else {
						foreach($this->yellow->config->getData($text) as $key=>$value)
						{
							$output .= htmlspecialchars(ucfirst($key).": ".$value)."<br />\n";
						}
					}
					$output .= "</span>\n";
					if($this->parserSafeMode) $this->error(500, "Yellow '$text' is not available in safe mode!");
				}
			}
		}
		if(defined("DEBUG") && DEBUG>=3 && !empty($name)) echo "YellowPage::parseContentBlock name:$name shortcut:$shortcut<br/>\n";
		return $output;
	}
	
	// Parse page
	function parsePage()
	{
		$this->outputData = null;
		if(!$this->isError())
		{
			foreach($this->yellow->plugins->plugins as $key=>$value)
			{
				if(method_exists($value["obj"], "onParsePage")) $value["obj"]->onParsePage();
			}
		}
		if(is_null($this->outputData))
		{
			ob_start();
			$this->parseTemplate($this->get("template"));
			$this->outputData = ob_get_contents();
			ob_end_clean();
		}
		if(!$this->isCacheable()) $this->setHeader("Cache-Control", "no-cache, must-revalidate");
		if(!$this->isHeader("Content-Type")) $this->setHeader("Content-Type", "text/html; charset=utf-8");
		if(!$this->isHeader("Page-Modified")) $this->setHeader("Page-Modified", $this->getModified(true));
		if(!$this->isHeader("Last-Modified")) $this->setHeader("Last-Modified", $this->getLastModified(true));
		if(!$this->yellow->text->isLanguage($this->get("language")))
		{
			$this->error(500, "Language '".$this->get("language")."' does not exist!");
		}
		if(!$this->yellow->themes->isExisting($this->get("theme")))
		{
			$this->error(500, "Theme '".$this->get("theme")."' does not exist!");
		}
		if(!is_object($this->parser))
		{
			$this->error(500, "Parser '".$this->get("parser")."' does not exist!");
		}
		if($this->yellow->toolbox->isRequestSelf()) $this->error(500, "Rewrite module not enabled on this server!");
		if($this->yellow->getRequestHandler()=="core" && $this->isExisting("redirect") && $this->statusCode==200)
		{
			$location = $this->yellow->lookup->normaliseLocation($this->get("redirect"), $this->base, $this->location);
			$location = $this->yellow->lookup->normaliseUrl($this->serverScheme, $this->serverName, "", $location);
			$this->clean(301, $location);
		}
		if($this->yellow->getRequestHandler()=="core" && !$this->isAvailable() && $this->statusCode==200)
		{
			$this->error(404);
		}
		if($this->isExisting("pageClean")) $this->outputData = null;
	}
	
	// Parse template
	function parseTemplate($name)
	{
		$fileNameTemplate = $this->yellow->config->get("templateDir").$this->yellow->lookup->normaliseName($name).".html";
		if(is_file($fileNameTemplate))
		{
			$this->setLastModified(filemtime($fileNameTemplate));
			global $yellow;
			require($fileNameTemplate);
		} else {
			$this->error(500, "Template '$name' does not exist!");
			echo "Template error<br/>\n";
		}
	}
	
	// Parse snippet
	function parseSnippet($name)
	{
		$fileNameSnippet = $this->yellow->config->get("snippetDir").$this->yellow->lookup->normaliseName($name).".php";
		if(is_file($fileNameSnippet))
		{
			$this->setLastModified(filemtime($fileNameSnippet));
			global $yellow;
			require($fileNameSnippet);
		} else {
			$this->error(500, "Snippet '$name' does not exist!");
			echo "Snippet error<br/>\n";
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
		return $this->isExisting($key) ? $this->metaData[$key] : "";
	}

	// Return page meta data, HTML encoded
	function getHtml($key)
	{
		return htmlspecialchars($this->get($key));
	}
	
	// Return page meta data as language specific date
	function getDate($key, $dateFormat = "")
	{
		if(!empty($dateFormat))
		{
			$format = $this->yellow->text->get($dateFormat);
		} else {
			$format = $this->yellow->text->get("dateFormatMedium");
		}
		return $this->yellow->text->getDateFormatted(strtotime($this->get($key)), $format);
	}

	// Return page meta data as language specific date, HTML encoded
	function getDateHtml($key, $dateFormat = "")
	{
		return htmlspecialchars($this->getDate($key, $dateFormat));
	}
	
	// Return page content, HTML encoded or raw format
	function getContent($rawFormat = false, $sizeMax = 0)
	{
		if($rawFormat)
		{
			$this->parseDataUpdate();
			$text = substrb($this->rawData, $this->metaDataOffsetBytes);
		} else {
			$this->parseContent($sizeMax);
			$text = $this->parserData;
		}
		return $sizeMax ? substrb($text, 0, $sizeMax) : $text;
	}
	
	// Return parent page of current page, null if none
	function getParent()
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->find($parentLocation);
	}
	
	// Return top-level page for current page, null if none
	function getParentTop($homeFailback = true)
	{
		$parentTopLocation = $this->yellow->pages->getParentTopLocation($this->location);
		if(!$this->yellow->pages->find($parentTopLocation) && $homeFailback)
		{
			$parentTopLocation = $this->yellow->pages->getHomeLocation($this->location);
		}
		return $this->yellow->pages->find($parentTopLocation);
	}
	
	// Return page collection with pages on the same level as current page
	function getSiblings($showInvisible = false)
	{
		$parentLocation = $this->yellow->pages->getParentLocation($this->location);
		return $this->yellow->pages->getChildren($parentLocation, $showInvisible);
	}
	
	// Return page collection with child pages of current page
	function getChildren($showInvisible = false)
	{
		return $this->yellow->pages->getChildren($this->location, $showInvisible);
	}
	
	// Return page collection with media files for current page
	function getFiles($showInvisible = false)
	{
		return $this->yellow->files->index($showInvisible, true)->filter("pageFile", $this->get("pageFile"));
	}
	
	// Set page collection with additional pages for current page
	function setPages($pages)
	{
		$this->pageCollection = $pages;
	}

	// Return page collection with additional pages for current page
	function getPages()
	{
		return $this->pageCollection;
	}
	
	// Set related page
	function setPage($key, $page)
	{
		$this->pageRelations[$key] = $page;
	}
	
	// Return related page
	function getPage($key)
	{
		return !is_null($this->pageRelations[$key]) ? $this->pageRelations[$key] : $this;
	}
	
	// Return page location
	function getLocation($absoluteLocation = false)
	{
		return $absoluteLocation ? $this->base.$this->location : $this->location;
	}
	
	// Return page URL with server scheme and server name
	function getUrl()
	{
		return $this->yellow->lookup->normaliseUrl($this->serverScheme, $this->serverName, $this->base, $this->location);
	}
	
	// Return page extra HTML data
	function getExtra($name)
	{
		$output = "";
		foreach($this->yellow->plugins->plugins as $key=>$value)
		{
			if(method_exists($value["obj"], "onExtra"))
			{
				$outputPlugin = $value["obj"]->onExtra($name);
				if(!is_null($outputPlugin)) $output .= $outputPlugin;
			}
		}
		if($name=="header")
		{
			if(is_file($this->yellow->config->get("themeDir").$this->get("theme").".css"))
			{
				$location = $this->yellow->config->get("serverBase").
					$this->yellow->config->get("themeLocation").$this->get("theme").".css";
				$output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"".htmlspecialchars($location)."\" />\n";
			}
			if(is_file($this->yellow->config->get("assetDir").$this->get("theme").".js"))
			{
				$location = $this->yellow->config->get("serverBase").
					$this->yellow->config->get("assetLocation").$this->get("theme").".js";
				$output .= "<script type=\"text/javascript\" src=\"".htmlspecialchars($location)."\"></script>\n";
			}
			if(is_file($this->yellow->config->get("assetDir").$this->get("siteicon").".png"))
			{
				$location = $this->yellow->config->get("serverBase").
					$this->yellow->config->get("assetLocation").$this->get("siteicon").".png";
				$contentType = $this->yellow->toolbox->getMimeContentType($location);
				$output .= "<link rel=\"icon\" type=\"$contentType\" href=\"".htmlspecialchars($location)."\" />\n";
				$output .= "<link rel=\"apple-touch-icon\" type=\"$contentType\" href=\"".htmlspecialchars($location)."\" />\n";
			}
		}
		return $this->normaliseExtra($output);
	}
	
	// Normalise page extra HTML data
	function normaliseExtra($text)
	{
		$outputScript = $outputStylesheet = $outputOther = $locations = array();
		foreach($this->yellow->toolbox->getTextLines($text) as $line)
		{
			if(preg_match("/^<script (.*?)src=\"([^\"]+)\"(.*?)><\/script>$/i", $line, $matches))
			{
				if(is_null($locations[$matches[2]]))
				{
					$locations[$matches[2]] = $matches[2];
					array_push($outputScript, $line);
				}
			} else if(preg_match("/^<link rel=\"stylesheet\"(.*?)href=\"([^\"]+)\"(.*?)>$/i", $line, $matches)) {
				if(is_null($locations[$matches[2]]))
				{
					$locations[$matches[2]] = $matches[2];
					array_push($outputStylesheet, $line);
				}
			} else {
				array_push($outputOther, $line);
			}
		}
		return implode($outputScript).implode($outputStylesheet).implode($outputOther);
	}
	
	// Set page response output
	function setOutput($output)
	{
		$this->outputData = $output;
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
	
	// Return page modification date, Unix time or HTTP format
	function getModified($httpFormat = false)
	{
		$modified = strtotime($this->get("modified"));
		return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($modified) : $modified;
	}
	
	// Set last modification date, Unix time
	function setLastModified($modified)
	{
		$this->lastModified = max($this->lastModified, $modified);
	}
	
	// Return last modification date, Unix time or HTTP format
	function getLastModified($httpFormat = false)
	{
		$modified = max($this->lastModified, $this->getModified(), $this->yellow->config->getModified(),
			$this->yellow->text->getModified(), $this->yellow->plugins->getModified());
		return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($modified) : $modified;
	}
	
	// Return page status code, number or HTTP format
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
	function clean($statusCode, $location = "")
	{
		if(!$this->isExisting("pageClean") && $statusCode>0)
		{
			$this->statusCode = $statusCode;
			$this->lastModified = 0;
			$this->headerData = array();
			if(!empty($location))
			{
				$this->setHeader("Location", $location);
				$this->setHeader("Cache-Control", "no-cache, must-revalidate");
			}
			$this->set("pageClean", (string)$statusCode);
		}
	}
	
	// Check if page is available
	function isAvailable()
	{
		return $this->available;
	}
	
	// Check if page is visible
	function isVisible()
	{
		return $this->visible;
	}

	// Check if page is within current request
	function isActive()
	{
		return $this->active;
	}
	
	// Check if page is cacheable
	function isCacheable()
	{
		return $this->cacheable;
	}

	// Check if page with error
	function isError()
	{
		return $this->isExisting("pageError");
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
	
	// Check if related page exists
	function isPage($key)
	{
		return !is_null($this->pageRelations[$key]);
	}
}

// Yellow data collection as array
class YellowDataCollection extends ArrayObject
{
	function __construct()
	{
		parent::__construct(array());
	}
	
	// Return array element
	function offsetGet($key)
	{
		if(is_string($key)) $key = lcfirst($key);
		return parent::offsetGet($key);
	}
	
	// Set array element
	function offsetSet($key, $value)
	{
		if(is_string($key)) $key = lcfirst($key);
		parent::offsetSet($key, $value);
	}
	
	// Remove array element
	function offsetUnset($key)
	{
		if(is_string($key)) $key = lcfirst($key);
		parent::offsetUnset($key);
	}
	
	// Check if array element exists
	function offsetExists($key)
	{
		if(is_string($key)) $key = lcfirst($key);
		return parent::offsetExists($key);
	}
}

// Yellow page collection as array
class YellowPageCollection extends ArrayObject
{
	var $yellow;				//access to API
	var $filterValue;			//current page filter value
	var $paginationNumber;		//current page number in pagination
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
						if($value==substru(strreplaceu(' ', '-', strtoloweru($pageValue)), 0, $pageValueLength))
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
	
	// Filter page collection by file name
	function match($regex = "/.*/")
	{
		$array = array();
		foreach($this->getArrayCopy() as $page)
		{
			if(preg_match($regex, $page->fileName)) array_push($array, $page);
		}
		$this->exchangeArray($array);
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
	
	// Sort page collection by meta data similarity
	function similar($page, $ascendingOrder = false)
	{
		$location = $page->location;
		$keywords = $this->yellow->toolbox->createTextKeywords($page->get("title"));
		$keywords .= ",".$page->get("tag").",".$page->get("author");
		$tokens = array_unique(array_filter(preg_split("/,\s*/", $keywords), "strlen"));
		if(!empty($tokens))
		{
			$array = array();
			foreach($this->getArrayCopy() as $page)
			{
				$searchScore = 0;
				foreach($tokens as $token)
				{
					if(stristr($page->get("title"), $token)) $searchScore += 10;
					if(stristr($page->get("tag"), $token)) $searchScore += 5;
					if(stristr($page->get("author"), $token)) $searchScore += 2;
				}
				if($page->location!=$location)
				{
					$page->set("searchscore", $searchScore);
					array_push($array, $page);
				}
			}
			$this->exchangeArray($array);
			$this->sort("searchscore", $ascendingOrder);
		}
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
	
	// Randomize page collection
	function shuffle()
	{
		$array = $this->getArrayCopy();
		shuffle($array);
		$this->exchangeArray($array);
		return $this;
	}

	// Paginate page collection
	function pagination($limit, $reverse = true)
	{
		$this->paginationNumber = 1;
		$this->paginationCount = ceil($this->count() / $limit);
		$pagination = $this->yellow->config->get("contentPagination");
		if(isset($_REQUEST[$pagination])) $this->paginationNumber = intval($_REQUEST[$pagination]);
		if($this->paginationNumber>$this->paginationCount) $this->paginationNumber = 0;
		if($this->paginationNumber>=1)
		{
			$array = $this->getArrayCopy();
			if($reverse) $array = array_reverse($array);
			$this->exchangeArray(array_slice($array, ($this->paginationNumber - 1) * $limit, $limit));
		}
		return $this;
	}
	
	// Return current page number in pagination 
	function getPaginationNumber()
	{
		return $this->paginationNumber;
	}
	
	// Return highest page number in pagination
	function getPaginationCount()
	{
		return $this->paginationCount;
	}
	
	// Return absolute location for a page in pagination
	function getPaginationLocation($pageNumber)
	{
		if($pageNumber>=1 && $pageNumber<=$this->paginationCount)
		{
			$pagination = $this->yellow->config->get("contentPagination");
			$location = $this->yellow->page->getLocation(true);
			$locationArgs = $this->yellow->toolbox->getLocationArgsNew(
				$pageNumber>1 ? "$pagination:$pageNumber" : "$pagination:", $pagination);
		}
		return $location.$locationArgs;
	}
	
	// Return absolute location for previous page in pagination
	function getPaginationPrevious()
	{
		$pageNumber = $this->paginationNumber;
		$pageNumber = ($pageNumber>1 && $pageNumber<=$this->paginationCount) ? $pageNumber-1 : 0;
		return $this->getPaginationLocation($pageNumber);
	}
	
	// Return absolute location for next page in pagination
	function getPaginationNext()
	{
		$pageNumber = $this->paginationNumber;
		$pageNumber = ($pageNumber>=1 && $pageNumber<$this->paginationCount) ? $pageNumber+1 : 0;
		return $this->getPaginationLocation($pageNumber);
	}
	
	// Return current page filter
	function getFilter()
	{
		return $this->filterValue;
	}
	
	// Return page collection modification date, Unix time or HTTP format
	function getModified($httpFormat = false)
	{
		$modified = 0;
		foreach($this->getIterator() as $page) $modified = max($modified, $page->getModified());
		return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($modified) : $modified;
	}
	
	// Check if there is a pagination
	function isPagination()
	{
		return $this->paginationCount>1;
	}
}

// Yellow pages
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
	
	// Scan file system on demand
	function scanLocation($location)
	{
		if(is_null($this->pages[$location]))
		{
			if(defined("DEBUG") && DEBUG>=2) echo "YellowPages::scanLocation location:$location<br/>\n";
			$this->pages[$location] = array();
			$serverScheme = $this->yellow->page->serverScheme;
			$serverName = $this->yellow->page->serverName;
			$base = $this->yellow->page->base;
			if(empty($location))
			{
				$rootLocations = $this->yellow->lookup->findRootLocations();
				foreach($rootLocations as $rootLocation)
				{
					list($rootLocation, $fileName) = explode(' ', $rootLocation, 2);
					$page = new YellowPage($this->yellow);
					$page->setRequestInformation($serverScheme, $serverName, $base, $rootLocation, $fileName);
					$page->parseData("", false, 0);
					array_push($this->pages[$location], $page);
				}
			} else {
				$fileNames = $this->yellow->lookup->findChildrenFromLocation($location);
				foreach($fileNames as $fileName)
				{
					$page = new YellowPage($this->yellow);
					$page->setRequestInformation($serverScheme, $serverName, $base,
						$this->yellow->lookup->findLocationFromFile($fileName), $fileName);
					$page->parseData($this->yellow->toolbox->readFile($fileName, 4096), false, 0);
					if(strlenb($page->rawData)<4096) $page->statusCode = 200;
					array_push($this->pages[$location], $page);
				}
			}
		}
		return $this->pages[$location];
	}	

	// Return page from file system, null if not found
	function find($location, $absoluteLocation = false)
	{
		if($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
		foreach($this->scanLocation($this->getParentLocation($location)) as $page)
		{
			if($page->location==$location)
			{
				if(!$this->yellow->lookup->isRootLocation($page->location)) { $found = true; break; }
			}
		}
		return $found ? $page : null;
	}
	
	// Return page collection with all pages
	function index($showInvisible = false, $multiLanguage = false, $levelMax = 0)
	{
		$rootLocation = $multiLanguage ? "" : $this->getRootLocation($this->yellow->page->location);
		return $this->getChildrenRecursive($rootLocation, $showInvisible, $levelMax);
	}
	
	// Return page collection with top-level navigation
	function top($showInvisible = false)
	{
		$rootLocation = $this->getRootLocation($this->yellow->page->location);
		return $this->getChildren($rootLocation, $showInvisible);
	}
	
	// Return page collection with path ancestry
	function path($location, $absoluteLocation = false)
	{
		$pages = new YellowPageCollection($this->yellow);
		if($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
		if($page = $this->find($location))
		{
			$pages->prepend($page);
			for(; $parent = $page->getParent(); $page=$parent) $pages->prepend($parent);
			$home = $this->find($this->getHomeLocation($page->location));
			if($home && $home->location!=$page->location) $pages->prepend($home);
		}
		return $pages;
	}
	
	// Return page collection with multiple languages
	function multi($location, $absoluteLocation = false, $showInvisible = false)
	{
		$pages = new YellowPageCollection($this->yellow);
		if($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
		$locationEnd = substru($location, strlenu($this->getRootLocation($location)) - 4);
		foreach($this->scanLocation("") as $page)
		{
			if($content = $this->find(substru($page->location, 4).$locationEnd))
			{
				if($content->isAvailable() && ($content->isVisible() || $showInvisible))
				{
					if(!$this->yellow->lookup->isRootLocation($content->location)) $pages->append($content);
				}
			}
		}
		return $pages;
	}
	
	// Return page collection that's empty
	function clean()
	{
		return new YellowPageCollection($this->yellow);
	}
	
	// Return languages in multi language mode
	function getLanguages($showInvisible = false)
	{
		$languages = array();
		foreach($this->scanLocation("") as $page)
		{
			if($page->isAvailable() && ($page->isVisible() || $showInvisible))
			{
				array_push($languages, $page->get("language"));
			}
		}
		return $languages;
	}
	
	// Return child pages
	function getChildren($location, $showInvisible = false)
	{
		$pages = new YellowPageCollection($this->yellow);
		foreach($this->scanLocation($location) as $page)
		{
			if($page->isAvailable() && ($page->isVisible() || $showInvisible))
			{
				if(!$this->yellow->lookup->isRootLocation($page->location)) $pages->append($page);
			}
		}
		return $pages;
	}
	
	// Return child pages recursively
	function getChildrenRecursive($location, $showInvisible = false, $levelMax = 0)
	{
		--$levelMax;
		$pages = new YellowPageCollection($this->yellow);
		foreach($this->scanLocation($location) as $page)
		{
			if($page->isAvailable() && ($page->isVisible() || $showInvisible))
			{
				if(!$this->yellow->lookup->isRootLocation($page->location)) $pages->append($page);
				if(!$this->yellow->lookup->isFileLocation($page->location) && $levelMax!=0)
				{
					$pages->merge($this->getChildrenRecursive($page->location, $showInvisible, $levelMax));
				}
			}
		}
		return $pages;
	}
	
	// Return root location
	function getRootLocation($location)
	{
		$rootLocation = "root/";
		if($this->yellow->config->get("multiLanguageMode"))
		{
			foreach($this->scanLocation("") as $page)
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
		$token = rtrim(substru($this->getRootLocation($location), 4), '/');
		if(preg_match("#^($token.*\/).+?$#", $location, $matches))
		{
			if($matches[1]!="$token/" || $this->yellow->lookup->isFileLocation($location)) $parentLocation = $matches[1];
		}
		if(empty($parentLocation)) $parentLocation = "root$token/";
		return $parentLocation;
	}
	
	// Return top-level location
	function getParentTopLocation($location)
	{
		$token = rtrim(substru($this->getRootLocation($location), 4), '/');
		if(preg_match("#^($token.+?\/)#", $location, $matches)) $parentTopLocation = $matches[1];
		if(empty($parentTopLocation)) $parentTopLocation = "$token/";
		return $parentTopLocation;
	}
}
	
// Yellow files
class YellowFiles
{
	var $yellow;		//access to API
	var $files;			//scanned files
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->files = array();
	}

	// Scan file system on demand
	function scanLocation($location)
	{
		if(is_null($this->files[$location]))
		{
			if(defined("DEBUG") && DEBUG>=2) echo "YellowFiles::scanLocation location:$location<br/>\n";
			$this->files[$location] = array();
			$serverScheme = $this->yellow->page->serverScheme;
			$serverName = $this->yellow->page->serverName;
			$base = $this->yellow->config->get("serverBase");
			if(empty($location))
			{
				$fileNames = array($this->yellow->config->get("mediaDir"));
			} else {
				$fileNames = array();
				$path = substru($location, 1);
				foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, true) as $entry)
				{
					array_push($fileNames, $entry."/");
				}
				foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, false, true) as $entry)
				{
					array_push($fileNames, $entry);
				}
			}
			foreach($fileNames as $fileName)
			{
				$file = new YellowPage($this->yellow);
				$file->setRequestInformation($serverScheme, $serverName, $base, "/".$fileName, $fileName);
				$file->parseData(null, false, 0);
				array_push($this->files[$location], $file);
			}
		}
		return $this->files[$location];
	}
	
	// Return page with media file information, null if not found
	function find($location, $absoluteLocation = false)
	{
		if($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
		foreach($this->scanLocation($this->getParentLocation($location)) as $file)
		{
			if($file->location==$location)
			{
				if($this->yellow->lookup->isFileLocation($file->location)) { $found = true; break; }
			}
		}
		return $found ? $file : null;
	}
	
	// Return page collection with all media files
	function index($showInvisible = false, $multiPass = false, $levelMax = 0)
	{
		return $this->getChildrenRecursive("", $showInvisible, $levelMax);
	}
	
	// Return page collection that's empty
	function clean()
	{
		return new YellowPageCollection($this->yellow);
	}
	
	// Return child files
	function getChildren($location, $showInvisible = false)
	{
		$files = new YellowPageCollection($this->yellow);
		foreach($this->scanLocation($location) as $file)
		{
			if($file->isAvailable() && ($file->isVisible() || $showInvisible))
			{
				if($this->yellow->lookup->isFileLocation($file->location)) $files->append($file);
			}
		}
		return $files;
	}
	
	// Return child files recursively
	function getChildrenRecursive($location, $showInvisible = false, $levelMax = 0)
	{
		--$levelMax;
		$files = new YellowPageCollection($this->yellow);
		foreach($this->scanLocation($location) as $file)
		{
			if($file->isAvailable() && ($file->isVisible() || $showInvisible))
			{
				if($this->yellow->lookup->isFileLocation($file->location)) $files->append($file);
				if(!$this->yellow->lookup->isFileLocation($file->location) && $levelMax!=0)
				{
					$files->merge($this->getChildrenRecursive($file->location, $showInvisible, $levelMax));
				}
			}
		}
		return $files;
	}
	
	// Return home location
	function getHomeLocation($location)
	{
		return "/".$this->yellow->config->get("mediaDir");
	}

	// Return parent location
	function getParentLocation($location)
	{
		$token = rtrim("/".$this->yellow->config->get("mediaDir"), '/');
		if(preg_match("#^($token.*\/).+?$#", $location, $matches))
		{
			if($matches[1]!="$token/" || $this->yellow->lookup->isFileLocation($location)) $parentLocation = $matches[1];
		}
		if(empty($parentLocation)) $parentLocation = "";
		return $parentLocation;
	}
	
	// Return top-level location
	function getParentTopLocation($location)
	{
		$token = rtrim("/".$this->yellow->config->get("mediaDir"), '/');
		if(preg_match("#^($token.+?\/)#", $location, $matches)) $parentTopLocation = $matches[1];
		if(empty($parentTopLocation)) $parentTopLocation = "$token/";
		return $parentTopLocation;
	}
}

// Yellow plugins
class YellowPlugins
{
	var $yellow;		//access to API
	var $modified;		//plugin modification date
	var $plugins;		//registered plugins

	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->modified = 0;
		$this->plugins = array();
	}
	
	// Load plugins
	function load()
	{
		$path = $this->yellow->config->get("pluginDir");
		foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.php$/", true, false) as $entry)
		{
			$this->modified = max($this->modified, filemtime($entry));
			global $yellow;
			require_once($entry);
		}
		$callback = function($a, $b)
		{
			return $a["priority"] - $b["priority"];
		};
		uasort($this->plugins, $callback);
		foreach($this->plugins as $key=>$value)
		{
			$this->plugins[$key]["obj"] = new $value["plugin"];
			if(defined("DEBUG") && DEBUG>=3) echo "YellowPlugins::load $value[plugin]:$value[version]<br/>\n";
			if(method_exists($this->plugins[$key]["obj"], "onLoad")) $this->plugins[$key]["obj"]->onLoad($yellow);
		}
	}
	
	// Register plugin
	function register($name, $plugin, $version, $priority = 0)
	{
		if(!$this->isExisting($name))
		{
			if($priority==0) $priority = count($this->plugins) + 10;
			$this->plugins[$name] = array();
			$this->plugins[$name]["plugin"] = $plugin;
			$this->plugins[$name]["version"] = $version;
			$this->plugins[$name]["priority"] = $priority;
		}
	}
	
	// Return plugin
	function get($name)
	{
		return $this->plugins[$name]["obj"];
	}
	
	// Return plugin version
	function getData()
	{
		$data = array();
		$data["YellowCore"] = YellowCore::VERSION;
		foreach($this->plugins as $key=>$value) $data[$value["plugin"]] = $value["version"];
		uksort($data, strnatcasecmp);
		return $data;
	}
	
	// Return plugin modification date, Unix time or HTTP format
	function getModified($httpFormat = false)
	{
		return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
	}
	
	// Check if plugin exists
	function isExisting($name)
	{
		return !is_null($this->plugins[$name]);
	}
}

// Yellow themes
class YellowThemes
{
	var $yellow;		//access to API
	var $modified;		//theme modification date
	var $themes;		//themes

	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->modified = 0;
		$this->themes = array();
	}
	
	// Load themes
	function load()
	{
		$path = $this->yellow->config->get("themeDir");
		foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.css$/", true, false) as $entry)
		{
			$name = $this->yellow->lookup->normaliseName(basename($entry), true, true);
			$theme = $version = "";
			$this->modified = max($this->modified, filemtime($entry));
			$fileData = $this->yellow->toolbox->readFile($entry, 4096);
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				preg_match("/^\/\*\s*(.*?)\s*:\s*(.*?)\s*\*\/$/", $line, $matches);
				if(lcfirst($matches[1])=="theme" && !strempty($matches[2])) $theme = $matches[2];
				if(lcfirst($matches[1])=="version" && !strempty($matches[2])) $version = $matches[2];
				if(!empty($line) && $line[0]!='/') break;
			}
			if(!empty($theme) && !empty($version))
			{
				$this->themes[$name] = array();
				$this->themes[$name]["theme"] = $theme;
				$this->themes[$name]["version"] = $version;
				if(defined("DEBUG") && DEBUG>=3) echo "YellowThemes::load $theme:$version<br/>\n";
			}
		}
	}
	
	// Return theme version
	function getData()
	{
		$data = array();
		foreach($this->themes as $key=>$value) $data[$value["theme"]] = $value["version"];
		uksort($data, strnatcasecmp);
		return $data;
	}
	
	// Return theme modification date, Unix time or HTTP format
	function getModified($httpFormat = false)
	{
		return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
	}

	// Check if theme exists
	function isExisting($name)
	{
		return !is_null($this->themes[$name]);
	}
}
	
// Yellow configuration
class YellowConfig
{
	var $yellow;			//access to API
	var $modified;			//configuration modification date
	var $config;			//configuration
	var $configDefaults;	//configuration defaults
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->modified = 0;
		$this->config = new YellowDataCollection();
		$this->configDefaults = new YellowDataCollection();
	}
	
	// Load configuration from file
	function load($fileName)
	{
		if(defined("DEBUG") && DEBUG>=2) echo "YellowConfig::load file:$fileName<br/>\n";
		$this->modified = $this->yellow->toolbox->getFileModified($fileName);
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			if(preg_match("/^\#/", $line)) continue;
			preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
			if(!empty($matches[1]) && !strempty($matches[2]))
			{
				$this->set($matches[1], $matches[2]);
				if(defined("DEBUG") && DEBUG>=3) echo "YellowConfig::load $matches[1]:$matches[2]<br/>\n";
			}
		}
	}
	
	// Update configuration in file
	function update($fileName, $config)
	{
		foreach($config as $key=>$value)
		{
			if(empty($key) || strempty($value)) { unset($config[$key]); continue; }
			$this->set($key, $value);
		}
		$this->modified = time();
		$fileData = $this->yellow->toolbox->readFile($fileName);
		foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
		{
			preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
			$keyOriginal = $matches[1]; $keySearch = strtoloweru($matches[1]); $keyFound = "";
			foreach($config as $key=>$value) if(strtoloweru($key)==$keySearch) { $keyFound = $key; break; }
			if(!empty($keyFound))
			{
				$fileDataNew .= "$keyOriginal: $config[$keyFound]\n";
				unset($config[$keyFound]);
			} else {
				$fileDataNew .= $line;
			}
		}
		foreach($config as $key=>$value)
		{
			$fileDataNew .= "$key: $value\n";
		}
		return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
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
			$config = array_merge($this->configDefaults->getArrayCopy(), $this->config->getArrayCopy());
		} else {
			foreach(array_merge($this->configDefaults->getArrayCopy(), $this->config->getArrayCopy()) as $key=>$value)
			{
				if(!empty($filterStart) && substru($key, 0, strlenu($filterStart))==$filterStart) $config[$key] = $value;
				if(!empty($filterEnd) && substru($key, -strlenu($filterEnd))==$filterEnd) $config[$key] = $value;
			}
		}
		return $config;
	}
	
	// Return configuration modification date, Unix time or HTTP format
	function getModified($httpFormat = false)
	{
		return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
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
	var $modified;		//text modification date
	var $text;			//text strings
	var $language;		//current language
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
		$this->modified = 0;
		$this->text = new YellowDataCollection();
	}
	
	// Load text strings from file
	function load($fileName)
	{
		$path = dirname($fileName);
		$regex = "/^".basename($fileName)."$/";
		foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry)
		{
			if(defined("DEBUG") && DEBUG>=2) echo "YellowText::load file:$entry<br/>\n";
			$language = "";
			$this->modified = max($this->modified, filemtime($entry));
			$fileData = $this->yellow->toolbox->readFile($entry);
			foreach($this->yellow->toolbox->getTextLines($fileData) as $line)
			{
				if(preg_match("/^\#/", $line)) continue;
				preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
				if(lcfirst($matches[1])=="language" && !strempty($matches[2])) $language = $matches[2];
				if(!empty($language) && !empty($matches[1]) && !strempty($matches[2]))
				{
					$this->setText($matches[1], $matches[2], $language);
					if(defined("DEBUG") && DEBUG>=3) echo "YellowText::load $matches[1]:$matches[2]<br/>\n";
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
		if(is_null($this->text[$language])) $this->text[$language] = new YellowDataCollection();
		$this->text[$language][$key] = $value;
	}
	
	// Return text string
	function get($key)
	{
		return $this->getText($key, $this->language);
	}
	
	// Return text string, HTML encoded
	function getHtml($key)
	{
		return htmlspecialchars($this->getText($key, $this->language));
	}
	
	// Return text string for specific language
	function getText($key, $language)
	{
		return $this->isExisting($key, $language) ? $this->text[$language][$key] : "[$key]";
	}
	
	// Return text string for specific language, HTML encoded
	function getTextHtml($key, $language)
	{
		return htmlspecialchars($this->getText($key, $language));
	}
	
	// Return text strings
	function getData($filterStart = "", $language = "")
	{
		$text = array();
		if(empty($language)) $language = $this->language;
		if($this->isLanguage($language))
		{
			if(empty($filterStart))
			{
				$text = $this->text[$language];
			} else {
				foreach($this->text[$language] as $key=>$value)
				{
					if(substru($key, 0, strlenu($filterStart))==$filterStart) $text[$key] = $value;
				}
			}
		}
		return $text;
	}
	
	// Return human readable date, custom date format
	function getDateFormatted($timestamp, $format)
	{
		$dateMonths = preg_split("/,\s*/", $this->get("dateMonths"));
		$dateWeekdays = preg_split("/,\s*/", $this->get("dateWeekdays"));
		$month = $dateMonths[date('n', $timestamp) - 1];
		$weekday = $dateWeekdays[date('N', $timestamp) - 1];
		$format = preg_replace("/(?<!\\\)F/", addcslashes($month, 'A..Za..z'), $format);
		$format = preg_replace("/(?<!\\\)M/", addcslashes(substru($month, 0, 3), 'A..Za..z'), $format);
		$format = preg_replace("/(?<!\\\)D/", addcslashes(substru($weekday, 0, 3), 'A..Za..z'), $format);
		$format = preg_replace("/(?<!\\\)l/", addcslashes($weekday, 'A..Za..z'), $format);
		return date($format, $timestamp);
	}
	
	// Return languages
	function getLanguages()
	{
		$languages = array();
		foreach($this->text as $key=>$value) array_push($languages, $key);
		return $languages;
	}
	
	// Return text modification date, Unix time or HTTP format
	function getModified($httpFormat = false)
	{
		return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
	}

	// Normalise date into known format
	function normaliseDate($text)
	{
		if(preg_match("/^\d+\-\d+$/", $text))
		{
			$output = $this->getDateFormatted(strtotime($text), $this->get("dateFormatShort"));
		} else if(preg_match("/^\d+\-\d+\-\d+$/", $text)) {
			$output = $this->getDateFormatted(strtotime($text), $this->get("dateFormatMedium"));
		} else if(preg_match("/^\d+\-\d+\-\d+ \d+\:\d+$/", $text)) {
			$output = $this->getDateFormatted(strtotime($text), $this->get("dateFormatLong"));
		} else {
			$output = $text;
		}
		return $output;
	}
	
	// Check if language exists
	function isLanguage($language)
	{
		return !is_null($this->text[$language]);
	}
	
	// Check if text string exists
	function isExisting($key, $language = "")
	{
		if(empty($language)) $language = $this->language;
		return !is_null($this->text[$language]) && !is_null($this->text[$language][$key]);
	}
}

// Yellow location and file lookup
class YellowLookup
{
	var $yellow;		//access to API
	
	function __construct($yellow)
	{
		$this->yellow = $yellow;
	}

	// Load file system information
	function load()
	{
		list($pathRoot, $pathHome) = $this->detectFileSystem();
		$this->yellow->config->set("contentRootDir", $pathRoot);
		$this->yellow->config->set("contentHomeDir", $pathHome);
		date_default_timezone_set($this->yellow->config->get("serverTime"));
	}
	
	// Detect file system
	function detectFileSystem()
	{
		$path = $this->yellow->config->get("contentDir");
		$pathRoot = $this->yellow->config->get("contentRootDir");
		$pathHome = $this->yellow->config->get("contentHomeDir");
		if(!$this->yellow->config->get("multiLanguageMode")) $pathRoot = "";
		if(!empty($pathRoot))
		{
			$token = $root = rtrim($pathRoot, '/');
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				if(empty($firstRoot)) { $firstRoot = $token = $entry; }
				if($this->normaliseName($entry)==$root) { $token = $entry; break; }
			}
			$pathRoot = $this->normaliseName($token)."/";
			$path .= "$firstRoot/";
		}
		if(!empty($pathHome))
		{
			$token = $home = rtrim($pathHome, '/');
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				if(empty($firstHome)) { $firstHome = $token = $entry; }
				if($this->normaliseName($entry)==$home) { $token = $entry; break; }
			}
			$pathHome = $this->normaliseName($token)."/";
			if(count($this->yellow->toolbox->getDirectoryEntries($path.$token, "/.*/", true, true, false))) $invalid = true;
		}
		return array($pathRoot, $invalid ? "invalid" : $pathHome);
	}
	
	// Return root locations
	function findRootLocations($includePath = true)
	{
		$locations = array();
		$pathBase = $this->yellow->config->get("contentDir");
		$pathRoot = $this->yellow->config->get("contentRootDir");
		if(!empty($pathRoot))
		{
			foreach($this->yellow->toolbox->getDirectoryEntries($pathBase, "/.*/", true, true, false) as $entry)
			{
				$token = $this->normaliseName($entry)."/";
				if($token==$pathRoot) $token = "";
				array_push($locations, $includePath ? "root/$token $pathBase$entry/" : "root/$token");
				if(defined("DEBUG") && DEBUG>=2) echo "YellowLookup::findRootLocations root/$token<br/>\n";
			}
		} else {
			array_push($locations, $includePath ? "root/ $pathBase" : "root/");
		}
		return $locations;
	}
	
	// Return location from file path
	function findLocationFromFile($fileName)
	{
		$location = "/";
		$pathBase = $this->yellow->config->get("contentDir");
		$pathRoot = $this->yellow->config->get("contentRootDir");
		$pathHome = $this->yellow->config->get("contentHomeDir");
		$fileDefault = $this->yellow->config->get("contentDefaultFile");
		$fileExtension = $this->yellow->config->get("contentExtension");
		if(substru($fileName, 0, strlenu($pathBase))==$pathBase)
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
			if($extension!=$fileExtension) $invalid = true;
		} else {
			$invalid = true;
		}
		if(defined("DEBUG") && DEBUG>=2)
		{
			$debug = ($invalid ? "INVALID" : $location)." <- $pathBase$fileName";
			echo "YellowLookup::findLocationFromFile $debug<br/>\n";
		}
		return $invalid ? "" : $location;
	}
	
	// Return file path from location
	function findFileFromLocation($location, $directory = false)
	{
		$path = $this->yellow->config->get("contentDir");
		$pathRoot = $this->yellow->config->get("contentRootDir");
		$pathHome = $this->yellow->config->get("contentHomeDir");
		$fileDefault = $this->yellow->config->get("contentDefaultFile");
		$fileExtension = $this->yellow->config->get("contentExtension");
		$tokens = explode('/', $location);
		if($this->isRootLocation($location))
		{
			if(!empty($pathRoot))
			{
				$token = (count($tokens)>2) ? $tokens[1] : rtrim($pathRoot, '/');
				$path .= $this->findFileDirectory($path, $token, true, true, $found, $invalid);
			}
		} else {
			if(!empty($pathRoot))
			{
				if(count($tokens)>2)
				{
					if($this->normaliseName($tokens[1])==$this->normaliseName($pathRoot)) $invalid = true;
					$path .= $this->findFileDirectory($path, $tokens[1], false, true, $found, $invalid);
					if($found) array_shift($tokens);
				}
				if(!$found) $path .= $this->findFileDirectory($path, rtrim($pathRoot, '/'), true, true, $found, $invalid);
				
			}
			if(count($tokens)>2)
			{
				if($this->normaliseName($tokens[1])==$this->normaliseName($pathHome)) $invalid = true;
				for($i=1; $i<count($tokens)-1; ++$i)
				{
					$path .= $this->findFileDirectory($path, $tokens[$i], true, true, $found, $invalid);
				}
			} else {
				$i = 1;
				$tokens[0] = rtrim($pathHome, '/');
				$path .= $this->findFileDirectory($path, $tokens[0], true, true, $found, $invalid);
			}
			if(!$directory)
			{
				if(!empty($tokens[$i]))
				{
					$token = $tokens[$i].$fileExtension;
					$fileFolder = $tokens[$i-1].$fileExtension;
					if($token==$fileDefault || $token==$fileFolder) $invalid = true;
					$path .= $this->findFileDirectory($path, $token, true, false, $found, $invalid);
				} else {
					$path .= $this->findFileDefault($path, $fileDefault, $fileExtension, false);
				}
				if(defined("DEBUG") && DEBUG>=2)
				{
					$debug = "$location -> ".($invalid ? "INVALID" : $path);
					echo "YellowLookup::findFileFromLocation $debug<br/>\n";
				}
			}
		}
		return $invalid ? "" : $path;
	}
	
	// Return file or directory that matches token
	function findFileDirectory($path, $token, $tokenFailback, $directory, &$found, &$invalid)
	{
		if($this->normaliseName($token)!=$token) $invalid = true;
		if(!$invalid)
		{
			$regex = "/^[\d\-\_\.]*".strreplaceu('-', '.', $token)."$/";
			foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, false, $directory, false) as $entry)
			{
				if($this->normaliseName($entry)==$token) { $token = $entry; $found = true; break; }
			}
		}
		if($directory) $token .= '/';
		return ($tokenFailback || $found) ? $token : "";
	}
	
	// Return default file in directory
	function findFileDefault($path, $fileDefault, $fileExtension, $includePath = true)
	{
		$token = $fileDefault;
		if(!is_file($path."/".$fileDefault))
		{
			$fileFolder = $this->normaliseName(basename($path)).$fileExtension;
			$regex = "/^[\d\-\_\.]*($fileDefault|$fileFolder)$/";
			foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry)
			{
				if($this->normaliseName($entry)==$fileDefault) { $token = $entry; break; }
				if($this->normaliseName($entry)==$fileFolder) { $token = $entry; break; }
			}
		}
		return $includePath ? "$path/$token" : $token;
	}
	
	// Return children from location
	function findChildrenFromLocation($location)
	{
		$fileNames = array();
		$fileDefault = $this->yellow->config->get("contentDefaultFile");
		$fileExtension = $this->yellow->config->get("contentExtension");
		if(!$this->isFileLocation($location))
		{
			$path = $this->findFileFromLocation($location, true);
			foreach($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry)
			{
				$token = $this->findFileDefault($path.$entry, $fileDefault, $fileExtension, false);
				array_push($fileNames, $path.$entry."/".$token);
			}
			if(!$this->isRootLocation($location))
			{
				$fileFolder = $this->normaliseName(basename($path)).$fileExtension;
				$regex = "/^.*\\".$fileExtension."$/";
				foreach($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry)
				{
					if($this->normaliseName($entry)==$fileDefault) continue;
					if($this->normaliseName($entry)==$fileFolder) continue;
					if($this->normaliseName($entry, true, true)=="") continue;
					array_push($fileNames, $path.$entry);
				}
			}
		}
		return $fileNames;
	}

	// Return language from file path
	function findLanguageFromFile($fileName, $languageDefault)
	{
		$language = $languageDefault;
		$pathBase = $this->yellow->config->get("contentDir");
		$pathRoot = $this->yellow->config->get("contentRootDir");
		if(!empty($pathRoot))
		{
			$fileName = substru($fileName, strlenu($pathBase));
			if(preg_match("/^(.+?)\//", $fileName, $matches)) $name = $this->normaliseName($matches[1]);
			if(strlenu($name)==2) $language = $name;
		}
		return $language;
	}

	// Return theme/template name from file path
	function findNameFromFile($fileName, $pathBase, $nameDefault, $fileExtension)
	{
		$name = "";
		if(preg_match("/^.*\/(.+?)$/", dirname($fileName), $matches)) $name = $this->normaliseName($matches[1]);
		if(!is_file("$pathBase$name$fileExtension")) $name = $this->normaliseName($nameDefault);
		return $name;
	}
	
	// Return file path from config
	function findFileFromConfig($fileName, $fileNameBase, $nameDefault)
	{
		$pathBase = $this->yellow->config->get("configDir");
		if(preg_match("/^.*\/(.+?)$/", dirname($fileName), $matches)) $name = $this->normaliseName($matches[1]);
		$fileName = strreplaceu("(.*)", $name, $pathBase.$fileNameBase);
		if(!is_file($fileName))
		{
			$name = $this->normaliseName($nameDefault);
			$fileName = strreplaceu("(.*)", $name, $pathBase.$fileNameBase);
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
	
	// Return file path for new page
	function findFilePageNew($fileName, $prefix = "")
	{
		$tokens = explode('/', $fileName);
		for($i=0; $i<count($tokens)-1; ++$i)
		{
			if(!is_dir($path.$tokens[$i]))
			{
				if(!preg_match("/^[\d\-\_\.]+(.*)$/", $tokens[$i]))
				{
					$number = 1;
					foreach($this->yellow->toolbox->getDirectoryEntries($path, "/^[\d\-\_\.]+(.*)$/", true, true, false) as $entry)
					{
						if($number!=1 && $number!=intval($entry)) break;
						$number = intval($entry)+1;
					}
					$tokens[$i] = (empty($prefix) ? "$number-" : $prefix).$tokens[$i];
				}
				$tokens[$i] = $this->normaliseName($tokens[$i], false, false, true);
			}
			$path .= $tokens[$i]."/";
		}
		$path .= $tokens[$i];
		return $path;
	}
	
	// Normalise file/directory/other name
	function normaliseName($text, $removePrefix = true, $removeExtension = false, $filterStrict = false)
	{
		if($removeExtension) $text = ($pos = strrposu($text, '.')) ? substru($text, 0, $pos) : $text;
		if($removePrefix) if(preg_match("/^[\d\-\_\.]+(.*)$/", $text, $matches)) $text = $matches[1];
		if($filterStrict) $text = strreplaceu('.', '-', strtoloweru($text));
		return preg_replace("/[^\pL\d\-\_\.]/u", "-", rtrim($text, '/'));
	}
	
	// Normalise content/media file name
	function normaliseFile($fileName, $convertExtension = false)
	{
		$fileName = basename($fileName);
		if($convertExtension)
		{
			$fileName = ($pos = strposu($fileName, '.')) ? substru($fileName, 0, $pos) : $fileName;
			$fileName .= $this->yellow->config->get("contentExtension");
		}
		return $fileName;
	}
	
	// Normalise location, make absolute location
	function normaliseLocation($location, $pageBase, $pageLocation, $staticLocation = "", $filterStrict = true)
	{
		if(!preg_match("/^\w+:/", trim(html_entity_decode($location, ENT_QUOTES, "UTF-8"))))
		{
			if(empty($staticLocation) || !preg_match("#^$staticLocation#", $location))
			{
				if(preg_match("/^\#/", $location))
				{
					$location = $pageBase.$pageLocation.$location;
				} else if(!preg_match("/^\//", $location)) {
					$location = $this->getDirectoryLocation($pageBase.$pageLocation).$location;
				} else if(!preg_match("#^$pageBase#", $location)) {
					$location = $pageBase.$location;
				}
				$location = strreplaceu(':', $this->yellow->toolbox->getLocationArgsSeparator(), $location);
			}
		} else {
			if($filterStrict && !preg_match("/^(http|https|ftp|mailto):/", $location)) $location = "error-xss-filter";
		}
		return $location;
	}
	
	// Normalise URL, make absolute URL
	function normaliseUrl($serverScheme, $serverName, $base, $location)
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
	
	// Check if location is specifying root
	function isRootLocation($location)
	{
		return $location[0]!="/";
	}
	
	// Check if location is specifying file or directory
	function isFileLocation($location)
	{
		return substru($location, -1, 1)!="/";
	}
	
	// Check if location is visible
	function isVisibleLocation($location, $fileName)
	{
		$visible = true;
		$pathBase = $this->yellow->config->get("contentDir");
		if(substru($fileName, 0, strlenu($pathBase))==$pathBase)
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
	
	// Check if location is within current request
	function isActiveLocation($location, $currentLocation)
	{
		if($this->isFileLocation($location))
		{
			$active = $currentLocation==$location;
		} else {
			if($location==$this->yellow->pages->getHomeLocation($location))
			{
				$active = $this->getDirectoryLocation($currentLocation)==$location;
			} else {
				$active = substru($currentLocation, 0, strlenu($location))==$location;
			}
		}
		return $active;
	}
	
	// Check if location is valid
	function isValidLocation($location)
	{
		$string = "";
		$tokens = explode('/', $location);
		for($i=1; $i<count($tokens); ++$i) $string .= '/'.$this->normaliseName($tokens[$i]);
		return $location==$string;
	}
	
	// Check if file is valid
	function isValidFile($fileName)
	{
		$contentDirLength = strlenu($this->yellow->config->get("contentDir"));
		$mediaDirLength = strlenu($this->yellow->config->get("mediaDir"));
		$systemDirLength = strlenu($this->yellow->config->get("systemDir"));
		return substru($fileName, 0, $contentDirLength)==$this->yellow->config->get("contentDir") ||
			substru($fileName, 0, $mediaDirLength)==$this->yellow->config->get("mediaDir") ||
			substru($fileName, 0, $systemDirLength)==$this->yellow->config->get("systemDir");
	}
}

// Yellow toolbox with helpers
class YellowToolbox
{
	// Return server software from current HTTP request
	function getServerSoftware()
	{
		$serverSoftware = strtoupperu(PHP_SAPI);
		if(preg_match("/^(\S+)/", $_SERVER["SERVER_SOFTWARE"], $matches)) $serverSoftware = $matches[1];
		return $serverSoftware." ".PHP_OS;
	}
	
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
		$serverName = $_SERVER["SERVER_NAME"];
		$serverPort = $_SERVER["SERVER_PORT"];
		if($serverPort!=80 && $serverPort!=443) $serverName .= ":$serverPort";
		return $serverName;
	}
	
	// Return server base from current HTTP request
	function getServerBase()
	{
		$serverBase = "";
		if(preg_match("/^(.*)\//", $_SERVER["SCRIPT_NAME"], $matches)) $serverBase = rtrim($matches[1], '/');
		return $serverBase;
	}
	
	// Return location from current HTTP request
	function getLocation($filterStrict = true)
	{
		$location = $_SERVER["REQUEST_URI"];
		$location = rawurldecode(($pos = strposu($location, '?')) ? substru($location, 0, $pos) : $location);
		if($filterStrict)
		{
			$location = $this->normaliseTokens($location, true);
			$separator = $this->getLocationArgsSeparator();
			if(preg_match("/^(.*?\/)([^\/]+$separator.*)$/", $location, $matches))
			{
				$_SERVER["LOCATION"] = $location = $matches[1];
				$_SERVER["LOCATION_ARGS"] = $matches[2];
				foreach(explode('/', $matches[2]) as $token)
				{
					preg_match("/^(.*?)$separator(.*)$/", $token, $matches);
					if(!empty($matches[1]) && !strempty($matches[2]))
					{
						$matches[1] = strreplaceu(array("\x1c", "\x1d", "\x1e"), array('/', ':', '='), $matches[1]);
						$matches[2] = strreplaceu(array("\x1c", "\x1d", "\x1e"), array('/', ':', '='), $matches[2]);
						$_REQUEST[$matches[1]] = $matches[2];
					}
				}
			} else {
				$_SERVER["LOCATION"] = $location;
				$_SERVER["LOCATION_ARGS"] = "";
			}
		}
		return $location;
	}
	
	// Return location arguments from current HTTP request
	function getLocationArgs()
	{
		return $_SERVER["LOCATION_ARGS"];
	}
	
	// Return location arguments from current HTTP request, modify existing arguments
	function getLocationArgsNew($arg, $pagination)
	{
		$separator = $this->getLocationArgsSeparator();
		preg_match("/^(.*?):(.*)$/", $arg, $args);
		foreach(explode('/', $_SERVER["LOCATION_ARGS"]) as $token)
		{
			preg_match("/^(.*?)$separator(.*)$/", $token, $matches);
			if($matches[1]==$args[1]) { $matches[2] = $args[2]; $found = true; }
			if(!empty($matches[1]) && !strempty($matches[2]))
			{
				if(!empty($locationArgs)) $locationArgs .= '/';
				$locationArgs .= "$matches[1]:$matches[2]";
			}
		}
		if(!$found && !empty($args[1]) && !strempty($args[2]))
		{
			if(!empty($locationArgs)) $locationArgs .= '/';
			$locationArgs .= "$args[1]:$args[2]";
		}
		if(!empty($locationArgs))
		{
			$locationArgs = $this->normaliseArgs($locationArgs, false, false);
			if(!$this->isLocationArgsPagination($locationArgs, $pagination)) $locationArgs .= '/';
		}
		return $locationArgs;
	}
	
	// Return location arguments from current HTTP request, convert form parameters
	function getLocationArgsClean($pagination)
	{
		foreach(array_merge($_GET, $_POST) as $key=>$value)
		{
			if(!empty($key) && !strempty($value))
			{
				if(!empty($locationArgs)) $locationArgs .= '/';
				$key = strreplaceu(array('/', ':', '='), array("\x1c", "\x1d", "\x1e"), $key);
				$value = strreplaceu(array('/', ':', '='), array("\x1c", "\x1d", "\x1e"), $value);
				$locationArgs .= "$key:$value";
			}
		}
		if(!empty($locationArgs))
		{
			$locationArgs = $this->normaliseArgs($locationArgs, false, false);
			if(!$this->isLocationArgsPagination($locationArgs, $pagination)) $locationArgs .= '/';
		}
		return $locationArgs;
	}

	// Return location arguments separator
	function getLocationArgsSeparator()
	{
		return (strtoupperu(substru(PHP_OS, 0, 3))!="WIN") ? ':' : '=';
	}
	
	// Check if location contains location arguments
	function isLocationArgs($location = "")
	{
		$location = empty($location) ? $_SERVER["LOCATION"].$_SERVER["LOCATION_ARGS"] : $location;
		$separator = $this->getLocationArgsSeparator();
		return preg_match("/[^\/]+$separator.*$/", $location);
	}
	
	// Check if location contains pagination arguments
	function isLocationArgsPagination($location, $pagination)
	{
		$separator = $this->getLocationArgsSeparator();
		return preg_match("/^(.*\/)?$pagination$separator.*$/", $location);
	}

	// Check if script location is requested
	function isRequestSelf()
	{
		return $_SERVER["REQUEST_URI"]==$_SERVER["SCRIPT_NAME"];
	}

	// Check if clean URL is requested
	function isRequestCleanUrl($location)
	{
		return (isset($_GET["clean-url"]) || isset($_POST["clean-url"])) && substru($location, -1, 1)=="/";
	}

	// Check if unmodified since last HTTP request
	function isRequestNotModified($lastModifiedFormatted)
	{
		return isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && $_SERVER["HTTP_IF_MODIFIED_SINCE"]==$lastModifiedFormatted;
	}
	
	// Normalise request data, take care of magic quotes
	function normaliseRequest()
	{
		if(get_magic_quotes_gpc())
		{
			function stripArray($data) { return is_array($data) ? array_map("stripArray", $data) : stripslashes($data); }
			$requestData = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
			foreach($requestData as &$data) $data = stripArray($data);
		}
	}
	
	// Normalise path or location, take care of relative path tokens
	function normaliseTokens($text, $prependSlash = false)
	{
		$textFiltered = "";
		if($prependSlash && $text[0]!='/') $textFiltered .= '/';
		for($pos=0; $pos<strlenb($text); ++$pos)
		{
			if($text[$pos]=='/' || $pos==0)
			{
				if($text[$pos+1]=='/') continue;
				if($text[$pos+1]=='.')
				{
					$posNew = $pos+1; while($text[$posNew]=='.') ++$posNew;
					if($text[$posNew]=='/' || $text[$posNew]=='')
					{
						$pos = $posNew-1;
						continue;
					}
				}
			}
			$textFiltered .= $text[$pos];
		}
		return $textFiltered;
	}
	
	// Normalise location arguments
	function normaliseArgs($text, $appendSlash = true, $filterStrict = true)
	{
		if($appendSlash) $text .= '/';
		if($filterStrict) $text = strreplaceu(' ', '-', strtoloweru($text));
		$text = strreplaceu(':', $this->getLocationArgsSeparator(), $text);
		return strreplaceu(array('%2F','%3A','%3D'), array('/',':','='), rawurlencode($text));
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
	
	// Return server time zone
	function getServerTime()
	{
		$serverTime = @date_default_timezone_get();
		if(PHP_OS=="Darwin" && $serverTime=="UTC")
		{
			if(preg_match("#zoneinfo/(.*)#", @readlink("/etc/localtime"), $matches)) $serverTime = $matches[1];
		}
		return $serverTime;
	}
	
	// Return human readable HTTP server status
	function getHttpStatusFormatted($statusCode, $shortFormat = false)
	{
		switch($statusCode)
		{
			case 0:		$text = "No data"; break;
			case 200:	$text = "OK"; break;
			case 301:	$text = "Moved permanently"; break;
			case 302:	$text = "Moved temporarily"; break;
			case 303:	$text = "Reload please"; break;
			case 304:	$text = "Not modified"; break;
			case 400:	$text = "Bad request"; break;
			case 403:	$text = "Forbidden"; break;
			case 404:	$text = "Not found"; break;
			case 424:	$text = "Not existing"; break;
			case 444:	$text = "No response"; break;
			case 500:	$text = "Server error"; break;
			case 503:	$text = "Service unavailable"; break;
			default:	$text = "Error $statusCode";
		}
		$serverProtocol = $_SERVER["SERVER_PROTOCOL"];
		if(!preg_match("/^HTTP\//", $serverProtocol)) $serverProtocol = "HTTP/1.1";
		return $shortFormat ? $text : "$serverProtocol $statusCode $text";
	}
							  
	// Return human readable HTTP date
	function getHttpDateFormatted($timestamp)
	{
		return gmdate("D, d M Y H:i:s", $timestamp)." GMT";
	}
				
	// Return MIME content type
	function getMimeContentType($fileName)
	{
		$mimeTypes = array(
			"css" => "text/css",
			"ico" => "image/x-icon",
			"js" => "application/javascript",
			"jpg" => "image/jpeg",
			"png" => "image/png",
			"svg" => "image/svg+xml",
			"txt" => "text/plain",
			"woff" => "application/font-woff",
			"xml" => "text/xml; charset=utf-8");
		$contentType = "text/html; charset=utf-8";
		$extension = $this->getFileExtension($fileName);
		if(array_key_exists(strtoloweru($extension), $mimeTypes)) $contentType = $mimeTypes[$extension];
		return $contentType;
	}
	
	// Return files and directories
	function getDirectoryEntries($path, $regex = "/.*/", $sort = true, $directories = true, $includePath = true)
	{
		$entries = array();
		$dirHandle = @opendir($path);
		if($dirHandle)
		{
			$path = rtrim($path, '/');
			while(($entry = readdir($dirHandle))!==false)
			{
				if(substru($entry, 0, 1)==".") continue;
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
			if($sort) natcasesort($entries);
			closedir($dirHandle);
		}
		return $entries;
	}
	
	// Return files and directories recursively
	function getDirectoryEntriesRecursive($path, $regex = "/.*/", $sort = true, $directories = true, $levelMax = 0)
	{
		--$levelMax;
		$entries = $this->getDirectoryEntries($path, $regex, $sort, $directories);
		if($levelMax!=0)
		{
			foreach($this->getDirectoryEntries($path, "/.*/", $sort, true) as $entry)
			{
				$entries = array_merge($entries, $this->getDirectoryEntriesRecursive($entry, $regex, $sort, $directories, $levelMax));
			}
		}
		return $entries;
	}
	
	// Read file, empty string if not found
	function readFile($fileName, $sizeMax = 0)
	{
		$fileData = "";
		$fileHandle = @fopen($fileName, "rb");
		if($fileHandle)
		{
			clearstatcache(true, $fileName);
			$fileData = fread($fileHandle, $sizeMax ? $sizeMax : filesize($fileName));
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
		$fileHandle = @fopen($fileName, "wb");
		if($fileHandle)
		{
			clearstatcache(true, $fileName);
			if(flock($fileHandle, LOCK_EX))
			{
				ftruncate($fileHandle, 0);
				fwrite($fileHandle, $fileData);
				flock($fileHandle, LOCK_UN);
			}
			fclose($fileHandle);
			$ok = true;
		}
		return $ok;
	}
	
	// Copy file
	function copyFile($fileNameSource, $fileNameDest, $mkdir = false)
	{
		clearstatcache();
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
		clearstatcache();
		if($mkdir)
		{
			$path = dirname($fileNameDest);
			if(!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
		}
		return @rename($fileNameSource, $fileNameDest);
	}
	
	// Delete file
	function deleteFile($fileName, $pathTrash = "")
	{
		clearstatcache();
		if(empty($pathTrash))
		{
			$ok = @unlink($fileName);
		} else {
			if(!is_dir($pathTrash)) @mkdir($pathTrash, 0777, true);
			$fileNameDest = $pathTrash;
			$fileNameDest .= pathinfo($fileName, PATHINFO_FILENAME);
			$fileNameDest .= "-".str_replace(array(" ", ":"), "-", date("Y-m-d H:i:s", filemtime($fileName)));
			$fileNameDest .= ".".pathinfo($fileName, PATHINFO_EXTENSION);
			$ok = @rename($fileName, $fileNameDest);
		}
		return $ok;
	}
	
	// Delete directory
	function deleteDirectory($path, $pathTrash = "")
	{
		clearstatcache();
		if(empty($pathTrash))
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
			$ok = @rmdir($path);
		} else {
			if(!is_dir($pathTrash)) @mkdir($pathTrash, 0777, true);
			$pathDest = $pathTrash;
			$pathDest .= basename($path);
			$pathDest .= "-".str_replace(array(" ", ":"), "-", date("Y-m-d H:i:s", filemtime($path)));
			$ok = @rename($path, $pathDest);
		}
		return $ok;
	}
	
	// Set file modification date, Unix time
	function modifyFile($fileName, $modified)
	{
		clearstatcache(true, $fileName);
		return @touch($fileName, $modified);
	}
	
	// Return file modification date, Unix time
	function getFileModified($fileName)
	{
		return is_file($fileName) ? filemtime($fileName) : 0;
	}
	
	// Return file extension
	function getFileExtension($fileName)
	{
		return strtoloweru(($pos = strrposu($fileName, '.')) ? substru($fileName, $pos+1) : "");
	}
	
	// Return lines from text string
	function getTextLines($text)
	{
		$lines = array();
		$split = preg_split("/(\R)/u", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		for($i=0; $i<count($split)-1; $i+=2) array_push($lines, $split[$i].$split[$i+1]);
		if($split[$i]!='') array_push($lines, $split[$i]);
		return $lines;
	}
	
	// Return arguments from text string
	function getTextArgs($text, $optional = "-")
	{
		$text = preg_replace("/\s+/s", " ", trim($text));
		$tokens = str_getcsv($text, ' ', '"');
		foreach($tokens as $key=>$value) if($value==$optional) $tokens[$key] = "";
		return $tokens;
	}
	
	// Create description from text string
	function createTextDescription($text, $lengthMax, $removeHtml = true, $endMarker = "", $endMarkerText = "")
	{
		if(preg_match("/^<h1>.*?<\/h1>(.*)$/si", $text, $matches)) $text = $matches[1];
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
				$output .= substru($string, 0, $length<$lengthMax ? $length : $lengthMax-1);
				$lengthMax -= $length;
				if(!empty($element) && $element==$endMarker) { $lengthMax = 0; $endMarkerFound = true; }
				if($lengthMax<=0 || !$elementFound) break;
				$offsetBytes = $elementOffsetBytes + strlenb($element);
			}
			$output = rtrim($output);
			if($lengthMax<=0) $output .= $endMarkerFound ? $endMarkerText : "…";
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
				$output .= substru($string, 0, $length<$lengthMax ? $length : $lengthMax-1);
				$lengthMax -= $length + ($element[0]=='&' ? 1 : 0);
				if(!empty($element) && $element==$endMarker) { $lengthMax = 0; $endMarkerFound = true; }
				if($lengthMax<=0 || !$elementFound) break;
				if(!empty($elementName) && substru($elementText, -1)!='/' &&
				   !preg_match("/^(area|br|col|hr|img|input|col|param|!)/i", $elementName))
				{
					if($elementName[0]!='/')
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
			if($lengthMax<=0) $output .= $endMarkerFound ? $endMarkerText : "…";
			for(; $i>=0; --$i) $output .= "</".$elementsOpen[$i].">";
		}
		return $output;
	}
	
	// Create keywords from text string
	function createTextKeywords($text, $keywordsMax = 0)
	{
		$tokens = array_unique(preg_split("/[,\s\(\)\+\-]/", strtoloweru($text)));
		foreach($tokens as $key=>$value) if(strlenu($value)<3) unset($tokens[$key]);
		if($keywordsMax) $tokens = array_slice($tokens, 0, $keywordsMax);
		return implode(", ", $tokens);
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
		if(strlenb($dataBuffer)==$dataBufferSize)
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
			case "bcrypt":	if(substrb($hash, 0, 4)=="$2y$" || substrb($hash, 0, 4)=="$2a$")
							{
								$hashCalculated = crypt($text, $hash);
							}
							break;
			case "sha256":	if(substrb($hash, 0, 4)=="$5y$")
							{
								$prefix = substrb($hash, 0, 4);
								$salt = substrb($hash, 4, 32);
								$hashCalculated = "$prefix$salt".hash("sha256", $salt.$text);
							}
							break;
		}
		$ok = !empty($hashCalculated) && strlenb($hashCalculated)==strlenb($hash);
		if($ok) for($i=0; $i<strlenb($hashCalculated); ++$i) $ok &= $hashCalculated[$i]==$hash[$i];
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
	
	// Detect image dimensions and type for jpg/png/svg
	function detectImageInfo($fileName)
	{
		$width = $height = 0;
		$type = "";
		$fileHandle = @fopen($fileName, "rb");
		if($fileHandle)
		{
			if(substru(strtoloweru($fileName), -3)=="jpg")
			{
				$dataBufferSizeMax = filesize($fileName);
				$dataBufferSize = min($dataBufferSizeMax, 4096);
				$dataBuffer = fread($fileHandle, $dataBufferSize);
				$dataSignature = substrb($dataBuffer, 0, 4);
				if(!feof($fileHandle) && ($dataSignature=="\xff\xd8\xff\xe0" || $dataSignature=="\xff\xd8\xff\xe1"))
				{
					for($pos=2; $pos+8<$dataBufferSize; $pos+=$length)
					{
						if($dataBuffer[$pos]!="\xff") break;
						if($dataBuffer[$pos+1]=="\xc0" || $dataBuffer[$pos+1]=="\xc2")
						{
							$width = (ord($dataBuffer[$pos+7])<<8) + ord($dataBuffer[$pos+8]);
							$height = (ord($dataBuffer[$pos+5])<<8) + ord($dataBuffer[$pos+6]);
							$type = "jpg";
							break;
						}
						$length = (ord($dataBuffer[$pos+2])<<8) + ord($dataBuffer[$pos+3]) + 2;
						while($pos+$length+8>=$dataBufferSize)
						{
							if($dataBufferSize==$dataBufferSizeMax) break;
							$dataBufferDiff = min($dataBufferSizeMax, $dataBufferSize*2) - $dataBufferSize;
							$dataBufferSize += $dataBufferDiff;
							$dataBuffer .= fread($fileHandle, $dataBufferDiff);
							if(feof($fileHandle)) { $dataBufferSize = 0; break; }
						}
					}
				}
			} else if(substru(strtoloweru($fileName), -3)=="png") {
				$dataSignature = fread($fileHandle, 8);
				$dataHeader = fread($fileHandle, 16);
				if(!feof($fileHandle) && $dataSignature=="\x89PNG\r\n\x1a\n")
				{
					$width = (ord($dataHeader[10])<<8) + ord($dataHeader[11]);
					$height = (ord($dataHeader[14])<<8) + ord($dataHeader[15]);
					$type = "png";
				}
			} else if(substru(strtoloweru($fileName), -3)=="svg") {
				$dataBufferSizeMax = filesize($fileName);
				$dataBufferSize = min($dataBufferSizeMax, 4096);
				$dataBuffer = fread($fileHandle, $dataBufferSize);
				$dataSignature = substrb($dataBuffer, 0, 5);
				if(!feof($fileHandle) && $dataSignature=="\x3csvg\x20")
				{
					$dataBuffer = ($pos = strposu($dataBuffer, '>')) ? substru($dataBuffer, 0, $pos) : $dataBuffer;
					if(preg_match("/ width=\"(\d+)\"/", $dataBuffer, $matches)) $width = $matches[1];
					if(preg_match("/ height=\"(\d+)\"/", $dataBuffer, $matches)) $height = $matches[1];
					$type = "svg";
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

// Unicode support for PHP
mb_internal_encoding("UTF-8");
function strempty($string) { return is_null($string) || $string===""; }
function strencode($string) { return addcslashes($string, "\'\"\\\/"); }
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

// Error reporting for PHP
error_reporting(E_ALL ^ E_NOTICE);
?>