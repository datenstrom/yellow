<?php
// Generate extension, https://github.com/annaesvensson/yellow-generate

class YellowGenerate {
    const VERSION = "0.8.52";
    public $yellow;                       // access to API
    public $files;                        // number of files
    public $errors;                       // number of errors
    public $locationsArguments;           // locations with location arguments detected
    public $locationsArgumentsPagination; // locations with pagination arguments detected
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("generateStaticUrl", "auto");
        $this->yellow->system->setDefault("generateStaticDirectory", "public/");
        $this->yellow->system->setDefault("generateStaticDefaultFile", "index.html");
        $this->yellow->system->setDefault("generateStaticErrorFile", "404.html");
    }
    
    // Handle update
    public function onUpdate($action) {
        if ($action=="install") {
            if ($this->yellow->system->isExisting("commandStaticUrl")) { //TODO: remove later, for backwards compatibility
                $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
                $settings = array(
                    "generateStaticUrl" => $this->yellow->system->get("commandStaticUrl"),
                    "generateStaticDirectory" => $this->yellow->system->get("commandStaticDirectory"),
                    "generateStaticDefaultFile" => $this->yellow->system->get("commandStaticDefaultFile"),
                    "generateStaticErrorFile" => $this->yellow->system->get("commandStaticErrorFile"));
                if (!$this->yellow->system->save($fileName, $settings)) {
                    $this->yellow->toolbox->log("error", "Can't write file '$fileName'!");
                }
                $this->yellow->toolbox->log("info", "Import settings for 'Generate ".YellowGenerate::VERSION."'");
            }
        }
    }

    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
       return $this->processRequestCache($scheme, $address, $base, $location, $fileName);
    }
    
    // Handle command
    public function onCommand($command, $text) {
        switch ($command) {
            case "generate": $statusCode = $this->processCommandGenerate($command, $text); break;
            case "clean":    $statusCode = $this->processCommandClean($command, $text); break;
            default:         $statusCode = 0;
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        return array("generate [directory location]", "clean [directory location]");
    }

    // Process command to generate static website
    public function processCommandGenerate($command, $text) {
        $statusCode = 0;
        list($path, $location) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($location) || substru($location, 0, 1)=="/") {
            if ($this->checkStaticSettings()) {
                $statusCode = $this->generateStatic($path, $location);
            } else {
                $statusCode = 500;
                $this->files = 0;
                $this->errors = 1;
                $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
                echo "ERROR generating files: Please configure GenerateStaticUrl in file '$fileName'!\n";
            }
            echo "Yellow $command: $this->files file".($this->files!=1 ? "s" : "");
            echo ", $this->errors error".($this->errors!=1 ? "s" : "")."\n";
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Generate static website
    public function generateStatic($path, $location) {
        $statusCode = 200;
        $this->files = $this->errors = 0;
        $path = rtrim(is_string_empty($path) ? $this->yellow->system->get("generateStaticDirectory") : $path, "/");
        if (is_string_empty($location)) {
            $statusCode = $this->cleanStatic($path, $location);
            foreach ($this->yellow->extension->data as $key=>$value) {
                if (method_exists($value["object"], "onUpdate")) $value["object"]->onUpdate("clean");
            }
        }
        $statusCode = max($statusCode, $this->generateStaticContent($path, $location, "\rGenerating static website", 5, 95));
        $statusCode = max($statusCode, $this->generateStaticMedia($path, $location));
        echo "\rGenerating static website 100%... done\n";
        return $statusCode;
    }
    
    // Generate static content
    public function generateStaticContent($path, $locationFilter, $progressText, $increments, $max) {
        $statusCode = 200;
        $this->locationsArguments = $this->locationsArgumentsPagination = array();
        $staticUrl = $this->yellow->system->get("generateStaticUrl");
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        $locations = $this->getContentLocations();
        $filesEstimated = count($locations);
        foreach ($locations as $location) {
            echo "$progressText ".$this->getProgressPercent($this->files, $filesEstimated, $increments, $max/1.5)."%... ";
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            $statusCode = max($statusCode, $this->generateStaticFile($path, $location, true));
        }
        foreach ($this->locationsArguments as $location) {
            echo "$progressText ".$this->getProgressPercent($this->files, $filesEstimated, $increments, $max/1.5)."%... ";
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            $statusCode = max($statusCode, $this->generateStaticFile($path, $location, true));
        }
        $filesEstimated = $this->files + count($this->locationsArguments) + count($this->locationsArgumentsPagination);
        foreach ($this->locationsArgumentsPagination as $location) {
            echo "$progressText ".$this->getProgressPercent($this->files, $filesEstimated, $increments, $max)."%... ";
            if (!preg_match("#^$base$locationFilter#", "$base$location")) continue;
            if (substru($location, -1)!=$this->yellow->toolbox->getLocationArgumentsSeparator()) {
                $statusCode = max($statusCode, $this->generateStaticFile($path, $location, false, true));
            }
            for ($pageNumber=2; $pageNumber<=999; ++$pageNumber) {
                $statusCodeLocation = $this->generateStaticFile($path, $location.$pageNumber, false, true);
                $statusCode = max($statusCode, $statusCodeLocation);
                if ($statusCodeLocation==100) break;
            }
        }
        echo "$progressText ".$this->getProgressPercent(100, 100, $increments, $max)."%... ";
        return $statusCode;
    }
    
    // Generate static media
    public function generateStaticMedia($path, $locationFilter) {
        $statusCode = 200;
        if (is_string_empty($locationFilter)) {
            foreach ($this->getMediaLocations() as $location) {
                $statusCode = max($statusCode, $this->generateStaticFile($path, $location));
            }
            foreach ($this->getExtraLocations($path) as $location) {
                $statusCode = max($statusCode, $this->generateStaticFile($path, $location));
            }
            $statusCode = max($statusCode, $this->generateStaticFile($path, "/error/", false, false, true));
        }
        return $statusCode;
    }
    
    // Generate static file
    public function generateStaticFile($path, $location, $analyse = false, $probe = false, $error = false) {
        $this->yellow->content = new YellowContent($this->yellow);
        $this->yellow->page = new YellowPage($this->yellow);
        $this->yellow->page->fileName = substru($location, 1);
        if (!is_readable($this->yellow->page->fileName)) {
            ob_start();
            $staticUrl = $this->yellow->system->get("generateStaticUrl");
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
            echo "\rERROR generating location '$location', ".$this->yellow->page->getStatusCode(true)."\n";
        }
        if ($this->yellow->system->get("coreDebugMode")>=1) {
            echo "YellowGenerate::generateStaticFile status:$statusCode location:$location<br/>\n";
        }
        return $statusCode;
    }
    
    // Request static file
    public function requestStaticFile($scheme, $address, $base, $location) {
        list($serverName, $serverPort) = $this->yellow->toolbox->getTextList($address, ":", 2);
        if (is_string_empty($serverPort)) $serverPort = $scheme=="https" ? 443 : 80;
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
                        echo "YellowGenerate::analyseLocations detected location:$location<br/>\n";
                    }
                }
            } else {
                $location = rtrim($location, "0..9");
                if (!isset($this->locationsArgumentsPagination[$location])) {
                    $this->locationsArgumentsPagination[$location] = $location;
                    if ($this->yellow->system->get("coreDebugMode")>=2) {
                        echo "YellowGenerate::analyseLocations detected location:$location<br/>\n";
                    }
                }
            }
        }
    }
    
    // Process command to clean static website
    public function processCommandClean($command, $text) {
        $statusCode = 0;
        list($path, $location) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($location) || substru($location, 0, 1)=="/") {
            $statusCode = $this->cleanStatic($path, $location);
            echo "Yellow $command: Static website";
            echo " ".($statusCode!=200 ? "not " : "")."cleaned\n";
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Clean static website
    public function cleanStatic($path, $location) {
        $statusCode = 200;
        $path = rtrim(is_string_empty($path) ? $this->yellow->system->get("generateStaticDirectory") : $path, "/");
        if (is_string_empty($location)) {
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
            if (!$this->yellow->lookup->isFileLocation($location)) $fileName .= $this->yellow->system->get("generateStaticDefaultFile");
            if (is_file($fileName) && is_readable($fileName) && !$this->yellow->lookup->isCommandLine()) {
                $statusCode = $this->yellow->sendFile(200, $fileName, true);
            }
        }
        return $statusCode;
    }
    
    // Check static settings
    public function checkStaticSettings() {
        return preg_match("/^(http|https):/", $this->yellow->system->get("generateStaticUrl"));
    }
    
    // Check static directory
    public function checkStaticDirectory($path) {
        $ok = false;
        if (!is_string_empty($path)) {
            if ($path==rtrim($this->yellow->system->get("generateStaticDirectory"), "/")) $ok = true;
            if ($path==rtrim($this->yellow->system->get("coreCacheDirectory"), "/")) $ok = true;
            if ($path==rtrim($this->yellow->system->get("coreTrashDirectory"), "/")) $ok = true;
            if (is_file("$path/".$this->yellow->system->get("generateStaticDefaultFile"))) $ok = true;
            if (is_file("$path/yellow.php")) $ok = false;
        }
        return $ok;
    }
    
    // Return progress in percent
    public function getProgressPercent($now, $total, $increments, $max) {
        $max = intval($max/$increments) * $increments;
        $percent = intval(($max/$total) * $now);
        if ($increments>1) $percent = intval($percent/$increments) * $increments;
        return min($max, $percent);
    }
    
    // Return static file
    public function getStaticFile($path, $location, $statusCode) {
        if ($statusCode<400) {
            $fileName = $path.$location;
            if (!$this->yellow->lookup->isFileLocation($location)) $fileName .= $this->yellow->system->get("generateStaticDefaultFile");
        } elseif ($statusCode==404) {
            $fileName = $path."/".$this->yellow->system->get("generateStaticErrorFile");
        } else {
            $fileName = $path."/error.html";
        }
        return $fileName;
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
        $staticUrl = $this->yellow->system->get("generateStaticUrl");
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($staticUrl);
        $this->yellow->page->setRequestInformation($scheme, $address, $base, "", "", false);
        foreach ($this->yellow->content->index(true, true) as $page) {
            if (preg_match("/exclude/i", $page->get("generate")) && !$includeAll) continue;
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
            $this->yellow->system->get("generateStaticDirectory")."|".
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
}
