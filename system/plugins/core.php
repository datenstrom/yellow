<?php
// Core plugin, https://github.com/datenstrom/yellow-plugins/tree/master/core
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowCore {
    const VERSION = "0.7.8";
    public $page;           //current page
    public $pages;          //pages from file system
    public $files;          //files from file system
    public $plugins;        //plugins
    public $themes;         //themes
    public $config;         //configuration
    public $text;           //text
    public $lookup;         //location and file lookup
    public $toolbox;        //toolbox with helpers

    public function __construct() {
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
        $this->config->setDefault("timezone", "UTC");
        $this->config->setDefault("theme", "default");
        $this->config->setDefault("staticUrl", "");
        $this->config->setDefault("staticDefaultFile", "index.html");
        $this->config->setDefault("staticErrorFile", "404.html");
        $this->config->setDefault("staticDir", "public/");
        $this->config->setDefault("cacheDir", "cache/");
        $this->config->setDefault("mediaLocation", "/media/");
        $this->config->setDefault("downloadLocation", "/media/downloads/");
        $this->config->setDefault("imageLocation", "/media/images/");
        $this->config->setDefault("pluginLocation", "/media/plugins/");
        $this->config->setDefault("themeLocation", "/media/themes/");
        $this->config->setDefault("assetLocation", "/media/themes/assets/");
        $this->config->setDefault("mediaDir", "media/");
        $this->config->setDefault("downloadDir", "media/downloads/");
        $this->config->setDefault("imageDir", "media/images/");
        $this->config->setDefault("systemDir", "system/");
        $this->config->setDefault("configDir", "system/config/");
        $this->config->setDefault("pluginDir", "system/plugins/");
        $this->config->setDefault("themeDir", "system/themes/");
        $this->config->setDefault("assetDir", "system/themes/assets/");
        $this->config->setDefault("snippetDir", "system/themes/snippets/");
        $this->config->setDefault("templateDir", "system/themes/templates/");
        $this->config->setDefault("trashDir", "system/trash/");
        $this->config->setDefault("contentDir", "content/");
        $this->config->setDefault("contentRootDir", "default/");
        $this->config->setDefault("contentHomeDir", "home/");
        $this->config->setDefault("contentPagination", "page");
        $this->config->setDefault("contentDefaultFile", "page.txt");
        $this->config->setDefault("contentExtension", ".txt");
        $this->config->setDefault("configExtension", ".ini");
        $this->config->setDefault("downloadExtension", ".download");
        $this->config->setDefault("configFile", "config.ini");
        $this->config->setDefault("textFile", "text.ini");
        $this->config->setDefault("languageFile", "language-(.*).txt");
        $this->config->setDefault("errorFile", "page-error-(.*).txt");
        $this->config->setDefault("newFile", "page-new-(.*).txt");
        $this->config->setDefault("robotsFile", "robots.txt");
        $this->config->setDefault("faviconFile", "favicon.ico");
        $this->config->setDefault("serverUrl", "");
        $this->config->setDefault("startupUpdate", "none");
        $this->config->setDefault("template", "default");
        $this->config->setDefault("navigation", "navigation");
        $this->config->setDefault("sidebar", "sidebar");
        $this->config->setDefault("siteicon", "icon");
        $this->config->setDefault("tagline", "");
        $this->config->setDefault("parser", "markdown");
        $this->config->setDefault("multiLanguageMode", "0");
        $this->config->setDefault("safeMode", "0");
    }
    
    public function __destruct() {
        $this->shutdown();
    }
    
    // Handle initialisation
    public function load() {
        if (defined("DEBUG") && DEBUG>=2) {
            $serverVersion = $this->toolbox->getServerVersion();
            echo "Datenstrom Yellow ".YellowCore::VERSION.", PHP ".PHP_VERSION.", $serverVersion<br/>\n";
        }
        $this->toolbox->timerStart($time);
        $this->config->load($this->config->get("configDir").$this->config->get("configFile"));
        $this->lookup->load();
        $this->themes->load();
        $this->plugins->load();
        $this->text->load($this->config->get("pluginDir").$this->config->get("languageFile"), "");
        $this->text->load($this->config->get("configDir").$this->config->get("textFile"), $this->config->get("language"));
        $this->toolbox->timerStop($time);
        $this->startup();
        if (defined("DEBUG") && DEBUG>=2) {
            $plugins = count($this->plugins->plugins);
            $themes = count($this->themes->themes);
            $languages = count($this->text->text);
            echo "YellowCore::load plugins:$plugins themes:$themes languages:$languages time:$time ms<br/>\n";
        }
    }
    
    // Handle request
    public function request() {
        ob_start();
        $statusCode = 0;
        $this->toolbox->timerStart($time);
        list($scheme, $address, $base, $location, $fileName) = $this->getRequestInformation();
        $this->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        foreach ($this->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onRequest")) {
                $this->lookup->requestHandler = $key;
                $statusCode = $value["obj"]->onRequest($scheme, $address, $base, $location, $fileName);
                if ($statusCode!=0) break;
            }
        }
        if ($statusCode==0) {
            $this->lookup->requestHandler = "core";
            $statusCode = $this->processRequest($scheme, $address, $base, $location, $fileName, true);
        }
        if ($this->page->isExisting("pageError")) $statusCode = $this->processRequestError();
        $this->toolbox->timerStop($time);
        ob_end_flush();
        if (defined("DEBUG") && DEBUG>=1 && $this->lookup->isContentFile($fileName)) {
            $handler = $this->getRequestHandler();
            echo "YellowCore::request status:$statusCode handler:$handler time:$time ms<br/>\n";
        }
        return $statusCode;
    }
    
    // Process request
    public function processRequest($scheme, $address, $base, $location, $fileName, $cacheable) {
        $statusCode = 0;
        if (is_readable($fileName)) {
            if ($this->toolbox->isRequestCleanUrl($location)) {
                $location = $location.$this->getRequestLocationArgsClean();
                $location = $this->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->sendStatus(303, $location);
            }
        } else {
            if ($this->lookup->isRedirectLocation($location)) {
                $location = $this->lookup->isFileLocation($location) ? "$location/" : "/".$this->getRequestLanguage()."/";
                $location = $this->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->sendStatus(301, $location);
            }
        }
        if ($statusCode==0) {
            $fileName = $this->lookup->findFileFromCache($location, $fileName, $cacheable && !$this->isCommandLine());
            if ($this->lookup->isContentFile($fileName) || !is_readable($fileName)) {
                $fileName = $this->readPage($scheme, $address, $base, $location, $fileName, $cacheable,
                    max(is_readable($fileName) ? 200 : 404, $this->page->statusCode), $this->page->get("pageError"));
                $statusCode = $this->sendPage();
            } else {
                $statusCode = $this->sendFile(200, $fileName, true);
            }
        }
        if (defined("DEBUG") && DEBUG>=1 && $this->lookup->isContentFile($fileName)) {
            echo "YellowCore::processRequest file:$fileName<br/>\n";
        }
        return $statusCode;
    }
    
    // Process request with error
    public function processRequestError() {
        ob_clean();
        $fileName = $this->readPage($this->page->scheme, $this->page->address, $this->page->base,
            $this->page->location, $this->page->fileName, $this->page->cacheable, $this->page->statusCode,
            $this->page->get("pageError"));
        $statusCode = $this->sendPage();
        if (defined("DEBUG") && DEBUG>=1) echo "YellowCore::processRequestError file:$fileName<br/>\n";
        return $statusCode;
    }
    
    // Read page
    public function readPage($scheme, $address, $base, $location, $fileName, $cacheable, $statusCode, $pageError) {
        if ($statusCode>=400) {
            $cacheable = false;
            $fileName = $this->config->get("configDir").$this->config->get("errorFile");
            $fileName = strreplaceu("(.*)", $statusCode, $fileName);
            $rawData = $this->toolbox->readFile($fileName);
            if (empty($rawData)) $rawData = "---\nTitle:".$this->toolbox->getHttpStatusFormatted($statusCode, true)."\n---\n";
        } else {
            $rawData = $this->toolbox->readFile($fileName);
        }
        $this->page = new YellowPage($this);
        $this->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $this->page->parseData($rawData, $cacheable, $statusCode, $pageError);
        $this->text->setLanguage($this->page->get("language"));
        $this->page->parseContent();
        return $fileName;
    }
    
    // Send page response
    public function sendPage() {
        $this->page->parsePage();
        $statusCode = $this->page->statusCode;
        $lastModifiedFormatted = $this->page->getHeader("Last-Modified");
        if ($statusCode==200 && $this->page->isCacheable() && $this->toolbox->isRequestNotModified($lastModifiedFormatted)) {
            $statusCode = 304;
            @header($this->toolbox->getHttpStatusFormatted($statusCode));
        } else {
            @header($this->toolbox->getHttpStatusFormatted($statusCode));
            foreach ($this->page->headerData as $key=>$value) {
                @header("$key: $value");
            }
            if (!is_null($this->page->outputData)) echo $this->page->outputData;
        }
        if (defined("DEBUG") && DEBUG>=1) {
            foreach ($this->page->headerData as $key=>$value) {
                echo "YellowCore::sendPage $key: $value<br/>\n";
            }
            $theme = $this->page->get("theme");
            $template = $this->page->get("template");
            $parser = $this->page->get("parser");
            echo "YellowCore::sendPage theme:$theme template:$template parser:$parser<br/>\n";
        }
        return $statusCode;
    }
    
    // Send file response
    public function sendFile($statusCode, $fileName, $cacheable) {
        $lastModifiedFormatted = $this->toolbox->getHttpDateFormatted($this->toolbox->getFileModified($fileName));
        if ($statusCode==200 && $cacheable && $this->toolbox->isRequestNotModified($lastModifiedFormatted)) {
            $statusCode = 304;
            @header($this->toolbox->getHttpStatusFormatted($statusCode));
        } else {
            @header($this->toolbox->getHttpStatusFormatted($statusCode));
            if (!$cacheable) @header("Cache-Control: no-cache, must-revalidate");
            @header("Content-Type: ".$this->toolbox->getMimeContentType($fileName));
            @header("Last-Modified: ".$lastModifiedFormatted);
            echo $this->toolbox->readFile($fileName);
        }
        return $statusCode;
    }
    
    // Send data response
    public function sendData($statusCode, $rawData, $fileName, $cacheable) {
        @header($this->toolbox->getHttpStatusFormatted($statusCode));
        if (!$cacheable) @header("Cache-Control: no-cache, must-revalidate");
        @header("Content-Type: ".$this->toolbox->getMimeContentType($fileName));
        @header("Last-Modified: ".$this->toolbox->getHttpDateFormatted(time()));
        echo $rawData;
        return $statusCode;
    }

    // Send status response
    public function sendStatus($statusCode, $location = "") {
        if (!empty($location)) $this->page->clean($statusCode, $location);
        @header($this->toolbox->getHttpStatusFormatted($statusCode));
        foreach ($this->page->headerData as $key=>$value) {
            @header("$key: $value");
        }
        if (defined("DEBUG") && DEBUG>=1) {
            foreach ($this->page->headerData as $key=>$value) {
                echo "YellowCore::sendStatus $key: $value<br/>\n";
            }
        }
        return $statusCode;
    }
    
    // Handle command
    public function command($args = null) {
        $statusCode = 0;
        $this->toolbox->timerStart($time);
        foreach ($this->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onCommand")) {
                $this->lookup->commandHandler = $key;
                $statusCode = $value["obj"]->onCommand(func_get_args());
                if ($statusCode!=0) break;
            }
        }
        if ($statusCode==0) {
            $this->lookup->commandHandler = "core";
            $statusCode = 400;
            list($command) = func_get_args();
            echo "Yellow $command: Command not found\n";
        }
        $this->toolbox->timerStop($time);
        if (defined("DEBUG") && DEBUG>=1) {
            $handler = $this->getCommandHandler();
            echo "YellowCore::command status:$statusCode handler:$handler time:$time ms<br/>\n";
        }
        return $statusCode;
    }
    
    // Handle startup
    public function startup() {
        $tokens = explode(",", $this->config->get("startupUpdate"));
        foreach ($this->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onStartup")) $value["obj"]->onStartup(in_array($value["plugin"], $tokens));
        }
        foreach ($this->themes->themes as $key=>$value) {
            if (method_exists($value["obj"], "onStartup")) $value["obj"]->onStartup(in_array($value["theme"], $tokens));
        }
        if ($this->config->get("startupUpdate")!="none") {
            $fileNameConfig = $this->config->get("configDir").$this->config->get("configFile");
            $this->config->save($fileNameConfig, array("startupUpdate" => "none"));
        }
    }
    
    // Handle shutdown
    public function shutdown() {
        foreach ($this->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onShutdown")) $value["obj"]->onShutdown();
        }
        foreach ($this->themes->themes as $key=>$value) {
            if (method_exists($value["obj"], "onShutdown")) $value["obj"]->onShutdown();
        }
    }
    
    // Include snippet
    public function snippet($name, $args = null) {
        $this->lookup->snippetArgs = func_get_args();
        $this->page->includePageSnippet($name);
    }
    
    // Return snippet arguments
    public function getSnippetArgs() {
        return $this->lookup->snippetArgs;
    }
    
    // Return request information
    public function getRequestInformation($scheme = "", $address = "", $base = "") {
        if (empty($scheme) && empty($address) && empty($base)) {
            $url = $this->config->get("serverUrl");
            if (empty($url) || $this->isCommandLine()) $url = $this->toolbox->getServerUrl();
            list($scheme, $address, $base) = $this->lookup->getUrlInformation($url);
            $this->config->set("serverScheme", $scheme);
            $this->config->set("serverAddress", $address);
            $this->config->set("serverBase", $base);
            if (defined("DEBUG") && DEBUG>=3) echo "YellowCore::getRequestInformation $scheme://$address$base<br/>\n";
        }
        $location = substru($this->toolbox->getLocation(), strlenu($base));
        if (empty($fileName)) $fileName = $this->lookup->findFileFromSystem($location);
        if (empty($fileName)) $fileName = $this->lookup->findFileFromMedia($location);
        if (empty($fileName)) $fileName = $this->lookup->findFileFromLocation($location);
        return array($scheme, $address, $base, $location, $fileName);
    }
    
    // Return request location
    public function getRequestLocationArgsClean() {
        return $this->toolbox->getLocationArgsClean($this->config->get("contentPagination"));
    }
    
    // Return request language
    public function getRequestLanguage() {
        return $this->toolbox->detectBrowserLanguage($this->pages->getLanguages(), $this->config->get("language"));
    }
    
    // Return request handler
    public function getRequestHandler() {
        return $this->lookup->requestHandler;
    }

    // Return command handler
    public function getCommandHandler() {
        return $this->lookup->commandHandler;
    }
    
    // Check if running at command line
    public function isCommandLine() {
        return !empty($this->lookup->commandHandler);
    }
}
    
class YellowPage {
    public $yellow;                 //access to API
    public $scheme;                 //server scheme
    public $address;                //server address
    public $base;                   //base location
    public $location;               //page location
    public $fileName;               //content file name
    public $rawData;                //raw data of page
    public $metaDataOffsetBytes;    //meta data offset
    public $metaData;               //meta data
    public $pageCollection;         //page collection
    public $pageRelations;          //page relations
    public $headerData;             //response header
    public $outputData;             //response output
    public $parser;                 //content parser
    public $parserData;             //content data of page
    public $safeMode;               //page is parsed in safe mode? (boolean)
    public $available;              //page is available? (boolean)
    public $visible;                //page is visible location? (boolean)
    public $active;                 //page is active location? (boolean)
    public $cacheable;              //page is cacheable? (boolean)
    public $lastModified;           //last modification date
    public $statusCode;             //status code

    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->metaData = new YellowDataCollection();
        $this->pageCollection = new YellowPageCollection($yellow);
        $this->pageRelations = array();
        $this->headerData = array();
    }

    // Set request information
    public function setRequestInformation($scheme, $address, $base, $location, $fileName) {
        $this->scheme = $scheme;
        $this->address = $address;
        $this->base = $base;
        $this->location = $location;
        $this->fileName = $fileName;
    }
    
    // Parse page data
    public function parseData($rawData, $cacheable, $statusCode, $pageError = "") {
        $this->rawData = $rawData;
        $this->parser = null;
        $this->parserData = "";
        $this->safeMode = intval($this->yellow->config->get("safeMode"));
        $this->available = true;
        $this->visible = $this->yellow->lookup->isVisibleLocation($this->location, $this->fileName);
        $this->active = $this->yellow->lookup->isActiveLocation($this->location, $this->yellow->page->location);
        $this->cacheable = $cacheable;
        $this->lastModified = 0;
        $this->statusCode = $statusCode;
        $this->parseMeta($pageError);
    }
    
    // Parse page data update
    public function parseDataUpdate() {
        if ($this->statusCode==0) {
            $this->rawData = $this->yellow->toolbox->readFile($this->fileName);
            $this->statusCode = 200;
            $this->parseMeta();
        }
    }
    
    // Parse page meta data
    public function parseMeta($pageError = "") {
        $this->metaData = new YellowDataCollection();
        if (!is_null($this->rawData)) {
            $this->set("title", $this->yellow->toolbox->createTextTitle($this->location));
            $this->set("language", $this->yellow->lookup->findLanguageFromFile($this->fileName, $this->yellow->config->get("language")));
            $this->set("modified", date("Y-m-d H:i:s", $this->yellow->toolbox->getFileModified($this->fileName)));
            $this->parseMetaRaw(array("theme", "template", "sitename", "siteicon", "tagline", "author", "navigation", "sidebar", "parser"));
            $titleHeader = ($this->location==$this->yellow->pages->getHomeLocation($this->location)) ?
                $this->get("sitename") : $this->get("title")." - ".$this->get("sitename");
            if (!$this->isExisting("titleContent")) $this->set("titleContent", $this->get("title"));
            if (!$this->isExisting("titleNavigation")) $this->set("titleNavigation", $this->get("title"));
            if (!$this->isExisting("titleHeader")) $this->set("titleHeader", $titleHeader);
            if ($this->get("status")=="hidden") $this->available = false;
            $this->set("pageRead", $this->yellow->lookup->normaliseUrl(
                $this->yellow->config->get("serverScheme"),
                $this->yellow->config->get("serverAddress"),
                $this->yellow->config->get("serverBase"),
                $this->location));
            $this->set("pageEdit", $this->yellow->lookup->normaliseUrl(
                $this->yellow->config->get("serverScheme"),
                $this->yellow->config->get("serverAddress"),
                $this->yellow->config->get("serverBase"),
                rtrim($this->yellow->config->get("editLocation"), "/").$this->location));
        } else {
            $this->set("type", $this->yellow->toolbox->getFileType($this->fileName));
            $this->set("group", $this->yellow->toolbox->getFileGroup($this->fileName, $this->yellow->config->get("mediaDir")));
            $this->set("modified", date("Y-m-d H:i:s", $this->yellow->toolbox->getFileModified($this->fileName)));
        }
        if (!empty($pageError)) $this->set("pageError", $pageError);
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onParseMeta")) $value["obj"]->onParseMeta($this);
        }
    }
    
    // Parse page meta data from raw data
    public function parseMetaRaw($defaultKeys) {
        foreach ($defaultKeys as $key) {
            $value = $this->yellow->config->get($key);
            if (!empty($key) && !strempty($value)) $this->set($key, $value);
        }
        if (preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)\-\-\-[\r\n]+/s", $this->rawData, $parts)) {
            $this->metaDataOffsetBytes = strlenb($parts[0]);
            foreach (preg_split("/[\r\n]+/", $parts[2]) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !strempty($matches[2])) $this->set($matches[1], $matches[2]);
            }
        } elseif (preg_match("/^(\xEF\xBB\xBF)?([^\r\n]+)[\r\n]+=+[\r\n]+/", $this->rawData, $parts)) {
            $this->metaDataOffsetBytes = strlenb($parts[0]);
            $this->set("title", $parts[2]);
        }
    }
    
    // Parse page content on demand
    public function parseContent($sizeMax = 0) {
        if (!is_object($this->parser)) {
            if ($this->yellow->plugins->isExisting($this->get("parser"))) {
                $plugin = $this->yellow->plugins->plugins[$this->get("parser")];
                if (method_exists($plugin["obj"], "onParseContentRaw")) {
                    $this->parser = $plugin["obj"];
                    $this->parserData = $this->getContent(true, $sizeMax);
                    $this->parserData = preg_replace("/@pageRead/i", $this->get("pageRead"), $this->parserData);
                    $this->parserData = preg_replace("/@pageEdit/i", $this->get("pageEdit"), $this->parserData);
                    $this->parserData = $this->parser->onParseContentRaw($this, $this->parserData);
                    foreach ($this->yellow->plugins->plugins as $key=>$value) {
                        if (method_exists($value["obj"], "onParseContentText")) {
                            $output = $value["obj"]->onParseContentText($this, $this->parserData);
                            if (!is_null($output)) $this->parserData = $output;
                        }
                    }
                }
            } else {
                $this->parserData = $this->getContent(true, $sizeMax);
                $this->parserData = preg_replace("/\[yellow error\]/i", $this->get("pageError"), $this->parserData);
            }
            if (!$this->isExisting("description")) {
                $this->set("description", $this->yellow->toolbox->createTextDescription($this->parserData, 150));
            }
            if (!$this->isExisting("keywords")) {
                $this->set("keywords", $this->yellow->toolbox->createTextKeywords($this->get("title"), 10));
            }
            if (defined("DEBUG") && DEBUG>=3) echo "YellowPage::parseContent location:".$this->location."<br/>\n";
        }
    }
    
    // Parse page content block
    public function parseContentBlock($name, $text, $shortcut) {
        $output = null;
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onParseContentBlock")) {
                $output = $value["obj"]->onParseContentBlock($this, $name, $text, $shortcut);
                if (!is_null($output)) break;
            }
        }
        if (is_null($output)) {
            if ($name=="yellow" && $shortcut) {
                $output = "Datenstrom Yellow ".YellowCore::VERSION;
                if ($text=="error") $output = $this->get("pageError");
                if ($text=="version") {
                    $output = "<span class=\"".htmlspecialchars($name)."\">\n";
                    $serverVersion = $this->yellow->toolbox->getServerVersion();
                    $output .= "Datenstrom Yellow ".YellowCore::VERSION.", PHP ".PHP_VERSION.", $serverVersion<br />\n";
                    foreach (array_merge($this->yellow->plugins->getData(), $this->yellow->themes->getData()) as $key=>$value) {
                        $output .= htmlspecialchars("$key $value")."<br />\n";
                    }
                    $output .= "</span>\n";
                    if ($this->safeMode) $this->error(500, "Yellow '$text' is not available in safe mode!");
                }
            }
        }
        if (defined("DEBUG") && DEBUG>=3 && !empty($name)) echo "YellowPage::parseContentBlock name:$name shortcut:$shortcut<br/>\n";
        return $output;
    }
    
    // Parse page
    public function parsePage() {
        $this->parsePageTemplate($this->get("template"));
        if (!$this->isCacheable()) $this->setHeader("Cache-Control", "no-cache, must-revalidate");
        if (!$this->isHeader("Content-Type")) $this->setHeader("Content-Type", "text/html; charset=utf-8");
        if (!$this->isHeader("Page-Modified")) $this->setHeader("Page-Modified", $this->getModified(true));
        if (!$this->isHeader("Last-Modified")) $this->setHeader("Last-Modified", $this->getLastModified(true));
        if (!$this->yellow->text->isLanguage($this->get("language"))) {
            $this->error(500, "Language '".$this->get("language")."' does not exist!");
        }
        if (!$this->yellow->themes->isExisting($this->get("theme"))) {
            $this->error(500, "Theme '".$this->get("theme")."' does not exist!");
        }
        if (!is_object($this->parser)) {
            $this->error(500, "Parser '".$this->get("parser")."' does not exist!");
        }
        if ($this->yellow->lookup->isNestedLocation($this->location, $this->fileName, true)) {
            $this->error(500, "Folder '".dirname($this->fileName)."' may not contain subfolders!");
        }
        if ($this->yellow->getRequestHandler()=="core" && $this->isExisting("redirect") && $this->statusCode==200) {
            $location = $this->yellow->lookup->normaliseLocation($this->get("redirect"), $this->location);
            $location = $this->yellow->lookup->normaliseUrl($this->scheme, $this->address, "", $location);
            $this->clean(301, $location);
        }
        if ($this->yellow->getRequestHandler()=="core" && !$this->isAvailable() && $this->statusCode==200) {
            $this->error(404);
        }
        if ($this->yellow->toolbox->isRequestSelf()) {
            $this->error(404);
        }
        if ($this->isExisting("pageClean")) $this->outputData = null;
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onParsePageOutput")) {
                $output = $value["obj"]->onParsePageOutput($this, $this->outputData);
                if (!is_null($output)) $this->outputData = $output;
            }
        }
    }
    
    // Parse page template
    public function parsePageTemplate($name) {
        $this->outputData = null;
        if (!$this->isError()) {
            foreach ($this->yellow->plugins->plugins as $key=>$value) {
                if (method_exists($value["obj"], "onParsePageTemplate")) {
                    $value["obj"]->onParsePageTemplate($this, $name);
                } elseif (method_exists($value["obj"], "onParsePage")) {    //TODO: remove later, old event handler
                    $value["obj"]->onParsePage();
                }
            }
        }
        if (is_null($this->outputData)) {
            ob_start();
            $this->includePageTemplate($name);
            $this->outputData = ob_get_contents();
            ob_end_clean();
        }
    }
    
    // Include page template
    public function includePageTemplate($name) {
        $fileNameTemplate = $this->yellow->config->get("templateDir").$this->yellow->lookup->normaliseName($name).".html";
        if (is_file($fileNameTemplate)) {
            $this->setLastModified(filemtime($fileNameTemplate));
            global $yellow;
            require($fileNameTemplate);
        } else {
            $this->error(500, "Template '$name' does not exist!");
            echo "Template error<br/>\n";
        }
    }
    
    // Include page snippet
    public function includePageSnippet($name) {
        $fileNameSnippet = $this->yellow->config->get("snippetDir").$this->yellow->lookup->normaliseName($name).".php";
        if (is_file($fileNameSnippet)) {
            $this->setLastModified(filemtime($fileNameSnippet));
            global $yellow;
            require($fileNameSnippet);
        } else {
            $this->error(500, "Snippet '$name' does not exist!");
            echo "Snippet error<br/>\n";
        }
    }
    
    // Set page meta data
    public function set($key, $value) {
        $this->metaData[$key] = $value;
    }
    
    // Return page meta data
    public function get($key) {
        return $this->isExisting($key) ? $this->metaData[$key] : "";
    }

    // Return page meta data, HTML encoded
    public function getHtml($key) {
        return htmlspecialchars($this->get($key));
    }
    
    // Return page meta data as language specific date
    public function getDate($key, $format = "") {
        if (!empty($format)) {
            $format = $this->yellow->text->get($format);
        } else {
            $format = $this->yellow->text->get("dateFormatMedium");
        }
        return $this->yellow->text->getDateFormatted(strtotime($this->get($key)), $format);
    }

    // Return page meta data as language specific date, HTML encoded
    public function getDateHtml($key, $format = "") {
        return htmlspecialchars($this->getDate($key, $format));
    }

    // Return page meta data as language specific date and relative to today
    public function getDateRelative($key, $format = "", $daysLimit = 0) {
        if (!empty($format)) {
            $format = $this->yellow->text->get($format);
        } else {
            $format = $this->yellow->text->get("dateFormatMedium");
        }
        return $this->yellow->text->getDateRelative(strtotime($this->get($key)), $format, $daysLimit);
    }
    
    // Return page meta data as language specific date and relative to today, HTML encoded
    public function getDateRelativeHtml($key, $format = "", $daysLimit = 0) {
        return htmlspecialchars($this->getDateRelative($key, $format, $daysLimit));
    }

    // Return page meta data as custom date
    public function getDateFormatted($key, $format) {
        return $this->yellow->text->getDateFormatted(strtotime($this->get($key)), $format);
    }
    
    // Return page meta data as custom date, HTML encoded
    public function getDateFormattedHtml($key, $format) {
        return htmlspecialchars($this->getDateFormatted($key, $format));
    }

    // Return page content, HTML encoded or raw format
    public function getContent($rawFormat = false, $sizeMax = 0) {
        if ($rawFormat) {
            $this->parseDataUpdate();
            $text = substrb($this->rawData, $this->metaDataOffsetBytes);
        } else {
            $this->parseContent($sizeMax);
            $text = $this->parserData;
        }
        return $sizeMax ? substrb($text, 0, $sizeMax) : $text;
    }
    
    // Return parent page, null if none
    public function getParent() {
        $parentLocation = $this->yellow->pages->getParentLocation($this->location);
        return $this->yellow->pages->find($parentLocation);
    }
    
    // Return top-level parent page, null if none
    public function getParentTop($homeFailback = true) {
        $parentTopLocation = $this->yellow->pages->getParentTopLocation($this->location);
        if (!$this->yellow->pages->find($parentTopLocation) && $homeFailback) {
            $parentTopLocation = $this->yellow->pages->getHomeLocation($this->location);
        }
        return $this->yellow->pages->find($parentTopLocation);
    }
    
    // Return page collection with pages on the same level
    public function getSiblings($showInvisible = false) {
        $parentLocation = $this->yellow->pages->getParentLocation($this->location);
        return $this->yellow->pages->getChildren($parentLocation, $showInvisible);
    }
    
    // Return page collection with child pages
    public function getChildren($showInvisible = false) {
        return $this->yellow->pages->getChildren($this->location, $showInvisible);
    }

    // Return page collection with sub pages
    public function getChildrenRecursive($showInvisible = false, $levelMax = 0) {
        return $this->yellow->pages->getChildrenRecursive($this->location, $showInvisible, $levelMax);
    }
    
    // Set page collection with additional pages
    public function setPages($pages) {
        $this->pageCollection = $pages;
    }

    // Return page collection with additional pages
    public function getPages() {
        return $this->pageCollection;
    }
    
    // Set related page
    public function setPage($key, $page) {
        $this->pageRelations[$key] = $page;
    }
    
    // Return related page
    public function getPage($key) {
        return !is_null($this->pageRelations[$key]) ? $this->pageRelations[$key] : $this;
    }
    
    // Return page base
    public function getBase($multiLanguage = false) {
        return $multiLanguage ? rtrim($this->base.$this->yellow->pages->getHomeLocation($this->location), "/") :  $this->base;
    }
    
    // Return page location
    public function getLocation($absoluteLocation = false) {
        return $absoluteLocation ? $this->base.$this->location : $this->location;
    }
    
    // Return page URL
    public function getUrl() {
        return $this->yellow->lookup->normaliseUrl($this->scheme, $this->address, $this->base, $this->location);
    }
    
    // Return page extra data
    public function getExtra($name) {
        $output = "";
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onParsePageExtra")) {
                $outputPlugin = $value["obj"]->onParsePageExtra($this, $name);
                if (!is_null($outputPlugin)) $output .= $outputPlugin;
            } elseif (method_exists($value["obj"], "onExtra")) {    //TODO: remove later, old event handler
                $outputPlugin = $value["obj"]->onExtra($name);
                if (!is_null($outputPlugin)) $output .= $outputPlugin;
            }
        }
        if ($name=="header") {
            if (is_file($this->yellow->config->get("assetDir").$this->get("theme").".css")) {
                $location = $this->yellow->config->get("serverBase").
                    $this->yellow->config->get("assetLocation").$this->get("theme").".css";
                $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"".htmlspecialchars($location)."\" />\n";
            }
            if (is_file($this->yellow->config->get("assetDir").$this->get("theme").".js")) {
                $location = $this->yellow->config->get("serverBase").
                    $this->yellow->config->get("assetLocation").$this->get("theme").".js";
                $output .= "<script type=\"text/javascript\" src=\"".htmlspecialchars($location)."\"></script>\n";
            }
            if (is_file($this->yellow->config->get("assetDir").$this->get("siteicon").".png")) {
                $location = $this->yellow->config->get("serverBase").
                    $this->yellow->config->get("assetLocation").$this->get("siteicon").".png";
                $contentType = $this->yellow->toolbox->getMimeContentType($location);
                $output .= "<link rel=\"icon\" type=\"$contentType\" href=\"".htmlspecialchars($location)."\" />\n";
                $output .= "<link rel=\"apple-touch-icon\" type=\"$contentType\" href=\"".htmlspecialchars($location)."\" />\n";
            }
        }
        return $output;
    }
    
    // Set page response output
    public function setOutput($output) {
        $this->outputData = $output;
    }
    
    // Set page response header
    public function setHeader($key, $value) {
        $this->headerData[$key] = $value;
    }
    
    // Return page response header
    public function getHeader($key) {
        return $this->isHeader($key) ? $this->headerData[$key] : "";
    }
    
    // Return page modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        $modified = strtotime($this->get("modified"));
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($modified) : $modified;
    }
    
    // Set last modification date, Unix time
    public function setLastModified($modified) {
        $this->lastModified = max($this->lastModified, $modified);
    }
    
    // Return last modification date, Unix time or HTTP format
    public function getLastModified($httpFormat = false) {
        $modified = max($this->lastModified, $this->getModified(), $this->yellow->config->getModified(),
            $this->yellow->text->getModified(), $this->yellow->plugins->getModified());
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($modified) : $modified;
    }
    
    // Return page status code, number or HTTP format
    public function getStatusCode($httpFormat = false) {
        $statusCode = $this->statusCode;
        if ($httpFormat) {
            $statusCode = $this->yellow->toolbox->getHttpStatusFormatted($statusCode);
            if ($this->isExisting("pageError")) $statusCode .= ": ".$this->get("pageError");
        }
        return $statusCode;
    }
    
    // Respond with error page
    public function error($statusCode, $pageError = "") {
        if (!$this->isExisting("pageError") && $statusCode>0) {
            $this->statusCode = $statusCode;
            $this->set("pageError", empty($pageError) ? "Template/snippet error!" : $pageError);
        }
    }
    
    // Respond with status code, no page content
    public function clean($statusCode, $location = "") {
        if (!$this->isExisting("pageClean") && $statusCode>0) {
            $this->statusCode = $statusCode;
            $this->lastModified = 0;
            $this->headerData = array();
            if (!empty($location)) {
                $this->setHeader("Location", $location);
                $this->setHeader("Cache-Control", "no-cache, must-revalidate");
            }
            $this->set("pageClean", (string)$statusCode);
        }
    }
    
    // Check if page is available
    public function isAvailable() {
        return $this->available;
    }
    
    // Check if page is visible
    public function isVisible() {
        return $this->visible;
    }

    // Check if page is within current HTTP request
    public function isActive() {
        return $this->active;
    }
    
    // Check if page is cacheable
    public function isCacheable() {
        return $this->cacheable;
    }

    // Check if page with error
    public function isError() {
        return $this->statusCode>=400;
    }
    
    // Check if response header exists
    public function isHeader($key) {
        return !is_null($this->headerData[$key]);
    }
    
    // Check if page meta data exists
    public function isExisting($key) {
        return !is_null($this->metaData[$key]);
    }
    
    // Check if related page exists
    public function isPage($key) {
        return !is_null($this->pageRelations[$key]);
    }
}

class YellowDataCollection extends ArrayObject {
    public function __construct() {
        parent::__construct(array());
    }
    
    // Return array element
    public function offsetGet($key) {
        if (is_string($key)) $key = lcfirst($key);
        return parent::offsetGet($key);
    }
    
    // Set array element
    public function offsetSet($key, $value) {
        if (is_string($key)) $key = lcfirst($key);
        parent::offsetSet($key, $value);
    }
    
    // Remove array element
    public function offsetUnset($key) {
        if (is_string($key)) $key = lcfirst($key);
        parent::offsetUnset($key);
    }
    
    // Check if array element exists
    public function offsetExists($key) {
        if (is_string($key)) $key = lcfirst($key);
        return parent::offsetExists($key);
    }
}

class YellowPageCollection extends ArrayObject {
    public $yellow;                 //access to API
    public $filterValue;            //current page filter value
    public $paginationNumber;       //current page number in pagination
    public $paginationCount;        //highest page number in pagination
    
    public function __construct($yellow) {
        parent::__construct(array());
        $this->yellow = $yellow;
    }
    
    // Filter page collection by meta data
    public function filter($key, $value, $exactMatch = true) {
        $array = array();
        $value = strreplaceu(" ", "-", strtoloweru($value));
        $valueLength = strlenu($value);
        $this->filterValue = "";
        foreach ($this->getArrayCopy() as $page) {
            if ($page->isExisting($key)) {
                foreach (preg_split("/\s*,\s*/", $page->get($key)) as $pageValue) {
                    $pageValueLength = $exactMatch ? strlenu($pageValue) : $valueLength;
                    if ($value==substru(strreplaceu(" ", "-", strtoloweru($pageValue)), 0, $pageValueLength)) {
                        if (empty($this->filterValue)) $this->filterValue = substru($pageValue, 0, $pageValueLength);
                        array_push($array, $page);
                        break;
                    }
                }
            }
        }
        $this->exchangeArray($array);
        return $this;
    }
    
    // Filter page collection by file name
    public function match($regex = "/.*/") {
        $array = array();
        foreach ($this->getArrayCopy() as $page) {
            if (preg_match($regex, $page->fileName)) array_push($array, $page);
        }
        $this->exchangeArray($array);
        return $this;
    }
    
    // Sort page collection by meta data
    public function sort($key, $ascendingOrder = true) {
        $array = $this->getArrayCopy();
        foreach ($array as $page) {
            $page->set("sortindex", ++$i);
        }
        $callback = function ($a, $b) use ($key, $ascendingOrder) {
            $result = $ascendingOrder ?
                strnatcasecmp($a->get($key), $b->get($key)) :
                strnatcasecmp($b->get($key), $a->get($key));
            return $result==0 ? $a->get("sortindex") - $b->get("sortindex") : $result;
        };
        usort($array, $callback);
        $this->exchangeArray($array);
        return $this;
    }
    
    // Sort page collection by meta data similarity
    public function similar($page, $ascendingOrder = false) {
        $location = $page->location;
        $keywords = $this->yellow->toolbox->createTextKeywords($page->get("title"));
        $keywords .= ",".$page->get("tag").",".$page->get("author");
        $tokens = array_unique(array_filter(preg_split("/\s*,\s*/", $keywords), "strlen"));
        if (!empty($tokens)) {
            $array = array();
            foreach ($this->getArrayCopy() as $page) {
                $searchScore = 0;
                foreach ($tokens as $token) {
                    if (stristr($page->get("title"), $token)) $searchScore += 10;
                    if (stristr($page->get("tag"), $token)) $searchScore += 5;
                    if (stristr($page->get("author"), $token)) $searchScore += 2;
                }
                if ($page->location!=$location) {
                    $page->set("searchscore", $searchScore);
                    array_push($array, $page);
                }
            }
            $this->exchangeArray($array);
            $this->sort("modified", $ascendingOrder)->sort("searchscore", $ascendingOrder);
        }
        return $this;
    }

    // Merge page collection
    public function merge($input) {
        $this->exchangeArray(array_merge($this->getArrayCopy(), (array)$input));
        return $this;
    }
    
    // Append to end of page collection
    public function append($page) {
        parent::append($page);
        return $this;
    }
    
    // Prepend to start of page collection
    public function prepend($page) {
        $array = $this->getArrayCopy();
        array_unshift($array, $page);
        $this->exchangeArray($array);
        return $this;
    }
    
    // Limit the number of pages in page collection
    public function limit($pagesMax) {
        $this->exchangeArray(array_slice($this->getArrayCopy(), 0, $pagesMax));
        return $this;
    }
    
    // Reverse page collection
    public function reverse() {
        $this->exchangeArray(array_reverse($this->getArrayCopy()));
        return $this;
    }
    
    // Randomize page collection
    public function shuffle() {
        $array = $this->getArrayCopy();
        shuffle($array);
        $this->exchangeArray($array);
        return $this;
    }

    // Paginate page collection
    public function pagination($limit, $reverse = true) {
        $this->paginationNumber = 1;
        $this->paginationCount = ceil($this->count() / $limit);
        $pagination = $this->yellow->config->get("contentPagination");
        if (isset($_REQUEST[$pagination])) $this->paginationNumber = intval($_REQUEST[$pagination]);
        if ($this->paginationNumber>$this->paginationCount) $this->paginationNumber = 0;
        if ($this->paginationNumber>=1) {
            $array = $this->getArrayCopy();
            if ($reverse) $array = array_reverse($array);
            $this->exchangeArray(array_slice($array, ($this->paginationNumber - 1) * $limit, $limit));
        }
        return $this;
    }
    
    // Return current page number in pagination
    public function getPaginationNumber() {
        return $this->paginationNumber;
    }
    
    // Return highest page number in pagination
    public function getPaginationCount() {
        return $this->paginationCount;
    }
    
    // Return location for a page in pagination
    public function getPaginationLocation($absoluteLocation = true, $pageNumber = 1) {
        if ($pageNumber>=1 && $pageNumber<=$this->paginationCount) {
            $pagination = $this->yellow->config->get("contentPagination");
            $location = $this->yellow->page->getLocation($absoluteLocation);
            $locationArgs = $this->yellow->toolbox->getLocationArgsNew(
                $pageNumber>1 ? "$pagination:$pageNumber" : "$pagination:", $pagination);
        }
        return $location.$locationArgs;
    }
    
    // Return location for previous page in pagination
    public function getPaginationPrevious($absoluteLocation = true) {
        $pageNumber = $this->paginationNumber-1;
        return $this->getPaginationLocation($absoluteLocation, $pageNumber);
    }
    
    // Return location for next page in pagination
    public function getPaginationNext($absoluteLocation = true) {
        $pageNumber = $this->paginationNumber+1;
        return $this->getPaginationLocation($absoluteLocation, $pageNumber);
    }
    
    // Return current page number in collection
    public function getPageNumber($page) {
        $pageNumber = 0;
        foreach ($this->getIterator() as $key=>$value) {
            if ($page->getLocation()==$value->getLocation()) {
                $pageNumber = $key+1;
                break;
            }
        }
        return $pageNumber;
    }
    
    // Return page in collection, null if none
    public function getPage($pageNumber = 1) {
        return ($pageNumber>=1 && $pageNumber<=$this->count()) ? $this->offsetGet($pageNumber-1) : null;
    }
    
    // Return previous page in collection, null if none
    public function getPagePrevious($page) {
        $pageNumber = $this->getPageNumber($page)-1;
        return $this->getPage($pageNumber);
    }
    
    // Return next page in collection, null if none
    public function getPageNext($page) {
        $pageNumber = $this->getPageNumber($page)+1;
        return $this->getPage($pageNumber);
    }
    
    // Return current page filter
    public function getFilter() {
        return $this->filterValue;
    }
    
    // Return page collection modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        $modified = 0;
        foreach ($this->getIterator() as $page) {
            $modified = max($modified, $page->getModified());
        }
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($modified) : $modified;
    }
    
    // Check if there is a pagination
    public function isPagination() {
        return $this->paginationCount>1;
    }
}

class YellowPages {
    public $yellow;         //access to API
    public $pages;          //scanned pages
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->pages = array();
    }
    
    // Scan file system on demand
    public function scanLocation($location) {
        if (is_null($this->pages[$location])) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowPages::scanLocation location:$location<br/>\n";
            $this->pages[$location] = array();
            $scheme = $this->yellow->page->scheme;
            $address = $this->yellow->page->address;
            $base = $this->yellow->page->base;
            if (empty($location)) {
                $rootLocations = $this->yellow->lookup->findRootLocations();
                foreach ($rootLocations as $rootLocation) {
                    list($rootLocation, $fileName) = explode(" ", $rootLocation, 2);
                    $page = new YellowPage($this->yellow);
                    $page->setRequestInformation($scheme, $address, $base, $rootLocation, $fileName);
                    $page->parseData("", false, 0);
                    array_push($this->pages[$location], $page);
                }
            } else {
                $fileNames = $this->yellow->lookup->findChildrenFromLocation($location);
                foreach ($fileNames as $fileName) {
                    $page = new YellowPage($this->yellow);
                    $page->setRequestInformation($scheme, $address, $base,
                        $this->yellow->lookup->findLocationFromFile($fileName), $fileName);
                    $page->parseData($this->yellow->toolbox->readFile($fileName, 4096), false, 0);
                    if (strlenb($page->rawData)<4096) $page->statusCode = 200;
                    array_push($this->pages[$location], $page);
                }
            }
        }
        return $this->pages[$location];
    }

    // Return page from file system, null if not found
    public function find($location, $absoluteLocation = false) {
        if ($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
        foreach ($this->scanLocation($this->getParentLocation($location)) as $page) {
            if ($page->location==$location) {
                if (!$this->yellow->lookup->isRootLocation($page->location)) {
                    $found = true;
                    break;
                }
            }
        }
        return $found ? $page : null;
    }
    
    // Return page collection with all pages
    public function index($showInvisible = false, $multiLanguage = false, $levelMax = 0) {
        $rootLocation = $multiLanguage ? "" : $this->getRootLocation($this->yellow->page->location);
        return $this->getChildrenRecursive($rootLocation, $showInvisible, $levelMax);
    }
    
    // Return page collection with top-level navigation
    public function top($showInvisible = false) {
        $rootLocation = $this->getRootLocation($this->yellow->page->location);
        return $this->getChildren($rootLocation, $showInvisible);
    }
    
    // Return page collection with path ancestry
    public function path($location, $absoluteLocation = false) {
        $pages = new YellowPageCollection($this->yellow);
        if ($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
        if ($page = $this->find($location)) {
            $pages->prepend($page);
            for (; $parent = $page->getParent(); $page=$parent) {
                $pages->prepend($parent);
            }
            $home = $this->find($this->getHomeLocation($page->location));
            if ($home && $home->location!=$page->location) $pages->prepend($home);
        }
        return $pages;
    }
    
    // Return page collection with multiple languages
    public function multi($location, $absoluteLocation = false, $showInvisible = false) {
        $pages = new YellowPageCollection($this->yellow);
        if ($absoluteLocation) $location = substru($location, strlenu($this->yellow->page->base));
        $locationEnd = substru($location, strlenu($this->getRootLocation($location)) - 4);
        foreach ($this->scanLocation("") as $page) {
            if ($content = $this->find(substru($page->location, 4).$locationEnd)) {
                if ($content->isAvailable() && ($content->isVisible() || $showInvisible)) {
                    if (!$this->yellow->lookup->isRootLocation($content->location)) $pages->append($content);
                }
            }
        }
        return $pages;
    }
    
    // Return page collection that's empty
    public function clean() {
        return new YellowPageCollection($this->yellow);
    }
    
    // Return languages in multi language mode
    public function getLanguages($showInvisible = false) {
        $languages = array();
        foreach ($this->scanLocation("") as $page) {
            if ($page->isAvailable() && ($page->isVisible() || $showInvisible)) array_push($languages, $page->get("language"));
        }
        return $languages;
    }
    
    // Return child pages
    public function getChildren($location, $showInvisible = false) {
        $pages = new YellowPageCollection($this->yellow);
        foreach ($this->scanLocation($location) as $page) {
            if ($page->isAvailable() && ($page->isVisible() || $showInvisible)) {
                if (!$this->yellow->lookup->isRootLocation($page->location) && is_readable($page->fileName)) $pages->append($page);
            }
        }
        return $pages;
    }
    
    // Return sub pages
    public function getChildrenRecursive($location, $showInvisible = false, $levelMax = 0) {
        --$levelMax;
        $pages = new YellowPageCollection($this->yellow);
        foreach ($this->scanLocation($location) as $page) {
            if ($page->isAvailable() && ($page->isVisible() || $showInvisible)) {
                if (!$this->yellow->lookup->isRootLocation($page->location) && is_readable($page->fileName)) $pages->append($page);
                if (!$this->yellow->lookup->isFileLocation($page->location) && $levelMax!=0) {
                    $pages->merge($this->getChildrenRecursive($page->location, $showInvisible, $levelMax));
                }
            }
        }
        return $pages;
    }
    
    // Return root location
    public function getRootLocation($location) {
        $rootLocation = "root/";
        if ($this->yellow->config->get("multiLanguageMode")) {
            foreach ($this->scanLocation("") as $page) {
                $token = substru($page->location, 4);
                if ($token!="/" && substru($location, 0, strlenu($token))==$token) {
                    $rootLocation = "root$token";
                    break;
                }
            }
        }
        return $rootLocation;
    }

    // Return home location
    public function getHomeLocation($location) {
        return substru($this->getRootLocation($location), 4);
    }
    
    // Return parent location
    public function getParentLocation($location) {
        $token = rtrim(substru($this->getRootLocation($location), 4), "/");
        if (preg_match("#^($token.*\/).+?$#", $location, $matches)) {
            if ($matches[1]!="$token/" || $this->yellow->lookup->isFileLocation($location)) $parentLocation = $matches[1];
        }
        if (empty($parentLocation)) $parentLocation = "root$token/";
        return $parentLocation;
    }
    
    // Return top-level location
    public function getParentTopLocation($location) {
        $token = rtrim(substru($this->getRootLocation($location), 4), "/");
        if (preg_match("#^($token.+?\/)#", $location, $matches)) $parentTopLocation = $matches[1];
        if (empty($parentTopLocation)) $parentTopLocation = "$token/";
        return $parentTopLocation;
    }
}
    
class YellowFiles {
    public $yellow;     //access to API
    public $files;      //scanned files
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->files = array();
    }

    // Scan file system on demand
    public function scanLocation($location) {
        if (is_null($this->files[$location])) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowFiles::scanLocation location:$location<br/>\n";
            $this->files[$location] = array();
            $scheme = $this->yellow->page->scheme;
            $address = $this->yellow->page->address;
            $base = $this->yellow->config->get("serverBase");
            if (empty($location)) {
                $fileNames = array($this->yellow->config->get("mediaDir"));
            } else {
                $fileNames = array();
                $path = substru($location, 1);
                foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, true) as $entry) {
                    array_push($fileNames, $entry."/");
                }
                foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, false, true) as $entry) {
                    array_push($fileNames, $entry);
                }
            }
            foreach ($fileNames as $fileName) {
                $file = new YellowPage($this->yellow);
                $file->setRequestInformation($scheme, $address, $base, "/".$fileName, $fileName);
                $file->parseData(null, false, 0);
                array_push($this->files[$location], $file);
            }
        }
        return $this->files[$location];
    }
    
    // Return page with media file information, null if not found
    public function find($location, $absoluteLocation = false) {
        if ($absoluteLocation) $location = substru($location, strlenu($this->yellow->config->get("serverBase")));
        foreach ($this->scanLocation($this->getParentLocation($location)) as $file) {
            if ($file->location==$location) {
                if ($this->yellow->lookup->isFileLocation($file->location)) {
                    $found = true;
                    break;
                }
            }
        }
        return $found ? $file : null;
    }
    
    // Return page collection with all media files
    public function index($showInvisible = false, $multiPass = false, $levelMax = 0) {
        return $this->getChildrenRecursive("", $showInvisible, $levelMax);
    }
    
    // Return page collection that's empty
    public function clean() {
        return new YellowPageCollection($this->yellow);
    }
    
    // Return child files
    public function getChildren($location, $showInvisible = false) {
        $files = new YellowPageCollection($this->yellow);
        foreach ($this->scanLocation($location) as $file) {
            if ($file->isAvailable() && ($file->isVisible() || $showInvisible)) {
                if ($this->yellow->lookup->isFileLocation($file->location)) $files->append($file);
            }
        }
        return $files;
    }
    
    // Return sub files
    public function getChildrenRecursive($location, $showInvisible = false, $levelMax = 0) {
        --$levelMax;
        $files = new YellowPageCollection($this->yellow);
        foreach ($this->scanLocation($location) as $file) {
            if ($file->isAvailable() && ($file->isVisible() || $showInvisible)) {
                if ($this->yellow->lookup->isFileLocation($file->location)) $files->append($file);
                if (!$this->yellow->lookup->isFileLocation($file->location) && $levelMax!=0) {
                    $files->merge($this->getChildrenRecursive($file->location, $showInvisible, $levelMax));
                }
            }
        }
        return $files;
    }
    
    // Return home location
    public function getHomeLocation($location) {
        return $this->yellow->config->get("mediaLocation");
    }

    // Return parent location
    public function getParentLocation($location) {
        $token = rtrim($this->yellow->config->get("mediaLocation"), "/");
        if (preg_match("#^($token.*\/).+?$#", $location, $matches)) {
            if ($matches[1]!="$token/" || $this->yellow->lookup->isFileLocation($location)) $parentLocation = $matches[1];
        }
        if (empty($parentLocation)) $parentLocation = "";
        return $parentLocation;
    }
    
    // Return top-level location
    public function getParentTopLocation($location) {
        $token = rtrim($this->yellow->config->get("mediaLocation"), "/");
        if (preg_match("#^($token.+?\/)#", $location, $matches)) $parentTopLocation = $matches[1];
        if (empty($parentTopLocation)) $parentTopLocation = "$token/";
        return $parentTopLocation;
    }
}

class YellowPlugins {
    public $yellow;     //access to API
    public $modified;   //plugin modification date
    public $plugins;    //registered plugins

    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->plugins = array();
    }
    
    // Load plugins
    public function load($path = "") {
        $path = empty($path) ? $this->yellow->config->get("pluginDir") : $path;
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.php$/", true, false) as $entry) {
            if (defined("DEBUG") && DEBUG>=3) echo "YellowPlugins::load file:$entry<br/>\n";
            $this->modified = max($this->modified, filemtime($entry));
            global $yellow;
            require_once($entry);
            $name = $this->yellow->lookup->normaliseName(basename($entry), true, true, true);
            $this->register($name, "Yellow".ucfirst($name));
        }
        $callback = function ($a, $b) {
            return $a["priority"] - $b["priority"];
        };
        uasort($this->plugins, $callback);
        foreach ($this->plugins as $key=>$value) {
            if (method_exists($this->plugins[$key]["obj"], "onLoad")) $this->plugins[$key]["obj"]->onLoad($this->yellow);
        }
    }
    
    // Register plugin
    public function register($name, $plugin, $obsoleteVersion = 0, $obsoletePriority = 0) {
        if (!$this->isExisting($name) && class_exists($plugin)) {
            $this->plugins[$name] = array();
            $this->plugins[$name]["obj"] = new $plugin;
            $this->plugins[$name]["plugin"] = $plugin;
            $this->plugins[$name]["version"] = defined("$plugin::VERSION") ? $plugin::VERSION : 0;
            $this->plugins[$name]["priority"] = defined("$plugin::PRIORITY") ? $plugin::PRIORITY : count($this->plugins) + 10;
        }
    }
    
    // Return plugin
    public function get($name) {
        return $this->plugins[$name]["obj"];
    }
    
    // Return plugin version
    public function getData() {
        $data = array();
        $data["YellowCore"] = YellowCore::VERSION;
        foreach ($this->plugins as $key=>$value) {
            if (empty($value["plugin"]) || empty($value["version"])) continue;
            $data[$value["plugin"]] = $value["version"];
        }
        uksort($data, "strnatcasecmp");
        return $data;
    }
    
    // Return plugin modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }
    
    // Check if plugin exists
    public function isExisting($name) {
        return !is_null($this->plugins[$name]);
    }
}

class YellowThemes {
    public $yellow;     //access to API
    public $modified;   //theme modification date
    public $themes;     //themes

    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->themes = array();
    }
    
    // Load themes
    public function load($path = "") {
        $path = empty($path) ? $this->yellow->config->get("assetDir") : $path;
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.php$/", true, false) as $entry) {
            if (defined("DEBUG") && DEBUG>=3) echo "YellowThemes::load file:$entry<br/>\n";
            $this->modified = max($this->modified, filemtime($entry));
            global $yellow;
            require_once($entry);
            $name = $this->yellow->lookup->normaliseName(basename($entry), true, true, true);
            $this->register($name, "YellowTheme".ucfirst($name));
        }
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.css$/", true, false) as $entry) {
            if (defined("DEBUG") && DEBUG>=3) echo "YellowThemes::load file:$entry<br/>\n";
            $this->modified = max($this->modified, filemtime($entry));
            $name = $this->yellow->lookup->normaliseName(basename($entry), true, true, true);
            if (substru($name, 0, 7)!="bundle-") $this->register($name, "stdClass");
        }
        $callback = function ($a, $b) {
            return $a["priority"] - $b["priority"];
        };
        uasort($this->themes, $callback);
        foreach ($this->themes as $key=>$value) {
            if (method_exists($this->themes[$key]["obj"], "onLoad")) $this->themes[$key]["obj"]->onLoad($this->yellow);
        }
    }
    
    // Register theme
    public function register($name, $theme, $obsoleteVersion = 0, $obsoletePriority = 0) {
        if (!$this->isExisting($name) && class_exists($theme)) {
            $this->themes[$name] = array();
            $this->themes[$name]["obj"] = new $theme;
            $this->themes[$name]["theme"] = $theme;
            $this->themes[$name]["version"] = defined("$theme::VERSION") ? $theme::VERSION : 0;
            $this->themes[$name]["priority"] = defined("$theme::PRIORITY") ? $theme::PRIORITY : count($this->themes) + 10;
        }
    }
    
    // Return theme
    public function get($name) {
        return $this->theme[$name]["obj"];
    }
    
    // Return theme version
    public function getData() {
        $data = array();
        foreach ($this->themes as $key=>$value) {
            if (empty($value["theme"]) || empty($value["version"])) continue;
            $data[$value["theme"]] = $value["version"];
        }
        uksort($data, "strnatcasecmp");
        return $data;
    }
    
    // Return theme modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }

    // Check if theme exists
    public function isExisting($name) {
        return !is_null($this->themes[$name]);
    }
}
    
class YellowConfig {
    public $yellow;         //access to API
    public $modified;       //configuration modification date
    public $config;         //configuration
    public $configDefaults; //configuration defaults
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->config = new YellowDataCollection();
        $this->configDefaults = new YellowDataCollection();
    }
    
    // Load configuration from file
    public function load($fileName) {
        if (defined("DEBUG") && DEBUG>=2) echo "YellowConfig::load file:$fileName<br/>\n";
        $this->modified = $this->yellow->toolbox->getFileModified($fileName);
        $fileData = $this->yellow->toolbox->readFile($fileName);
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            if (preg_match("/^\#/", $line)) continue;
            preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
            if (!empty($matches[1]) && !strempty($matches[2])) {
                $this->set($matches[1], $matches[2]);
                if (defined("DEBUG") && DEBUG>=3) echo "YellowConfig::load $matches[1]:$matches[2]<br/>\n";
            }
        }
    }
    
    // Save configuration to file
    public function save($fileName, $config) {
        $configNew = new YellowDataCollection();
        foreach ($config as $key=>$value) {
            if (!empty($key) && !strempty($value)) {
                $this->set($key, $value);
                $configNew[$key] = $value;
            }
        }
        $this->modified = time();
        $fileData = $this->yellow->toolbox->readFile($fileName);
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
            if (!empty($matches[1]) && !is_null($configNew[$matches[1]])) {
                $fileDataNew .= "$matches[1]: ".$configNew[$matches[1]]."\n";
                unset($configNew[$matches[1]]);
            } else {
                $fileDataNew .= $line;
            }
        }
        foreach ($configNew as $key=>$value) {
            $fileDataNew .= ucfirst($key).": $value\n";
        }
        return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
    }
    
    // Set default configuration
    public function setDefault($key, $value) {
        $this->configDefaults[$key] = $value;
    }
    
    // Set configuration
    public function set($key, $value) {
        $this->config[$key] = $value;
    }
    
    // Return configuration
    public function get($key) {
        if (!is_null($this->config[$key])) {
            $value = $this->config[$key];
        } else {
            $value = !is_null($this->configDefaults[$key]) ? $this->configDefaults[$key] : "";
        }
        return $value;
    }
    
    // Return configuration, HTML encoded
    public function getHtml($key) {
        return htmlspecialchars($this->get($key));
    }
    
    // Return configuration strings
    public function getData($filterStart = "", $filterEnd = "") {
        $config = array();
        if (empty($filterStart) && empty($filterEnd)) {
            $config = array_merge($this->configDefaults->getArrayCopy(), $this->config->getArrayCopy());
        } else {
            foreach (array_merge($this->configDefaults->getArrayCopy(), $this->config->getArrayCopy()) as $key=>$value) {
                if (!empty($filterStart) && substru($key, 0, strlenu($filterStart))==$filterStart) $config[$key] = $value;
                if (!empty($filterEnd) && substru($key, -strlenu($filterEnd))==$filterEnd) $config[$key] = $value;
            }
        }
        return $config;
    }
    
    // Return configuration modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }
    
    // Check if configuration exists
    public function isExisting($key) {
        return !is_null($this->config[$key]);
    }
}

class YellowText {
    public $yellow;         //access to API
    public $modified;       //text modification date
    public $text;           //text
    public $language;       //current language
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->text = new YellowDataCollection();
    }
    
    // Load text strings from file
    public function load($fileName, $languageDefault) {
        $path = dirname($fileName);
        $regex = "/^".basename($fileName)."$/";
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowText::load file:$entry<br/>\n";
            $language = $languageDefault;
            $this->modified = max($this->modified, filemtime($entry));
            $fileData = $this->yellow->toolbox->readFile($entry);
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                if (preg_match("/^\#/", $line)) continue;
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (lcfirst($matches[1])=="language" && !strempty($matches[2])) $language = $matches[2];
                if (!empty($language) && !empty($matches[1]) && !strempty($matches[2])) {
                    $this->setText($matches[1], $matches[2], $language);
                    if (defined("DEBUG") && DEBUG>=3) echo "YellowText::load $matches[1]:$matches[2]<br/>\n";
                }
            }
        }
    }
    
    // Set current language
    public function setLanguage($language) {
        $this->language = $language;
    }
    
    // Set text string for specific language
    public function setText($key, $value, $language) {
        if (is_null($this->text[$language])) $this->text[$language] = new YellowDataCollection();
        $this->text[$language][$key] = $value;
    }
    
    // Return text string
    public function get($key) {
        return $this->getText($key, $this->language);
    }
    
    // Return text string, HTML encoded
    public function getHtml($key) {
        return htmlspecialchars($this->getText($key, $this->language));
    }
    
    // Return text string for specific language
    public function getText($key, $language) {
        return $this->isExisting($key, $language) ? $this->text[$language][$key] : "[$key]";
    }
    
    // Return text string for specific language, HTML encoded
    public function getTextHtml($key, $language) {
        return htmlspecialchars($this->getText($key, $language));
    }
    
    // Return text strings
    public function getData($filterStart = "", $language = "") {
        $text = array();
        if (empty($language)) $language = $this->language;
        if ($this->isLanguage($language)) {
            if (empty($filterStart)) {
                $text = $this->text[$language];
            } else {
                foreach ($this->text[$language] as $key=>$value) {
                    if (substru($key, 0, strlenu($filterStart))==$filterStart) $text[$key] = $value;
                }
            }
        }
        return $text;
    }
    
    // Return human readable date, custom date
    public function getDateFormatted($timestamp, $format) {
        $dateMonths = preg_split("/\s*,\s*/", $this->get("dateMonths"));
        $dateWeekdays = preg_split("/\s*,\s*/", $this->get("dateWeekdays"));
        $month = $dateMonths[date("n", $timestamp) - 1];
        $weekday = $dateWeekdays[date("N", $timestamp) - 1];
        $timeZone = $this->yellow->config->get("timezone");
        $timeZoneHelper = new DateTime(null, new DateTimeZone($timeZone));
        $timeZoneOffset = $timeZoneHelper->getOffset();
        $timeZoneAbbreviation = "GMT".($timeZoneOffset<0 ? "-" : "+").abs(intval($timeZoneOffset/3600));
        $format = preg_replace("/(?<!\\\)F/", addcslashes($month, "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)M/", addcslashes(substru($month, 0, 3), "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)D/", addcslashes(substru($weekday, 0, 3), "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)l/", addcslashes($weekday, "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)T/", addcslashes($timeZoneAbbreviation, "A..Za..z"), $format);
        return date($format, $timestamp);
    }
    
    // Return human readable date, relative to today
    public function getDateRelative($timestamp, $format, $daysLimit) {
        $timeDifference = time() - $timestamp;
        $days = abs(intval($timeDifference / 86400));
        if ($days<=$daysLimit || $daysLimit==0) {
            $tokens = preg_split("/\s*,\s*/", $this->get($timeDifference>=0 ? "datePast" : "dateFuture"));
            if ($days==0) {
                $output = $tokens[0];
            } elseif ($days==1) {
                $output = $tokens[1];
            } elseif ($days>=2 && $days<=29) {
                $output = preg_replace("/@x/i", $days, $tokens[2]);
            } elseif ($days>=30 && $days<=59) {
                $output = $tokens[3];
            } elseif ($days>=60 && $days<=364) {
                $output = preg_replace("/@x/i", intval($days/30), $tokens[4]);
            } elseif ($days>=365 && $days<=729) {
                $output = $tokens[5];
            } else {
                $output = preg_replace("/@x/i", intval($days/365.25), $tokens[6]);
            }
        } else {
            $output = $this->getDateFormatted($timestamp, $format);
        }
        return $output;
    }
    
    // Return languages
    public function getLanguages() {
        $languages = array();
        foreach ($this->text as $key=>$value) {
            array_push($languages, $key);
        }
        return $languages;
    }
    
    // Return text modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }

    // Normalise date into known format
    public function normaliseDate($text) {
        if (preg_match("/^\d+\-\d+$/", $text)) {
            $output = $this->getDateFormatted(strtotime($text), $this->get("dateFormatShort"));
        } elseif (preg_match("/^\d+\-\d+\-\d+$/", $text)) {
            $output = $this->getDateFormatted(strtotime($text), $this->get("dateFormatMedium"));
        } elseif (preg_match("/^\d+\-\d+\-\d+ \d+\:\d+$/", $text)) {
            $output = $this->getDateFormatted(strtotime($text), $this->get("dateFormatLong"));
        } else {
            $output = $text;
        }
        return $output;
    }
    
    // Check if language exists
    public function isLanguage($language) {
        return !is_null($this->text[$language]);
    }
    
    // Check if text string exists
    public function isExisting($key, $language = "") {
        if (empty($language)) $language = $this->language;
        return !is_null($this->text[$language]) && !is_null($this->text[$language][$key]);
    }
}

class YellowLookup {
    public $yellow;             //access to API
    public $requestHandler;     //request handler name
    public $commandHandler;     //command handler name
    public $snippetArgs;        //snippet arguments
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
    }

    // Load file system information
    public function load() {
        list($pathRoot, $pathHome) = $this->detectFileSystem();
        $this->yellow->config->set("contentRootDir", $pathRoot);
        $this->yellow->config->set("contentHomeDir", $pathHome);
        date_default_timezone_set($this->yellow->config->get("timezone"));
    }
    
    // Detect file system
    public function detectFileSystem() {
        $path = $this->yellow->config->get("contentDir");
        $pathRoot = $this->yellow->config->get("contentRootDir");
        $pathHome = $this->yellow->config->get("contentHomeDir");
        if (!$this->yellow->config->get("multiLanguageMode")) $pathRoot = "";
        if (!empty($pathRoot)) {
            $token = $root = rtrim($pathRoot, "/");
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry) {
                if (empty($firstRoot)) $firstRoot = $token = $entry;
                if ($this->normaliseToken($entry)==$root) {
                    $token = $entry;
                    break;
                }
            }
            $pathRoot = $this->normaliseToken($token)."/";
            $path .= "$firstRoot/";
        }
        if (!empty($pathHome)) {
            $token = $home = rtrim($pathHome, "/");
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry) {
                if (empty($firstHome)) $firstHome = $token = $entry;
                if ($this->normaliseToken($entry)==$home) {
                    $token = $entry;
                    break;
                }
            }
            $pathHome = $this->normaliseToken($token)."/";
        }
        return array($pathRoot, $pathHome);
    }
    
    // Return root locations
    public function findRootLocations($includePath = true) {
        $locations = array();
        $pathBase = $this->yellow->config->get("contentDir");
        $pathRoot = $this->yellow->config->get("contentRootDir");
        if (!empty($pathRoot)) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($pathBase, "/.*/", true, true, false) as $entry) {
                $token = $this->normaliseToken($entry)."/";
                if ($token==$pathRoot) $token = "";
                array_push($locations, $includePath ? "root/$token $pathBase$entry/" : "root/$token");
                if (defined("DEBUG") && DEBUG>=2) echo "YellowLookup::findRootLocations root/$token<br/>\n";
            }
        } else {
            array_push($locations, $includePath ? "root/ $pathBase" : "root/");
        }
        return $locations;
    }
    
    // Return location from file path
    public function findLocationFromFile($fileName) {
        $location = "/";
        $pathBase = $this->yellow->config->get("contentDir");
        $pathRoot = $this->yellow->config->get("contentRootDir");
        $pathHome = $this->yellow->config->get("contentHomeDir");
        $fileDefault = $this->yellow->config->get("contentDefaultFile");
        $fileExtension = $this->yellow->config->get("contentExtension");
        if (substru($fileName, 0, strlenu($pathBase))==$pathBase) {
            $fileName = substru($fileName, strlenu($pathBase));
            $tokens = explode("/", $fileName);
            if (!empty($pathRoot)) {
                $token = $this->normaliseToken($tokens[0])."/";
                if ($token!=$pathRoot) $location .= $token;
                array_shift($tokens);
            }
            for ($i=0; $i<count($tokens)-1; ++$i) {
                $token = $this->normaliseToken($tokens[$i])."/";
                if ($i || $token!=$pathHome) $location .= $token;
            }
            $token = $this->normaliseToken($tokens[$i], $fileExtension);
            $fileFolder = $this->normaliseToken($tokens[$i-1], $fileExtension);
            if ($token!=$fileDefault && $token!=$fileFolder) {
                $location .= $this->normaliseToken($tokens[$i], $fileExtension, true);
            }
            $extension = ($pos = strrposu($fileName, ".")) ? substru($fileName, $pos) : "";
            if ($extension!=$fileExtension) $invalid = true;
        } else {
            $invalid = true;
        }
        if (defined("DEBUG") && DEBUG>=2) {
            $debug = ($invalid ? "INVALID" : $location)." <- $pathBase$fileName";
            echo "YellowLookup::findLocationFromFile $debug<br/>\n";
        }
        return $invalid ? "" : $location;
    }
    
    // Return file path from location
    public function findFileFromLocation($location, $directory = false) {
        $path = $this->yellow->config->get("contentDir");
        $pathRoot = $this->yellow->config->get("contentRootDir");
        $pathHome = $this->yellow->config->get("contentHomeDir");
        $fileDefault = $this->yellow->config->get("contentDefaultFile");
        $fileExtension = $this->yellow->config->get("contentExtension");
        $tokens = explode("/", $location);
        if ($this->isRootLocation($location)) {
            if (!empty($pathRoot)) {
                $token = (count($tokens)>2) ? $tokens[1] : rtrim($pathRoot, "/");
                $path .= $this->findFileDirectory($path, $token, "", true, true, $found, $invalid);
            }
        } else {
            if (!empty($pathRoot)) {
                if (count($tokens)>2) {
                    if ($this->normaliseToken($tokens[1])==$this->normaliseToken(rtrim($pathRoot, "/"))) $invalid = true;
                    $path .= $this->findFileDirectory($path, $tokens[1], "", true, false, $found, $invalid);
                    if ($found) array_shift($tokens);
                }
                if (!$found) {
                    $path .= $this->findFileDirectory($path, rtrim($pathRoot, "/"), "", true, true, $found, $invalid);
                }
            }
            if (count($tokens)>2) {
                if ($this->normaliseToken($tokens[1])==$this->normaliseToken(rtrim($pathHome, "/"))) $invalid = true;
                for ($i=1; $i<count($tokens)-1; ++$i) {
                    $path .= $this->findFileDirectory($path, $tokens[$i], "", true, true, $found, $invalid);
                }
            } else {
                $i = 1;
                $tokens[0] = rtrim($pathHome, "/");
                $path .= $this->findFileDirectory($path, $tokens[0], "", true, true, $found, $invalid);
            }
            if (!$directory) {
                if (!strempty($tokens[$i])) {
                    $token = $tokens[$i].$fileExtension;
                    $fileFolder = $tokens[$i-1].$fileExtension;
                    if ($token==$fileDefault || $token==$fileFolder) $invalid = true;
                    $path .= $this->findFileDirectory($path, $token, $fileExtension, false, true, $found, $invalid);
                } else {
                    $path .= $this->findFileDefault($path, $fileDefault, $fileExtension, false);
                }
                if (defined("DEBUG") && DEBUG>=2) {
                    $debug = "$location -> ".($invalid ? "INVALID" : $path);
                    echo "YellowLookup::findFileFromLocation $debug<br/>\n";
                }
            }
        }
        return $invalid ? "" : $path;
    }
    
    // Return file or directory that matches token
    public function findFileDirectory($path, $token, $fileExtension, $directory, $default, &$found, &$invalid) {
        if ($this->normaliseToken($token, $fileExtension)!=$token) $invalid = true;
        if (!$invalid) {
            $regex = "/^[\d\-\_\.]*".strreplaceu("-", ".", $token)."$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, false, $directory, false) as $entry) {
                if ($this->normaliseToken($entry, $fileExtension)==$token) {
                    $token = $entry;
                    $found = true;
                    break;
                }
            }
        }
        if ($directory) $token .= "/";
        return ($default || $found) ? $token : "";
    }
    
    // Return default file in directory
    public function findFileDefault($path, $fileDefault, $fileExtension, $includePath = true) {
        $token = $fileDefault;
        if (!is_file($path."/".$fileDefault)) {
            $fileFolder = $this->normaliseToken(basename($path), $fileExtension);
            $regex = "/^[\d\-\_\.]*($fileDefault|$fileFolder)$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry) {
                if ($this->normaliseToken($entry, $fileExtension)==$fileDefault) {
                    $token = $entry;
                    break;
                }
                if ($this->normaliseToken($entry, $fileExtension)==$fileFolder) {
                    $token = $entry;
                    break;
                }
            }
        }
        return $includePath ? "$path/$token" : $token;
    }
    
    // Return new file
    public function findFileNew($location, $filePrefix = "") {
        $fileName = $this->findFileFromLocation($location);
        if (!empty($filePrefix) && !empty($fileName)) {
            preg_match("/^([\d\-\_\.]*)(.*)$/", $filePrefix, $matches);
            $filePrefix = empty($matches[1]) ? "" : $matches[1]."-";
            $fileText = $this->normaliseName(basename($fileName), true, true);
            if (preg_match("/^[\d\-\_\.]*$/", $fileText) && !empty($filePrefix)) $filePrefix = "";
            $fileName = dirname($fileName)."/".$filePrefix.$fileText.$this->yellow->config->get("contentExtension");
        }
        if (!is_dir(dirname($fileName))) {
            $tokens = explode("/", $fileName);
            for ($i=0; $i<count($tokens)-1; ++$i) {
                if (!is_dir($path.$tokens[$i])) {
                    if (!preg_match("/^[\d\-\_\.]+(.*)$/", $tokens[$i])) {
                        $number = 1;
                        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^[\d\-\_\.]+(.*)$/", true, true, false) as $entry) {
                            if ($number!=1 && $number!=intval($entry)) break;
                            $number = intval($entry)+1;
                        }
                        $tokens[$i] = "$number-".$tokens[$i];
                    }
                    $tokens[$i] = $this->normaliseName($tokens[$i], false, false, true);
                }
                $path .= $tokens[$i]."/";
            }
            $fileName = $path.$tokens[$i];
        }
        return $fileName;
    }
    
    // Return children from location
    public function findChildrenFromLocation($location) {
        $fileNames = array();
        $fileDefault = $this->yellow->config->get("contentDefaultFile");
        $fileExtension = $this->yellow->config->get("contentExtension");
        if (!$this->isFileLocation($location)) {
            $path = $this->findFileFromLocation($location, true);
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry) {
                $token = $this->findFileDefault($path.$entry, $fileDefault, $fileExtension, false);
                array_push($fileNames, $path.$entry."/".$token);
            }
            if (!$this->isRootLocation($location)) {
                $fileFolder = $this->normaliseToken(basename($path), $fileExtension);
                $regex = "/^.*\\".$fileExtension."$/";
                foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry) {
                    if ($this->normaliseToken($entry, $fileExtension)==$fileDefault) continue;
                    if ($this->normaliseToken($entry, $fileExtension)==$fileFolder) continue;
                    array_push($fileNames, $path.$entry);
                }
            }
        }
        return $fileNames;
    }

    // Return language from file path
    public function findLanguageFromFile($fileName, $languageDefault) {
        $language = $languageDefault;
        $pathBase = $this->yellow->config->get("contentDir");
        $pathRoot = $this->yellow->config->get("contentRootDir");
        if (!empty($pathRoot)) {
            $fileName = substru($fileName, strlenu($pathBase));
            if (preg_match("/^(.+?)\//", $fileName, $matches)) $name = $this->normaliseToken($matches[1]);
            if (strlenu($name)==2) $language = $name;
        }
        return $language;
    }

    // Return file path from media location
    public function findFileFromMedia($location) {
        if ($this->isFileLocation($location)) {
            $mediaLocationLength = strlenu($this->yellow->config->get("mediaLocation"));
            if (substru($location, 0, $mediaLocationLength)==$this->yellow->config->get("mediaLocation")) {
                $fileName = $this->yellow->config->get("mediaDir").substru($location, 7);
            }
        }
        return $fileName;
    }
    
    // Return file path from system location
    public function findFileFromSystem($location) {
        if (preg_match("/\.(css|gif|ico|js|jpg|png|svg|txt|woff|woff2)$/", $location)) {
            $pluginLocationLength = strlenu($this->yellow->config->get("pluginLocation"));
            $themeLocationLength = strlenu($this->yellow->config->get("themeLocation"));
            if (substru($location, 0, $pluginLocationLength)==$this->yellow->config->get("pluginLocation")) {
                $fileName = $this->yellow->config->get("pluginDir").substru($location, $pluginLocationLength);
            } elseif (substru($location, 0, $themeLocationLength)==$this->yellow->config->get("themeLocation")) {
                $fileName = $this->yellow->config->get("themeDir").substru($location, $themeLocationLength);
            } elseif ($location=="/".$this->yellow->config->get("robotsFile")) {
                $fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("robotsFile");
            } elseif ($location=="/".$this->yellow->config->get("faviconFile")) {
                $fileName = $this->yellow->config->get("assetDir").$this->yellow->config->get("siteicon").".png";
            }
        }
        return $fileName;
    }
    
    // Return file path from cache if possible
    public function findFileFromCache($location, $fileName, $cacheable) {
        if ($cacheable) {
            $location .= $this->yellow->toolbox->getLocationArgs();
            $fileNameStatic = rtrim($this->yellow->config->get("cacheDir"), "/").$location;
            if (!$this->isFileLocation($location)) $fileNameStatic .= $this->yellow->config->get("staticDefaultFile");
            if (is_readable($fileNameStatic)) $fileName = $fileNameStatic;
        }
        return $fileName;
    }
    
    // Normalise file/directory token
    public function normaliseToken($text, $fileExtension = "", $removeExtension = false) {
        if (!empty($fileExtension)) $text = ($pos = strrposu($text, ".")) ? substru($text, 0, $pos) : $text;
        if (preg_match("/^[\d\-\_\.]+(.*)$/", $text, $matches) && !empty($matches[1])) $text = $matches[1];
        return preg_replace("/[^\pL\d\-\_]/u", "-", $text).($removeExtension ? "" : $fileExtension);
    }
    
    // Normalise name
    public function normaliseName($text, $removePrefix = false, $removeExtension = false, $filterStrict = false) {
        if ($removeExtension) $text = ($pos = strrposu($text, ".")) ? substru($text, 0, $pos) : $text;
        if ($removePrefix && preg_match("/^[\d\-\_\.]+(.*)$/", $text, $matches) && !empty($matches[1])) $text = $matches[1];
        if ($filterStrict) $text = strtoloweru($text);
        return preg_replace("/[^\pL\d\-\_]/u", "-", $text);
    }
    
    // Normalise array, make keys with same upper/lower case
    public function normaliseUpperLower($input) {
        $array = array();
        foreach ($input as $key=>$value) {
            if (empty($key) || strempty($value)) continue;
            $keySearch = strtoloweru($key);
            foreach ($array as $keyNew=>$valueNew) {
                if (strtoloweru($keyNew)==$keySearch) {
                    $key = $keyNew;
                    break;
                }
            }
            $array[$key] += $value;
        }
        return $array;
    }
    
    // Normalise location, make absolute location
    public function normaliseLocation($location, $pageLocation, $filterStrict = true) {
        if (!preg_match("/^\w+:/", trim(html_entity_decode($location, ENT_QUOTES, "UTF-8")))) {
            $pageBase = $this->yellow->page->base;
            $mediaBase = $this->yellow->config->get("serverBase").$this->yellow->config->get("mediaLocation");
            if (preg_match("/^\#/", $location)) {
                $location = $pageBase.$pageLocation.$location;
            } elseif (!preg_match("/^\//", $location)) {
                $location = $this->getDirectoryLocation($pageBase.$pageLocation).$location;
            } elseif (!preg_match("#^($pageBase|$mediaBase)#", $location)) {
                $location = $pageBase.$location;
            }
            $location = strreplaceu("/./", "/", $location);
            $location = strreplaceu(":", $this->yellow->toolbox->getLocationArgsSeparator(), $location);
        } else {
            if ($filterStrict && !preg_match("/^(http|https|ftp|mailto):/", $location)) $location = "error-xss-filter";
        }
        return $location;
    }
    
    // Normalise URL, make absolute URL
    public function normaliseUrl($scheme, $address, $base, $location, $filterStrict = true) {
        if (!preg_match("/^\w+:/", $location)) {
            $url = "$scheme://$address$base$location";
        } else {
            if ($filterStrict && !preg_match("/^(http|https|ftp|mailto):/", $location)) $location = "error-xss-filter";
            $url = $location;
        }
        return $url;
    }
    
    // Return URL information
    public function getUrlInformation($url) {
        if (preg_match("#^(\w+)://([^/]+)(.*)$#", rtrim($url, "/"), $matches)) {
            $scheme = $matches[1];
            $address = $matches[2];
            $base = $matches[3];
        }
        return array($scheme, $address, $base);
    }
    
    // Return directory location
    public function getDirectoryLocation($location) {
        return ($pos = strrposu($location, "/")) ? substru($location, 0, $pos+1) : "/";
    }
    
    // Check if location is specifying root
    public function isRootLocation($location) {
        return $location[0]!="/";
    }
    
    // Check if location is specifying file or directory
    public function isFileLocation($location) {
        return substru($location, -1, 1)!="/";
    }
    
    // Check if location can be redirected into directory
    public function isRedirectLocation($location) {
        $redirect = false;
        if ($this->isFileLocation($location)) {
            $redirect = is_dir($this->findFileFromLocation("$location/", true));
        } elseif ($location=="/") {
            $redirect = $this->yellow->config->get("multiLanguageMode");
        }
        return $redirect;
    }
    
    // Check if location contains nested directories
    public function isNestedLocation($location, $fileName, $checkHomeLocation = false) {
        $nested = false;
        if (!$checkHomeLocation || $location==$this->yellow->pages->getHomeLocation($location)) {
            $path = dirname($fileName);
            if (count($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false))) $nested = true;
        }
        return $nested;
    }
    
    // Check if location is visible
    public function isVisibleLocation($location, $fileName) {
        $visible = true;
        $pathBase = $this->yellow->config->get("contentDir");
        if (substru($fileName, 0, strlenu($pathBase))==$pathBase) {
            $fileName = substru($fileName, strlenu($pathBase));
            $tokens = explode("/", $fileName);
            for ($i=0; $i<count($tokens)-1; ++$i) {
                if (!preg_match("/^[\d\-\_\.]+(.*)$/", $tokens[$i])) {
                    $visible = false;
                    break;
                }
            }
        } else {
            $visible = false;
        }
        return $visible;
    }
    
    // Check if location is within current HTTP request
    public function isActiveLocation($location, $currentLocation) {
        if ($this->isFileLocation($location)) {
            $active = $currentLocation==$location;
        } else {
            if ($location==$this->yellow->pages->getHomeLocation($location)) {
                $active = $this->getDirectoryLocation($currentLocation)==$location;
            } else {
                $active = substru($currentLocation, 0, strlenu($location))==$location;
            }
        }
        return $active;
    }
    
    // Check if file is valid
    public function isValidFile($fileName) {
        $contentDirLength = strlenu($this->yellow->config->get("contentDir"));
        $mediaDirLength = strlenu($this->yellow->config->get("mediaDir"));
        $systemDirLength = strlenu($this->yellow->config->get("systemDir"));
        return substru($fileName, 0, $contentDirLength)==$this->yellow->config->get("contentDir") ||
            substru($fileName, 0, $mediaDirLength)==$this->yellow->config->get("mediaDir") ||
            substru($fileName, 0, $systemDirLength)==$this->yellow->config->get("systemDir");
    }
    
    // Check if content file
    public function isContentFile($fileName) {
        $contentDirLength = strlenu($this->yellow->config->get("contentDir"));
        return substru($fileName, 0, $contentDirLength)==$this->yellow->config->get("contentDir");
    }
    
    // Check if media file
    public function isMediaFile($fileName) {
        $mediaDirLength = strlenu($this->yellow->config->get("mediaDir"));
        return substru($fileName, 0, $mediaDirLength)==$this->yellow->config->get("mediaDir");
    }
    
    // Check if system file
    public function isSystemFile($fileName) {
        $systemDirLength = strlenu($this->yellow->config->get("systemDir"));
        return substru($fileName, 0, $systemDirLength)==$this->yellow->config->get("systemDir");
    }
}

class YellowToolbox {
    
    // Return server version from current HTTP request
    public function getServerVersion($shortFormat = false) {
        $serverVersion = strtoupperu(PHP_SAPI)." ".PHP_OS;
        if (preg_match("/^(\S+)/", $_SERVER["SERVER_SOFTWARE"], $matches)) $serverVersion = $matches[1]." ".PHP_OS;
        if ($shortFormat && preg_match("/^(\pL+)/u", $serverVersion, $matches)) $serverVersion = $matches[1];
        return $serverVersion;
    }
    
    // Return server URL from current HTTP request
    public function getServerUrl() {
        $scheme = $this->getScheme();
        $address = $this->getAddress();
        $base = $this->getBase();
        return "$scheme://$address$base/";
    }
    
    // Return scheme from current HTTP request
    public function getScheme() {
        $scheme = "";
        if (preg_match("/^HTTP\//", $_SERVER["SERVER_PROTOCOL"])) {
            $secure = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]!="off";
            $scheme = $secure ? "https" : "http";
        }
        return $scheme;
    }
    
    // Return address from current HTTP request
    public function getAddress() {
        $address = $_SERVER["SERVER_NAME"];
        $port = $_SERVER["SERVER_PORT"];
        if ($port!=80 && $port!=443) $address .= ":$port";
        return $address;
    }
    
    // Return base from current HTTP request
    public function getBase() {
        $base = "";
        if (preg_match("/^(.*)\/.*\.php$/", $_SERVER["SCRIPT_NAME"], $matches)) $base = $matches[1];
        return $base;
    }
    
    // Return location from current HTTP request
    public function getLocation($filterStrict = true) {
        $location = $_SERVER["REQUEST_URI"];
        $location = rawurldecode(($pos = strposu($location, "?")) ? substru($location, 0, $pos) : $location);
        if ($filterStrict) {
            $location = $this->normaliseTokens($location, true);
            $separator = $this->getLocationArgsSeparator();
            if (preg_match("/^(.*?\/)([^\/]+$separator.*)$/", $location, $matches)) {
                $_SERVER["LOCATION"] = $location = $matches[1];
                $_SERVER["LOCATION_ARGS"] = $matches[2];
                foreach (explode("/", $matches[2]) as $token) {
                    preg_match("/^(.*?)$separator(.*)$/", $token, $matches);
                    if (!empty($matches[1]) && !strempty($matches[2])) {
                        $matches[1] = strreplaceu(array("\x1c", "\x1d", "\x1e"), array("/", ":", "="), $matches[1]);
                        $matches[2] = strreplaceu(array("\x1c", "\x1d", "\x1e"), array("/", ":", "="), $matches[2]);
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
    public function getLocationArgs() {
        return $_SERVER["LOCATION_ARGS"];
    }
    
    // Return location arguments from current HTTP request, modify existing arguments
    public function getLocationArgsNew($arg, $pagination) {
        $separator = $this->getLocationArgsSeparator();
        preg_match("/^(.*?):(.*)$/", $arg, $args);
        foreach (explode("/", $_SERVER["LOCATION_ARGS"]) as $token) {
            preg_match("/^(.*?)$separator(.*)$/", $token, $matches);
            if ($matches[1]==$args[1]) {
                $matches[2] = $args[2];
                $found = true;
            }
            if (!empty($matches[1]) && !strempty($matches[2])) {
                if (!empty($locationArgs)) $locationArgs .= "/";
                $locationArgs .= "$matches[1]:$matches[2]";
            }
        }
        if (!$found && !empty($args[1]) && !strempty($args[2])) {
            if (!empty($locationArgs)) $locationArgs .= "/";
            $locationArgs .= "$args[1]:$args[2]";
        }
        if (!empty($locationArgs)) {
            $locationArgs = $this->normaliseArgs($locationArgs, false, false);
            if (!$this->isLocationArgsPagination($locationArgs, $pagination)) $locationArgs .= "/";
        }
        return $locationArgs;
    }
    
    // Return location arguments from current HTTP request, convert form parameters
    public function getLocationArgsClean($pagination) {
        foreach (array_merge($_GET, $_POST) as $key=>$value) {
            if (!empty($key) && !strempty($value)) {
                if (!empty($locationArgs)) $locationArgs .= "/";
                $key = strreplaceu(array("/", ":", "="), array("\x1c", "\x1d", "\x1e"), $key);
                $value = strreplaceu(array("/", ":", "="), array("\x1c", "\x1d", "\x1e"), $value);
                $locationArgs .= "$key:$value";
            }
        }
        if (!empty($locationArgs)) {
            $locationArgs = $this->normaliseArgs($locationArgs, false, false);
            if (!$this->isLocationArgsPagination($locationArgs, $pagination)) $locationArgs .= "/";
        }
        return $locationArgs;
    }

    // Return location arguments separator
    public function getLocationArgsSeparator() {
        return (strtoupperu(substru(PHP_OS, 0, 3))!="WIN") ? ":" : "=";
    }
    
    // Check if there are location arguments in current HTTP request
    public function isLocationArgs($location = "") {
        $location = empty($location) ? $_SERVER["LOCATION"].$_SERVER["LOCATION_ARGS"] : $location;
        $separator = $this->getLocationArgsSeparator();
        return preg_match("/[^\/]+$separator.*$/", $location);
    }
    
    // Check if there are pagination arguments in current HTTP request
    public function isLocationArgsPagination($location, $pagination) {
        $separator = $this->getLocationArgsSeparator();
        return preg_match("/^(.*\/)?$pagination$separator.*$/", $location);
    }

    // Check if script location is requested
    public function isRequestSelf() {
        return substru($_SERVER["REQUEST_URI"], -10, 10)=="yellow.php";
    }

    // Check if clean URL is requested
    public function isRequestCleanUrl($location) {
        return (isset($_GET["clean-url"]) || isset($_POST["clean-url"])) && substru($location, -1, 1)=="/";
    }

    // Check if unmodified since last HTTP request
    public function isRequestNotModified($lastModifiedFormatted) {
        return isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && $_SERVER["HTTP_IF_MODIFIED_SINCE"]==$lastModifiedFormatted;
    }
    
    // Normalise path or location, take care of relative path tokens
    public function normaliseTokens($text, $prependSlash = false) {
        $textFiltered = "";
        if ($prependSlash && $text[0]!="/") $textFiltered .= "/";
        for ($pos=0; $pos<strlenb($text); ++$pos) {
            if ($text[$pos]=="/" || $pos==0) {
                if ($text[$pos+1]=="/") continue;
                if ($text[$pos+1]==".") {
                    $posNew = $pos+1;
                    while ($text[$posNew]==".") {
                        ++$posNew;
                    }
                    if ($text[$posNew]=="/" || $text[$posNew]=="") {
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
    public function normaliseArgs($text, $appendSlash = true, $filterStrict = true) {
        if ($appendSlash) $text .= "/";
        if ($filterStrict) $text = strreplaceu(" ", "-", strtoloweru($text));
        $text = strreplaceu(":", $this->getLocationArgsSeparator(), $text);
        return strreplaceu(array("%2F","%3A","%3D"), array("/",":","="), rawurlencode($text));
    }
    
    // Normalise text into UTF-8 NFC
    public function normaliseUnicode($text) {
        if (PHP_OS=="Darwin" && !mb_check_encoding($text, "ASCII")) {
            $utf8nfc = preg_match("//u", $text) && !preg_match("/[^\\x00-\\x{2FF}]/u", $text);
            if (!$utf8nfc) $text = iconv("UTF-8-MAC", "UTF-8", $text);
        }
        return $text;
    }
    
    // Return timezone
    public function getTimezone() {
        $timezone = @date_default_timezone_get();
        if (PHP_OS=="Darwin" && $timezone=="UTC") {
            if (preg_match("#zoneinfo/(.*)#", @readlink("/etc/localtime"), $matches)) $timezone = $matches[1];
        }
        return $timezone;
    }
    
    // Return human readable HTTP server status
    public function getHttpStatusFormatted($statusCode, $shortFormat = false) {
        switch ($statusCode) {
            case 0:     $text = "No data"; break;
            case 200:   $text = "OK"; break;
            case 301:   $text = "Moved permanently"; break;
            case 302:   $text = "Moved temporarily"; break;
            case 303:   $text = "Reload please"; break;
            case 304:   $text = "Not modified"; break;
            case 400:   $text = "Bad request"; break;
            case 403:   $text = "Forbidden"; break;
            case 404:   $text = "Not found"; break;
            case 430:   $text = "Login failed"; break;
            case 434:   $text = "Not existing"; break;
            case 500:   $text = "Server error"; break;
            case 503:   $text = "Service unavailable"; break;
            default:    $text = "Error $statusCode";
        }
        $serverProtocol = $_SERVER["SERVER_PROTOCOL"];
        if (!preg_match("/^HTTP\//", $serverProtocol)) $serverProtocol = "HTTP/1.1";
        return $shortFormat ? $text : "$serverProtocol $statusCode $text";
    }
                              
    // Return human readable HTTP date
    public function getHttpDateFormatted($timestamp) {
        return gmdate("D, d M Y H:i:s", $timestamp)." GMT";
    }
                
    // Return MIME content type
    public function getMimeContentType($fileName) {
        $contentType = "";
        $contentTypes = array(
            "css" => "text/css",
            "gif" => "image/gif",
            "html" => "text/html; charset=utf-8",
            "ico" => "image/x-icon",
            "js" => "application/javascript",
            "json" => "application/json",
            "jpg" => "image/jpeg",
            "png" => "image/png",
            "svg" => "image/svg+xml",
            "txt" => "text/plain",
            "woff" => "application/font-woff",
            "woff2" => "application/font-woff2",
            "xml" => "text/xml; charset=utf-8");
        $fileType = $this->getFileType($fileName);
        if (empty($fileType)) {
            $contentType = $contentTypes["html"];
        } elseif (array_key_exists($fileType, $contentTypes)) {
            $contentType = $contentTypes[$fileType];
        }
        return $contentType;
    }
    
    // Return file type
    public function getFileType($fileName) {
        return strtoloweru(($pos = strrposu($fileName, ".")) ? substru($fileName, $pos+1) : "");
    }
    
    // Return file group
    public function getFileGroup($fileName, $path) {
        preg_match("#^$path(.+?)\/#", $fileName, $matches);
        return strtoloweru($matches[1]);
    }
    
    // Return number of bytes
    public function getNumberBytes($string) {
        $bytes = intval($string);
        switch (strtoupperu(substru($string, -1))) {
            case "G": $bytes *= 1024*1024*1024; break;
            case "M": $bytes *= 1024*1024; break;
            case "K": $bytes *= 1024; break;
        }
        return $bytes;
    }
    
    // Return files and directories
    public function getDirectoryEntries($path, $regex = "/.*/", $sort = true, $directories = true, $includePath = true) {
        $entries = array();
        $dirHandle = @opendir($path);
        if ($dirHandle) {
            $path = rtrim($path, "/");
            while (($entry = readdir($dirHandle))!==false) {
                if (substru($entry, 0, 1)==".") continue;
                $entry = $this->normaliseUnicode($entry);
                if (preg_match($regex, $entry)) {
                    if ($directories) {
                        if (is_dir("$path/$entry")) array_push($entries, $includePath ? "$path/$entry" : $entry);
                    } else {
                        if (is_file("$path/$entry")) array_push($entries, $includePath ? "$path/$entry" : $entry);
                    }
                }
            }
            if ($sort) natcasesort($entries);
            closedir($dirHandle);
        }
        return $entries;
    }
    
    // Return files and directories recursively
    public function getDirectoryEntriesRecursive($path, $regex = "/.*/", $sort = true, $directories = true, $levelMax = 0) {
        --$levelMax;
        $entries = $this->getDirectoryEntries($path, $regex, $sort, $directories);
        if ($levelMax!=0) {
            foreach ($this->getDirectoryEntries($path, "/.*/", $sort, true) as $entry) {
                $entries = array_merge($entries, $this->getDirectoryEntriesRecursive($entry, $regex, $sort, $directories, $levelMax));
            }
        }
        return $entries;
    }
    
    // Read file, empty string if not found
    public function readFile($fileName, $sizeMax = 0) {
        $fileData = "";
        $fileHandle = @fopen($fileName, "rb");
        if ($fileHandle) {
            clearstatcache(true, $fileName);
            $fileSize = $sizeMax ? $sizeMax : filesize($fileName);
            if ($fileSize) $fileData = fread($fileHandle, $fileSize);
            fclose($fileHandle);
        }
        return $fileData;
    }
    
    // Create file
    public function createFile($fileName, $fileData, $mkdir = false) {
        $ok = false;
        if ($mkdir) {
            $path = dirname($fileName);
            if (!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
        }
        $fileHandle = @fopen($fileName, "wb");
        if ($fileHandle) {
            clearstatcache(true, $fileName);
            if (flock($fileHandle, LOCK_EX)) {
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
    public function copyFile($fileNameSource, $fileNameDestination, $mkdir = false) {
        clearstatcache();
        if ($mkdir) {
            $path = dirname($fileNameDestination);
            if (!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
        }
        return @copy($fileNameSource, $fileNameDestination);
    }
    
    // Rename file
    public function renameFile($fileNameSource, $fileNameDestination, $mkdir = false) {
        clearstatcache();
        if ($mkdir) {
            $path = dirname($fileNameDestination);
            if (!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
        }
        return @rename($fileNameSource, $fileNameDestination);
    }
    
    // Delete file
    public function deleteFile($fileName, $pathTrash = "") {
        clearstatcache();
        if (empty($pathTrash)) {
            $ok = @unlink($fileName);
        } else {
            if (!is_dir($pathTrash)) @mkdir($pathTrash, 0777, true);
            $fileNameDestination = $pathTrash;
            $fileNameDestination .= pathinfo($fileName, PATHINFO_FILENAME);
            $fileNameDestination .= "-".str_replace(array(" ", ":"), "-", date("Y-m-d H:i:s", filemtime($fileName)));
            $fileNameDestination .= ".".pathinfo($fileName, PATHINFO_EXTENSION);
            $ok = @rename($fileName, $fileNameDestination);
        }
        return $ok;
    }
    
    // Delete directory
    public function deleteDirectory($path, $pathTrash = "") {
        clearstatcache();
        if (empty($pathTrash)) {
            $iterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getRealPath());
                } else {
                    @unlink($file->getRealPath());
                }
            }
            $ok = @rmdir($path);
        } else {
            if (!is_dir($pathTrash)) @mkdir($pathTrash, 0777, true);
            $pathDestination = $pathTrash;
            $pathDestination .= basename($path);
            $pathDestination .= "-".str_replace(array(" ", ":"), "-", date("Y-m-d H:i:s", filemtime($path)));
            $ok = @rename($path, $pathDestination);
        }
        return $ok;
    }
    
    // Set file modification date, Unix time
    public function modifyFile($fileName, $modified) {
        clearstatcache(true, $fileName);
        return @touch($fileName, $modified);
    }
    
    // Return file modification date, Unix time
    public function getFileModified($fileName) {
        return is_file($fileName) ? filemtime($fileName) : 0;
    }
    
    // Return lines from text string, including newline
    public function getTextLines($text) {
        $lines = preg_split("/\n/", $text);
        foreach ($lines as &$line) {
            $line = $line."\n";
        }
        if (strempty($text) || substru($text, -1, 1)=="\n") array_pop($lines);
        return $lines;
    }
    
    // Return arguments from text string, space separated
    public function getTextArgs($text, $optional = "-") {
        $text = preg_replace("/\s+/s", " ", trim($text));
        $tokens = str_getcsv($text, " ", "\"");
        foreach ($tokens as $key=>$value) {
            if ($value==$optional) $tokens[$key] = "";
        }
        return $tokens;
    }

    // Return number of words in text string
    public function getTextWords($text) {
        $text = preg_replace("/([\p{Han}\p{Hiragana}\p{Katakana}]{3})/u", "$1 ", $text);
        $text = preg_replace("/(\pL|\p{N})/u", "x", $text);
        return str_word_count($text);
    }
    
    // Create description from text string
    public function createTextDescription($text, $lengthMax = 0, $removeHtml = true, $endMarker = "", $endMarkerText = "") {
        if (preg_match("/^<h1>.*?<\/h1>(.*)$/si", $text, $matches)) $text = $matches[1];
        if ($lengthMax==0) $lengthMax = strlenu($text);
        if ($removeHtml) {
            while (true) {
                $elementFound = preg_match("/<\s*?([\/!]?\w*)(.*?)\s*?\>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
                $element = $matches[0][0];
                $elementName = $matches[1][0];
                $elementText = $matches[2][0];
                $elementOffsetBytes = $elementFound ? $matches[0][1] : strlenb($text);
                $string = html_entity_decode(substrb($text, $offsetBytes, $elementOffsetBytes - $offsetBytes), ENT_QUOTES, "UTF-8");
                if (preg_match("/^(blockquote|br|div|h\d|hr|li|ol|p|pre|ul)/i", $elementName)) $string .= " ";
                if (preg_match("/^\/(code|pre)/i", $elementName)) $string = preg_replace("/^(\d+\n){2,}$/", "", $string);
                $string = preg_replace("/\s+/s", " ", $string);
                if (substru($string, 0, 1)==" " && (empty($output) || substru($output, -1)==" ")) $string = substru($string, 1);
                $length = strlenu($string);
                $output .= substru($string, 0, $length<$lengthMax ? $length : $lengthMax-1);
                $lengthMax -= $length;
                if (!empty($element) && $element==$endMarker) {
                    $lengthMax = 0;
                    $endMarkerFound = true;
                }
                if ($lengthMax<=0 || !$elementFound) break;
                $offsetBytes = $elementOffsetBytes + strlenb($element);
            }
            $output = rtrim($output);
            if ($lengthMax<=0) $output .= $endMarkerFound ? $endMarkerText : "…";
        } else {
            $elementsOpen = array();
            while (true) {
                $elementFound = preg_match("/&.*?\;|<\s*?([\/!]?\w*)(.*?)\s*?\>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
                $element = $matches[0][0];
                $elementName = $matches[1][0];
                $elementText = $matches[2][0];
                $elementOffsetBytes = $elementFound ? $matches[0][1] : strlenb($text);
                $string = substrb($text, $offsetBytes, $elementOffsetBytes - $offsetBytes);
                $length = strlenu($string);
                $output .= substru($string, 0, $length<$lengthMax ? $length : $lengthMax-1);
                $lengthMax -= $length + ($element[0]=="&" ? 1 : 0);
                if (!empty($element) && $element==$endMarker) {
                    $lengthMax = 0;
                    $endMarkerFound = true;
                }
                if ($lengthMax<=0 || !$elementFound) break;
                if (!empty($elementName) && substru($elementText, -1)!="/" &&
                   !preg_match("/^(area|br|col|hr|img|input|col|param|!)/i", $elementName)) {
                    if ($elementName[0]!="/") {
                        array_push($elementsOpen, $elementName);
                    } else {
                        array_pop($elementsOpen);
                    }
                }
                $output .= $element;
                $offsetBytes = $elementOffsetBytes + strlenb($element);
            }
            $output = rtrim($output);
            for ($i=count($elementsOpen)-1; $i>=0; --$i) {
                if (!preg_match("/^(dl|ol|ul|table|tbody|thead|tfoot|tr)/i", $elementsOpen[$i])) break;
                $output .= "</".$elementsOpen[$i].">";
            }
            if ($lengthMax<=0) $output .= $endMarkerFound ? $endMarkerText : "…";
            for (; $i>=0; --$i) {
                $output .= "</".$elementsOpen[$i].">";
            }
        }
        return $output;
    }
    
    // Create keywords from text string
    public function createTextKeywords($text, $keywordsMax = 0) {
        $tokens = array_unique(preg_split("/[,\s\(\)\+\-]/", strtoloweru($text)));
        foreach ($tokens as $key=>$value) {
            if (strlenu($value)<3) unset($tokens[$key]);
        }
        if ($keywordsMax) $tokens = array_slice($tokens, 0, $keywordsMax);
        return implode(", ", $tokens);
    }
    
    // Create title from text string
    public function createTextTitle($text) {
        if (preg_match("/^.*\/([\w\-]+)/", $text, $matches)) $text = strreplaceu("-", " ", ucfirst($matches[1]));
        return $text;
    }

    // Create random text for cryptography
    public function createSalt($length, $bcryptFormat = false) {
        $dataBuffer = $salt = "";
        $dataBufferSize = $bcryptFormat ? intval(ceil($length/4) * 3) : intval(ceil($length/2));
        if (empty($dataBuffer) && function_exists("random_bytes")) {
            $dataBuffer = @random_bytes($dataBufferSize);
        }
        if (empty($dataBuffer) && function_exists("mcrypt_create_iv")) {
            $dataBuffer = @mcrypt_create_iv($dataBufferSize, MCRYPT_DEV_URANDOM);
        }
        if (empty($dataBuffer) && function_exists("openssl_random_pseudo_bytes")) {
            $dataBuffer = @openssl_random_pseudo_bytes($dataBufferSize);
        }
        if (strlenb($dataBuffer)==$dataBufferSize) {
            if ($bcryptFormat) {
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
    public function createHash($text, $algorithm, $cost = 0) {
        $hash = "";
        switch ($algorithm) {
            case "bcrypt":  $prefix = sprintf("$2y$%02d$", $cost);
                            $salt = $this->createSalt(22, true);
                            $hash = crypt($text, $prefix.$salt);
                            if (empty($salt) || strlenb($hash)!=60) $hash = "";
                            break;
            case "sha256":  $prefix = "$5y$";
                            $salt = $this->createSalt(32);
                            $hash = "$prefix$salt".hash("sha256", $salt.$text);
                            if (empty($salt) || strlenb($hash)!=100) $hash = "";
                            break;
        }
        return $hash;
    }
    
    // Verify that text matches hash
    public function verifyHash($text, $algorithm, $hash) {
        $hashCalculated = "";
        switch ($algorithm) {
            case "bcrypt":  if (substrb($hash, 0, 4)=="$2y$" || substrb($hash, 0, 4)=="$2a$") {
                                $hashCalculated = crypt($text, $hash);
                            }
                            break;
            case "sha256":  if (substrb($hash, 0, 4)=="$5y$") {
                                $prefix = "$5y$";
                                $salt = substrb($hash, 4, 32);
                                $hashCalculated = "$prefix$salt".hash("sha256", $salt.$text);
                            }
                            break;
        }
        return $this->verifyToken($hashCalculated, $hash);
    }
    
    // Verify that token is not empty and identical, timing attack safe text string comparison
    public function verifyToken($tokenExpected, $tokenReceived) {
        $ok = false;
        $lengthExpected = strlenb($tokenExpected);
        $lengthReceived = strlenb($tokenReceived);
        if ($lengthExpected!=0 && $lengthReceived!=0) {
            $ok = $lengthExpected==$lengthReceived;
            for ($i=0; $i<$lengthReceived; ++$i) {
                $ok &= $tokenExpected[$i<$lengthExpected ? $i : 0]==$tokenReceived[$i];
            }
        }
        return $ok;
    }
    
    // Return meta data from raw data
    public function getMetaData($rawData, $key) {
        $value = "";
        if (preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)\-\-\-[\r\n]+(.*)$/s", $rawData, $parts)) {
            $key = lcfirst($key);
            foreach ($this->getTextLines($parts[2]) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (lcfirst($matches[1])==$key && !strempty($matches[2])) {
                    $value = $matches[2];
                    break;
                }
            }
        }
        return $value;
    }
    
    // Set meta data in raw data
    public function setMetaData($rawData, $key, $value) {
        if (preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)\-\-\-[\r\n]+(.*)$/s", $rawData, $parts)) {
            $key = lcfirst($key);
            foreach ($this->getTextLines($parts[2]) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (lcfirst($matches[1])==$key) {
                    $rawDataNew .= "$matches[1]: $value\n";
                    $found = true;
                } else {
                    $rawDataNew .= $line;
                }
            }
            if (!$found) $rawDataNew .= ucfirst($key).": $value\n";
            $rawDataNew = $parts[1]."---\n".$rawDataNew."---\n".$parts[3];
        } else {
            $rawDataNew = $rawData;
        }
        return $rawDataNew;
    }
    
    // Detect web browser language
    public function detectBrowserLanguage($languages, $languageDefault) {
        $languageFound = $languageDefault;
        if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
            foreach (preg_split("/\s*,\s*/", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $string) {
                list($language) = explode(";", $string);
                if (in_array($language, $languages)) {
                    $languageFound = $language;
                    break;
                }
            }
        }
        return $languageFound;
    }
    
    // Detect image dimensions and type for gif/jpg/png/svg
    public function detectImageInformation($fileName, $fileType = "") {
        $width = $height = 0;
        $type = "";
        $fileHandle = @fopen($fileName, "rb");
        if ($fileHandle) {
            if (empty($fileType)) $fileType = $this->getFileType($fileName);
            if ($fileType=="gif") {
                $dataSignature = fread($fileHandle, 6);
                $dataHeader = fread($fileHandle, 7);
                if (!feof($fileHandle) && ($dataSignature=="GIF87a" || $dataSignature=="GIF89a")) {
                    $width = (ord($dataHeader[1])<<8) + ord($dataHeader[0]);
                    $height = (ord($dataHeader[3])<<8) + ord($dataHeader[2]);
                    $type = $fileType;
                }
            } elseif ($fileType=="jpg") {
                $dataBufferSizeMax = filesize($fileName);
                $dataBufferSize = min($dataBufferSizeMax, 4096);
                if ($dataBufferSize) $dataBuffer = fread($fileHandle, $dataBufferSize);
                $dataSignature = substrb($dataBuffer, 0, 4);
                if (!feof($fileHandle) && ($dataSignature=="\xff\xd8\xff\xe0" || $dataSignature=="\xff\xd8\xff\xe1")) {
                    for ($pos=2; $pos+8<$dataBufferSize; $pos+=$length) {
                        if ($dataBuffer[$pos]!="\xff") break;
                        if ($dataBuffer[$pos+1]=="\xc0" || $dataBuffer[$pos+1]=="\xc2") {
                            $width = (ord($dataBuffer[$pos+7])<<8) + ord($dataBuffer[$pos+8]);
                            $height = (ord($dataBuffer[$pos+5])<<8) + ord($dataBuffer[$pos+6]);
                            $type = $fileType;
                            break;
                        }
                        $length = (ord($dataBuffer[$pos+2])<<8) + ord($dataBuffer[$pos+3]) + 2;
                        while ($pos+$length+8>=$dataBufferSize) {
                            if ($dataBufferSize==$dataBufferSizeMax) break;
                            $dataBufferDiff = min($dataBufferSizeMax, $dataBufferSize*2) - $dataBufferSize;
                            $dataBufferSize += $dataBufferDiff;
                            $dataBufferChunk = fread($fileHandle, $dataBufferDiff);
                            if (feof($fileHandle) || $dataBufferChunk===false) {
                                $dataBufferSize = 0;
                                break;
                            }
                            $dataBuffer .= $dataBufferChunk;
                        }
                    }
                }
            } elseif ($fileType=="png") {
                $dataSignature = fread($fileHandle, 8);
                $dataHeader = fread($fileHandle, 16);
                if (!feof($fileHandle) && $dataSignature=="\x89PNG\r\n\x1a\n") {
                    $width = (ord($dataHeader[10])<<8) + ord($dataHeader[11]);
                    $height = (ord($dataHeader[14])<<8) + ord($dataHeader[15]);
                    $type = $fileType;
                }
            } elseif ($fileType=="svg") {
                $dataBufferSizeMax = filesize($fileName);
                $dataBufferSize = min($dataBufferSizeMax, 4096);
                if ($dataBufferSize) $dataBuffer = fread($fileHandle, $dataBufferSize);
                if (!feof($fileHandle) && preg_match("/<svg (.*?)>/", $dataBuffer, $matches)) {
                    if (preg_match("/ width=\"(\d+)\"/", $matches[1], $tokens)) $width = $tokens[1];
                    if (preg_match("/ height=\"(\d+)\"/", $matches[1], $tokens)) $height = $tokens[1];
                    $type = $fileType;
                }
            }
            fclose($fileHandle);
        }
        return array($width, $height, $type);
    }
    
    // Start timer
    public function timerStart(&$time) {
        $time = microtime(true);
    }
    
    // Stop timer and calculate elapsed time in milliseconds
    public function timerStop(&$time) {
        $time = intval((microtime(true)-$time) * 1000);
    }
}

// Unicode support for PHP
mb_internal_encoding("UTF-8");
function strempty($string) {
    return is_null($string) || $string==="";
}
function strencode($string) {
    return addcslashes($string, "\'\"\\\/");
}
function strreplaceu() {
    return call_user_func_array("str_replace", func_get_args());
}
function strtoloweru() {
    return call_user_func_array("mb_strtolower", func_get_args());
}
function strtoupperu() {
    return call_user_func_array("mb_strtoupper", func_get_args());
}
function strlenu() {
    return call_user_func_array("mb_strlen", func_get_args());
}
function strlenb() {
    return call_user_func_array("strlen", func_get_args());
}
function strposu() {
    return call_user_func_array("mb_strpos", func_get_args());
}
function strposb() {
    return call_user_func_array("strpos", func_get_args());
}
function strrposu() {
    return call_user_func_array("mb_strrpos", func_get_args());
}
function strrposb() {
    return call_user_func_array("strrpos", func_get_args());
}
function substru() {
    return call_user_func_array("mb_substr", func_get_args());
}
function substrb() {
    return call_user_func_array("substr", func_get_args());
}

// Error reporting for PHP
error_reporting(E_ALL ^ E_NOTICE);
