<?php
// Core extension, https://github.com/annaesvensson/yellow-core
// This file is only needed for backwards compatibility with Datenstrom Yellow 0.8
// Please note that the latest core can be found in file `system/workers/core.php`
    
class YellowCore {
    const VERSION = "0.8.134";
    const RELEASE = "0.8.23";
    
    // Handle initialisation
    public function load() {
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    }
    
    // Handle request from web browser
    public function request() {
        $statusCode = $this->processCoreUpdate();
        if ($statusCode==303) {
            header("HTTP/1.0 303 Reload please");
            header("Location: ".$_SERVER["REQUEST_URI"]);
            header("Cache-Control: no-cache, no-store");
        }
        return $statusCode;
    }
    
    // Handle command from command line
    public function command($line = "") {
        $statusCode = $this->processCoreUpdate();
        if ($statusCode==303) {
            echo "Flux capacitor has reached 1.21 gigawatts. Please run command again.\n";
        }
        return $statusCode<400 ? 0 : 1;
    }
    
    // Update core for new release
    public function processCoreUpdate() {
        $statusCode = 303;
        if (is_file("system/extensions/core.php") && is_file("system/workers/core.php")) {
            $fileName = "yellow.php";
            $fileData = $fileDataNew = $this->readFile($fileName);
            $fileDataNew = str_replace("system/extensions/core.php", "system/workers/core.php", $fileDataNew);
            if ($fileData!=$fileDataNew && !$this->createFile($fileName, $fileDataNew)) {
                $statusCode = 500;
                header("HTTP/1.0 500 Server error");
                echo "Something went wrong during core update: Can't write file '$fileName'! <br/>\n";
                echo "Datenstrom Yellow stopped with fatal error. Activate the debug mode for more information. ";
                echo "See https://datenstrom.se/yellow/help/troubleshooting <br/>\n";
            }
            if (function_exists("opcache_reset")) opcache_reset();
        }
        return $statusCode;
    }
    
    // Read file, empty string if not found
    public function readFile($fileName, $sizeMax = 0) {
        $fileData = "";
        $fileHandle = @fopen($fileName, "rb");
        if ($fileHandle) {
            clearstatcache(true, $fileName);
            if (flock($fileHandle, LOCK_SH)) {
                $fileSize = $sizeMax ? $sizeMax : filesize($fileName);
                if ($fileSize) $fileData = fread($fileHandle, $fileSize);
                flock($fileHandle, LOCK_UN);
            }
            fclose($fileHandle);
        }
        return $fileData;
    }
    
    // Create file
    public function createFile($fileName, $fileData, $mkdir = false) {
        $ok = false;
        if ($mkdir) {
            $path = dirname($fileName);
            if (!is_string_empty($path) && !is_dir($path)) @mkdir($path, 0777, true);
        }
        $fileHandle = @fopen($fileName, "cb");
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
}
