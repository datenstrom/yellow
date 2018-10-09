<?php
// Command plugin, https://github.com/datenstrom/yellow-plugins/tree/master/command
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowCommand {
    const VERSION = "0.7.11";
    public $yellow;                     //access to API
    public $files;                      //number of files
    public $links;                      //number of links
    public $errors;                     //number of errors
    public $locationsArgs;              //locations with location arguments detected
    public $locationsArgsPagination;    //locations with pagination arguments detected
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle command
    public function onCommand($args) {
        list($command) = $args;
        switch ($command) {
            case "":        $statusCode = $this->processCommandHelp(); break;
            case "build":   $statusCode = $this->processCommandBuild($args); break;
            case "check":   $statusCode = $this->processCommandCheck($args); break;
            case "clean":   $statusCode = $this->processCommandClean($args); break;
            case "version": $statusCode = $this->processCommandVersion($args); break;
            default:        $statusCode = 0;
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        $help .= "build [directory location]\n";
        $help .= "check [directory location]\n";
        $help .= "clean [directory location]\n";
        $help .= "version\n";
        return $help;
    }
    
    // Process command to show available commands
    public function processCommandHelp() {
        echo "Datenstrom Yellow ".YellowCore::VERSION."\n";
        $lineCounter = 0;
        foreach ($this->getCommandHelp() as $line) {
            echo(++$lineCounter>1 ? "        " : "Syntax: ")."yellow.php $line\n";
        }
        return 200;
    }
    
    // Process command to build static website
    public function processCommandBuild($args) {
        $statusCode = 0;
        list($command, $path, $location) = $args;
        if (empty($location) || $location[0]=="/") {
            if ($this->checkStaticConfig()) {
                $statusCode = $this->buildStaticFiles($path, $location);
            } else {
                $statusCode = 500;
                $this->files = 0;
                $this->errors = 1;
                $fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
                echo "ERROR building files: Please configure StaticUrl in file '$fileName'!\n";
            }
            echo "Yellow $command: $this->files file".($this->files!=1 ? "s" : "");
            echo ", $this->errors error".($this->errors!=1 ? "s" : "")."\n";
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Build static files
    public function buildStaticFiles($path, $locationFilter) {
        $path = rtrim(empty($path) ? $this->yellow->config->get("staticDir") : $path, "/");
        $this->files = $this->errors = 0;
        $this->locationsArgs = $this->locationsArgsPagination = array();
        $statusCode = empty($locationFilter) ? $this->cleanStaticFiles($path, $locationFilter) : 200;
        $staticUrl = $this->yellow->config->get("staticUrl");
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        foreach ($this->getContentLocations() as $location) {
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            $statusCode = max($statusCode, $this->buildStaticFile($path, $location, true));
        }
        foreach ($this->locationsArgs as $location) {
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            $statusCode = max($statusCode, $this->buildStaticFile($path, $location, true));
        }
        foreach ($this->locationsArgsPagination as $location) {
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            if (substru($location, -1)!=$this->yellow->toolbox->getLocationArgsSeparator()) {
                $statusCode = max($statusCode, $this->buildStaticFile($path, $location, false, true));
            }
            for ($pageNumber=2; $pageNumber<=999; ++$pageNumber) {
                $statusCodeLocation = $this->buildStaticFile($path, $location.$pageNumber, false, true);
                $statusCode = max($statusCode, $statusCodeLocation);
                if ($statusCodeLocation==100) break;
            }
        }
        if (empty($locationFilter)) {
            foreach ($this->getMediaLocations() as $location) {
                $statusCode = max($statusCode, $this->buildStaticFile($path, $location));
            }
            foreach ($this->getSystemLocations() as $location) {
                $statusCode = max($statusCode, $this->buildStaticFile($path, $location));
            }
            $statusCode = max($statusCode, $this->buildStaticFile($path, "/error/", false, false, true));
        }
        return $statusCode;
    }
    
    // Build static file
    public function buildStaticFile($path, $location, $analyse = false, $probe = false, $error = false) {
        $this->yellow->pages = new YellowPages($this->yellow);
        $this->yellow->page = new YellowPage($this->yellow);
        $this->yellow->page->fileName = substru($location, 1);
        if (!is_readable($this->yellow->page->fileName)) {
            ob_start();
            $staticUrl = $this->yellow->config->get("staticUrl");
            list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
            $statusCode = $this->requestStaticFile($scheme, $address, $base, $location);
            if ($statusCode<400 || $error) {
                $fileData = ob_get_contents();
                $statusCode = $this->saveStaticFile($path, $location, $fileData, $statusCode);
            }
            ob_end_clean();
        } else {
            $statusCode = $this->copyStaticFile($path, $location);
        }
        if ($statusCode==200 && $analyse) $this->analyseLocations($scheme, $address, $base, $fileData);
        if ($statusCode==404 && $probe) $statusCode = 100;
        if ($statusCode==404 && $error) $statusCode = 200;
        if ($statusCode>=200) ++$this->files;
        if ($statusCode>=400) {
            ++$this->errors;
            echo "ERROR building location '$location', ".$this->yellow->page->getStatusCode(true)."\n";
        }
        if (defined("DEBUG") && DEBUG>=1) echo "YellowCommand::buildStaticFile status:$statusCode location:$location<br/>\n";
        return $statusCode;
    }
    
    // Request static file
    public function requestStaticFile($scheme, $address, $base, $location) {
        list($serverName, $serverPort) = explode(":", $address);
        if (is_null($serverPort)) $serverPort = $scheme=="https" ? 443 : 80;
        $_SERVER["HTTPS"] = $scheme=="https" ? "on" : "off";
        $_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
        $_SERVER["SERVER_NAME"] = $serverName;
        $_SERVER["SERVER_PORT"] = $serverPort;
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REQUEST_URI"] = $base.$location;
        $_SERVER["SCRIPT_NAME"] = $base."/yellow.php";
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_REQUEST = array();
        return $this->yellow->request();
    }
    
    // Save static file
    public function saveStaticFile($path, $location, $fileData, $statusCode) {
        $modified = strtotime($this->yellow->page->getHeader("Last-Modified"));
        if ($modified==0) $modified = $this->yellow->toolbox->getFileModified($this->yellow->page->fileName);
        if ($statusCode>=301 && $statusCode<=303) {
            $fileData = $this->getStaticRedirect($this->yellow->page->getHeader("Location"));
            $modified = time();
        }
        $fileName = $this->getStaticFile($path, $location, $statusCode);
        if (!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
            !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
            $statusCode = 500;
            $this->yellow->page->statusCode = $statusCode;
            $this->yellow->page->set("pageError", "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Copy static file
    public function copyStaticFile($path, $location) {
        $statusCode = 200;
        $modified = $this->yellow->toolbox->getFileModified($this->yellow->page->fileName);
        $fileName = $this->getStaticFile($path, $location, $statusCode);
        if (!$this->yellow->toolbox->copyFile($this->yellow->page->fileName, $fileName, true) ||
            !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
            $statusCode = 500;
            $this->yellow->page->statusCode = $statusCode;
            $this->yellow->page->set("pageError", "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Analyse locations with arguments
    public function analyseLocations($scheme, $address, $base, $rawData) {
        $pagination = $this->yellow->config->get("contentPagination");
        preg_match_all("/<(.*?)href=\"([^\"]+)\"(.*?)>/i", $rawData, $matches);
        foreach ($matches[2] as $match) {
            $location = rawurldecode($match);
            if (preg_match("/^(.*?)#(.*)$/", $location, $tokens)) $location = $tokens[1];
            if (preg_match("/^(\w+):\/\/([^\/]+)(.*)$/", $location, $tokens)) {
                if ($tokens[1]!=$scheme) continue;
                if ($tokens[2]!=$address) continue;
                $location = $tokens[3];
            }
            if (substru($location, 0, strlenu($base))!=$base) continue;
            $location = substru($location, strlenu($base));
            if (!$this->yellow->toolbox->isLocationArgs($location)) continue;
            if (!$this->yellow->toolbox->isLocationArgsPagination($location, $pagination)) {
                $location = rtrim($location, "/")."/";
                if (is_null($this->locationsArgs[$location])) {
                    $this->locationsArgs[$location] = $location;
                    if (defined("DEBUG") && DEBUG>=2) echo "YellowCommand::analyseLocations detected location:$location<br/>\n";
                }
            } else {
                $location = rtrim($location, "0..9");
                if (is_null($this->locationsArgsPagination[$location])) {
                    $this->locationsArgsPagination[$location] = $location;
                    if (defined("DEBUG") && DEBUG>=2) echo "YellowCommand::analyseLocations detected location:$location<br/>\n";
                }
            }
        }
    }

    // Process command to check static files for broken links
    public function processCommandCheck($args) {
        $statusCode = 0;
        list($command, $path, $location) = $args;
        if (empty($location) || $location[0]=="/") {
            if ($this->checkStaticConfig()) {
                $statusCode = $this->checkStaticFiles($path, $location);
            } else {
                $statusCode = 500;
                $this->files = $this->links = 0;
                $fileName = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
                echo "ERROR checking files: Please configure StaticUrl in file '$fileName'!\n";
            }
            echo "Yellow $command: $this->files file".($this->files!=1 ? "s" : "");
            echo ", $this->links link".($this->links!=1 ? "s" : "")."\n";
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Check static files for broken links
    public function checkStaticFiles($path, $locationFilter) {
        $path = rtrim(empty($path) ? $this->yellow->config->get("staticDir") : $path, "/");
        $this->files = $this->links = 0;
        $regex = "/^[^.]+$|".$this->yellow->config->get("staticDefaultFile")."$/";
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($path, $regex, false, false);
        list($statusCodeFiles, $links) = $this->analyseLinks($path, $locationFilter, $fileNames);
        list($statusCodeLinks, $broken, $redirect) = $this->analyseStatus($path, $links);
        if ($statusCodeLinks!=200) {
            $this->showLinks($broken, "Broken links");
            $this->showLinks($redirect, "Redirect links");
        }
        return max($statusCodeFiles, $statusCodeLinks);
    }
    
    // Analyse links in static files
    public function analyseLinks($path, $locationFilter, $fileNames) {
        $statusCode = 200;
        $links = array();
        if (!empty($fileNames)) {
            $staticUrl = $this->yellow->config->get("staticUrl");
            list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
            foreach ($fileNames as $fileName) {
                if (is_readable($fileName)) {
                    $locationSource = $this->getStaticLocation($path, $fileName);
                    if (!preg_match("#^$base$locationFilter#", "$base$locationSource")) continue;
                    $fileData = $this->yellow->toolbox->readFile($fileName);
                    preg_match_all("/<(.*?)href=\"([^\"]+)\"(.*?)>/i", $fileData, $matches);
                    foreach ($matches[2] as $match) {
                        $location = rawurldecode($match);
                        if (preg_match("/^(.*?)#(.*)$/", $location, $tokens)) $location = $tokens[1];
                        if (preg_match("/^(\w+):\/\/([^\/]+)(.*)$/", $location, $matches)) {
                            $url = $location.(empty($matches[3]) ? "/" : "");
                            if (!is_null($links[$url])) $links[$url] .= ",";
                            $links[$url] .= $locationSource;
                            if (defined("DEBUG") && DEBUG>=2) echo "YellowCommand::analyseLinks detected url:$url<br/>\n";
                        } elseif ($location[0]=="/") {
                            $url = "$scheme://$address$location";
                            if (!is_null($links[$url])) $links[$url] .= ",";
                            $links[$url] .= $locationSource;
                            if (defined("DEBUG") && DEBUG>=2) echo "YellowCommand::analyseLinks detected url:$url<br/>\n";
                        }
                    }
                    ++$this->files;
                } else {
                    $statusCode = 500;
                    echo "ERROR reading files: Can't read file '$fileName'!\n";
                }
            }
            $this->links = count($links);
        } else {
            $statusCode = 500;
            echo "ERROR reading files: Can't find files in directory '$path'!\n";
        }
        return array($statusCode, $links);
    }
    
    // Analyse link status
    public function analyseStatus($path, $links) {
        $statusCode = 200;
        $broken = $redirect = $data = array();
        $staticUrl = $this->yellow->config->get("staticUrl");
        $staticUrlLength = strlenu(rtrim($staticUrl, "/"));
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        $staticLocations = $this->getContentLocations(true);
        uksort($links, "strnatcasecmp");
        foreach ($links as $url=>$value) {
            if (defined("DEBUG") && DEBUG>=1) echo "YellowCommand::analyseStatus url:$url\n";
            if (preg_match("#^$staticUrl#", $url)) {
                $location = substru($url, $staticUrlLength);
                $fileName = $path.substru($url, $staticUrlLength);
                if (is_readable($fileName)) continue;
                if (in_array($location, $staticLocations)) continue;
            }
            if (preg_match("/^(http|https):/", $url)) {
                $referer = "$scheme://$address$base".(($pos = strposu($value, ",")) ? substru($value, 0, $pos) : $value);
                $statusCodeUrl = $this->getLinkStatus($url, $referer);
                if ($statusCodeUrl!=200) {
                    $statusCode = max($statusCode, $statusCodeUrl);
                    $data[$url] = "$statusCodeUrl,$value";
                }
            }
        }
        foreach ($data as $url=>$value) {
            $locations = preg_split("/\s*,\s*/", $value);
            $statusCodeUrl = array_shift($locations);
            foreach ($locations as $location) {
                if ($statusCodeUrl==302) continue;
                if ($statusCodeUrl>=300 && $statusCodeUrl<=399) {
                    $redirect["$scheme://$address$base$location -> $url - ".$this->getStatusFormatted($statusCodeUrl)] = $statusCodeUrl;
                } else {
                    $broken["$scheme://$address$base$location -> $url - ".$this->getStatusFormatted($statusCodeUrl)] = $statusCodeUrl;
                }
            }
        }
        return array($statusCode, $broken, $redirect);
    }

    // Show links
    public function showLinks($data, $text) {
        if (!empty($data)) {
            echo "$text\n\n";
            uksort($data, "strnatcasecmp");
            $data = array_slice($data, 0, 99);
            foreach ($data as $key=>$value) {
                echo "- $key\n";
            }
            echo "\n";
        }
    }
    
    // Process command to clean static files
    public function processCommandClean($args) {
        $statusCode = 0;
        list($command, $path, $location) = $args;
        if (empty($location) || $location[0]=="/") {
            $statusCode = $this->cleanStaticFiles($path, $location);
            echo "Yellow $command: Static file".(empty($location) ? "s" : "")." ".($statusCode!=200 ? "not " : "")."cleaned\n";
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Clean static files and directories
    public function cleanStaticFiles($path, $location) {
        $statusCode = 200;
        $path = rtrim(empty($path) ? $this->yellow->config->get("staticDir") : $path, "/");
        if (empty($location)) {
            $statusCode = max($statusCode, $this->broadcastCommand("clean", "all"));
            $statusCode = max($statusCode, $this->cleanStaticDirectory($path));
        } else {
            if ($this->yellow->lookup->isFileLocation($location)) {
                $fileName = $this->getStaticFile($path, $location, $statusCode);
                $statusCode = $this->cleanStaticFile($fileName);
            } else {
                $statusCode = $this->cleanStaticDirectory($path.$location);
            }
        }
        return $statusCode;
    }
    
    // Clean static directory
    public function cleanStaticDirectory($path) {
        $statusCode = 200;
        if (is_dir($path) && $this->checkStaticDirectory($path)) {
            if (!$this->yellow->toolbox->deleteDirectory($path)) {
                $statusCode = 500;
                echo "ERROR cleaning files: Can't delete directory '$path'!\n";
            }
        }
        return $statusCode;
    }
    
    // Clean static file
    public function cleanStaticFile($fileName) {
        $statusCode = 200;
        if (is_file($fileName)) {
            if (!$this->yellow->toolbox->deleteFile($fileName)) {
                $statusCode = 500;
                echo "ERROR cleaning files: Can't delete file '$fileName'!\n";
            }
        }
        return $statusCode;
    }
    
    // Broadcast command to other plugins
    public function broadcastCommand($args) {
        $statusCode = 0;
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if ($key=="command") continue;
            if (method_exists($value["obj"], "onCommand")) {
                $statusCode = $value["obj"]->onCommand(func_get_args());
                if ($statusCode!=0) break;
            }
        }
        return $statusCode;
    }
    
    // Process command to show software version and updates
    public function processCommandVersion($args) {
        $serverVersion = $this->yellow->toolbox->getServerVersion();
        echo "Datenstrom Yellow ".YellowCore::VERSION.", PHP ".PHP_VERSION.", $serverVersion\n";
        list($statusCode, $dataCurrent) = $this->getSoftwareVersion();
        list($statusCode, $dataLatest) = $this->getSoftwareVersion(true);
        foreach ($dataCurrent as $key=>$value) {
            if (strnatcasecmp($dataCurrent[$key], $dataLatest[$key])>=0) {
                echo "$key $value\n";
            } else {
                echo "$key $value - Update available\n";
            }
        }
        if ($statusCode!=200) echo "ERROR checking updates: ".$this->yellow->page->get("pageError")."\n";
        return $statusCode;
    }
    
    // Check static configuration
    public function checkStaticConfig() {
        $staticUrl = $this->yellow->config->get("staticUrl");
        return !empty($staticUrl);
    }
    
    // Check static directory
    public function checkStaticDirectory($path) {
        $ok = false;
        if (!empty($path)) {
            if ($path==rtrim($this->yellow->config->get("staticDir"), "/")) $ok = true;
            if ($path==rtrim($this->yellow->config->get("trashDir"), "/")) $ok = true;
            if (is_file("$path/".$this->yellow->config->get("staticDefaultFile"))) $ok = true;
            if (is_file("$path/yellow.php")) $ok = false;
        }
        return $ok;
    }
    
    // Return static file
    public function getStaticFile($path, $location, $statusCode) {
        if ($statusCode<400) {
            $fileName = $path.$location;
            if (!$this->yellow->lookup->isFileLocation($location)) $fileName .= $this->yellow->config->get("staticDefaultFile");
        } elseif ($statusCode==404) {
            $fileName = $path."/".$this->yellow->config->get("staticErrorFile");
        }
        return $fileName;
    }
    
    // Return static location
    public function getStaticLocation($path, $fileName) {
        $location = substru($fileName, strlenu($path));
        if (basename($location)==$this->yellow->config->get("staticDefaultFile")) {
            $defaultFileLength = strlenu($this->yellow->config->get("staticDefaultFile"));
            $location = substru($location, 0, -$defaultFileLength);
        }
        return $location;
    }
    
    // Return static redirect
    public function getStaticRedirect($location) {
        $output = "<!DOCTYPE html><html>\n<head>\n";
        $output .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
        $output .= "<meta http-equiv=\"refresh\" content=\"0;url=".htmlspecialchars($location)."\" />\n";
        $output .= "</head>\n</html>";
        return $output;
    }

    // Return human readable status
    public function getStatusFormatted($statusCode) {
        return $this->yellow->toolbox->getHttpStatusFormatted($statusCode, true);
    }
    
    // Return content locations
    public function getContentLocations($includeAll = false) {
        $locations = array();
        $staticUrl = $this->yellow->config->get("staticUrl");
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        $this->yellow->page->setRequestInformation($scheme, $address, $base, "", "");
        foreach ($this->yellow->pages->index(true, true) as $page) {
            if (($page->get("status")!="ignore" && $page->get("status")!="draft") || $includeAll) {
                array_push($locations, $page->location);
            }
        }
        if (!$this->yellow->pages->find("/") && $this->yellow->config->get("multiLanguageMode")) array_unshift($locations, "/");
        return $locations;
    }
    
    // Return media locations
    public function getMediaLocations() {
        $locations = array();
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($this->yellow->config->get("mediaDir"), "/.*/", false, false);
        foreach ($fileNames as $fileName) {
            array_push($locations, "/".$fileName);
        }
        return $locations;
    }

    // Return system locations
    public function getSystemLocations() {
        $locations = array();
        $regex = "/\.(css|gif|ico|js|jpg|png|svg|txt|woff|woff2)$/";
        $pluginDirLength = strlenu($this->yellow->config->get("pluginDir"));
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($this->yellow->config->get("pluginDir"), $regex, false, false);
        foreach ($fileNames as $fileName) {
            array_push($locations, $this->yellow->config->get("pluginLocation").substru($fileName, $pluginDirLength));
        }
        $themeDirLength = strlenu($this->yellow->config->get("themeDir"));
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($this->yellow->config->get("themeDir"), $regex, false, false);
        foreach ($fileNames as $fileName) {
            array_push($locations, $this->yellow->config->get("themeLocation").substru($fileName, $themeDirLength));
        }
        array_push($locations, "/".$this->yellow->config->get("robotsFile"));
        return $locations;
    }
    
    // Return command help
    public function getCommandHelp() {
        $data = array();
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onCommandHelp")) {
                foreach (preg_split("/[\r\n]+/", $value["obj"]->onCommandHelp()) as $line) {
                    list($command) = explode(" ", $line);
                    if (!empty($command) && is_null($data[$command])) $data[$command] = $line;
                }
            }
        }
        uksort($data, "strnatcasecmp");
        return $data;
    }

    // Return software version
    public function getSoftwareVersion($latest = false) {
        $data = array();
        if ($this->yellow->plugins->isExisting("update")) {
            list($statusCode, $data) = $this->yellow->plugins->get("update")->getSoftwareVersion($latest);
        } else {
            $statusCode = 200;
            $data = array_merge($this->yellow->plugins->getData(), $this->yellow->themes->getData());
        }
        return array($statusCode, $data);
    }
    
    // Return link status
    public function getLinkStatus($url, $referer) {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_REFERER, $referer);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; DatenstromYellow/".YellowCore::VERSION."; LinkChecker)");
        curl_setopt($curlHandle, CURLOPT_NOBODY, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        if (defined("DEBUG") && DEBUG>=2) echo "YellowCommand::getLinkStatus status:$statusCode url:$url<br/>\n";
        return $statusCode;
    }
}
