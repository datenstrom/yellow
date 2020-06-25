<?php
// Update extension, https://github.com/datenstrom/yellow-extensions/tree/master/features/update
// Copyright (c) 2013-2020 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowUpdate {
    const VERSION = "0.8.20";
    const TYPE = "feature";
    const PRIORITY = "2";
    public $yellow;                 //access to API
    public $updates;                //number of updates
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("updateExtensionUrl", "https://github.com/datenstrom/yellow-extensions");
        $this->yellow->system->setDefault("updateExtensionDirectory", "/Users/yourname/Documents/GitHub/");
        $this->yellow->system->setDefault("updateExtensionFile", "extension.ini");
        $this->yellow->system->setDefault("updateVersionFile", "version.ini");
        $this->yellow->system->setDefault("updateWaffleFile", "waffle.ini");
        $this->yellow->system->setDefault("updateNotification", "none");
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName) && $this->isExtensionPending()) {
            $statusCode = $this->processRequestPending($scheme, $address, $base, $location, $fileName);
        }
        return $statusCode;
    }
    
    // Handle command
    public function onCommand($command, $text) {
        $statusCode = 0;
        if ($this->isExtensionPending()) $statusCode = $this->processCommandPending();
        if ($statusCode==0) {
            switch ($command) {
                case "clean":       $statusCode = $this->processCommandClean($command, $text); break;
                case "install":     $statusCode = $this->processCommandInstall($command, $text); break;
                case "uninstall":   $statusCode = $this->processCommandUninstall($command, $text); break;
                case "update":      $statusCode = $this->processCommandUpdate($command, $text); break;
                default:            $statusCode = 0; break;
            }
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        $help = "install [extension]\n";
        $help .= "uninstall [extension]\n";
        $help .= "update [extension]\n";
        return $help;
    }

    // Handle update
    public function onUpdate($action) {
        if ($action=="update") {  //TODO: remove later, converts old server settings
            if ($this->yellow->system->isExisting("staticUrl")) {
                $coreStaticUrl = $this->yellow->system->get("staticUrl");
                $coreServerUrl = empty($this->yellow->system->get("serverUrl")) ? "auto" : $this->yellow->system->get("serverUrl");
                $coreServerTimezone = $this->yellow->system->get("timezone");
                $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
                $this->yellow->system->save($fileName, array("coreStaticUrl" => $coreStaticUrl, "coreServerUrl" => $coreServerUrl,
                    "coreServerTimezone" => $coreServerTimezone));
            }
        }
        if ($action=="update") {  //TODO: remove later, converts old content settings
            if ($this->yellow->system->isExisting("multiLanguageMode")) {
                $coreMultiLanguageMode = $this->yellow->system->get("multiLanguageMode");
                $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
                $this->yellow->system->save($fileName, array("coreMultiLanguageMode" => $coreMultiLanguageMode));
                $path = $this->yellow->system->get("coreContentDirectory");
                foreach ($this->yellow->toolbox->getDirectoryEntriesRecursive($path, "/^.*\.md$/", true, false) as $entry) {
                    $fileData = $fileDataNew = $this->yellow->toolbox->readFile($entry);
                    $fileStatusUnlisted = false;
                    $tokens = explode("/", substru($entry, strlenu($path)));
                    for ($i=0; $i<count($tokens)-1; ++$i) {
                        if (!preg_match("/^[\d\-\_\.]+(.*)$/", $tokens[$i]) && $tokens[$i]!="shared") {
                            $fileStatusUnlisted = true;
                            break;
                        }
                    }
                    $fileDataNew = preg_replace("/Status: hidden/i", "Status: shared", $fileDataNew);
                    $fileDataNew = preg_replace("/Status: ignore/i", "Build: exclude", $fileDataNew);
                    if ($fileStatusUnlisted && empty($this->yellow->toolbox->getMetaData($fileDataNew, "status"))) {
                        $fileDataNew = $this->yellow->toolbox->setMetaData($fileDataNew, "status", "unlisted");
                    }
                    if ($fileData!=$fileDataNew) {
                        $modified = $this->yellow->toolbox->getFileModified($entry);
                        if (!$this->yellow->toolbox->deleteFile($entry) ||
                            !$this->yellow->toolbox->createFile($entry, $fileDataNew) ||
                            !$this->yellow->toolbox->modifyFile($entry, $modified)) {
                            $this->yellow->log("error", "Can't write file '$entry'!");
                        }
                    }
                }
            }
        }
        if ($action=="update") {  //TODO: remove later, converts old layout files
            if ($this->yellow->system->isExisting("navigation")) {
                $navigation = $this->yellow->system->get("navigation");
                $path = $this->yellow->system->get("coreLayoutDirectory");
                foreach ($this->yellow->toolbox->getDirectoryEntriesRecursive($path, "/^.*\.html$/", true, false) as $entry) {
                    $fileData = $fileDataNew = $this->yellow->toolbox->readFile($entry);
                    $fileDataNew = str_replace("system->get(\"serverScheme\")", "system->get(\"coreServerScheme\")", $fileDataNew);
                    $fileDataNew = str_replace("system->get(\"serverAddress\")", "system->get(\"coreServerAddress\")", $fileDataNew);
                    $fileDataNew = str_replace("system->get(\"serverBase\")", "system->get(\"coreServerBase\")", $fileDataNew);
                    $fileDataNew = str_replace("system->get(\"imageLocation\")", "system->get(\"coreImageLocation\")", $fileDataNew);
                    $fileDataNew = str_replace("system->get(\"extensionLocation\")", "system->get(\"coreExtensionLocation\")", $fileDataNew);
                    $fileDataNew = str_replace("system->get(\"resourceLocation\")", "system->get(\"coreResourceLocation\")", $fileDataNew);
                    $fileDataNew = str_replace("text->getHtml(\"paginationPrevious\")", "text->getHtml(\"corePaginationPrevious\")", $fileDataNew);
                    $fileDataNew = str_replace("text->getHtml(\"paginationNext\")", "text->getHtml(\"corePaginationNext\")", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"header\")", "\"header\"", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"sidebar\")", "\"sidebar\"", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"footer\")", "\"footer\"", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"navigation\")", "\"$navigation\"", $fileDataNew);
                    if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($entry, $fileDataNew)) {
                        $this->yellow->log("error", "Can't write file '$entry'!");
                    }
                }
            }
        }
        if ($action=="startup") {
            if ($this->yellow->system->get("updateNotification")!="none") {
                foreach (explode(",", $this->yellow->system->get("updateNotification")) as $token) {
                    list($extension, $action) = explode("/", $token, 2);
                    if ($this->yellow->extensions->isExisting($extension) && ($action!="startup" && $action!="uninstall")) {
                        $value = $this->yellow->extensions->extensions[$extension];
                        if (method_exists($value["obj"], "onUpdate")) $value["obj"]->onUpdate($action);
                    }
                }
                $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
                $this->yellow->system->save($fileName, array("updateNotification" => "none"));
                $fileData = $this->yellow->toolbox->readFile($fileName);
                $fileDataHeader = $fileDataSettings = $fileDataFooter = "";
                $settings = new YellowDataCollection();
                $settings->exchangeArray($this->yellow->system->settingsDefaults->getArrayCopy());
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (empty($fileDataHeader) && preg_match("/^\#/", $line)) {
                        $fileDataHeader = $line;
                    } elseif (!empty($matches[1]) && isset($settings[$matches[1]])) {
                        $settings[$matches[1]] = $matches[2];
                    } elseif (!empty($matches[1]) && substru($matches[1], 0, 1)!="#") {
                        $fileDataFooter .= "# $line";
                    } elseif (!empty($matches[1])) {
                        $fileDataFooter .= $line;
                    }
                }
                unset($settings["coreSystemFile"]);
                foreach ($settings as $key=>$value) {
                    if ($key=="coreStaticUrl") $fileDataSettings .= "\n";
                    $fileDataSettings .= ucfirst($key).(strempty($value) ? ":\n" : ": $value\n");
                }
                if (!empty($fileDataHeader)) $fileDataHeader .= "\n";
                if (!empty($fileDataFooter)) $fileDataSettings .= "\n";
                $fileDataNew = $fileDataHeader.$fileDataSettings.$fileDataFooter;
                if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($fileName, $fileDataNew)) {
                    $this->yellow->log("error", "Can't write file '$fileName'!");
                }
            }
        }
    }
    
    // Process command to clean downloads
    public function processCommandClean($command, $text) {
        $statusCode = 0;
        if ($command=="clean" && $text=="all") {
            $path = $this->yellow->system->get("coreExtensionDirectory");
            $regex = "/^.*\\".$this->yellow->system->get("coreDownloadExtension")."$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, false, false) as $entry) {
                if (!$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
            }
            if ($statusCode==500) echo "ERROR cleaning downloads: Can't delete files in directory '$path'!\n";
        }
        return $statusCode;
    }
    
    // Process command to install extensions
    public function processCommandInstall($command, $text) {
        list($extensions) = $this->getExtensionInformation($text);
        if (!empty($extensions)) {
            $this->updates = 0;
            list($statusCode, $data) = $this->getInstallInformation($extensions);
            if ($statusCode==200) $statusCode = $this->downloadExtensions($data);
            if ($statusCode==200) $statusCode = $this->updateExtensions("install");
            if ($statusCode>=400) echo "ERROR installing files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates extension".($this->updates!=1 ? "s" : "")." installed\n";
        } else {
            $statusCode = $this->showExtensions();
        }
        return $statusCode;
    }
    
    // Process command to uninstall extensions
    public function processCommandUninstall($command, $text) {
        list($extensions) = $this->getExtensionInformation($text);
        if (!empty($extensions)) {
            $this->updates = 0;
            list($statusCode, $data) = $this->getUninstallInformation($extensions, "core, command, update");
            if ($statusCode==200) $statusCode = $this->removeExtensions($data);
            if ($statusCode>=400) echo "ERROR uninstalling files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates extension".($this->updates!=1 ? "s" : "")." uninstalled\n";
        } else {
            $statusCode = $this->showExtensions();
        }
        return $statusCode;
    }
    
    // Process command to update website
    public function processCommandUpdate($command, $text) {
        list($extensions, $force) = $this->getExtensionInformation($text);
        list($statusCode, $data) = $this->getUpdateInformation($extensions, $force);
        if ($statusCode!=200 || !empty($data)) {
            $this->updates = 0;
            if ($statusCode==200) $statusCode = $this->downloadExtensions($data);
            if ($statusCode==200) $statusCode = $this->updateExtensions("update", $force);
            if ($statusCode>=400) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates update".($this->updates!=1 ? "s" : "")." installed\n";
        } else {
            echo "Your website is up to date\n";
        }
        return $statusCode;
    }
    
    // Process command to install pending extension
    public function processCommandPending() {
        $statusCode = $this->updateExtensions("install");
        if ($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
        echo "Your website has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
        return $statusCode;
    }
    
    // Process request to install pending extension
    public function processRequestPending($scheme, $address, $base, $location, $fileName) {
        $statusCode = $this->updateExtensions("install");
        if ($statusCode==200) {
            $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
            $statusCode = $this->yellow->sendStatus(303, $location);
        }
        return $statusCode;
    }
    
    // Process update notification
    public function processUpdateNotification($extension, $action) {
        $statusCode = 200;
        if ($this->yellow->extensions->isExisting($extension) && $action=="uninstall") {
            $value = $this->yellow->extensions->extensions[$extension];
            if (method_exists($value["obj"], "onUpdate")) $value["obj"]->onUpdate($action);
        }
        $updateNotification = $this->yellow->system->get("updateNotification");
        if ($updateNotification=="none") $updateNotification = "";
        if (!empty($updateNotification)) $updateNotification .= ",";
        $updateNotification .= "$extension/$action";
        $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
        if (!$this->yellow->system->save($fileName, array("updateNotification" => $updateNotification))) {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Return extension information
    public function getExtensionInformation($text) {
        $extensions = array_unique(array_filter($this->yellow->toolbox->getTextArguments($text), "strlen"));
        $force = false;
        foreach ($extensions as $key=>$value) {
            if ($value=="force") {
                $force = true;
                unset($extensions[$key]);
            }
        }
        return array($extensions, $force);
    }

    // Return install information
    public function getInstallInformation($extensions) {
        $data = array();
        list($statusCodeCurrent, $dataCurrent) = $this->getExtensionsVersion();
        list($statusCodeLatest, $dataLatest) = $this->getExtensionsVersion(true, true);
        $statusCode = max($statusCodeCurrent, $statusCodeLatest);
        foreach ($extensions as $extension) {
            $found = false;
            foreach ($dataLatest as $key=>$value) {
                if (strtoloweru($key)==strtoloweru($extension)) {
                    $data[$key] = $dataLatest[$key];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't find extension '$extension'!");
            }
        }
        return array($statusCode, $data);
    }

    // Return uninstall information
    public function getUninstallInformation($extensions, $extensionsProtected) {
        $data = array();
        list($statusCodeCurrent, $dataCurrent) = $this->getExtensionsVersion();
        list($statusCodeLatest, $dataLatest) = $this->getExtensionsVersion(true, true);
        list($statusCodeRelevant, $dataRelevant) = $this->getExtensionsRelevant();
        $statusCode = max($statusCodeCurrent, $statusCodeLatest, $statusCodeRelevant);
        foreach ($extensions as $extension) {
            $found = false;
            foreach ($dataCurrent as $key=>$value) {
                if (strtoloweru($key)==strtoloweru($extension) && isset($dataLatest[$key]) && isset($dataRelevant[$key])) {
                    $data[$key] = $dataRelevant[$key];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't find extension '$extension'!");
            }
        }
        $protected = preg_split("/\s*,\s*/", $extensionsProtected);
        foreach ($data as $key=>$value) {
            if (in_array($key, $protected)) unset($data[$key]);
        }
        return array($statusCode, $data);
    }

    // Return update information
    public function getUpdateInformation($extensions, $force) {
        $data = array();
        list($statusCodeCurrent, $dataCurrent) = $this->getExtensionsVersion();
        list($statusCodeLatest, $dataLatest) = $this->getExtensionsVersion(true, true);
        list($statusCodeModified, $dataModified) = $this->getExtensionsModified();
        $statusCode = max($statusCodeCurrent, $statusCodeLatest, $statusCodeModified);
        if (empty($extensions)) {
            foreach ($dataCurrent as $key=>$value) {
                if (isset($dataLatest[$key])) {
                    list($version) = explode(",", $dataLatest[$key]);
                    if (strnatcasecmp($dataCurrent[$key], $version)<0) $data[$key] = $dataLatest[$key];
                    if (isset($dataModified[$key]) && !empty($version) && $force) $data[$key] = $dataLatest[$key];
                }
            }
        } else {
            foreach ($extensions as $extension) {
                $found = false;
                foreach ($dataCurrent as $key=>$value) {
                    if (isset($dataLatest[$key])) {
                        list($version) = explode(",", $dataLatest[$key]);
                        if (strtoloweru($key)==strtoloweru($extension) && !empty($version)) {
                            $data[$key] = $dataLatest[$key];
                            $dataModified = array_intersect_key($dataModified, $data);
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't find extension '$extension'!");
                }
            }
        }
        if ($statusCode==200) {
            foreach (array_merge($dataModified, $data) as $key=>$value) {
                list($version) = explode(",", $value);
                if (!isset($dataModified[$key]) || $force) {
                    echo ucfirst($key)." $version\n";
                } else {
                    echo ucfirst($key)." $version has been modified - Force update\n";
                }
            }
        }
        return array($statusCode, $data);
    }
    
    // Show extensions
    public function showExtensions() {
        list($statusCode, $dataLatest) = $this->getExtensionsVersion(true, true);
        foreach ($dataLatest as $key=>$value) {
            list($version, $url, $description) = explode(",", $value, 3);
            echo ucfirst($key).": $description\n";
        }
        if ($statusCode!=200) echo "ERROR checking extensions: ".$this->yellow->page->get("pageError")."\n";
        return $statusCode;
    }
    
    // Download extensions
    public function downloadExtensions($data) {
        $statusCode = 200;
        $path = $this->yellow->system->get("coreExtensionDirectory");
        $fileExtension = $this->yellow->system->get("coreDownloadExtension");
        foreach ($data as $key=>$value) {
            $fileName = $path.$this->yellow->lookup->normaliseName($key, true, false, true).".zip";
            list($version, $url) = explode(",", $value);
            list($statusCode, $fileData) = $this->getExtensionFile($url);
            if (empty($fileData) || !$this->yellow->toolbox->createFile($fileName.$fileExtension, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                break;
            }
        }
        if ($statusCode==200) {
            foreach ($data as $key=>$value) {
                $fileName = $path.$this->yellow->lookup->normaliseName($key, true, false, true).".zip";
                if (!$this->yellow->toolbox->renameFile($fileName.$fileExtension, $fileName)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
        }
        return $statusCode;
    }

    // Update extensions
    public function updateExtensions($action, $force = false) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->system->get("coreExtensionDirectory");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            $statusCode = max($statusCode, $this->updateExtensionArchive($entry, $action, $force));
            if (!$this->yellow->toolbox->deleteFile($entry)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
            }
        }
        return $statusCode;
    }

    // Update extension from archive
    public function updateExtensionArchive($path, $action, $force = false) {
        $statusCode = 200;
        $zip = new ZipArchive();
        if ($zip->open($path)===true) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::updateExtensionArchive file:$path<br/>\n";
            $extension = $version = $language = "";
            $modified = $lastModified = $lastPublished = 0;
            if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
            $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                    $fileName = $matches[1];
                    if (is_file($fileName)) {
                        $lastPublished = filemtime($fileName);
                        break;
                    }
                }
            }
            $rootPages = array();
            foreach ($this->yellow->content->scanLocation("") as $page) {
                if ($page->isAvailable() && $page->isVisible()) array_push($rootPages, $page);
            }
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])=="extension") $extension = lcfirst($matches[2]);
                    if (lcfirst($matches[1])=="version") $version = lcfirst($matches[2]);
                    if (lcfirst($matches[1])=="published") $modified = strtotime($matches[2]);
                    if (lcfirst($matches[1])=="language") $language = $matches[2];
                    if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                        $fileName = $matches[1];
                        list($dummy, $entry, $flags) = explode(",", $matches[2], 3);
                        foreach ($rootPages as $page) {
                            list($fileNameSource, $fileNameDestination) = $this->getExtensionsFileNames($fileName, $entry, $flags, $language, $pathBase, $page);
                            $fileData = $zip->getFromName($fileNameSource);
                            $lastModified = $this->yellow->toolbox->getFileModified($fileNameDestination);
                            $statusCode = $this->updateExtensionFile($fileNameDestination, $fileData,
                                $modified, $lastModified, $lastPublished, $flags, $force, $extension);
                        }
                        if ($statusCode!=200) break;
                    }
                }
            }
            $zip->close();
            $statusCode = max($statusCode, $this->processUpdateNotification($extension, $action));
            $this->yellow->log($statusCode==200 ? "info" : "error", ucfirst($action)." extension '".ucfirst($extension)." $version'");
            ++$this->updates;
        } else {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't open file '$path'!");
        }
        return $statusCode;
    }
    
    // Update extension from file
    public function updateExtensionFile($fileName, $fileData, $modified, $lastModified, $lastPublished, $flags, $force, $extension) {
        $statusCode = 200;
        $fileName = $this->yellow->toolbox->normaliseTokens($fileName);
        if ($this->yellow->lookup->isValidFile($fileName) && !empty($extension)) {
            $create = $update = $delete = false;
            if (preg_match("/create/i", $flags) && !is_file($fileName) && !empty($fileData)) $create = true;
            if (preg_match("/update/i", $flags) && is_file($fileName) && !empty($fileData)) $update = true;
            if (preg_match("/delete/i", $flags) && is_file($fileName)) $delete = true;
            if (preg_match("/optional/i", $flags) && $this->yellow->extensions->isExisting($extension)) $create = $update = $delete = false;
            if (preg_match("/careful/i", $flags) && is_file($fileName) && $lastModified!=$lastPublished && !$force) $update = false;
            if ($create) {
                if (!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
            if ($update) {
                if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("coreTrashDirectory")) ||
                    !$this->yellow->toolbox->createFile($fileName, $fileData) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
            if ($delete) {
                if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("coreTrashDirectory"))) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
                }
            }
            if (defined("DEBUG") && DEBUG>=2) {
                $debug = "action:".($create ? "create" : "").($update ? "update" : "").($delete ? "delete" : "");
                if (!$create && !$update && !$delete) $debug = "action:none";
                echo "YellowUpdate::updateExtensionFile file:$fileName $debug<br/>\n";
            }
        }
        return $statusCode;
    }
    
    // Return extension file names
    public function getExtensionsFileNames($fileName, $entry, $flags, $language, $pathBase, $page) {
        if (preg_match("/multi-language/i", $flags)) {
            $languagesAvailable = preg_split("/\s*,\s*/", $language);
            $languagesWanted = array($page->get("language"), "en");
            foreach ($languagesWanted as $language) {
                if (in_array($language, $languagesAvailable)) {
                    $languageFound = $language;
                    break;
                }
            }
            $pathLanguage = $languageFound ? "$languageFound/" : "";
            $fileNameSource = $pathBase.$pathLanguage.basename($entry);
        } else {
            $fileNameSource = $pathBase.basename($entry);
        }
        if ($this->yellow->system->get("coreMultiLanguageMode") && $this->yellow->lookup->isContentFile($fileName)) {
            $contentDirectoryLength = strlenu($this->yellow->system->get("coreContentDirectory"));
            $fileNameDestination = $page->fileName.substru($fileName, $contentDirectoryLength);
        } else {
            $fileNameDestination = $fileName;
        }
        return array($fileNameSource, $fileNameDestination);
    }

    // Remove extensions
    public function removeExtensions($data) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        foreach ($data as $key=>$value) {
            foreach (preg_split("/\s*,\s*/", $value) as $fileName) {
                $statusCode = max($statusCode, $this->removeExtensionsFile($fileName, $key));
            }
            $statusCode = max($statusCode, $this->processUpdateNotification($key, "uninstall"));
            $version = $this->yellow->extensions->isExisting($key) ? $this->yellow->extensions->extensions[$key]["version"] : "";
            $this->yellow->log($statusCode==200 ? "info" : "error", "Uninstall extension '".ucfirst($key)." $version'");
            ++$this->updates;
        }
        return $statusCode;
    }
    
    // Remove extensions file
    public function removeExtensionsFile($fileName, $extension) {
        $statusCode = 200;
        $fileName = $this->yellow->toolbox->normaliseTokens($fileName);
        if ($this->yellow->lookup->isValidFile($fileName) && !empty($extension)) {
            if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("coreTrashDirectory"))) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
            }
            if (defined("DEBUG") && DEBUG>=2) {
                echo "YellowUpdate::removeExtensionsFile file:$fileName action:delete<br/>\n";
            }
        }
        return $statusCode;
    }
    
    // Return extensions version
    public function getExtensionsVersion($latest = false, $rawFormat = false) {
        $data = array();
        if ($latest) {
            $url = $this->yellow->system->get("updateExtensionUrl")."/raw/master/".$this->yellow->system->get("updateVersionFile");
            list($statusCode, $fileData) = $this->getExtensionFile($url);
            if ($statusCode==200) {
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (!empty($matches[1]) && !empty($matches[2])) {
                        $extension = lcfirst($matches[1]);
                        list($version) = explode(",", $matches[2]);
                        $data[$extension] = $rawFormat ? $matches[2] : $version;
                    }
                }
            }
        } else {
            $statusCode = 200;
            $data = $this->yellow->extensions->getData();
        }
        return array($statusCode, $data);
    }
 
    // Return extensions relevant files
    public function getExtensionsRelevant() {
        $data = array();
        $url = $this->yellow->system->get("updateExtensionUrl")."/raw/master/".$this->yellow->system->get("updateWaffleFile");
        list($statusCode, $fileData) = $this->getExtensionFile($url);
        if ($statusCode==200) {
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    $fileName = $matches[1];
                    list($extension) = explode(",", lcfirst($matches[2]), 3);
                    if (!isset($data[$extension])) {
                        $data[$extension] = $fileName;
                    } else {
                        $data[$extension] .= ",".$fileName;
                    }
                }
            }
        }
        return array($statusCode, $data);
    }
    
    // Return extensions modified files
    public function getExtensionsModified() {
        $data = array();
        $dataCurrent = $this->yellow->extensions->getData();
        $url = $this->yellow->system->get("updateExtensionUrl")."/raw/master/".$this->yellow->system->get("updateWaffleFile");
        list($statusCode, $fileData) = $this->getExtensionFile($url);
        if ($statusCode==200) {
            $extension = "";
            $lastModified = $lastPublished = 0;
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    $fileName = $matches[1];
                    list($extensionNew, $dummy, $flags) = explode(",", lcfirst($matches[2]), 3);
                    if ($extension!=$extensionNew) {
                        $extension = $extensionNew;
                        $lastPublished = $this->yellow->toolbox->getFileModified($fileName);
                    }
                    if (isset($dataCurrent[$extension])) {
                        $lastModified = $this->yellow->toolbox->getFileModified($fileName);
                        if (preg_match("/update/i", $flags) && preg_match("/careful/i", $flags) && $lastModified!=$lastPublished) {
                            $data[$extension] = $dataCurrent[$extension];
                            if (defined("DEBUG") && DEBUG>=2) {
                                echo "YellowUpdate::getExtensionsModified detected file:$fileName extension:$extension<br/>\n";
                            }
                        }
                    }
                }
            }
        }
        return array($statusCode, $data);
    }
    
    // Return extension file
    public function getExtensionFile($url) {
        $urlRequest = $url;
        if (preg_match("#^https://github.com/(.+)/raw/(.+)$#", $url, $matches)) $urlRequest = "https://raw.githubusercontent.com/".$matches[1]."/".$matches[2];
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $urlRequest);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; DatenstromYellow/".YellowCore::VERSION."; SoftwareUpdater)");
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
        $rawData = curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $fileData = "";
        curl_close($curlHandle);
        if ($statusCode==200) {
            $fileData = $rawData;
        } elseif ($statusCode==0) {
            $statusCode = 500;
            list($scheme, $address) = $this->yellow->lookup->getUrlInformation($url);
            $this->yellow->page->error($statusCode, "Can't connect to server '$scheme://$address'!");
        } else {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't download file '$url'!");
        }
        if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::getExtensionFile status:$statusCode url:$url<br/>\n";
        return array($statusCode, $fileData);
    }

    // Check if extension pending
    public function isExtensionPending() {
        $path = $this->yellow->system->get("coreExtensionDirectory");
        return count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", false, false))>0;
    }
}
