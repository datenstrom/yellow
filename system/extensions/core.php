<?php
// Core extension, https://github.com/datenstrom/yellow-extensions/tree/master/source/core

class YellowCore {
    const VERSION = "0.8.52";
    const RELEASE = "0.8.18";
    public $page;           // current page
    public $content;        // content files
    public $media;          // media files
    public $system;         // system settings
    public $user;           // user settings
    public $language;       // language settings
    public $extension;      // extensions
    public $lookup;         // location and file lookup
    public $toolbox;        // toolbox with helper functions

    public function __construct() {
        $this->checkRequirements();
        $this->page = new YellowPage($this);
        $this->content = new YellowContent($this);
        $this->media = new YellowMedia($this);
        $this->system = new YellowSystem($this);
        $this->user = new YellowUser($this);
        $this->language = new YellowLanguage($this);
        $this->extension = new YellowExtension($this);
        $this->lookup = new YellowLookup($this);
        $this->toolbox = new YellowToolbox();
        $this->system->setDefault("sitename", "Localhost");
        $this->system->setDefault("author", "Datenstrom");
        $this->system->setDefault("email", "webmaster");
        $this->system->setDefault("theme", "default");
        $this->system->setDefault("language", "en");
        $this->system->setDefault("layout", "default");
        $this->system->setDefault("parser", "markdown");
        $this->system->setDefault("status", "public");
        $this->system->setDefault("coreStaticUrl", "");
        $this->system->setDefault("coreServerUrl", "auto");
        $this->system->setDefault("coreServerTimezone", "UTC");
        $this->system->setDefault("coreMultiLanguageMode", "0");
        $this->system->setDefault("coreTrashTimeout", "7776660");
        $this->system->setDefault("coreMediaLocation", "/media/");
        $this->system->setDefault("coreDownloadLocation", "/media/downloads/");
        $this->system->setDefault("coreImageLocation", "/media/images/");
        $this->system->setDefault("coreExtensionLocation", "/media/extensions/");
        $this->system->setDefault("coreThemeLocation", "/media/themes/");
        $this->system->setDefault("coreMediaDirectory", "media/");
        $this->system->setDefault("coreDownloadDirectory", "media/downloads/");
        $this->system->setDefault("coreImageDirectory", "media/images/");
        $this->system->setDefault("coreSystemDirectory", "system/");
        $this->system->setDefault("coreExtensionDirectory", "system/extensions/");
        $this->system->setDefault("coreLayoutDirectory", "system/layouts/");
        $this->system->setDefault("coreThemeDirectory", "system/themes/");
        $this->system->setDefault("coreTrashDirectory", "system/trash/");
        $this->system->setDefault("coreCacheDirectory", "cache/");
        $this->system->setDefault("coreContentDirectory", "content/");
        $this->system->setDefault("coreContentRootDirectory", "default/");
        $this->system->setDefault("coreContentHomeDirectory", "home/");
        $this->system->setDefault("coreContentSharedDirectory", "shared/");
        $this->system->setDefault("coreContentDefaultFile", "page.md");
        $this->system->setDefault("coreContentErrorFile", "page-error-(.*).md");
        $this->system->setDefault("coreContentExtension", ".md");
        $this->system->setDefault("coreDownloadExtension", ".download");
        $this->system->setDefault("coreSystemFile", "yellow-system.ini");
        $this->system->setDefault("coreUserFile", "yellow-user.ini");
        $this->system->setDefault("coreLanguageFile", "yellow-language.ini");
        $this->system->setDefault("coreLogFile", "yellow.log");
        $this->language->setDefault("coreDateFormatShort");
        $this->language->setDefault("coreDateFormatMedium");
        $this->language->setDefault("coreDateFormatLong");
    }
    
    public function __destruct() {
        $this->shutdown();
    }
    
    // Check requirements
    public function checkRequirements() {
        $troubleshooting = PHP_SAPI!="cli" ? "<a href=\"".$this->getTroubleshootingUrl()."\">See troubleshooting</a>." : "";
        version_compare(PHP_VERSION, "5.6", ">=") || die("Datenstrom Yellow requires PHP 5.6 or higher! $troubleshooting\n");
        extension_loaded("curl") || die("Datenstrom Yellow requires PHP curl extension! $troubleshooting\n");
        extension_loaded("gd") || die("Datenstrom Yellow requires PHP gd extension! $troubleshooting\n");
        extension_loaded("mbstring") || die("Datenstrom Yellow requires PHP mbstring extension! $troubleshooting\n");
        extension_loaded("zip") || die("Datenstrom Yellow requires PHP zip extension! $troubleshooting\n");
        mb_internal_encoding("UTF-8");
        if (defined("DEBUG") && DEBUG>=1) {
            ini_set("display_errors", 1);
            error_reporting(E_ALL);
        }
    }
    
    // Handle initialisation
    public function load() {
        register_shutdown_function(array($this, "processFatalError"));
        $this->system->load($this->system->get("coreExtensionDirectory").$this->system->get("coreSystemFile"));
        $this->user->load($this->system->get("coreExtensionDirectory").$this->system->get("coreUserFile"));
        $this->language->load($this->system->get("coreExtensionDirectory"));
        $this->language->load($this->system->get("coreExtensionDirectory").$this->system->get("coreLanguageFile"));
        $this->extension->load($this->system->get("coreExtensionDirectory"));
        $this->startup();
    }
    
    // Handle request
    public function request() {
        $statusCode = 0;
        $this->toolbox->timerStart($time);
        ob_start();
        list($scheme, $address, $base, $location, $fileName) = $this->getRequestInformation();
        $this->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        foreach ($this->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onRequest")) {
                $this->lookup->requestHandler = $key;
                $statusCode = $value["object"]->onRequest($scheme, $address, $base, $location, $fileName);
                if ($statusCode!=0) break;
            }
        }
        if ($statusCode==0) {
            $this->lookup->requestHandler = "core";
            $statusCode = $this->processRequest($scheme, $address, $base, $location, $fileName, true);
        }
        if ($this->page->isExisting("pageError")) $statusCode = $this->processRequestError();
        ob_end_flush();
        $this->toolbox->timerStop($time);
        if (defined("DEBUG") && DEBUG>=1 && $this->lookup->isContentFile($fileName)) {
            echo "YellowCore::request status:$statusCode time:$time ms<br/>\n";
        }
        return $statusCode;
    }
    
    // Process request
    public function processRequest($scheme, $address, $base, $location, $fileName, $cacheable) {
        $statusCode = 0;
        if (is_readable($fileName)) {
            if ($this->lookup->isRequestCleanUrl($location)) {
                $location = $location.$this->toolbox->getLocationArgumentsCleanUrl();
                $location = $this->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->sendStatus(303, $location);
            }
        } else {
            if ($this->lookup->isRedirectLocation($location)) {
                $location = $this->lookup->getRedirectLocation($location);
                $location = $this->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->sendStatus(301, $location);
            }
        }
        if ($statusCode==0) {
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
    
    // Process fatal runtime error
    public function processFatalError() {
        $error = error_get_last();
        if (!is_null($error) && isset($error["type"]) && ($error["type"]==E_ERROR || $error["type"]==E_PARSE)) {
            $fileNameAbsolute = isset($error["file"]) ? $error["file"] : "";
            $fileName = substru($fileNameAbsolute, strlenu($this->system->get("coreServerInstallDirectory")));
            $this->log("error", "Can't parse file '$fileName'!");
            @header($this->toolbox->getHttpStatusFormatted(500));
            $troubleshooting = PHP_SAPI!="cli" ? "<a href=\"".$this->getTroubleshootingUrl()."\">See troubleshooting</a>." : "";
            echo "<br/>\nCheck the log file. Please activate the debug mode for more information. $troubleshooting\n";
        }
    }
    
    // Read page
    public function readPage($scheme, $address, $base, $location, $fileName, $cacheable, $statusCode, $pageError) {
        if ($statusCode>=400) {
            $locationError = $this->content->getHomeLocation($this->page->location).$this->system->get("coreContentSharedDirectory");
            $fileNameError = $this->lookup->findFileFromLocation($locationError, true).$this->system->get("coreContentErrorFile");
            $fileNameError = str_replace("(.*)", $statusCode, $fileNameError);
            $languageError = $this->lookup->findLanguageFromFile($fileName, $this->system->get("language"));
            if (is_file($fileNameError)) {
                $rawData = $this->toolbox->readFile($fileNameError);
            } elseif ($this->language->isText("coreError${statusCode}Title", $languageError)) {
                $rawData = "---\nTitle: ".$this->language->getText("coreError${statusCode}Title", $languageError)."\n";
                $rawData .= "Layout: error\n---\n".$this->language->getText("coreError${statusCode}Text", $languageError);
            } else {
                $rawData = "---\nTitle:".$this->toolbox->getHttpStatusFormatted($statusCode, true)."\n";
                $rawData .= "Layout:error\n---\n$pageError";
            }
            $cacheable = false;
        } else {
            $rawData = $this->toolbox->readFile($fileName);
        }
        $this->page = new YellowPage($this);
        $this->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $this->page->parseData($rawData, $cacheable, $statusCode, $pageError);
        $this->language->set($this->page->get("language"));
        $this->page->parseContent();
        return $fileName;
    }
    
    // Send page response
    public function sendPage() {
        $this->page->parsePage();
        $statusCode = $this->page->statusCode;
        $lastModifiedFormatted = $this->page->getHeader("Last-Modified");
        if ($statusCode==200 && $this->page->isCacheable() && $this->toolbox->isNotModified($lastModifiedFormatted)) {
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
            $language = $this->page->get("language");
            $layout = $this->page->get("layout");
            $parser = $this->page->get("parser");
            echo "YellowCore::sendPage theme:$theme language:$language layout:$layout parser:$parser<br/>\n";
        }
        return $statusCode;
    }
    
    // Send file response
    public function sendFile($statusCode, $fileName, $cacheable) {
        $lastModifiedFormatted = $this->toolbox->getHttpDateFormatted($this->toolbox->getFileModified($fileName));
        if ($statusCode==200 && $cacheable && $this->toolbox->isNotModified($lastModifiedFormatted)) {
            $statusCode = 304;
            @header($this->toolbox->getHttpStatusFormatted($statusCode));
        } else {
            @header($this->toolbox->getHttpStatusFormatted($statusCode));
            if (!$cacheable) @header("Cache-Control: no-cache, no-store");
            @header("Content-Type: ".$this->toolbox->getMimeContentType($fileName));
            @header("Last-Modified: ".$lastModifiedFormatted);
            echo $this->toolbox->readFile($fileName);
        }
        return $statusCode;
    }
    
    // Send data response
    public function sendData($statusCode, $rawData, $fileName, $cacheable) {
        @header($this->toolbox->getHttpStatusFormatted($statusCode));
        if (!$cacheable) @header("Cache-Control: no-cache, no-store");
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
    public function command($line = "") {
        $statusCode = 0;
        $this->toolbox->timerStart($time);
        list($command, $text) = $this->getCommandInformation($line);
        foreach ($this->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onCommand")) {
                $this->lookup->commandHandler = $key;
                $statusCode = $value["object"]->onCommand($command, $text);
                if ($statusCode!=0) break;
            }
        }
        if ($statusCode==0 && empty($command)) {
            $lineCounter = 0;
            echo "Datenstrom Yellow is for people who make small websites. https://datenstrom.se/yellow/\n";
            foreach ($this->getCommandHelp() as $line) {
                echo(++$lineCounter>1 ? "        " : "Syntax: ")."php yellow.php $line\n";
            }
            $statusCode = 200;
        }
        if ($statusCode==0) {
            $this->lookup->commandHandler = "core";
            $statusCode = 400;
            echo "Yellow $command: Command not found\n";
        }
        $this->toolbox->timerStop($time);
        if (defined("DEBUG") && DEBUG>=1) {
            echo "YellowCore::command status:$statusCode time:$time ms<br/>\n";
        }
        return $statusCode<400 ? 0 : 1;
    }
    
    // Handle startup
    public function startup() {
        if ($this->isLoaded()) {
            foreach ($this->extension->data as $key=>$value) {
                if (method_exists($value["object"], "onStartup")) $value["object"]->onStartup();
            }
        }
    }
    
    // Handle shutdown
    public function shutdown() {
        if ($this->isLoaded()) {
            foreach ($this->extension->data as $key=>$value) {
                if (method_exists($value["object"], "onShutdown")) $value["object"]->onShutdown();
            }
        }
    }
    
    // Handle logging
    public function log($action, $message) {
        $statusCode = 0;
        foreach ($this->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onLog")) {
                $statusCode = $value["object"]->onLog($action, $message);
                if ($statusCode!=0) break;
            }
        }
        if ($statusCode==0) {
            $line = date("Y-m-d H:i:s")." ".trim($action)." ".trim($message)."\n";
            $this->toolbox->appendFile($this->system->get("coreServerInstallDirectory").
                $this->system->get("coreExtensionDirectory").$this->system->get("coreLogFile"), $line);
        }
    }
    
    // Include layout
    public function layout($name, $arguments = null) {
        $this->lookup->layoutArguments = func_get_args();
        $this->page->includeLayout($name);
    }

    // Return layout arguments
    public function getLayoutArguments($sizeMin = 9) {
        return array_pad($this->lookup->layoutArguments, $sizeMin, null);
    }
    
    // Return troubleshooting URL
    public function getTroubleshootingUrl() {
        return "https://datenstrom.se/yellow/help/troubleshooting";
    }
    
    // Return request information
    public function getRequestInformation($scheme = "", $address = "", $base = "") {
        if (empty($scheme) && empty($address) && empty($base)) {
            $url = $this->system->get("coreServerUrl");
            if ($url=="auto" || $this->isCommandLine()) $url = $this->toolbox->detectServerUrl();
            list($scheme, $address, $base) = $this->lookup->getUrlInformation($url);
            $this->system->set("coreServerScheme", $scheme);
            $this->system->set("coreServerAddress", $address);
            $this->system->set("coreServerBase", $base);
            if (defined("DEBUG") && DEBUG>=3) echo "YellowCore::getRequestInformation $scheme://$address$base<br/>\n";
        }
        $location = substru($this->toolbox->detectServerLocation(), strlenu($base));
        if (empty($fileName)) $fileName = $this->lookup->findFileFromSystem($location);
        if (empty($fileName)) $fileName = $this->lookup->findFileFromMedia($location);
        if (empty($fileName)) $fileName = $this->lookup->findFileFromLocation($location);
        return array($scheme, $address, $base, $location, $fileName);
    }

    // Return command information
    public function getCommandInformation($line = "") {
        if (empty($line)) {
            $line = $this->toolbox->getTextString(array_slice($this->toolbox->getServer("argv"), 1));
            if (defined("DEBUG") && DEBUG>=3) echo "YellowCore::getCommandInformation $line<br/>\n";
        }
        return $this->toolbox->getTextList($line, " ", 2);
    }
    
    // Return command help
    public function getCommandHelp() {
        $data = array();
        foreach ($this->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onCommandHelp")) {
                foreach (preg_split("/[\r\n]+/", $value["object"]->onCommandHelp()) as $line) {
                    list($command, $dummy) = $this->toolbox->getTextList($line, " ", 2);
                    if (!empty($command) && !isset($data[$command])) $data[$command] = $line;
                }
            }
        }
        uksort($data, "strnatcasecmp");
        return $data;
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
        return isset($this->lookup->commandHandler);
    }
    
    // Check if all extensions loaded
    public function isLoaded() {
        return isset($this->extension->data);
    }
}
    
class YellowPage {
    public $yellow;                 // access to API
    public $scheme;                 // server scheme
    public $address;                // server address
    public $base;                   // base location
    public $location;               // page location
    public $fileName;               // content file name
    public $rawData;                // raw data of page
    public $metaDataOffsetBytes;    // meta data offset
    public $metaData;               // meta data
    public $pageCollections;        // additional pages
    public $sharedPages;            // shared pages
    public $headerData;             // response header
    public $outputData;             // response output
    public $parser;                 // content parser
    public $parserData;             // content data of page
    public $available;              // page is available? (boolean)
    public $visible;                // page is visible location? (boolean)
    public $active;                 // page is active location? (boolean)
    public $cacheable;              // page is cacheable? (boolean)
    public $lastModified;           // last modification date
    public $statusCode;             // status code

    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->metaData = new YellowArray();
        $this->pageCollections = array();
        $this->sharedPages = array();
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
        $this->available = $this->yellow->lookup->isAvailableLocation($this->location, $this->fileName);
        $this->visible = true;
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
        $this->metaData = new YellowArray();
        if (!is_null($this->rawData)) {
            $this->set("title", $this->yellow->toolbox->createTextTitle($this->location));
            $this->set("language", $this->yellow->lookup->findLanguageFromFile($this->fileName, $this->yellow->system->get("language")));
            $this->set("modified", date("Y-m-d H:i:s", $this->yellow->toolbox->getFileModified($this->fileName)));
            $this->parseMetaRaw(array("sitename", "author", "theme", "layout", "parser", "status"));
            $titleHeader = ($this->location==$this->yellow->content->getHomeLocation($this->location)) ?
                $this->get("sitename") : $this->get("title")." - ".$this->get("sitename");
            if (!$this->isExisting("titleContent")) $this->set("titleContent", $this->get("title"));
            if (!$this->isExisting("titleNavigation")) $this->set("titleNavigation", $this->get("title"));
            if (!$this->isExisting("titleHeader")) $this->set("titleHeader", $titleHeader);
            if ($this->get("status")=="unlisted") $this->visible = false;
            if ($this->get("status")=="shared") $this->available = false;
            $this->set("pageReadUrl", $this->yellow->lookup->normaliseUrl(
                $this->yellow->system->get("coreServerScheme"),
                $this->yellow->system->get("coreServerAddress"),
                $this->yellow->system->get("coreServerBase"),
                $this->location));
            $this->set("pageEditUrl", $this->yellow->lookup->normaliseUrl(
                $this->yellow->system->get("coreServerScheme"),
                $this->yellow->system->get("coreServerAddress"),
                $this->yellow->system->get("coreServerBase"),
                rtrim($this->yellow->system->get("editLocation"), "/").$this->location));
            $this->setPage("main", $this);
        } else {
            $this->set("type", $this->yellow->toolbox->getFileType($this->fileName));
            $this->set("group", $this->yellow->toolbox->getFileGroup($this->fileName, $this->yellow->system->get("coreMediaDirectory")));
            $this->set("modified", date("Y-m-d H:i:s", $this->yellow->toolbox->getFileModified($this->fileName)));
        }
        if (!empty($pageError)) $this->set("pageError", $pageError);
        foreach ($this->yellow->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onParseMeta")) $value["object"]->onParseMeta($this);
        }
    }
    
    // Parse page meta data from raw data
    public function parseMetaRaw($defaultKeys) {
        foreach ($defaultKeys as $key) {
            $value = $this->yellow->system->get($key);
            if (!empty($key) && !strempty($value)) $this->set($key, $value);
        }
        if (preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)\-\-\-[\r\n]+/s", $this->rawData, $parts)) {
            $this->metaDataOffsetBytes = strlenb($parts[0]);
            foreach (preg_split("/[\r\n]+/", $parts[2]) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (!empty($matches[1]) && !strempty($matches[2])) $this->set($matches[1], $matches[2]);
                }
            }
        } elseif (preg_match("/^(\xEF\xBB\xBF)?([^\r\n]+)[\r\n]+=+[\r\n]+/", $this->rawData, $parts)) {
            $this->metaDataOffsetBytes = strlenb($parts[0]);
            $this->set("title", $parts[2]);
        }
    }
    
    // Parse page content on demand
    public function parseContent($sizeMax = 0) {
        if (!is_null($this->rawData) && !is_object($this->parser)) {
            if ($this->yellow->extension->isExisting($this->get("parser"))) {
                $value = $this->yellow->extension->data[$this->get("parser")];
                if (method_exists($value["object"], "onParseContentRaw")) {
                    $this->parser = $value["object"];
                    $this->parserData = $this->getContent(true, $sizeMax);
                    $this->parserData = preg_replace("/@pageReadUrl/i", $this->get("pageReadUrl"), $this->parserData);
                    $this->parserData = preg_replace("/@pageEditUrl/i", $this->get("pageEditUrl"), $this->parserData);
                    $this->parserData = $this->parser->onParseContentRaw($this, $this->parserData);
                    foreach ($this->yellow->extension->data as $key=>$value) {
                        if (method_exists($value["object"], "onParseContentHtml")) {
                            $output = $value["object"]->onParseContentHtml($this, $this->parserData);
                            if (!is_null($output)) $this->parserData = $output;
                        }
                    }
                }
            } else {
                $this->parserData = $this->getContent(true, $sizeMax);
                $this->parserData = preg_replace("/\[yellow error\]/i", $this->get("pageError"), $this->parserData);
            }
            if (!$this->isExisting("description")) {
                $description = $this->yellow->toolbox->createTextDescription($this->parserData, 150);
                $this->set("description", !empty($description) ? $description : $this->get("title"));
            }
            if (defined("DEBUG") && DEBUG>=3) echo "YellowPage::parseContent location:".$this->location."<br/>\n";
        }
    }
    
    // Parse page content shortcut
    public function parseContentShortcut($name, $text, $type) {
        $output = null;
        foreach ($this->yellow->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onParseContentShortcut")) {
                $output = $value["object"]->onParseContentShortcut($this, $name, $text, $type);
                if (!is_null($output)) break;
            }
        }
        if (is_null($output)) {
            if ($name=="yellow" && $type=="inline") {
                if ($text=="about") {
                    $output = "Datenstrom Yellow ".YellowCore::RELEASE."<br />\n";
                    $dataCurrent = $this->yellow->extension->data;
                    uksort($dataCurrent, "strnatcasecmp");
                    foreach ($dataCurrent as $key=>$value) {
                        $output .= ucfirst($key)." ".$value["version"]."<br />\n";
                    }
                }
                if ($text=="release") $output = "Datenstrom Yellow ".YellowCore::RELEASE;
                if ($text=="error") $output = $this->get("pageError");
                if ($text=="log") {
                    $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreLogFile");
                    $fileHandle = @fopen($fileName, "r");
                    if ($fileHandle) {
                        $dataBufferSize = 512;
                        fseek($fileHandle, max(0, filesize($fileName) - $dataBufferSize));
                        $dataBuffer = fread($fileHandle, $dataBufferSize);
                        if (strlenb($dataBuffer)==$dataBufferSize) {
                            $dataBuffer = ($pos = strposu($dataBuffer, "\n")) ? substru($dataBuffer, $pos+1) : $dataBuffer;
                        }
                        fclose($fileHandle);
                    }
                    $output = str_replace("\n", "<br />\n", htmlspecialchars($dataBuffer));
                }
            }
        }
        if (defined("DEBUG") && DEBUG>=3 && !empty($name)) echo "YellowPage::parseContentShortcut name:$name type:$type<br/>\n";
        return $output;
    }
    
    // Parse page
    public function parsePage() {
        $this->parsePageLayout($this->get("layout"));
        if (!$this->isCacheable()) $this->setHeader("Cache-Control", "no-cache, no-store");
        if (!$this->isHeader("Content-Type")) $this->setHeader("Content-Type", "text/html; charset=utf-8");
        if (!$this->isHeader("Content-Modified")) $this->setHeader("Content-Modified", $this->getModified(true));
        if (!$this->isHeader("Last-Modified")) $this->setHeader("Last-Modified", $this->getLastModified(true));
        $fileNameTheme = $this->yellow->system->get("coreThemeDirectory").$this->yellow->lookup->normaliseName($this->get("theme")).".css";
        if (!is_file($fileNameTheme)) {
            $this->error(500, "Theme '".$this->get("theme")."' does not exist!");
        }
        if (!$this->yellow->language->isExisting($this->get("language"))) {
            $this->error(500, "Language '".$this->get("language")."' does not exist!");
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
        if ($this->isExisting("pageClean")) $this->outputData = null;
        foreach ($this->yellow->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onParsePageOutput")) {
                $output = $value["object"]->onParsePageOutput($this, $this->outputData);
                if (!is_null($output)) $this->outputData = $output;
            }
        }
    }
    
    // Parse page layout
    public function parsePageLayout($name) {
        foreach ($this->yellow->content->getShared($this->location) as $page) {
            $this->sharedPages[basename($page->location)] = $page;
            $page->sharedPages["main"] = $this;
        }
        $this->outputData = null;
        foreach ($this->yellow->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onParsePageLayout")) {
                $value["object"]->onParsePageLayout($this, $name);
            }
        }
        if (is_null($this->outputData)) {
            ob_start();
            $this->includeLayout($name);
            $this->outputData = ob_get_contents();
            ob_end_clean();
        }
    }
    
    // Include page layout
    public function includeLayout($name) {
        $fileNameLayoutNormal = $this->yellow->system->get("coreLayoutDirectory").$this->yellow->lookup->normaliseName($name).".html";
        $fileNameLayoutTheme = $this->yellow->system->get("coreLayoutDirectory").
            $this->yellow->lookup->normaliseName($this->get("theme"))."-".$this->yellow->lookup->normaliseName($name).".html";
        if (is_file($fileNameLayoutTheme)) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowPage::includeLayout file:$fileNameLayoutTheme<br/>\n";
            $this->setLastModified(filemtime($fileNameLayoutTheme));
            require($fileNameLayoutTheme);
        } elseif (is_file($fileNameLayoutNormal)) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowPage::includeLayout file:$fileNameLayoutNormal<br/>\n";
            $this->setLastModified(filemtime($fileNameLayoutNormal));
            require($fileNameLayoutNormal);
        } else {
            $this->error(500, "Layout '$name' does not exist!");
            echo "Layout error<br/>\n";
        }
    }
    
    // Set page setting
    public function set($key, $value) {
        $this->metaData[$key] = $value;
    }
    
    // Return page setting
    public function get($key) {
        return $this->isExisting($key) ? $this->metaData[$key] : "";
    }

    // Return page setting, HTML encoded
    public function getHtml($key) {
        return htmlspecialchars($this->get($key));
    }
    
    // Return page setting as language specific date
    public function getDate($key, $format = "") {
        if (!empty($format)) {
            $format = $this->yellow->language->getText($format);
        } else {
            $format = $this->yellow->language->getText("coreDateFormatMedium");
        }
        return $this->yellow->language->getDateFormatted(strtotime($this->get($key)), $format);
    }

    // Return page setting as language specific date, HTML encoded
    public function getDateHtml($key, $format = "") {
        return htmlspecialchars($this->getDate($key, $format));
    }

    // Return page setting as language specific date, relative to today
    public function getDateRelative($key, $format = "", $daysLimit = 30) {
        if (!empty($format)) {
            $format = $this->yellow->language->getText($format);
        } else {
            $format = $this->yellow->language->getText("coreDateFormatMedium");
        }
        return $this->yellow->language->getDateRelative(strtotime($this->get($key)), $format, $daysLimit);
    }
    
    // Return page setting as language specific date, relative to today, HTML encoded
    public function getDateRelativeHtml($key, $format = "", $daysLimit = 30) {
        return htmlspecialchars($this->getDateRelative($key, $format, $daysLimit));
    }
    
    // Return page setting as date
    public function getDateFormatted($key, $format) {
        return $this->yellow->language->getDateFormatted(strtotime($this->get($key)), $format);
    }
    
    // Return page setting as date, HTML encoded
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
        $parentLocation = $this->yellow->content->getParentLocation($this->location);
        return $this->yellow->content->find($parentLocation);
    }
    
    // Return top-level parent page, null if none
    public function getParentTop($homeFallback = false) {
        $parentTopLocation = $this->yellow->content->getParentTopLocation($this->location);
        if (!$this->yellow->content->find($parentTopLocation) && $homeFallback) {
            $parentTopLocation = $this->yellow->content->getHomeLocation($this->location);
        }
        return $this->yellow->content->find($parentTopLocation);
    }
    
    // Return page collection with pages on the same level
    public function getSiblings($showInvisible = false) {
        $parentLocation = $this->yellow->content->getParentLocation($this->location);
        return $this->yellow->content->getChildren($parentLocation, $showInvisible);
    }
    
    // Return page collection with child pages
    public function getChildren($showInvisible = false) {
        return $this->yellow->content->getChildren($this->location, $showInvisible);
    }

    // Return page collection with child pages recursively
    public function getChildrenRecursive($showInvisible = false, $levelMax = 0) {
        return $this->yellow->content->getChildrenRecursive($this->location, $showInvisible, $levelMax);
    }
    
    // Set page collection with additional pages
    public function setPages($key, $pages) {
        $this->pageCollections[$key] = $pages;
    }

    // Return page collection with additional pages
    public function getPages($key) {
        return isset($this->pageCollections[$key]) ? $this->pageCollections[$key] : new YellowPageCollection($this->yellow);
    }
    
    // Set shared page
    public function setPage($key, $page) {
        $this->sharedPages[$key] = $page;
    }
    
    // Return shared page
    public function getPage($key) {
        return isset($this->sharedPages[$key]) ? $this->sharedPages[$key] : new YellowPage($this->yellow);
    }
    
    // Return page URL
    public function getUrl() {
        return $this->yellow->lookup->normaliseUrl($this->scheme, $this->address, $this->base, $this->location);
    }
    
    // Return page base
    public function getBase($multiLanguage = false) {
        return $multiLanguage ? rtrim($this->base.$this->yellow->content->getHomeLocation($this->location), "/") :  $this->base;
    }
    
    // Return page location
    public function getLocation($absoluteLocation = false) {
        return $absoluteLocation ? $this->base.$this->location : $this->location;
    }
    
    // Set page request argument
    public function setRequest($key, $value) {
        $_REQUEST[$key] = $value;
    }
    
    // Return page request argument
    public function getRequest($key) {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : "";
    }
    
    // Return page request argument, HTML encoded
    public function getRequestHtml($key) {
        return htmlspecialchars($this->getRequest($key));
    }
    
    // Set page response header
    public function setHeader($key, $value) {
        $this->headerData[$key] = $value;
    }
    
    // Return page response header
    public function getHeader($key) {
        return $this->isHeader($key) ? $this->headerData[$key] : "";
    }
    
    // Return page extra data
    public function getExtra($name) {
        $output = "";
        foreach ($this->yellow->extension->data as $key=>$value) {
            if (method_exists($value["object"], "onParsePageExtra")) {
                $outputExtension = $value["object"]->onParsePageExtra($this, $name);
                if (!is_null($outputExtension)) $output .= $outputExtension;
            }
        }
        if ($name=="header") {
            $fileNameTheme = $this->yellow->system->get("coreThemeDirectory").$this->yellow->lookup->normaliseName($this->get("theme")).".css";
            if (is_file($fileNameTheme)) {
                $locationTheme = $this->yellow->system->get("coreServerBase").
                    $this->yellow->system->get("coreThemeLocation").$this->yellow->lookup->normaliseName($this->get("theme")).".css";
                $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"$locationTheme\" />\n";
            }
            $fileNameScript = $this->yellow->system->get("coreThemeDirectory").$this->yellow->lookup->normaliseName($this->get("theme")).".js";
            if (is_file($fileNameScript)) {
                $locationScript = $this->yellow->system->get("coreServerBase").
                    $this->yellow->system->get("coreThemeLocation").$this->yellow->lookup->normaliseName($this->get("theme")).".js";
                $output .= "<script type=\"text/javascript\" src=\"$locationScript\"></script>\n";
            }
            $fileNameFavicon = $this->yellow->system->get("coreThemeDirectory").$this->yellow->lookup->normaliseName($this->get("theme")).".png";
            if (is_file($fileNameFavicon)) {
                $locationFavicon = $this->yellow->system->get("coreServerBase").
                    $this->yellow->system->get("coreThemeLocation").$this->yellow->lookup->normaliseName($this->get("theme")).".png";
                $output .= "<link rel=\"icon\" type=\"image/png\" href=\"$locationFavicon\" />\n";
            }
        }
        return $output;
    }
    
    // Set page response output
    public function setOutput($output) {
        $this->outputData = $output;
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
        $lastModified = max($this->lastModified, $this->getModified(), $this->yellow->system->getModified(),
            $this->yellow->language->getModified(), $this->yellow->extension->getModified());
        foreach ($this->pageCollections as $pages) $lastModified = max($lastModified, $pages->getModified());
        foreach ($this->sharedPages as $page) $lastModified = max($lastModified, $page->getModified());
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($lastModified) : $lastModified;
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
            $this->set("pageError", empty($pageError) ? "Page error!" : $pageError);
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
                $this->setHeader("Cache-Control", "no-cache, no-store");
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
    
    // Check if page setting exists
    public function isExisting($key) {
        return isset($this->metaData[$key]);
    }
    
    // Check if request argument exists
    public function isRequest($key) {
        return isset($_REQUEST[$key]);
    }
    
    // Check if response header exists
    public function isHeader($key) {
        return isset($this->headerData[$key]);
    }
    
    // Check if shared page exists
    public function isPage($key) {
        return isset($this->sharedPages[$key]);
    }
}

class YellowPageCollection extends ArrayObject {
    public $yellow;                 // access to API
    public $filterValue;            // current page filter value
    public $paginationNumber;       // current page number in pagination
    public $paginationCount;        // highest page number in pagination
    
    public function __construct($yellow) {
        parent::__construct(array());
        $this->yellow = $yellow;
    }
    
    // Filter page collection by page setting
    public function filter($key, $value, $exactMatch = true) {
        $array = array();
        $value = str_replace(" ", "-", strtoloweru($value));
        $valueLength = strlenu($value);
        $this->filterValue = "";
        foreach ($this->getArrayCopy() as $page) {
            if ($page->isExisting($key)) {
                foreach (preg_split("/\s*,\s*/", $page->get($key)) as $pageValue) {
                    $pageValueLength = $exactMatch ? strlenu($pageValue) : $valueLength;
                    if ($value==substru(str_replace(" ", "-", strtoloweru($pageValue)), 0, $pageValueLength)) {
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
    
    // Sort page collection by page setting
    public function sort($key, $ascendingOrder = true) {
        $array = $this->getArrayCopy();
        $sortIndex = 0;
        foreach ($array as $page) {
            $page->set("sortindex", ++$sortIndex);
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
    
    // Sort page collection by settings similarity
    public function similar($page, $ascendingOrder = false) {
        $location = $page->location;
        $keywords = strtoloweru($page->get("title").",".$page->get("tag").",".$page->get("author"));
        $tokens = array_unique(array_filter(preg_split("/[,\s\(\)\+\-]/", $keywords), "strlen"));
        if (!empty($tokens)) {
            $array = array();
            foreach ($this->getArrayCopy() as $page) {
                $searchScore = 0;
                foreach ($tokens as $token) {
                    if (stristr($page->get("title"), $token)) $searchScore += 50;
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

    // Calculate union, merge page collection
    public function merge($input) {
        $this->exchangeArray(array_merge($this->getArrayCopy(), (array)$input));
        return $this;
    }
    
    // Calculate intersection, remove pages that are not present in another page collection
    public function intersect($input) {
        $callback = function ($a, $b) {
            return strcmp(spl_object_hash($a), spl_object_hash($b));
        };
        $this->exchangeArray(array_uintersect($this->getArrayCopy(), (array)$input, $callback));
        return $this;
    }

    // Calculate difference, remove pages that are present in another page collection
    public function diff($input) {
        $callback = function ($a, $b) {
            return strcmp(spl_object_hash($a), spl_object_hash($b));
        };
        $this->exchangeArray(array_udiff($this->getArrayCopy(), (array)$input, $callback));
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
        if ($this->yellow->page->isRequest("page")) $this->paginationNumber = intval($this->yellow->page->getRequest("page"));
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
        $location = $locationArguments = "";
        if ($pageNumber>=1 && $pageNumber<=$this->paginationCount) {
            $location = $this->yellow->page->getLocation($absoluteLocation);
            $locationArguments = $this->yellow->toolbox->getLocationArgumentsNew("page", $pageNumber>1 ? "$pageNumber" : "");
        }
        return $location.$locationArguments;
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

class YellowContent {
    public $yellow;         // access to API
    public $pages;          // scanned pages
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->pages = array();
    }
    
    // Scan file system on demand
    public function scanLocation($location) {
        if (!isset($this->pages[$location])) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowContent::scanLocation location:$location<br/>\n";
            $this->pages[$location] = array();
            $scheme = $this->yellow->page->scheme;
            $address = $this->yellow->page->address;
            $base = $this->yellow->page->base;
            if (empty($location)) {
                $rootLocations = $this->yellow->lookup->findRootLocations();
                foreach ($rootLocations as $rootLocation) {
                    list($rootLocation, $fileName) = $this->yellow->toolbox->getTextList($rootLocation, " ", 2);
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

    // Return page from, null if not found
    public function find($location, $absoluteLocation = false) {
        $found = false;
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
    public function top($showInvisible = false, $showOnePager = true) {
        $rootLocation = $this->getRootLocation($this->yellow->page->location);
        $pages = $this->getChildren($rootLocation, $showInvisible);
        if (count($pages)==1 && $showOnePager) {
            $scheme = $this->yellow->page->scheme;
            $address = $this->yellow->page->address;
            $base = $this->yellow->page->base;
            $one = ($pages->offsetGet(0)->location!=$this->yellow->page->location) ? $pages->offsetGet(0) : $this->yellow->page;
            preg_match_all("/<h(\d) id=\"([^\"]+)\">(.*?)<\/h\d>/i", $one->getContent(), $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if ($match[1]==2) {
                    $page = new YellowPage($this->yellow);
                    $page->setRequestInformation($scheme, $address, $base, $one->location."#".$match[2], $one->fileName);
                    $page->parseData("---\nTitle: $match[3]\n---\n", false, 0);
                    $pages->append($page);
                }
            }
        }
        return $pages;
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
    
    // Return child pages recursively
    public function getChildrenRecursive($location, $showInvisible = false, $levelMax = 0) {
        --$levelMax;
        $pages = new YellowPageCollection($this->yellow);
        foreach ($this->scanLocation($location) as $page) {
            if ($page->isAvailable() && ($page->isVisible() || $showInvisible)) {
                if (!$this->yellow->lookup->isRootLocation($page->location) && is_readable($page->fileName)) $pages->append($page);
            }
            if (!$this->yellow->lookup->isFileLocation($page->location) && $levelMax!=0) {
                $pages->merge($this->getChildrenRecursive($page->location, $showInvisible, $levelMax));
            }
        }
        return $pages;
    }
    
    // Return shared pages
    public function getShared($location) {
        $pages = new YellowPageCollection($this->yellow);
        $location = $this->getHomeLocation($location).$this->yellow->system->get("coreContentSharedDirectory");
        foreach ($this->scanLocation($location) as $page) {
            if ($page->get("status")=="shared") $pages->append($page);
        }
        return $pages;
    }
    
    // Return root location
    public function getRootLocation($location) {
        $rootLocation = "root/";
        if ($this->yellow->system->get("coreMultiLanguageMode")) {
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
    
class YellowMedia {
    public $yellow;     // access to API
    public $files;      // scanned files
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->files = array();
    }

    // Scan file system on demand
    public function scanLocation($location) {
        if (!isset($this->files[$location])) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowMedia::scanLocation location:$location<br/>\n";
            $this->files[$location] = array();
            $scheme = $this->yellow->page->scheme;
            $address = $this->yellow->page->address;
            $base = $this->yellow->system->get("coreServerBase");
            if (empty($location)) {
                $fileNames = array($this->yellow->system->get("coreMediaDirectory"));
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
        $found = false;
        if ($absoluteLocation) $location = substru($location, strlenu($this->yellow->system->get("coreServerBase")));
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
    
    // Return child files recursively
    public function getChildrenRecursive($location, $showInvisible = false, $levelMax = 0) {
        --$levelMax;
        $files = new YellowPageCollection($this->yellow);
        foreach ($this->scanLocation($location) as $file) {
            if ($file->isAvailable() && ($file->isVisible() || $showInvisible)) {
                if ($this->yellow->lookup->isFileLocation($file->location)) $files->append($file);
            }
            if (!$this->yellow->lookup->isFileLocation($file->location) && $levelMax!=0) {
                $files->merge($this->getChildrenRecursive($file->location, $showInvisible, $levelMax));
            }
        }
        return $files;
    }
    
    // Return home location
    public function getHomeLocation($location) {
        return $this->yellow->system->get("coreMediaLocation");
    }

    // Return parent location
    public function getParentLocation($location) {
        $token = rtrim($this->yellow->system->get("coreMediaLocation"), "/");
        if (preg_match("#^($token.*\/).+?$#", $location, $matches)) {
            if ($matches[1]!="$token/" || $this->yellow->lookup->isFileLocation($location)) $parentLocation = $matches[1];
        }
        if (empty($parentLocation)) $parentLocation = "";
        return $parentLocation;
    }
    
    // Return top-level location
    public function getParentTopLocation($location) {
        $token = rtrim($this->yellow->system->get("coreMediaLocation"), "/");
        if (preg_match("#^($token.+?\/)#", $location, $matches)) $parentTopLocation = $matches[1];
        if (empty($parentTopLocation)) $parentTopLocation = "$token/";
        return $parentTopLocation;
    }
}

class YellowSystem {
    public $yellow;             // access to API
    public $modified;           // system modification date
    public $settings;           // system settings
    public $settingsDefaults;   // system settings defaults
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->settings = new YellowArray();
        $this->settingsDefaults = new YellowArray();
    }
    
    // Load system settings from file
    public function load($fileName) {
        if (defined("DEBUG") && DEBUG>=2) echo "YellowSystem::load file:$fileName<br/>\n";
        $this->modified = $this->yellow->toolbox->getFileModified($fileName);
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $this->settings = $this->yellow->toolbox->getTextSettings($fileData, "");
        if (defined("DEBUG") && DEBUG>=3) {
            foreach ($this->settings as $key=>$value) {
                echo "YellowSystem::load ".ucfirst($key).":$value<br/>\n";
            }
        }
        list($pathInstall, $pathRoot, $pathHome) = $this->yellow->lookup->findFileSystemInformation();
        $this->yellow->system->set("coreServerInstallDirectory", $pathInstall);
        $this->yellow->system->set("coreContentRootDirectory", $pathRoot);
        $this->yellow->system->set("coreContentHomeDirectory", $pathHome);
        date_default_timezone_set($this->yellow->system->get("coreServerTimezone"));
    }
    
    // Save system settings to file
    public function save($fileName, $settings) {
        $this->modified = time();
        $settingsNew = new YellowArray();
        foreach ($settings as $key=>$value) {
            if (!empty($key) && !strempty($value)) {
                $this->set($key, $value);
                $settingsNew[$key] = $value;
            }
        }
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileData = $this->yellow->toolbox->setTextSettings($fileData, "", "", $settingsNew);
        return $this->yellow->toolbox->createFile($fileName, $fileData);
    }
    
    // Set default system setting
    public function setDefault($key, $value) {
        $this->settingsDefaults[$key] = $value;
    }
    
    // Set system setting
    public function set($key, $value) {
        $this->settings[$key] = $value;
    }
    
    // Return system setting
    public function get($key) {
        if (isset($this->settings[$key])) {
            $value = $this->settings[$key];
        } else {
            $value = isset($this->settingsDefaults[$key]) ? $this->settingsDefaults[$key] : "";
        }
        return $value;
    }
    
    // Return system setting, HTML encoded
    public function getHtml($key) {
        return htmlspecialchars($this->get($key));
    }
    
    // Return system settings
    public function getSettings($filterStart = "", $filterEnd = "") {
        $settings = array();
        if (empty($filterStart) && empty($filterEnd)) {
            $settings = array_merge($this->settingsDefaults->getArrayCopy(), $this->settings->getArrayCopy());
        } else {
            foreach (array_merge($this->settingsDefaults->getArrayCopy(), $this->settings->getArrayCopy()) as $key=>$value) {
                if (!empty($filterStart) && substru($key, 0, strlenu($filterStart))==$filterStart) $settings[$key] = $value;
                if (!empty($filterEnd) && substru($key, -strlenu($filterEnd))==$filterEnd) $settings[$key] = $value;
            }
        }
        return $settings;
    }
    
    // Return supported values for system setting, empty if not known
    public function getValues($key) {
        $values = array();
        if ($key=="email") {
            foreach ($this->yellow->user->settings as $userKey=>$userValue) {
                array_push($values, $userKey);
            }
        } elseif ($key=="theme") {
            $path = $this->yellow->system->get("coreThemeDirectory");
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.css$/", true, false, false) as $entry) {
                array_push($values, lcfirst(substru($entry, 0, -4)));
            }
        } elseif ($key=="language") {
            foreach ($this->yellow->language->settings as $languageKey=>$languageValue) {
                array_push($values, $languageKey);
            }
        } elseif ($key=="layout") {
            $path = $this->yellow->system->get("coreLayoutDirectory");
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.html$/", true, false, false) as $entry) {
                array_push($values, lcfirst(substru($entry, 0, -5)));
            }
        }
        return $values;
    }
    
    // Return system settings modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }
    
    // Check if system setting exists
    public function isExisting($key) {
        return isset($this->settings[$key]);
    }
}

class YellowUser {
    public $yellow;         // access to API
    public $modified;       // user modification date
    public $settings;       // user settings
    public $email;          // current email
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->settings = new YellowArray();
        $this->email = "";
    }

    // Load user settings from file
    public function load($fileName) {
        if (defined("DEBUG") && DEBUG>=2) echo "YellowUser::load file:$fileName<br/>\n";
        $this->modified = $this->yellow->toolbox->getFileModified($fileName);
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $this->settings = $this->yellow->toolbox->getTextSettings($fileData, "email");
    }

    // Save user settings to file
    public function save($fileName, $email, $settings) {
        $this->modified = time();
        $settingsNew = new YellowArray();
        $settingsNew["email"] = $email;
        foreach ($settings as $key=>$value) {
            if (!empty($key) && !strempty($value)) {
                $this->setUser($key, $value, $email);
                $settingsNew[$key] = $value;
            }
        }
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileData = $this->yellow->toolbox->setTextSettings($fileData, "email", $email, $settingsNew);
        return $this->yellow->toolbox->createFile($fileName, $fileData);
    }
    
    // Remove user settings from file
    public function remove($fileName, $email) {
        $this->modified = time();
        if (isset($this->settings[$email])) unset($this->settings[$email]);
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileData = $this->yellow->toolbox->unsetTextSettings($fileData, "email", $email);
        return $this->yellow->toolbox->createFile($fileName, $fileData);
    }
    
    // Set current email
    public function set($email) {
        $this->email = $email;
    }
    
    // Set user setting
    public function setUser($key, $value, $email) {
        if (!isset($this->settings[$email])) $this->settings[$email] = new YellowArray();
        $this->settings[$email][$key] = $value;
    }
    
    // Return user setting
    public function getUser($key, $email = "") {
        if (empty($email)) $email = $this->email;
        return isset($this->settings[$email]) && isset($this->settings[$email][$key]) ? $this->settings[$email][$key] : "";
    }

    // Return user setting, HTML encoded
    public function getUserHtml($key, $email = "") {
        return htmlspecialchars($this->getUser($key, $email));
    }

    // Return user settings
    public function getSettings($email = "") {
        $settings = array();
        if (empty($email)) $email = $this->email;
        if (isset($this->settings[$email])) $settings = $this->settings[$email]->getArrayCopy();
        return $settings;
    }
    
    // Return user settings modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }
    
    // Check if user setting exists
    public function isUser($key, $email = "") {
        if (empty($email)) $email = $this->email;
        return isset($this->settings[$email]) && isset($this->settings[$email][$key]);
    }
    
    // Check if user exists
    public function isExisting($email) {
        return isset($this->settings[$email]);
    }
}

class YellowLanguage {
    public $yellow;             // access to API
    public $modified;           // language modification date
    public $settings;           // language settings
    public $settingsDefaults;   // language settings defaults
    public $language;           // current language
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->settings = new YellowArray();
        $this->settingsDefaults = new YellowArray();
        $this->language = "";
    }
    
    // Load language settings from file or directory
    public function load($fileName) {
        if (substru($fileName, -1, 1)!="/") {
            $path = dirname($fileName);
            $regex = "/^".basename($fileName)."$/";
        } else {
            $path = $fileName;
            $regex = "/^.*\.txt$/";
        }
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowLanguage::load file:$entry<br/>\n";
            $this->modified = max($this->modified, filemtime($entry));
            $fileData = $this->yellow->toolbox->readFile($entry);
            $settings = $this->yellow->toolbox->getTextSettings($fileData, "language");
            foreach ($settings as $language=>$block) {
                if (!isset($this->settings[$language])) {
                    $this->settings[$language] = $block;
                } else {
                    foreach ($block as $key=>$value) {
                        $this->settings[$language][$key] = $value;
                    }
                }
            }
        }
        foreach ($this->settings->getArrayCopy() as $key=>$value) {
            if (!isset($this->settings[$key]["languageDescription"])) {
                unset($this->settings[$key]);
            }
        }
        $callback = function ($a, $b) {
            return strnatcmp($a["languageDescription"], $b["languageDescription"]);
        };
        $this->settings->uasort($callback);
    }
    
    // Set current language
    public function set($language) {
        $this->language = $language;
    }
    
    // Set default language setting
    public function setDefault($key) {
        $this->settingsDefaults[$key] = true;
    }
    
    // Set language setting
    public function setText($key, $value, $language) {
        if (!isset($this->settings[$language])) $this->settings[$language] = new YellowArray();
        $this->settings[$language][$key] = $value;
    }
    
    // Return language setting
    public function getText($key, $language = "") {
        if (empty($language)) $language = $this->language;
        return $this->isText($key, $language) ? $this->settings[$language][$key] : "[$key]";
    }
    
    // Return language setting, HTML encoded
    public function getTextHtml($key, $language = "") {
        return htmlspecialchars($this->getText($key, $language));
    }
    
    // Return human readable date
    public function getDateFormatted($timestamp, $format, $language = "") {
        $dateMonthsNominative = preg_split("/\s*,\s*/", $this->getText("coreDateMonthsNominative", $language));
        $dateMonthsGenitive = preg_split("/\s*,\s*/", $this->getText("coreDateMonthsGenitive", $language));
        $dateWeekdays = preg_split("/\s*,\s*/", $this->getText("coreDateWeekdays", $language));
        $monthNominative = $dateMonthsNominative[date("n", $timestamp) - 1];
        $monthGenitive = $dateMonthsGenitive[date("n", $timestamp) - 1];
        $weekday = $dateWeekdays[date("N", $timestamp) - 1];
        $timeZone = $this->yellow->system->get("coreServerTimezone");
        $timeZoneHelper = new DateTime(null, new DateTimeZone($timeZone));
        $timeZoneOffset = $timeZoneHelper->getOffset();
        $timeZoneAbbreviation = "GMT".($timeZoneOffset<0 ? "-" : "+").abs(intval($timeZoneOffset/3600));
        $format = preg_replace("/(?<!\\\)F/", addcslashes($monthNominative, "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)V/", addcslashes($monthGenitive, "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)M/", addcslashes(substru($monthNominative, 0, 3), "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)D/", addcslashes(substru($weekday, 0, 3), "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)l/", addcslashes($weekday, "A..Za..z"), $format);
        $format = preg_replace("/(?<!\\\)T/", addcslashes($timeZoneAbbreviation, "A..Za..z"), $format);
        return date($format, $timestamp);
    }
    
    // Return human readable date, relative to today
    public function getDateRelative($timestamp, $format, $daysLimit, $language = "") {
        $timeDifference = time() - $timestamp;
        $days = abs(intval($timeDifference / 86400));
        $key = $timeDifference>=0 ? "coreDatePast" : "coreDateFuture";
        $tokens = preg_split("/\s*,\s*/", $this->getText($key, $language));
        if (count($tokens)>=8) {
            if ($days<=$daysLimit || $daysLimit==0) {
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
                $output = preg_replace("/@x/i", $this->getDateFormatted($timestamp, $format, $language), $tokens[7]);
            }
        } else {
            $output = "[$key]";
        }
        return $output;
    }
    
    // Return language settings
    public function getSettings($filterStart = "", $filterEnd = "", $language = "") {
        $settings = array();
        if (empty($language)) $language = $this->language;
        if (isset($this->settings[$language])) {
            if (empty($filterStart) && empty($filterEnd)) {
                $settings = $this->settings[$language]->getArrayCopy();
            } else {
                foreach ($this->settings[$language] as $key=>$value) {
                    if (!empty($filterStart) && substru($key, 0, strlenu($filterStart))==$filterStart) $settings[$key] = $value;
                    if (!empty($filterEnd) && substru($key, -strlenu($filterEnd))==$filterEnd) $settings[$key] = $value;
                }
            }
        }
        return $settings;
    }
    
    // Return language settings modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }
    
    // Normalise date into known format
    public function normaliseDate($text, $language = "") {
        if (preg_match("/^\d+\-\d+$/", $text)) {
            $output = $this->getDateFormatted(strtotime($text), $this->getText("coreDateFormatShort", $language), $language);
        } elseif (preg_match("/^\d+\-\d+\-\d+$/", $text)) {
            $output = $this->getDateFormatted(strtotime($text), $this->getText("coreDateFormatMedium", $language), $language);
        } elseif (preg_match("/^\d+\-\d+\-\d+ \d+\:\d+$/", $text)) {
            $output = $this->getDateFormatted(strtotime($text), $this->getText("coreDateFormatLong", $language), $language);
        } else {
            $output = $text;
        }
        return $output;
    }
    
    // Check if language setting exists
    public function isText($key, $language = "") {
        if (empty($language)) $language = $this->language;
        return isset($this->settings[$language]) && isset($this->settings[$language][$key]);
    }

    // Check if language exists
    public function isExisting($language) {
        return isset($this->settings[$language]);
    }
}

class YellowExtension {
    public $yellow;     // access to API
    public $modified;   // extension modification date
    public $data;       // extension data

    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->modified = 0;
        $this->data = array();
    }
    
    // Load extensions
    public function load($path) {
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.php$/", true, false) as $entry) {
            if (defined("DEBUG") && DEBUG>=3) echo "YellowExtension::load file:$entry<br/>\n";
            $this->modified = max($this->modified, filemtime($entry));
            require_once($entry);
            $name = $this->yellow->lookup->normaliseName(basename($entry), true, true);
            $this->register(lcfirst($name), "Yellow".ucfirst($name));
        }
        $callback = function ($a, $b) {
            return $a["priority"] - $b["priority"];
        };
        uasort($this->data, $callback);
        foreach ($this->data as $key=>$value) {
            if (method_exists($this->data[$key]["object"], "onLoad")) $this->data[$key]["object"]->onLoad($this->yellow);
        }
    }
    
    // Register extension
    public function register($key, $class) {
        if (!$this->isExisting($key) && class_exists($class)) {
            $this->data[$key] = array();
            $this->data[$key]["object"] = $class=="YellowCore" ? new stdClass : new $class;
            $this->data[$key]["class"] = $class;
            $this->data[$key]["version"] = defined("$class::VERSION") ? $class::VERSION : 0;
            $this->data[$key]["priority"] = defined("$class::PRIORITY") ? $class::PRIORITY : count($this->data) + 10;
        }
    }
    
    // Return extension
    public function get($key) {
        return $this->data[$key]["object"];
    }
    
    // Return extensions modification date, Unix time or HTTP format
    public function getModified($httpFormat = false) {
        return $httpFormat ? $this->yellow->toolbox->getHttpDateFormatted($this->modified) : $this->modified;
    }
    
    // Check if extension exists
    public function isExisting($key) {
        return isset($this->data[$key]);
    }
}

class YellowLookup {
    public $yellow;             // access to API
    public $requestHandler;     // request handler name
    public $commandHandler;     // command handler name
    public $layoutArguments;    // layout arguments
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
    }
    
    // Return file system information
    public function findFileSystemInformation() {
        $pathInstall = substru(__DIR__, 0, 1-strlenu($this->yellow->system->get("coreExtensionDirectory")));
        $pathBase = $this->yellow->system->get("coreContentDirectory");
        $pathRoot = $this->yellow->system->get("coreContentRootDirectory");
        $pathHome = $this->yellow->system->get("coreContentHomeDirectory");
        if (!$this->yellow->system->get("coreMultiLanguageMode")) $pathRoot = "";
        if (!empty($pathRoot)) {
            $token = $root = rtrim($pathRoot, "/");
            foreach ($this->yellow->toolbox->getDirectoryEntries($pathBase, "/.*/", true, true, false) as $entry) {
                if (empty($firstRoot)) $firstRoot = $token = $entry;
                if ($this->normaliseToken($entry)==$root) {
                    $token = $entry;
                    break;
                }
            }
            $pathRoot = $this->normaliseToken($token)."/";
            $pathBase .= "$firstRoot/";
        }
        if (!empty($pathHome)) {
            $token = $home = rtrim($pathHome, "/");
            foreach ($this->yellow->toolbox->getDirectoryEntries($pathBase, "/.*/", true, true, false) as $entry) {
                if (empty($firstHome)) $firstHome = $token = $entry;
                if ($this->normaliseToken($entry)==$home) {
                    $token = $entry;
                    break;
                }
            }
            $pathHome = $this->normaliseToken($token)."/";
        }
        return array($pathInstall, $pathRoot, $pathHome);
    }

    // Return root locations
    public function findRootLocations($includePath = true) {
        $locations = array();
        $pathBase = $this->yellow->system->get("coreContentDirectory");
        $pathRoot = $this->yellow->system->get("coreContentRootDirectory");
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
        $invalid = false;
        $location = "/";
        $pathBase = $this->yellow->system->get("coreContentDirectory");
        $pathRoot = $this->yellow->system->get("coreContentRootDirectory");
        $pathHome = $this->yellow->system->get("coreContentHomeDirectory");
        $fileDefault = $this->yellow->system->get("coreContentDefaultFile");
        $fileExtension = $this->yellow->system->get("coreContentExtension");
        if (substru($fileName, 0, strlenu($pathBase))==$pathBase && mb_check_encoding($fileName, "UTF-8")) {
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
            if ($token!=$fileDefault) {
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
        $found = $invalid = false;
        $path = $this->yellow->system->get("coreContentDirectory");
        $pathRoot = $this->yellow->system->get("coreContentRootDirectory");
        $pathHome = $this->yellow->system->get("coreContentHomeDirectory");
        $fileDefault = $this->yellow->system->get("coreContentDefaultFile");
        $fileExtension = $this->yellow->system->get("coreContentExtension");
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
                    if ($token==$fileDefault) $invalid = true;
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
            $regex = "/^[\d\-\_\.]*".str_replace("-", ".", $token)."$/";
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
            $regex = "/^[\d\-\_\.]*($fileDefault)$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry) {
                if ($this->normaliseToken($entry, $fileExtension)==$fileDefault) {
                    $token = $entry;
                    break;
                }
            }
        }
        return $includePath ? "$path/$token" : $token;
    }
    
    // Return children from location
    public function findChildrenFromLocation($location) {
        $fileNames = array();
        $fileDefault = $this->yellow->system->get("coreContentDefaultFile");
        $fileExtension = $this->yellow->system->get("coreContentExtension");
        if (!$this->isFileLocation($location)) {
            $path = $this->findFileFromLocation($location, true);
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false) as $entry) {
                $token = $this->findFileDefault($path.$entry, $fileDefault, $fileExtension, false);
                array_push($fileNames, $path.$entry."/".$token);
            }
            if (!$this->isRootLocation($location)) {
                $regex = "/^.*\\".$fileExtension."$/";
                foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry) {
                    if ($this->normaliseToken($entry, $fileExtension)==$fileDefault) continue;
                    array_push($fileNames, $path.$entry);
                }
            }
        }
        return $fileNames;
    }

    // Return language from file path
    public function findLanguageFromFile($fileName, $languageDefault) {
        $language = $languageDefault;
        $pathBase = $this->yellow->system->get("coreContentDirectory");
        $pathRoot = $this->yellow->system->get("coreContentRootDirectory");
        if (!empty($pathRoot)) {
            $fileName = substru($fileName, strlenu($pathBase));
            if (preg_match("/^(.+?)\//", $fileName, $matches)) {
                $name = $this->normaliseToken($matches[1]);
                if (strlenu($name)==2) $language = $name;
            }
        }
        return $language;
    }

    // Return file path from media location
    public function findFileFromMedia($location) {
        $fileName = null;
        if ($this->isFileLocation($location)) {
            $mediaLocationLength = strlenu($this->yellow->system->get("coreMediaLocation"));
            if (substru($location, 0, $mediaLocationLength)==$this->yellow->system->get("coreMediaLocation")) {
                $fileName = $this->yellow->system->get("coreMediaDirectory").substru($location, 7);
            }
        }
        return $fileName;
    }
    
    // Return file path from system location
    public function findFileFromSystem($location) {
        $fileName = null;
        if (preg_match("/\.(css|gif|ico|js|jpg|png|svg|woff|woff2)$/", $location)) {
            $extensionLocationLength = strlenu($this->yellow->system->get("coreExtensionLocation"));
            $themeLocationLength = strlenu($this->yellow->system->get("coreThemeLocation"));
            if (substru($location, 0, $extensionLocationLength)==$this->yellow->system->get("coreExtensionLocation")) {
                $fileName = $this->yellow->system->get("coreExtensionDirectory").substru($location, $extensionLocationLength);
            } elseif (substru($location, 0, $themeLocationLength)==$this->yellow->system->get("coreThemeLocation")) {
                $fileName = $this->yellow->system->get("coreThemeDirectory").substru($location, $themeLocationLength);
            }
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
    
    // Normalise prefix
    public function normalisePrefix($text) {
        $prefix = "";
        if (preg_match("/^([\d\-\_\.]*)(.*)$/", $text, $matches)) $prefix = $matches[1];
        if (!empty($prefix) && !preg_match("/[\-\_\.]$/", $prefix)) $prefix .= "-";
        return $prefix;
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
            if (!isset($array[$key])) $array[$key] = 0;
            $array[$key] += $value;
        }
        return $array;
    }
    
    // Normalise location, make absolute location
    public function normaliseLocation($location, $pageLocation, $filterStrict = true) {
        if (!preg_match("/^\w+:/", trim(html_entity_decode($location, ENT_QUOTES, "UTF-8")))) {
            $pageBase = $this->yellow->page->base;
            $mediaBase = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreMediaLocation");
            if (!preg_match("/^\#/", $location)) {
                if (!preg_match("/^\//", $location)) {
                    $location = $this->getDirectoryLocation($pageBase.$pageLocation).$location;
                } elseif (!preg_match("#^($pageBase|$mediaBase)#", $location)) {
                    $location = $pageBase.$location;
                }
            }
            $location = str_replace("/./", "/", $location);
            $location = str_replace(":", $this->yellow->toolbox->getLocationArgumentsSeparator(), $location);
        } else {
            if ($filterStrict && !preg_match("/^(http|https|ftp|mailto|tel):/", $location)) $location = "error-xss-filter";
        }
        return $location;
    }
    
    // Normalise URL, make absolute URL
    public function normaliseUrl($scheme, $address, $base, $location, $filterStrict = true) {
        if (!preg_match("/^\w+:/", $location)) {
            $url = "$scheme://$address$base$location";
        } else {
            if ($filterStrict && !preg_match("/^(http|https|ftp|mailto|tel):/", $location)) $location = "error-xss-filter";
            $url = $location;
        }
        return $url;
    }
    
    // Return URL information
    public function getUrlInformation($url) {
        $scheme = $address = $base = "";
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
    
    // Return redirect location
    public function getRedirectLocation($location) {
        if ($this->isFileLocation($location)) {
            $location = "$location/";
        } else {
            $languageDefault = $this->yellow->system->get("language");
            $language = $this->yellow->toolbox->detectBrowserLanguage($this->yellow->content->getLanguages(), $languageDefault);
            $location = "/$language/";
        }
        return $location;
    }
    
    // Check if clean URL is requested
    public function isRequestCleanUrl($location) {
        return isset($_REQUEST["clean-url"]) && substru($location, -1, 1)=="/";
    }
    
    // Check if location is specifying root
    public function isRootLocation($location) {
        return substru($location, 0, 1)!="/";
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
            $redirect = $this->yellow->system->get("coreMultiLanguageMode");
        }
        return $redirect;
    }
    
    // Check if location contains nested directories
    public function isNestedLocation($location, $fileName, $checkHomeLocation = false) {
        $nested = false;
        if (!$checkHomeLocation || $location==$this->yellow->content->getHomeLocation($location)) {
            $path = dirname($fileName);
            if (count($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", true, true, false))) $nested = true;
        }
        return $nested;
    }
    
    // Check if location is available
    public function isAvailableLocation($location, $fileName) {
        $available = true;
        $pathBase = $this->yellow->system->get("coreContentDirectory");
        if (substru($fileName, 0, strlenu($pathBase))==$pathBase) {
            $sharedLocation = $this->yellow->content->getHomeLocation($location).$this->yellow->system->get("coreContentSharedDirectory");
            if (substru($location, 0, strlenu($sharedLocation))==$sharedLocation) $available = false;
        }
        return $available;
    }
    
    // Check if location is within current HTTP request
    public function isActiveLocation($location, $currentLocation) {
        if ($this->isFileLocation($location)) {
            $active = $currentLocation==$location;
        } else {
            if ($location==$this->yellow->content->getHomeLocation($location)) {
                $active = $this->getDirectoryLocation($currentLocation)==$location;
            } else {
                $active = substru($currentLocation, 0, strlenu($location))==$location;
            }
        }
        return $active;
    }
    
    // Check if file is valid
    public function isValidFile($fileName) {
        $contentDirectoryLength = strlenu($this->yellow->system->get("coreContentDirectory"));
        $mediaDirectoryLength = strlenu($this->yellow->system->get("coreMediaDirectory"));
        $systemDirectoryLength = strlenu($this->yellow->system->get("coreSystemDirectory"));
        return substru($fileName, 0, $contentDirectoryLength)==$this->yellow->system->get("coreContentDirectory") ||
            substru($fileName, 0, $mediaDirectoryLength)==$this->yellow->system->get("coreMediaDirectory") ||
            substru($fileName, 0, $systemDirectoryLength)==$this->yellow->system->get("coreSystemDirectory");
    }
    
    // Check if content file
    public function isContentFile($fileName) {
        $contentDirectoryLength = strlenu($this->yellow->system->get("coreContentDirectory"));
        return substru($fileName, 0, $contentDirectoryLength)==$this->yellow->system->get("coreContentDirectory");
    }
    
    // Check if media file
    public function isMediaFile($fileName) {
        $mediaDirectoryLength = strlenu($this->yellow->system->get("coreMediaDirectory"));
        return substru($fileName, 0, $mediaDirectoryLength)==$this->yellow->system->get("coreMediaDirectory");
    }
    
    // Check if system file
    public function isSystemFile($fileName) {
        $systemDirectoryLength = strlenu($this->yellow->system->get("coreSystemDirectory"));
        return substru($fileName, 0, $systemDirectoryLength)==$this->yellow->system->get("coreSystemDirectory");
    }
}

class YellowToolbox {
    
    // Return browser cookie from from current HTTP request
    public function getCookie($key) {
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : "";
    }
    
    // Return server argument from current HTTP request
    public function getServer($key) {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : "";
    }
    
    // Return location arguments from current HTTP request
    public function getLocationArguments() {
        return $this->getServer("LOCATION_ARGUMENTS");
    }
    
    // Return location arguments from current HTTP request, modify existing arguments
    public function getLocationArgumentsNew($key, $value) {
        $locationArguments = "";
        $found = false;
        $separator = $this->getLocationArgumentsSeparator();
        foreach (explode("/", $this->getServer("LOCATION_ARGUMENTS")) as $token) {
            if (preg_match("/^(.*?)$separator(.*)$/", $token, $matches)) {
                if ($matches[1]==$key) {
                    $matches[2] = $value;
                    $found = true;
                }
                if (!empty($matches[1]) && !strempty($matches[2])) {
                    if (!empty($locationArguments)) $locationArguments .= "/";
                    $locationArguments .= "$matches[1]:$matches[2]";
                }
            }
        }
        if (!$found && !empty($key) && !strempty($value)) {
            if (!empty($locationArguments)) $locationArguments .= "/";
            $locationArguments .= "$key:$value";
        }
        if (!empty($locationArguments)) {
            $locationArguments = $this->normaliseArguments($locationArguments, false, false);
            if (!$this->isLocationArgumentsPagination($locationArguments)) $locationArguments .= "/";
        }
        return $locationArguments;
    }
    
    // Return location arguments from current HTTP request, convert form parameters
    public function getLocationArgumentsCleanUrl() {
        $locationArguments = "";
        foreach (array_merge($_GET, $_POST) as $key=>$value) {
            if (!empty($key) && !strempty($value)) {
                if (!empty($locationArguments)) $locationArguments .= "/";
                $key = str_replace(array("/", ":", "="), array("\x1c", "\x1d", "\x1e"), $key);
                $value = str_replace(array("/", ":", "="), array("\x1c", "\x1d", "\x1e"), $value);
                $locationArguments .= "$key:$value";
            }
        }
        if (!empty($locationArguments)) {
            $locationArguments = $this->normaliseArguments($locationArguments, false, false);
            if (!$this->isLocationArgumentsPagination($locationArguments)) $locationArguments .= "/";
        }
        return $locationArguments;
    }

    // Return location arguments separator
    public function getLocationArgumentsSeparator() {
        return (strtoupperu(substru(PHP_OS, 0, 3))!="WIN") ? ":" : "=";
    }
    
    // Return human readable HTTP date
    public function getHttpDateFormatted($timestamp) {
        return gmdate("D, d M Y H:i:s", $timestamp)." GMT";
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
            case 420:   $text = "Not public"; break;
            case 430:   $text = "Login failed"; break;
            case 434:   $text = "Can create"; break;
            case 435:   $text = "Can restore"; break;
            case 500:   $text = "Server error"; break;
            case 503:   $text = "Service unavailable"; break;
            default:    $text = "Error $statusCode";
        }
        $serverProtocol = $this->getServer("SERVER_PROTOCOL");
        if (!preg_match("/^HTTP\//", $serverProtocol)) $serverProtocol = "HTTP/1.1";
        return $shortFormat ? $text : "$serverProtocol $statusCode $text";
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
            "md" => "text/markdown",
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
    
    // Return files and directories
    public function getDirectoryEntries($path, $regex = "/.*/", $sort = true, $directories = true, $includePath = true) {
        $entries = array();
        $directoryHandle = @opendir($path);
        if ($directoryHandle) {
            $path = rtrim($path, "/");
            while (($entry = readdir($directoryHandle))!==false) {
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
            closedir($directoryHandle);
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
    
    // Append file
    public function appendFile($fileName, $fileData, $mkdir = false) {
        $ok = false;
        if ($mkdir) {
            $path = dirname($fileName);
            if (!empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
        }
        $fileHandle = @fopen($fileName, "ab");
        if ($fileHandle) {
            clearstatcache(true, $fileName);
            if (flock($fileHandle, LOCK_EX)) {
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
    
    // Rename directory
    public function renameDirectory($pathSource, $pathDestination, $mkdir = false) {
        return $pathSource==$pathDestination || $this->renameFile($pathSource, $pathDestination, $mkdir);
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
            $fileNameDestination .= "-".str_replace(array(" ", ":"), "-", date("Y-m-d H:i:s"));
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
                if ($file->getType()=="dir") {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
            $ok = @rmdir($path);
        } else {
            if (!is_dir($pathTrash)) @mkdir($pathTrash, 0777, true);
            $pathDestination = $pathTrash;
            $pathDestination .= basename($path);
            $pathDestination .= "-".str_replace(array(" ", ":"), "-", date("Y-m-d H:i:s"));
            $ok = @rename($path, $pathDestination);
        }
        return $ok;
    }
    
    // Set file/directory modification date, Unix time
    public function modifyFile($fileName, $modified) {
        clearstatcache(true, $fileName);
        return @touch($fileName, $modified);
    }
    
    // Return file/directory modification date, Unix time
    public function getFileModified($fileName) {
        return (is_file($fileName) || is_dir($fileName)) ? filemtime($fileName) : 0;
    }
    
    // Return file/directory deletion date, Unix time
    public function getFileDeleted($fileName) {
        $deleted = 0;
        $text = basename($fileName);
        $text = ($pos = strrposu($text, ".")) ? substru($text, 0, $pos) : $text;
        if (preg_match("#^(.+)-(\d\d\d\d-\d\d-\d\d)-(\d\d)-(\d\d)-(\d\d)$#", $text, $matches)) {
            $deleted = strtotime("$matches[2] $matches[3]:$matches[4]:$matches[5]");
        }
        return $deleted;
    }
    
    // Return file type
    public function getFileType($fileName) {
        return strtoloweru(($pos = strrposu($fileName, ".")) ? substru($fileName, $pos+1) : "");
    }
    
    // Return file group
    public function getFileGroup($fileName, $path) {
        $group = "none";
        if (preg_match("#^$path(.+?)\/#", $fileName, $matches)) $group = strtoloweru($matches[1]);
        return $group;
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
    
    // Return lines from text, including newline
    public function getTextLines($text) {
        $lines = preg_split("/\n/", $text);
        foreach ($lines as &$line) {
            $line = $line."\n";
        }
        if (strempty($text) || substru($text, -1, 1)=="\n") array_pop($lines);
        return $lines;
    }
    
    // Return settings from text
    function getTextSettings($text, $blockStart) {
        $settings = new YellowArray();
        if (empty($blockStart)) {
            foreach ($this->getTextLines($text) as $line) {
                if (preg_match("/^\#/", $line)) continue;
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (!empty($matches[1]) && !strempty($matches[2])) {
                        $settings[$matches[1]] = $matches[2];
                        
                    }
                }
            }
        } else {
            $blockKey = "";
            foreach ($this->getTextLines($text) as $line) {
                if (preg_match("/^\#/", $line)) continue;
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])==$blockStart && !strempty($matches[2])) {
                        $blockKey = $matches[2];
                        $settings[$blockKey] = new YellowArray();
                    }
                    if (!empty($blockKey) && !empty($matches[1]) && !strempty($matches[2])) {
                        $settings[$blockKey][$matches[1]] = $matches[2];
                    }
                }
            }
        }
        return $settings;
    }
    
    // Set settings in text
    function setTextSettings($text, $blockStart, $blockKey, $settings) {
        $textNew = "";
        if (empty($blockStart)) {
            foreach ($this->getTextLines($text) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (!empty($matches[1]) && isset($settings[$matches[1]])) {
                        $textNew .= "$matches[1]: ".$settings[$matches[1]]."\n";
                        unset($settings[$matches[1]]);
                        continue;
                    }
                }
                $textNew .= $line;
            }
            foreach ($settings as $key=>$value) {
                $textNew .= (strposu($key, "/") ? $key : ucfirst($key)).": $value\n";
            }
        } else {
            $scan = false;
            $textStart = $textMiddle = $textEnd = "";
            foreach ($this->getTextLines($text) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])==$blockStart && !strempty($matches[2])) {
                        $scan = lcfirst($matches[2])==lcfirst($blockKey);
                    }
                }
                if (!$scan && empty($textMiddle)) {
                    $textStart .= $line;
                } elseif ($scan) {
                    $textMiddle .= $line;
                } else {
                    $textEnd .= $line;
                }
            }
            $textSettings = "";
            foreach ($this->getTextLines($textMiddle) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (!empty($matches[1]) && isset($settings[$matches[1]])) {
                        $textSettings .= "$matches[1]: ".$settings[$matches[1]]."\n";
                        unset($settings[$matches[1]]);
                        continue;
                    }
                    $textSettings .= $line;
                }
            }
            foreach ($settings as $key=>$value) {
                $textSettings .= (strposu($key, "/") ? $key : ucfirst($key)).": $value\n";
            }
            if (!empty($textMiddle)) {
                $textMiddle = $textSettings;
                if (!empty($textEnd)) $textMiddle .= "\n";
            } else {
                if (!empty($textStart)) $textEnd .= "\n";
                $textEnd .= $textSettings;
            }
            $textNew = $textStart.$textMiddle.$textEnd;
        }
        return $textNew;
    }

    // Remove settings from text
    function unsetTextSettings($text, $blockStart, $blockKey) {
        $textNew = "";
        if (!empty($blockStart)) {
            $scan = false;
            $textStart = $textMiddle = $textEnd = "";
            foreach ($this->getTextLines($text) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])==$blockStart && !strempty($matches[2])) {
                        $scan = lcfirst($matches[2])==lcfirst($blockKey);
                    }
                }
                if (!$scan && empty($textMiddle)) {
                    $textStart .= $line;
                } elseif ($scan) {
                    $textMiddle .= $line;
                } else {
                    $textEnd .= $line;
                }
            }
            $textNew = rtrim($textStart.$textEnd)."\n";
        }
        return $textNew;
    }
    
    // Return attributes from text
    public function getTextAttributes($text) {
        $tokens = array();
        $posStart = $posQuote = 0;
        $textLength = strlenb($text);
        for ($pos=0; $pos<$textLength; ++$pos) {
            if ($text[$pos]==" " && !$posQuote) {
                if ($pos>$posStart) array_push($tokens, substrb($text, $posStart, $pos-$posStart));
                $posStart = $pos+1;
            }
            if ($text[$pos]=="=" && !$posQuote) {
                if ($pos>$posStart) array_push($tokens, substrb($text, $posStart, $pos-$posStart));
                array_push($tokens, "=");
                $posStart = $pos+1;
            }
            if ($text[$pos]=="\"") {
                if ($posQuote) {
                    if ($pos>$posQuote) array_push($tokens, substrb($text, $posQuote+1, $pos-$posQuote-1));
                    $posQuote = 0;
                    $posStart = $pos+1;
                } else {
                    if ($pos==$posStart) $posQuote = $pos;
                }
            }
        }
        if ($pos>$posStart && !$posQuote) {
            array_push($tokens, substrb($text, $posStart, $pos-$posStart));
        }
        $attributes = array();
        for ($i=0; $i<count($tokens); ++$i) {
            if ($i+2<count($tokens) && $tokens[$i+1]=="=") {
                $key = $tokens[$i];
                $value = $tokens[$i+2];
                $i += 2;
            } else {
                $key = $value = $tokens[$i];
            }
            if (!strempty($key) && !strempty($value)) {
                $attributes[$key] = $value;
            }
        }
        return $attributes;
    }
    
    // Return array of specific size from text
    public function getTextList($text, $separator, $size) {
        $tokens = explode($separator, $text, $size);
        return array_pad($tokens, $size, null);
    }
    
    // Return array of variable size from text, space separated
    public function getTextArguments($text, $optional = "-", $sizeMin = 9) {
        $text = preg_replace("/\s+/s", " ", trim($text));
        $tokens = str_getcsv($text, " ", "\"");
        foreach ($tokens as $key=>$value) {
            if ($value==$optional) $tokens[$key] = "";
        }
        return array_pad($tokens, $sizeMin, null);
    }
    
    // Return text from array, space separated
    public function getTextString($tokens, $optional = "-") {
        $text = "";
        foreach ($tokens as $token) {
            if (preg_match("/\s/", $token)) $token = "\"$token\"";
            if (empty($token)) $token = $optional;
            if (!empty($text)) $text .= " ";
            $text .= $token;
        }
        return $text;
    }

    // Return number of words in text
    public function getTextWords($text) {
        $text = preg_replace("/([\p{Han}\p{Hiragana}\p{Katakana}]{3})/u", "$1 ", $text);
        $text = preg_replace("/(\pL|\p{N})/u", "x", $text);
        return str_word_count($text);
    }
    
    // Return text truncated at word boundary
    public function getTextTruncated($text, $lengthMax) {
        if (strlenu($text)>$lengthMax-1) {
            $text = substru($text, 0, $lengthMax);
            $pos = strrposu($text, " ");
            $text = substru($text, 0, $pos ? $pos : $lengthMax-1)."…";
        }
        return $text;
    }
    
    // Create text description, with or without HTML
    public function createTextDescription($text, $lengthMax = 0, $removeHtml = true, $endMarker = "", $endMarkerText = "") {
        $output = "";
        $elementsBlock = array("blockquote", "br", "div", "h1", "h2", "h3", "h4", "h5", "h6", "hr", "li", "ol", "p", "pre", "ul");
        $elementsVoid = array("area", "br", "col", "embed", "hr", "img", "input", "param", "source", "wbr");
        if ($lengthMax==0) $lengthMax = strlenu($text);
        if ($removeHtml) {
            $offsetBytes = 0;
            while (true) {
                $elementFound = preg_match("/<(\/?)([\!\?\w]+)(.*?)(\/?)>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
                $elementBefore = $elementFound ? substrb($text, $offsetBytes, $matches[0][1] - $offsetBytes) : substrb($text, $offsetBytes);
                $elementRawData = isset($matches[0][0]) ? $matches[0][0] : "";
                $elementStart = isset($matches[1][0]) ? $matches[1][0] : "";
                $elementName = isset($matches[2][0]) ? $matches[2][0] : "";
                if (!strempty($elementBefore)) {
                    $rawText = preg_replace("/\s+/s", " ", html_entity_decode($elementBefore, ENT_QUOTES, "UTF-8"));
                    if (empty($elementStart) && in_array(strtolower($elementName), $elementsBlock)) $rawText = rtrim($rawText)." ";
                    if (substru($rawText, 0, 1)==" " && (empty($output) || substru($output, -1)==" ")) $rawText = ltrim($rawText);
                    $output .= $this->getTextTruncated($rawText, $lengthMax);
                    $lengthMax -= strlenu($rawText);
                }
                if (!empty($elementRawData) && $elementRawData==$endMarker) {
                    $output .= $endMarkerText;
                    $lengthMax = 0;
                }
                if ($lengthMax<=0 || !$elementFound) break;
                $offsetBytes = $matches[0][1] + strlenb($matches[0][0]);
            }
            $output = preg_replace("/\s+\…$/s", "…", $output);
        } else {
            $elementsOpen = array();
            $offsetBytes = 0;
            while (true) {
                $elementFound = preg_match("/&.*?\;|<(\/?)([\!\?\w]+)(.*?)(\/?)>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
                $elementBefore = $elementFound ? substrb($text, $offsetBytes, $matches[0][1] - $offsetBytes) : substrb($text, $offsetBytes);
                $elementRawData = isset($matches[0][0]) ? $matches[0][0] : "";
                $elementStart = isset($matches[1][0]) ? $matches[1][0] : "";
                $elementName = isset($matches[2][0]) ? $matches[2][0] : "";
                $elementEnd = isset($matches[4][0]) ? $matches[4][0] : "";
                if (!strempty($elementBefore)) {
                    $output .= $this->getTextTruncated($elementBefore, $lengthMax);
                    $lengthMax -= strlenu($elementBefore);
                }
                if (!empty($elementRawData) && $elementRawData==$endMarker) {
                    $output .= $endMarkerText;
                    $lengthMax = 0;
                }
                if ($lengthMax<=0 || !$elementFound) break;
                if (!empty($elementName) && empty($elementEnd) && !in_array(strtolower($elementName), $elementsVoid)) {
                    if (empty($elementStart)) {
                        array_push($elementsOpen, $elementName);
                    } else {
                        array_pop($elementsOpen);
                    }
                }
                $output .= $elementRawData;
                if ($elementRawData[0]=="&") --$lengthMax;
                $offsetBytes = $matches[0][1] + strlenb($matches[0][0]);
            }
            $output = preg_replace("/\s+\…$/s", "…", $output);
            for ($i=count($elementsOpen)-1; $i>=0; --$i) {
                $output .= "</".$elementsOpen[$i].">";
            }
        }
        return trim($output);
    }
    
    // Create title from text
    public function createTextTitle($text) {
        if (preg_match("/^.*\/([\pL\d\-\_]+)/u", $text, $matches)) $text = str_replace("-", " ", ucfirst($matches[1]));
        return $text;
    }

    // Create random text for cryptography
    public function createSalt($length, $bcryptFormat = false) {
        $dataBuffer = $salt = "";
        $dataBufferSize = $bcryptFormat ? intval(ceil($length/4) * 3) : intval(ceil($length/2));
        if (empty($dataBuffer) && function_exists("random_bytes")) {
            $dataBuffer = @random_bytes($dataBufferSize);
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
    
    // Verify that token is not empty and identical, timing attack safe string comparison
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
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])==$key && !strempty($matches[2])) {
                        $value = $matches[2];
                        break;
                    }
                }
            }
        }
        return $value;
    }
    
    // Set meta data in raw data
    public function setMetaData($rawData, $key, $value) {
        if (preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)\-\-\-[\r\n]+(.*)$/s", $rawData, $parts)) {
            $found = false;
            $key = lcfirst($key);
            $rawDataMiddle = "";
            foreach ($this->getTextLines($parts[2]) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])==$key) {
                        $rawDataMiddle .= "$matches[1]: $value\n";
                        $found = true;
                        continue;
                    }
                }
                $rawDataMiddle .= $line;
            }
            if (!$found) $rawDataMiddle .= (strposu($key, "/") ? $key : ucfirst($key)).": $value\n";
            $rawDataNew = $parts[1]."---\n".$rawDataMiddle."---\n".$parts[3];
        } else {
            $rawDataNew = $rawData;
        }
        return $rawDataNew;
    }
    
    // Remove meta data in raw data
    public function unsetMetaData($rawData, $key) {
        if (preg_match("/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+(.+?)\-\-\-[\r\n]+(.*)$/s", $rawData, $parts)) {
            $key = lcfirst($key);
            $rawDataMiddle = "";
            foreach ($this->getTextLines($parts[2]) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])==$key) continue;
                }
                $rawDataMiddle .= $line;
            }
            $rawDataNew = $parts[1]."---\n".$rawDataMiddle."---\n".$parts[3];
        } else {
            $rawDataNew = $rawData;
        }
        return $rawDataNew;
    }

    // Detect server URL
    public function detectServerUrl() {
        $scheme = "http";
        if ($this->getServer("REQUEST_SCHEME")=="https" || $this->getServer("HTTPS")=="on") $scheme = "https";
        if ($this->getServer("HTTP_X_FORWARDED_PROTO")=="https") $scheme = "https";
        $address = $this->getServer("SERVER_NAME");
        $port = $this->getServer("SERVER_PORT");
        if ($port!=80 && $port!=443) $address .= ":$port";
        $base = "";
        if (preg_match("/^(.*)\/.*\.php$/", $this->getServer("SCRIPT_NAME"), $matches)) $base = $matches[1];
        return "$scheme://$address$base/";
    }
    
    // Detect server location
    public function detectServerLocation() {
        if (isset($_SERVER["REQUEST_URI"])) {
            $location = $_SERVER["REQUEST_URI"];
            $location = rawurldecode(($pos = strposu($location, "?")) ? substru($location, 0, $pos) : $location);
            $location = $this->normalisePath($location);
            if (substru($location, 0, 1)!="/") $location = "/".$location;
            $separator = $this->getLocationArgumentsSeparator();
            if (preg_match("/^(.*?\/)([^\/]+$separator.*)$/", $location, $matches)) {
                $_SERVER["LOCATION"] = $location = $matches[1];
                $_SERVER["LOCATION_ARGUMENTS"] = $matches[2];
                foreach (explode("/", $matches[2]) as $token) {
                    if (preg_match("/^(.*?)$separator(.*)$/", $token, $matches)) {
                        if (!empty($matches[1]) && !strempty($matches[2])) {
                            $matches[1] = str_replace(array("\x1c", "\x1d", "\x1e"), array("/", ":", "="), $matches[1]);
                            $matches[2] = str_replace(array("\x1c", "\x1d", "\x1e"), array("/", ":", "="), $matches[2]);
                            $_REQUEST[$matches[1]] = $matches[2];
                        }
                    }
                }
            } else {
                $_SERVER["LOCATION"] = $location;
                $_SERVER["LOCATION_ARGUMENTS"] = "";
            }
        }
        return $this->getServer("LOCATION");
    }
    
    // Detect server sitename
    public function detectServerSitename() {
        $sitename = "Localhost";
        if (preg_match("#^(www\.)?([\w\-]+)#", $this->getServer("SERVER_NAME"), $matches)) {
            $sitename = ucfirst($matches[2]);
        }
        return $sitename;
    }
    
    // Detect server timezone
    public function detectServerTimezone() {
        $timezone = @date_default_timezone_get();
        if (PHP_OS=="Darwin" && $timezone=="UTC") {
            if (preg_match("#zoneinfo/(.*)#", @readlink("/etc/localtime"), $matches)) $timezone = $matches[1];
        }
        return $timezone;
    }
    
    // Detect server name, version and operating system
    public function detectServerInformation() {
        if (preg_match("/^(\S+)\/(\S+)/", $this->getServer("SERVER_SOFTWARE"), $matches)) {
            $name = $matches[1];
            $version = $matches[2];
        } elseif (preg_match("/^(\pL+)/u", $this->getServer("SERVER_SOFTWARE"), $matches)) {
            $name = $matches[1];
            $version = "x.x.x";
        } else {
            $name = "CLI";
            $version = PHP_VERSION;
        }
        if (PHP_OS=="Darwin") {
            $os = "Mac";
        } elseif (strtoupperu(substru(PHP_OS, 0, 3))=="WIN") {
            $os = "Windows";
        } else {
            $os = PHP_OS;
        }
        return array($name, $version, $os);
    }
    
    // Detect browser language
    public function detectBrowserLanguage($languages, $languageDefault) {
        $languageFound = $languageDefault;
        foreach (preg_split("/\s*,\s*/", $this->getServer("HTTP_ACCEPT_LANGUAGE")) as $string) {
            list($language, $dummy) = $this->getTextList($string, ";", 2);
            if (!empty($language) && in_array($language, $languages)) {
                $languageFound = $language;
                break;
            }
        }
        return $languageFound;
    }
    
    // Detect image width, height, orientation and type for gif/jpg/png/svg
    public function detectImageInformation($fileName, $fileType = "") {
        $width = $height = $orientation = 0;
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
                        $dataMarker = $dataBuffer[$pos+1];
                        if ($dataMarker=="\xe1") {
                            $orientation = $this->getImageOrientationFromBuffer($dataBuffer, $pos+4, $dataBufferSize);
                        }
                        if ($dataMarker>="\xc0" && $dataMarker<="\xcf") {
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
                if (!feof($fileHandle) && preg_match("/<svg(\s.*?)>/s", $dataBuffer, $matches)) {
                    if (preg_match("/\swidth=\"(\d+)\"/s", $matches[1], $tokens)) $width = $tokens[1];
                    if (preg_match("/\sheight=\"(\d+)\"/s", $matches[1], $tokens)) $height = $tokens[1];
                    $type = $fileType;
                }
            }
            fclose($fileHandle);
        }
        return array($width, $height, $orientation, $type);
    }
    
    // Return image orientation from Exif
    public function getImageOrientationFromBuffer($dataBuffer, $pos, $size) {
        $orientation = 0;
        $dataSignature = substrb($dataBuffer, $pos, 6);
        if ($dataSignature=="\x45\x78\x69\x66\x00\x00" && $pos+14<=$size) {
            $startPos = $pos+6;
            $bigEndian = $dataBuffer[$startPos]=="M";
            $ifdOffset = $this->getLongFromBuffer($dataBuffer, $startPos+4, $bigEndian);
            $ifdStartPos = $startPos+$ifdOffset;
            $ifdCount = $ifdStartPos+2<=$size ? $this->getShortFromBuffer($dataBuffer, $ifdStartPos, $bigEndian) : 0;
            $pos = $ifdStartPos+2;
            while ($ifdCount && $pos+12<=$size) {
                $ifdTag = $this->getShortFromBuffer($dataBuffer, $pos, $bigEndian);
                $ifdFormat = $this->getShortFromBuffer($dataBuffer, $pos+2, $bigEndian);
                if ($ifdTag==0x8769 && $ifdFormat==4) {
                    $ifdOffset = $this->getLongFromBuffer($dataBuffer, $pos+8, $bigEndian);
                    $ifdStartPos = $startPos+$ifdOffset;
                    $ifdCount = $ifdStartPos+2<=$size ? $this->getShortFromBuffer($dataBuffer, $ifdStartPos, $bigEndian) : 0;
                    $pos = $ifdStartPos+2;
                    continue;
                }
                if ($ifdTag==0x0112 && $ifdFormat==3) {
                    $orientation = $this->getShortFromBuffer($dataBuffer, $pos+8, $bigEndian);
                    break;
                }
                --$ifdCount;
                $pos += 12;
            }
        }
        return $orientation;
    }
    
    // Return unsigned short value from buffer
    public  function getShortFromBuffer($dataBuffer, $pos, $bigEndian) {
        if ($bigEndian) {
            $value = (ord($dataBuffer[$pos])<<8) + ord($dataBuffer[$pos+1]);
        } else {
            $value = (ord($dataBuffer[$pos+1])<<8) + ord($dataBuffer[$pos]);
        }
        return $value;
    }
    
    // Return unsigned long value from buffer
    public function getLongFromBuffer($dataBuffer, $pos, $bigEndian) {
        if ($bigEndian) {
            $value = (ord($dataBuffer[$pos])<<24) + (ord($dataBuffer[$pos+1])<<16) +
                (ord($dataBuffer[$pos+2])<<8) + ord($dataBuffer[$pos+3]);
        } else {
            $value = (ord($dataBuffer[$pos+3])<<24) + (ord($dataBuffer[$pos+2])<<16) +
                (ord($dataBuffer[$pos+1])<<8) + ord($dataBuffer[$pos]);
        }
        return $value;
    }
    
    // Normalise location arguments
    public function normaliseArguments($text, $appendSlash = true, $filterStrict = true) {
        if ($appendSlash) $text .= "/";
        if ($filterStrict) $text = str_replace(" ", "-", strtoloweru($text));
        $text = str_replace(":", $this->getLocationArgumentsSeparator(), $text);
        return str_replace(array("%2F","%3A","%3D"), array("/",":","="), rawurlencode($text));
    }
    
    // Normalise elements and attributes in HTML/SVG data
    public function normaliseData($text, $type = "html", $filterStrict = true) {
        $output = "";
        $elementsHtml = array(
            "a", "abbr", "acronym", "address", "area", "article", "aside", "audio", "b", "bdi", "bdo", "big", "blink", "blockquote", "body", "br", "button", "canvas", "caption", "center", "cite", "code", "col", "colgroup", "content", "data", "datalist", "dd", "decorator", "del", "details", "dfn", "dir", "div", "dl", "dt", "element", "em", "fieldset", "figcaption", "figure", "font", "footer", "form", "h1", "h2", "h3", "h4", "h5", "h6", "head", "header", "hgroup", "hr", "html", "i", "iframe", "image", "img", "input", "ins", "kbd", "label", "legend", "li", "main", "map", "mark", "marquee", "menu", "menuitem", "meta", "meter", "nav", "nobr", "ol", "optgroup", "option", "output", "p", "pre", "progress", "q", "rp", "rt", "ruby", "s", "samp", "section", "select", "shadow", "small", "source", "spacer", "span", "strike", "strong", "style", "sub", "summary", "sup", "table", "tbody", "td", "template", "textarea", "tfoot", "th", "thead", "time", "title", "tr", "track", "tt", "u", "ul", "var", "video", "wbr");
        $elementsSvg = array(
            "svg", "altglyph", "altglyphdef", "altglyphitem", "animatecolor", "animatemotion", "animatetransform", "circle", "clippath", "defs", "desc", "ellipse", "feblend", "fecolormatrix", "fecomponenttransfer", "fecomposite", "feconvolvematrix", "fediffuselighting", "fedisplacementmap", "fedistantlight", "feflood", "fefunca", "fefuncb", "fefuncg", "fefuncr", "fegaussianblur", "femerge", "femergenode", "femorphology", "feoffset", "fepointlight", "fespecularlighting", "fespotlight", "fetile", "feturbulence", "filter", "font", "g", "glyph", "glyphref", "hkern", "image", "line", "lineargradient", "marker", "mask", "metadata", "mpath", "path", "pattern", "polygon", "polyline", "radialgradient", "rect", "stop", "switch", "symbol", "text", "textpath", "title", "tref", "tspan", "use", "view", "vkern");
        $attributesHtml = array(
            "accept", "action", "align", "allowfullscreen", "alt", "autocomplete", "background", "bgcolor", "border", "cellpadding", "cellspacing", "charset", "checked", "cite", "class", "clear", "color", "cols", "colspan", "content", "contenteditable", "controls", "coords", "crossorigin", "datetime", "default", "dir", "disabled", "download", "enctype", "face", "for", "frameborder", "headers", "height", "hidden", "high", "href", "hreflang", "id", "integrity", "ismap", "label", "lang", "list", "loop", "low", "max", "maxlength", "media", "method", "min", "multiple", "name", "noshade", "novalidate", "nowrap", "open", "optimum", "pattern", "placeholder", "poster", "prefix", "preload", "property", "pubdate", "radiogroup", "readonly", "rel", "required", "rev", "reversed", "role", "rows", "rowspan", "spellcheck", "scope", "selected", "shape", "size", "sizes", "span", "srclang", "start", "src", "srcset", "step", "style", "summary", "tabindex", "target", "title", "type", "usemap", "valign", "value", "width", "xmlns");
        $attributesSvg = array(
            "accent-height", "accumulate", "additivive", "alignment-baseline", "ascent", "attributename", "attributetype", "azimuth", "basefrequency", "baseline-shift", "begin", "bias", "by", "class", "clip", "clip-path", "clip-rule", "color", "color-interpolation", "color-interpolation-filters", "color-profile", "color-rendering", "cx", "cy", "d", "datenstrom", "dx", "dy", "diffuseconstant", "direction", "display", "divisor", "dur", "edgemode", "elevation", "end", "fill", "fill-opacity", "fill-rule", "filter", "flood-color", "flood-opacity", "font-family", "font-size", "font-size-adjust", "font-stretch", "font-style", "font-variant", "font-weight", "fx", "fy", "g1", "g2", "glyph-name", "glyphref", "gradientunits", "gradienttransform", "height", "href", "id", "image-rendering", "in", "in2", "k", "k1", "k2", "k3", "k4", "kerning", "keypoints", "keysplines", "keytimes", "lang", "lengthadjust", "letter-spacing", "kernelmatrix", "kernelunitlength", "lighting-color", "local", "marker-end", "marker-mid", "marker-start", "markerheight", "markerunits", "markerwidth", "maskcontentunits", "maskunits", "max", "mask", "media", "method", "mode", "min", "name", "numoctaves", "offset", "operator", "opacity", "order", "orient", "orientation", "origin", "overflow", "paint-order", "path", "pathlength", "patterncontentunits", "patterntransform", "patternunits", "points", "preservealpha", "preserveaspectratio", "r", "rx", "ry", "radius", "refx", "refy", "repeatcount", "repeatdur", "restart", "result", "rotate", "scale", "seed", "shape-rendering", "specularconstant", "specularexponent", "spreadmethod", "stddeviation", "stitchtiles", "stop-color", "stop-opacity", "stroke-dasharray", "stroke-dashoffset", "stroke-linecap", "stroke-linejoin", "stroke-miterlimit", "stroke-opacity", "stroke", "stroke-width", "style", "surfacescale", "tabindex", "targetx", "targety", "transform", "text-anchor", "text-decoration", "text-rendering", "textlength", "type", "u1", "u2", "unicode", "values", "viewbox", "visibility", "vert-adv-y", "vert-origin-x", "vert-origin-y", "width", "word-spacing", "wrap", "writing-mode", "xchannelselector", "ychannelselector", "x", "x1", "x2", "xlink:href", "xml:id", "xml:space", "xmlns", "y", "y1", "y2", "z", "zoomandpan");
        $elementsSafe = $elementsHtml;
        $attributesSafe = $attributesHtml;
        if ($type=="svg") {
            $elementsSafe = array_merge($elementsHtml, $elementsSvg);
            $attributesSafe = array_merge($attributesHtml, $attributesSvg);
        }
        $offsetBytes = 0;
        while (true) {
            $elementFound = preg_match("/<(\/?)([\!\?\w]+)(.*?)(\/?)>/s", $text, $matches, PREG_OFFSET_CAPTURE, $offsetBytes);
            $elementBefore = $elementFound ? substrb($text, $offsetBytes, $matches[0][1] - $offsetBytes) : substrb($text, $offsetBytes);
            $elementStart = $elementFound ? $matches[1][0] : "";
            $elementName = $elementFound ? $matches[2][0]: "";
            $elementMiddle = $elementFound ? $matches[3][0]: "";
            $elementEnd = $elementFound ? $matches[4][0]: "";
            $output .= $elementBefore;
            if (substrb($elementName, 0, 1)=="!") {
                $output .= "<$elementName$elementMiddle>";
            } elseif (in_array(strtolower($elementName), $elementsSafe)) {
                $elementAttributes = $this->getTextAttributes($elementMiddle);
                foreach ($elementAttributes as $key=>$value) {
                    if (!in_array(strtolower($key), $attributesSafe) && !preg_match("/^(aria|data)-/i", $key)) {
                        unset($elementAttributes[$key]);
                    }
                }
                if ($filterStrict) {
                    $href = isset($elementAttributes["href"]) ? $elementAttributes["href"] : "";
                    if (preg_match("/^\w+:/", $href) && !preg_match("/^(http|https|ftp|mailto|tel):/", $href)) {
                        $elementAttributes["href"] = "error-xss-filter";
                    }
                    $href = isset($elementAttributes["xlink:href"]) ? $elementAttributes["xlink:href"] : "";
                    if (preg_match("/^\w+:/", $href) && !preg_match("/^(http|https|ftp|mailto|tel):/", $href)) {
                        $elementAttributes["xlink:href"] = "error-xss-filter";
                    }
                }
                $output .= "<$elementStart$elementName";
                foreach ($elementAttributes as $key=>$value) $output .= " $key=\"$value\"";
                if (!empty($elementEnd)) $output .= " ";
                $output .= "$elementEnd>";
            }
            if (!$elementFound) break;
            $offsetBytes = $matches[0][1] + strlenb($matches[0][0]);
        }
        return $output;
    }

    // Normalise relative path tokens
    public function normalisePath($text) {
        $textFiltered = "";
        $textLength = strlenb($text);
        for ($pos=0; $pos<$textLength; ++$pos) {
            if (($text[$pos]=="/" || $pos==0) && $pos+1<$textLength) {
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
    
    // Normalise text lines, convert line endings
    public function normaliseLines($text, $endOfLine = "lf") {
        if ($endOfLine=="lf") {
            $text = preg_replace("/\R/u", "\n", $text);
        } else {
            $text = preg_replace("/\R/u", "\r\n", $text);
        }
        return $text;
    }
    
    // Normalise text into UTF-8 NFC
    public function normaliseUnicode($text) {
        if (PHP_OS=="Darwin" && !mb_check_encoding($text, "ASCII")) {
            $utf8nfc = preg_match("//u", $text) && !preg_match("/[^\\x00-\\x{2FF}]/u", $text);
            if (!$utf8nfc) $text = iconv("UTF-8-MAC", "UTF-8", $text);
        }
        return $text;
    }
    
    // Start timer
    public function timerStart(&$time) {
        $time = microtime(true);
    }
    
    // Stop timer and calculate elapsed time in milliseconds
    public function timerStop(&$time) {
        $time = intval((microtime(true)-$time) * 1000);
    }
    
    // Check if there are location arguments in current HTTP request
    public function isLocationArguments($location = "") {
        if (empty($location)) $location = $this->getServer("LOCATION").$this->getServer("LOCATION_ARGUMENTS");
        $separator = $this->getLocationArgumentsSeparator();
        return preg_match("/[^\/]+$separator.*$/", $location);
    }
    
    // Check if there are pagination arguments in current HTTP request
    public function isLocationArgumentsPagination($location) {
        $separator = $this->getLocationArgumentsSeparator();
        return preg_match("/^(.*\/)?page$separator.*$/", $location);
    }

    // Check if unmodified since last HTTP request
    public function isNotModified($lastModifiedFormatted) {
        return $this->getServer("HTTP_IF_MODIFIED_SINCE")==$lastModifiedFormatted;
    }
}

class YellowArray extends ArrayObject {
    public function __construct() {
        parent::__construct(array());
    }
    
    // Set array element
    public function set($key, $value) {
        $this->offsetSet($key, $value);
    }
    
    // Return array element
    public function get($key) {
        return $this->offsetExists($key) ? $this->offsetGet($key) : "";
    }
    
    // Check if array element exists
    public function isExisting($key) {
        return $this->offsetExists($key);
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

// Make string lowercase, UTF-8 compatible
function strtoloweru() {
    return call_user_func_array("mb_strtolower", func_get_args());
}

// Make string uppercase, UTF-8 compatible
function strtoupperu() {
    return call_user_func_array("mb_strtoupper", func_get_args());
}

// Return string length, UTF-8 characters
function strlenu() {
    return call_user_func_array("mb_strlen", func_get_args());
}

// Return string length, bytes
function strlenb() {
    return call_user_func_array("strlen", func_get_args());
}

// Return string position of first match, UTF-8 characters
function strposu() {
    return call_user_func_array("mb_strpos", func_get_args());
}

// Return string position of first match, bytes
function strposb() {
    return call_user_func_array("strpos", func_get_args());
}

// Return string position of last match, UTF-8 characters
function strrposu() {
    return call_user_func_array("mb_strrpos", func_get_args());
}

// Return string position of last match, bytes
function strrposb() {
    return call_user_func_array("strrpos", func_get_args());
}

// Return part of a string, UTF-8 characters
function substru() {
    return call_user_func_array("mb_substr", func_get_args());
}

// Return part of a string, bytes
function substrb() {
    return call_user_func_array("substr", func_get_args());
}

// Check if string is empty
function strempty($string) {
    return is_null($string) || $string==="";
}
