<?php
// Command extension, https://github.com/annaesvensson/yellow-command

class YellowCommand {
    const VERSION = "0.8.42";
    public $yellow;                       // access to API
    public $files;                        // number of files
    public $links;                        // number of links
    public $errors;                       // number of errors
    public $locationsArguments;           // locations with location arguments detected
    public $locationsArgumentsPagination; // locations with pagination arguments detected
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("commandStaticBuildDirectory", "public/");
        $this->yellow->system->setDefault("commandStaticDefaultFile", "index.html");
        $this->yellow->system->setDefault("commandStaticErrorFile", "404.html");
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
       return $this->processRequestCache($scheme, $address, $base, $location, $fileName);
    }
    
    // Handle command
    public function onCommand($command, $text) {
        switch ($command) {
            case "build":   $statusCode = $this->processCommandBuild($command, $text); break;
            case "check":   $statusCode = $this->processCommandCheck($command, $text); break;
            case "clean":   $statusCode = $this->processCommandClean($command, $text); break;
            default:        $statusCode = 0;
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        $help = "build [directory location]\n";
        $help .= "check [directory location]\n";
        $help .= "clean [directory location]\n";
        return $help;
    }

    // Process command to build static website
    public function processCommandBuild($command, $text) {
        $statusCode = 0;
        list($path, $location) = $this->yellow->toolbox->getTextArguments($text);
        if (empty($location) || substru($location, 0, 1)=="/") {
            if ($this->checkStaticSettings()) {
                $statusCode = $this->buildStaticFiles($path, $location);
            } else {
                $statusCode = 500;
                $this->files = 0;
                $this->errors = 1;
                $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
                echo "ERROR building files: Please configure CoreStaticUrl in file '$fileName'!\n";
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
        $path = rtrim(empty($path) ? $this->yellow->system->get("commandStaticBuildDirectory") : $path, "/");
        $this->files = $this->errors = 0;
        $this->locationsArguments = $this->locationsArgumentsPagination = array();
        $statusCode = empty($locationFilter) ? $this->cleanStaticFiles($path, $locationFilter) : 200;
        $staticUrl = $this->yellow->system->get("coreStaticUrl");
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        $locations = $this->getContentLocations();
        $filesEstimated = count($locations);
        foreach ($locations as $location) {
            echo "\rBuilding static website ".$this->getProgressPercent($this->files, $filesEstimated, 5, 60)."%... ";
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            $statusCode = max($statusCode, $this->buildStaticFile($path, $location, true));
        }
        foreach ($this->locationsArguments as $location) {
            echo "\rBuilding static website ".$this->getProgressPercent($this->files, $filesEstimated, 5, 60)."%... ";
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            $statusCode = max($statusCode, $this->buildStaticFile($path, $location, true));
        }
        $filesEstimated = $this->files + count($this->locationsArguments) + count($this->locationsArgumentsPagination);
        foreach ($this->locationsArgumentsPagination as $location) {
            echo "\rBuilding static website ".$this->getProgressPercent($this->files, $filesEstimated, 5, 95)."%... ";
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            if (substru($location, -1)!=$this->yellow->toolbox->getLocationArgumentsSeparator()) {
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
            foreach ($this->getExtraLocations($path) as $location) {
                $statusCode = max($statusCode, $this->buildStaticFile($path, $location));
            }
            $statusCode = max($statusCode, $this->buildStaticFile($path, "/error/", false, false, true));
        }
        echo "\rBuilding static website 100%... done\n";
        return $statusCode;
    }
    
    // Build static file
    public function buildStaticFile($path, $location, $analyse = false, $probe = false, $error = false) {
        $this->yellow->content = new YellowContent($this->yellow);
        $this->yellow->page = new YellowPage($this->yellow);
        $this->yellow->page->fileName = substru($location, 1);
        if (!is_readable($this->yellow->page->fileName)) {
            ob_start();
            $staticUrl = $this->yellow->system->get("coreStaticUrl");
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
            echo "\rERROR building location '$location', ".$this->yellow->page->getStatusCode(true)."\n";
        }
        if ($this->yellow->system->get("coreDebugMode")>=1) {
            echo "YellowCommand::buildStaticFile status:$statusCode location:$location<br/>\n";
        }
        return $statusCode;
    }
    
    // Request static file
    public function requestStaticFile($scheme, $address, $base, $location) {
        list($serverName, $serverPort) = $this->yellow->toolbox->getTextList($address, ":", 2);
        if (empty($serverPort)) $serverPort = $scheme=="https" ? 443 : 80;
        $_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1";
        $_SERVER["SERVER_NAME"] = $serverName;
        $_SERVER["SERVER_PORT"] = $serverPort;
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REQUEST_SCHEME"] = $scheme;
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
        if (is_file($fileName)) $this->yellow->toolbox->deleteFile($fileName);
        if (!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
            !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
            $statusCode = 500;
            $this->yellow->page->statusCode = $statusCode;
            $this->yellow->page->errorMessage = "Can't write file '$fileName'!";
        }
        return $statusCode;
    }
    
    // Copy static file
    public function copyStaticFile($path, $location) {
        $statusCode = 200;
        $modified = $this->yellow->toolbox->getFileModified($this->yellow->page->fileName);
        $fileName = $this->getStaticFile($path, $location, $statusCode);
        if (is_file($fileName)) $this->yellow->toolbox->deleteFile($fileName);
        if (!$this->yellow->toolbox->copyFile($this->yellow->page->fileName, $fileName, true) ||
            !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
            $statusCode = 500;
            $this->yellow->page->statusCode = $statusCode;
            $this->yellow->page->errorMessage = "Can't write file '$fileName'!";
        }
        return $statusCode;
    }
    
    // Analyse locations with arguments
    public function analyseLocations($scheme, $address, $base, $rawData) {
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
            if (substru($location, strlenu($base), 1)!="/") continue;
            $location = substru($location, strlenu($base));
            if (!$this->yellow->toolbox->isLocationArguments($location)) continue;
            if (!$this->yellow->toolbox->isLocationArgumentsPagination($location)) {
                $location = rtrim($location, "/")."/";
                if (!isset($this->locationsArguments[$location])) {
                    $this->locationsArguments[$location] = $location;
                    if ($this->yellow->system->get("coreDebugMode")>=2) {
                        echo "YellowCommand::analyseLocations detected location:$location<br/>\n";
                    }
                }
            } else {
                $location = rtrim($location, "0..9");
                if (!isset($this->locationsArgumentsPagination[$location])) {
                    $this->locationsArgumentsPagination[$location] = $location;
                    if ($this->yellow->system->get("coreDebugMode")>=2) {
                        echo "YellowCommand::analyseLocations detected location:$location<br/>\n";
                    }
                }
            }
        }
    }

    // Process command to check static files for broken links
    public function processCommandCheck($command, $text) {
        $statusCode = 0;
        list($path, $location) = $this->yellow->toolbox->getTextArguments($text);
        if (empty($location) || substru($location, 0, 1)=="/") {
            if ($this->checkStaticSettings()) {
                $statusCode = $this->checkStaticFiles($path, $location);
            } else {
                $statusCode = 500;
                $this->links = 0;
                $this->errors = 1;
                $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
                echo "ERROR checking files: Please configure CoreStaticUrl in file '$fileName'!\n";
            }
            echo "Yellow $command: $this->links link".($this->links!=1 ? "s" : "");
            echo ", $this->errors error".($this->errors!=1 ? "s" : "")."\n";
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Check static files for broken links
    public function checkStaticFiles($path, $locationFilter) {
        $path = rtrim(empty($path) ? $this->yellow->system->get("commandStaticBuildDirectory") : $path, "/");
        $this->links = $this->errors = 0;
        $regex = "/^[^.]+$|".$this->yellow->system->get("commandStaticDefaultFile")."$/";
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
            $staticUrl = $this->yellow->system->get("coreStaticUrl");
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
                            if (!isset($links[$url])) {
                                $links[$url] = $locationSource;
                            } else {
                                $links[$url] .= ",".$locationSource;
                            }
                            if ($this->yellow->system->get("coreDebugMode")>=2) {
                                echo "YellowCommand::analyseLinks detected url:$url<br/>\n";
                            }
                        } elseif (substru($location, 0, 1)=="/") {
                            $url = "$scheme://$address$location";
                            if (!isset($links[$url])) {
                                $links[$url] = $locationSource;
                            } else {
                                $links[$url] .= ",".$locationSource;
                            }
                            if ($this->yellow->system->get("coreDebugMode")>=2) {
                                echo "YellowCommand::analyseLinks detected url:$url<br/>\n";
                            }
                        }
                    }
                    if ($this->yellow->system->get("coreDebugMode")>=1) {
                        echo "YellowCommand::analyseLinks location:$locationSource<br/>\n";
                    }
                } else {
                    $statusCode = 500;
                    ++$this->errors;
                    echo "ERROR reading files: Can't read file '$fileName'!\n";
                }
            }
            $this->links = count($links);
        } else {
            $statusCode = 500;
            ++$this->errors;
            echo "ERROR reading files: Can't find files in directory '$path'!\n";
        }
        return array($statusCode, $links);
    }
    
    // Analyse link status
    public function analyseStatus($path, $links) {
        $statusCode = 200;
        $remote = $broken = $redirect = $data = array();
        $staticUrl = $this->yellow->system->get("coreStaticUrl");
        $staticUrlLength = strlenu(rtrim($staticUrl, "/"));
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        $staticLocations = $this->getContentLocations(true);
        foreach ($links as $url=>$value) {
            if (preg_match("#^$staticUrl#", $url)) {
                $location = substru($url, $staticUrlLength);
                $fileName = $path.substru($url, $staticUrlLength);
                if (is_readable($fileName)) continue;
                if (in_array($location, $staticLocations)) continue;
            }
            if (preg_match("/^(http|https):/", $url)) $remote[$url] = $value;
        }
        $remoteNow = 0;
        uksort($remote, "strnatcasecmp");
        foreach ($remote as $url=>$value) {
            echo "\rChecking static website ".$this->getProgressPercent(++$remoteNow, count($remote), 5, 95)."%... ";
            if ($this->yellow->system->get("coreDebugMode")>=1) echo "YellowCommand::analyseStatus url:$url\n";
            $referer = "$scheme://$address$base".(($pos = strposu($value, ",")) ? substru($value, 0, $pos) : $value);
            $statusCodeUrl = $this->getLinkStatus($url, $referer);
            if ($statusCodeUrl!=200) {
                $statusCode = max($statusCode, $statusCodeUrl);
                $data[$url] = "$statusCodeUrl,$value";
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
                ++$this->errors;
            }
        }
        echo "\rChecking static website 100%... done\n";
        return array($statusCode, $broken, $redirect);
    }

    // Show links
    public function showLinks($data, $text) {
        if (!empty($data)) {
            echo "$text\n\n";
            uksort($data, "strnatcasecmp");
            $data = array_slice($data, 0, 99);
            foreach ($data as $key=>$value) {
                echo "$key\n";
            }
            echo "\n";
        }
    }
    
    // Process command to clean static files
    public function processCommandClean($command, $text) {
        $statusCode = 0;
        list($path, $location) = $this->yellow->toolbox->getTextArguments($text);
        if (empty($location) || substru($location, 0, 1)=="/") {
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
        $path = rtrim(empty($path) ? $this->yellow->system->get("commandStaticBuildDirectory") : $path, "/");
        if (empty($location)) {
            foreach ($this->yellow->extension->data as $key=>$value) {
                if (method_exists($value["object"], "onUpdate")) $value["object"]->onUpdate("clean");
            }
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
    
    // Process request for cached files
    public function processRequestCache($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if (is_dir($this->yellow->system->get("coreCacheDirectory"))) {
            $location .= $this->yellow->toolbox->getLocationArguments();
            $fileName = rtrim($this->yellow->system->get("coreCacheDirectory"), "/").$location;
            if (!$this->yellow->lookup->isFileLocation($location)) $fileName .= $this->yellow->system->get("commandStaticDefaultFile");
            if (is_file($fileName) && is_readable($fileName) && !$this->yellow->isCommandLine()) {
                $statusCode = $this->yellow->sendFile(200, $fileName, true);
            }
        }
        return $statusCode;
    }
    
    // Check static settings
    public function checkStaticSettings() {
        return preg_match("/^(http|https):/", $this->yellow->system->get("coreStaticUrl"));
    }
    
    // Check static directory
    public function checkStaticDirectory($path) {
        $ok = false;
        if (!empty($path)) {
            if ($path==rtrim($this->yellow->system->get("commandStaticBuildDirectory"), "/")) $ok = true;
            if ($path==rtrim($this->yellow->system->get("coreCacheDirectory"), "/")) $ok = true;
            if ($path==rtrim($this->yellow->system->get("coreTrashDirectory"), "/")) $ok = true;
            if (is_file("$path/".$this->yellow->system->get("commandStaticDefaultFile"))) $ok = true;
            if (is_file("$path/yellow.php")) $ok = false;
        }
        return $ok;
    }
    
    // Return human readable status
    public function getStatusFormatted($statusCode) {
        return $this->yellow->toolbox->getHttpStatusFormatted($statusCode, true);
    }
    
    // Return progress in percent
    public function getProgressPercent($now, $total, $increments, $max) {
        $percent = intval(($max/$total) * $now);
        if ($increments>1) $percent = intval($percent/$increments) * $increments;
        return min($max, $percent);
    }
    
    // Return static file
    public function getStaticFile($path, $location, $statusCode) {
        if ($statusCode<400) {
            $fileName = $path.$location;
            if (!$this->yellow->lookup->isFileLocation($location)) $fileName .= $this->yellow->system->get("commandStaticDefaultFile");
        } elseif ($statusCode==404) {
            $fileName = $path."/".$this->yellow->system->get("commandStaticErrorFile");
        } else {
            $fileName = $path."/error.html";
        }
        return $fileName;
    }
    
    // Return static location
    public function getStaticLocation($path, $fileName) {
        $location = substru($fileName, strlenu($path));
        if (basename($location)==$this->yellow->system->get("commandStaticDefaultFile")) {
            $defaultFileLength = strlenu($this->yellow->system->get("commandStaticDefaultFile"));
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

    // Return content locations
    public function getContentLocations($includeAll = false) {
        $locations = array();
        $staticUrl = $this->yellow->system->get("coreStaticUrl");
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        $this->yellow->page->setRequestInformation($scheme, $address, $base, "", "", false);
        foreach ($this->yellow->content->index(true, true) as $page) {
            if (preg_match("/exclude/i", $page->get("build")) && !$includeAll) continue;
            if ($page->get("status")=="private" || $page->get("status")=="draft") continue;
            array_push($locations, $page->location);
        }
        if (!$this->yellow->content->find("/") && $this->yellow->system->get("coreMultiLanguageMode")) array_unshift($locations, "/");
        return $locations;
    }
    
    // Return media locations
    public function getMediaLocations() {
        $locations = array();
        $mediaPath = $this->yellow->system->get("coreMediaDirectory");
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($mediaPath, "/.*/", false, false);
        foreach ($fileNames as $fileName) {
            array_push($locations, $this->yellow->lookup->findMediaLocationFromFile($fileName));
        }
        $extensionPath = $this->yellow->system->get("coreExtensionDirectory");
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($extensionPath, "/.*/", false, false);
        foreach ($fileNames as $fileName) {
            array_push($locations, $this->yellow->lookup->findMediaLocationFromFile($fileName));
        }
        $themePath = $this->yellow->system->get("coreThemeDirectory");
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($themePath, "/.*/", false, false);
        foreach ($fileNames as $fileName) {
            array_push($locations, $this->yellow->lookup->findMediaLocationFromFile($fileName));
        }
        return array_diff($locations, $this->getMediaLocationsIgnore());
    }
    
    // Return media locations to ignore
    public function getMediaLocationsIgnore() {
        $locations = array("");
        $extensionPath = $this->yellow->system->get("coreExtensionDirectory");
        $extensionDirectoryLength = strlenu($this->yellow->system->get("coreExtensionDirectory"));
        if ($this->yellow->extension->isExisting("bundle")) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($extensionPath, "/^bundle-(.*)/", false, false) as $entry) {
                list($locationsBundle) = $this->yellow->extension->get("bundle")->getBundleInformation($entry);
                $locations = array_merge($locations, $locationsBundle);
            }
        }
        if ($this->yellow->extension->isExisting("edit")) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($extensionPath, "/^edit\.(.*)/", false, false) as $entry) {
                $location = $this->yellow->system->get("coreExtensionLocation").substru($entry, $extensionDirectoryLength);
                array_push($locations, $location);
            }
        }
        return array_unique($locations);
    }
    
    // Return extra locations
    public function getExtraLocations($path) {
        $locations = array();
        $pathIgnore = "($path/|".
            $this->yellow->system->get("commandStaticBuildDirectory")."|".
            $this->yellow->system->get("coreContentDirectory")."|".
            $this->yellow->system->get("coreMediaDirectory")."|".
            $this->yellow->system->get("coreSystemDirectory").")";
        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive(".", "/.*/", false, false);
        foreach ($fileNames as $fileName) {
            $fileName = substru($fileName, 2);
            if (preg_match("#^$pathIgnore#", $fileName) || $fileName=="yellow.php") continue;
            array_push($locations, "/".$fileName);
        }
        return $locations;
    }
    
    // Return link status
    public function getLinkStatus($url, $referer) {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_REFERER, $referer);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowCommand/".YellowCommand::VERSION."; LinkChecker)");
        curl_setopt($curlHandle, CURLOPT_NOBODY, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        if ($statusCode<200) $statusCode = 404;
        if ($this->yellow->system->get("coreDebugMode")>=2) {
            echo "YellowCommand::getLinkStatus status:$statusCode url:$url<br/>\n";
        }
        return $statusCode;
    }
}
