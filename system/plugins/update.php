<?php
// Update plugin, https://github.com/datenstrom/yellow-plugins/tree/master/update
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowUpdate {
    const VERSION = "0.7.19";
    public $yellow;                 //access to API
    public $updates;                //number of updates
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->config->setDefault("updatePluginsUrl", "https://github.com/datenstrom/yellow-plugins");
        $this->yellow->config->setDefault("updateThemesUrl", "https://github.com/datenstrom/yellow-themes");
        $this->yellow->config->setDefault("updateInformationFile", "update.ini");
        $this->yellow->config->setDefault("updateVersionFile", "version.ini");
        $this->yellow->config->setDefault("updateResourceFile", "resource.ini");
    }
    
    // Handle startup
    public function onStartup($update) {
        if ($update) {
            $fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
            $fileData = $this->yellow->toolbox->readFile($fileNameConfig);
            $configDefaults = new YellowDataCollection();
            $configDefaults->exchangeArray($this->yellow->config->configDefaults->getArrayCopy());
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !is_null($configDefaults[$matches[1]])) unset($configDefaults[$matches[1]]);
                if (!empty($matches[1]) && $matches[1][0]!="#" && is_null($this->yellow->config->configDefaults[$matches[1]])) {
                    $fileDataNew .= "# $line";
                } else {
                    $fileDataNew .= $line;
                }
            }
            unset($configDefaults["configFile"]);
            foreach ($configDefaults as $key=>$value) {
                $fileDataNew .= ucfirst($key).": $value\n";
            }
            if ($fileData!=$fileDataNew) $this->yellow->toolbox->createFile($fileNameConfig, $fileDataNew);
        }
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->config->get("installationMode")) {
            $statusCode = $this->processRequestInstallationMode($scheme, $address, $base, $location, $fileName);
        } else {
            $statusCode = $this->processRequestInstallationPending($scheme, $address, $base, $location, $fileName);
        }
        return $statusCode;
    }
    
    // Handle command
    public function onCommand($args) {
        list($command) = $args;
        switch ($command) {
            case "clean":       $statusCode = $this->cleanCommand($args); break;
            case "install":     $statusCode = $this->installCommand($args); break;
            case "uninstall":   $statusCode = $this->uninstallCommand($args); break;
            case "update":      $statusCode = $this->updateCommand($args); break;
            default:            $statusCode = $this->processCommandInstallationPending($args); break;
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        $help .= "install [feature]\n";
        $help .= "uninstall [feature]\n";
        $help .= "update [feature]\n";
        return $help;
    }
    
    // Clean downloads
    public function cleanCommand($args) {
        $statusCode = 0;
        list($command, $path) = $args;
        if ($path=="all") {
            $path = $this->yellow->config->get("pluginDir");
            $regex = "/^.*\\".$this->yellow->config->get("downloadExtension")."$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, false, false) as $entry) {
                if (!$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
            }
            if ($statusCode==500) echo "ERROR cleaning downloads: Can't delete files in directory '$path'!\n";
        }
        return $statusCode;
    }
    
    // Install feature
    public function installCommand($args) {
        list($command, $features) = $this->getCommandFeatures($args);
        if (!empty($features)) {
            $this->updates = 0;
            list($statusCode, $data) = $this->getInstallInformation($features);
            if ($statusCode==200) $statusCode = $this->downloadSoftware($data);
            if ($statusCode==200) $statusCode = $this->updateSoftware();
            if ($statusCode>=400) echo "ERROR installing files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates feature".($this->updates!=1 ? "s" : "")." installed\n";
        } else {
            $statusCode = $this->showSoftware();
        }
        return $statusCode;
    }
    
    // Uninstall feature
    public function uninstallCommand($args) {
        list($command, $features) = $this->getCommandFeatures($args);
        if (!empty($features)) {
            $this->updates = 0;
            list($statusCode, $data) = $this->getUninstallInformation($features, "YellowCore, YellowUpdate");
            if ($statusCode==200) $statusCode = $this->removeSoftware($data);
            if ($statusCode>=400) echo "ERROR uninstalling files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates feature".($this->updates!=1 ? "s" : "")." uninstalled\n";
        } else {
            $statusCode = $this->showSoftware();
        }
        return $statusCode;
    }
    
    // Update website
    public function updateCommand($args) {
        list($command, $features, $force) = $this->getCommandFeatures($args);
        list($statusCode, $data) = $this->getUpdateInformation($features, $force);
        if ($statusCode!=200 || !empty($data)) {
            $this->updates = 0;
            if ($statusCode==200) $statusCode = $this->downloadSoftware($data);
            if ($statusCode==200) $statusCode = $this->updateSoftware($force);
            if ($statusCode>=400) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates update".($this->updates!=1 ? "s" : "")." installed\n";
        } else {
            echo "Your website is up to date\n";
        }
        return $statusCode;
    }

    // Return install information
    public function getInstallInformation($features) {
        $data = array();
        list($statusCodeCurrent, $dataCurrent) = $this->getSoftwareVersion();
        list($statusCodeLatest, $dataLatest) = $this->getSoftwareVersion(true, true);
        $statusCode = max($statusCodeCurrent, $statusCodeLatest);
        foreach ($features as $feature) {
            $found = false;
            foreach ($dataLatest as $key=>$value) {
                if (strtoloweru($key)==strtoloweru($feature)) {
                    $data[$key] = $dataLatest[$key];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't find feature '$feature'!");
            }
        }
        return array($statusCode, $data);
    }

    // Return uninstall information
    public function getUninstallInformation($features, $featuresProtected) {
        $data = array();
        list($statusCodeCurrent, $dataCurrent) = $this->getSoftwareVersion();
        list($statusCodeLatest, $dataLatest) = $this->getSoftwareVersion(true, true);
        list($statusCodeFiles, $dataFiles) = $this->getSoftwareFiles();
        $statusCode = max($statusCodeCurrent, $statusCodeLatest, $statusCodeFiles);
        foreach ($features as $feature) {
            $found = false;
            foreach ($dataCurrent as $key=>$value) {
                if (strtoloweru($key)==strtoloweru($feature) && !is_null($dataLatest[$key]) && !is_null($dataFiles[$key])) {
                    $data[$key] = $dataFiles[$key];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't find feature '$feature'!");
            }
        }
        $protected = preg_split("/\s*,\s*/", $featuresProtected);
        foreach ($data as $key=>$value) {
            if (in_array($key, $protected)) unset($data[$key]);
        }
        return array($statusCode, $data);
    }

    // Return update information
    public function getUpdateInformation($features, $force) {
        $data = array();
        list($statusCodeCurrent, $dataCurrent) = $this->getSoftwareVersion();
        list($statusCodeLatest, $dataLatest) = $this->getSoftwareVersion(true, true);
        list($statusCodeModified, $dataModified) = $this->getSoftwareModified();
        $statusCode = max($statusCodeCurrent, $statusCodeLatest, $statusCodeModified);
        if (empty($features)) {
            foreach ($dataCurrent as $key=>$value) {
                list($version) = explode(",", $dataLatest[$key]);
                if (strnatcasecmp($dataCurrent[$key], $version)<0) $data[$key] = $dataLatest[$key];
                if (!is_null($dataModified[$key]) && !empty($version) && $force) $data[$key] = $dataLatest[$key];
            }
        } else {
            foreach ($features as $feature) {
                $found = false;
                foreach ($dataCurrent as $key=>$value) {
                    list($version) = explode(",", $dataLatest[$key]);
                    if (strtoloweru($key)==strtoloweru($feature) && !empty($version)) {
                        $data[$key] = $dataLatest[$key];
                        $dataModified = array_intersect_key($dataModified, $data);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't find feature '$feature'!");
                }
            }
        }
        if ($statusCode==200) {
            foreach (array_merge($dataModified, $data) as $key=>$value) {
                list($version) = explode(",", $value);
                if (is_null($dataModified[$key]) || $force) {
                    echo "$key $version\n";
                } else {
                    echo "$key $version has been modified - Force update\n";
                }
            }
        }
        return array($statusCode, $data);
    }
    
    // Show software features
    public function showSoftware() {
        list($statusCode, $dataLatest) = $this->getSoftwareVersion(true, true);
        foreach ($dataLatest as $key=>$value) {
            list($version, $url, $description) = explode(",", $value, 3);
            echo "$key: $description\n";
        }
        if ($statusCode!=200) echo "ERROR checking features: ".$this->yellow->page->get("pageError")."\n";
        return $statusCode;
    }
    
    // Download software
    public function downloadSoftware($data) {
        $statusCode = 200;
        $path = $this->yellow->config->get("pluginDir");
        $fileExtension = $this->yellow->config->get("downloadExtension");
        foreach ($data as $key=>$value) {
            $fileName = $path.$this->yellow->lookup->normaliseName($key, true, false, true).".zip";
            list($version, $url) = explode(",", $value);
            list($statusCode, $fileData) = $this->getSoftwareFile($url);
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

    // Update software
    public function updateSoftware($force = false) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->config->get("pluginDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            $statusCode = max($statusCode, $this->updateSoftwareArchive($entry, $force));
            if (!$this->yellow->toolbox->deleteFile($entry)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
            }
        }
        $path = $this->yellow->config->get("themeDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            $statusCode = max($statusCode, $this->updateSoftwareArchive($entry, $force));
            if (!$this->yellow->toolbox->deleteFile($entry)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
            }
        }
        return $statusCode;
    }

    // Update software from archive
    public function updateSoftwareArchive($path, $force = false) {
        $statusCode = 200;
        $zip = new ZipArchive();
        if ($zip->open($path)===true) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::updateSoftwareArchive file:$path<br/>\n";
            if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
            $fileData = $zip->getFromName($pathBase.$this->yellow->config->get("updateInformationFile"));
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    list($dummy, $entry) = explode("/", $matches[1], 2);
                    list($fileName, $flags) = explode(",", $matches[2], 2);
                    if ($dummy[0]!="Y") $fileName = $matches[1];    //TODO: remove later, converts old file format
                    if (is_file($fileName)) {
                        $lastPublished = filemtime($fileName);
                        break;
                    }
                }
            }
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (lcfirst($matches[1])=="plugin" || lcfirst($matches[1])=="theme") $software = $matches[2];
                if (lcfirst($matches[1])=="published") $modified = strtotime($matches[2]);
                if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                    list($dummy, $entry) = explode("/", $matches[1], 2);
                    list($fileName, $flags) = explode(",", $matches[2], 2);
                    if ($dummy[0]!="Y") { //TODO: remove later, converts old file format
                        list($entry, $flags) = explode(",", $matches[2], 2);
                        $fileName = $matches[1];
                    }
                    $fileData = $zip->getFromName($pathBase.$entry);
                    $lastModified = $this->yellow->toolbox->getFileModified($fileName);
                    $statusCode = $this->updateSoftwareFile($fileName, $fileData, $modified, $lastModified, $lastPublished, $flags, $force, $software);
                    if ($statusCode!=200) break;
                }
            }
            $zip->close();
            if ($statusCode==200) $statusCode = $this->updateSoftwareMultiLanguage($software);
            if ($statusCode==200) $statusCode = $this->updateSoftwareNotification($software);
            ++$this->updates;
        } else {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't open file '$path'!");
        }
        return $statusCode;
    }
    
    // Update software file
    public function updateSoftwareFile($fileName, $fileData, $modified, $lastModified, $lastPublished, $flags, $force, $software) {
        $statusCode = 200;
        $fileName = $this->yellow->toolbox->normaliseTokens($fileName);
        if ($this->yellow->lookup->isValidFile($fileName) && !empty($software)) {
            $create = $update = $delete = false;
            if (preg_match("/create/i", $flags) && !is_file($fileName) && !empty($fileData)) $create = true;
            if (preg_match("/update/i", $flags) && is_file($fileName) && !empty($fileData)) $update = true;
            if (preg_match("/delete/i", $flags) && is_file($fileName)) $delete = true;
            if (preg_match("/careful/i", $flags) && is_file($fileName) && $lastModified!=$lastPublished && !$force) $update = false;
            if (preg_match("/optional/i", $flags) && $this->isSoftwareExisting($software)) $create = $update = $delete = false;
            if ($create) {
                if (!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
            if ($update) {
                if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->config->get("trashDir")) ||
                    !$this->yellow->toolbox->createFile($fileName, $fileData) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
            if ($delete) {
                if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->config->get("trashDir"))) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
                }
            }
            if (defined("DEBUG") && DEBUG>=2) {
                $debug = "action:".($create ? "create" : "").($update ? "update" : "").($delete ? "delete" : "");
                if (!$create && !$update && !$delete) $debug = "action:none";
                echo "YellowUpdate::updateSoftwareFile file:$fileName $debug<br/>\n";
            }
        }
        return $statusCode;
    }

    // Update software for multiple languages
    public function updateSoftwareMultiLanguage($software) {
        $statusCode = 200;
        if ($this->yellow->config->get("multiLanguageMode") && !$this->isSoftwareExisting($software)) {
            $pathsSource = $pathsTarget = array();
            $pathBase = $this->yellow->config->get("contentDir");
            $fileExtension = $this->yellow->config->get("contentExtension");
            $fileRegex = "/^.*\\".$fileExtension."$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($pathBase, "/.*/", true, true) as $entry) {
                if (count($this->yellow->toolbox->getDirectoryEntries($entry, $fileRegex, false, false))) {
                    array_push($pathsSource, $entry."/");
                } elseif (count($this->yellow->toolbox->getDirectoryEntries($entry, "/.*/", false, true))) {
                    array_push($pathsTarget, $entry."/");
                }
            }
            if (count($pathsSource) && count($pathsTarget)) {
                foreach ($pathsSource as $pathSource) {
                    foreach ($pathsTarget as $pathTarget) {
                        $fileNames = $this->yellow->toolbox->getDirectoryEntriesRecursive($pathSource, "/.*/", false, false);
                        foreach ($fileNames as $fileName) {
                            $modified = $this->yellow->toolbox->getFileModified($fileName);
                            $fileNameTarget = $pathTarget.substru($fileName, strlenu($pathBase));
                            if (!is_file($fileNameTarget)) {
                                if (!$this->yellow->toolbox->copyFile($fileName, $fileNameTarget, true) ||
                                    !$this->yellow->toolbox->modifyFile($fileNameTarget, $modified)) {
                                    $statusCode = 500;
                                    $this->yellow->page->error(500, "Can't write file '$fileNameTarget'!");
                                }
                            }
                            if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::updateSoftwareNew file:$fileNameTarget<br/>\n";
                        }
                    }
                    if (!$this->yellow->toolbox->deleteDirectory($pathSource)) {
                        $statusCode = 500;
                        $this->yellow->page->error(500, "Can't delete path '$pathSource'!");
                    }
                }
            }
        }
        return $statusCode;
    }
    
    // Update software notification for next startup
    public function updateSoftwareNotification($software) {
        $statusCode = 200;
        $startupUpdate = $this->yellow->config->get("startupUpdate");
        if ($startupUpdate=="none") $startupUpdate = "YellowUpdate";
        if ($software!="YellowUpdate") $startupUpdate .= ",$software";
        $fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
        if (!$this->yellow->config->save($fileNameConfig, array("startupUpdate" => $startupUpdate))) {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't write file '$fileNameConfig'!");
        }
        return $statusCode;
    }
    
    // Update installation features
    public function updateInstallationFeatures($feature) {
        $statusCode = 200;
        $path = $this->yellow->config->get("pluginDir");
        $regex = "/^.*\.installation$/";
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry) {
            if (preg_match("/^(.*?)-(.*?)\./", basename($entry), $matches)) {
                if (strtoloweru($matches[2])==strtoloweru($feature)) {
                    $statusCode = max($statusCode, $this->updateSoftwareArchive($entry));
                    break;
                }
            }
        }
        if ($statusCode==200) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false) as $entry) {
                $this->yellow->toolbox->deleteFile($entry);
            }
        }
        return $statusCode;
    }
    
    // Update installation page
    public function updateInstallationPage($fileName, $name, $language) {
        $statusCode = 200;
        if ($language!="en") {
            $fileData = strreplaceu("\r\n", "\n", $this->yellow->toolbox->readFile($fileName));
            $rawDataOld = strreplaceu("\\n", "\n", $this->yellow->text->getText("editInstallation{$name}Page", "en"));
            $rawDataNew = strreplaceu("\\n", "\n", $this->yellow->text->getText("editInstallation{$name}Page", $language));
            if (!$this->yellow->toolbox->createFile($fileName, strreplaceu($rawDataOld, $rawDataNew, $fileData))) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Remove software
    public function removeSoftware($data) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        foreach ($data as $key=>$value) {
            foreach (preg_split("/\s*,\s*/", $value) as $fileName) {
                $statusCode = max($statusCode, $this->removeSoftwareFile($fileName, $key));
            }
            ++$this->updates;
        }
        return $statusCode;
    }
    
    // Remove software file
    public function removeSoftwareFile($fileName, $software) {
        $statusCode = 200;
        $fileName = $this->yellow->toolbox->normaliseTokens($fileName);
        if ($this->yellow->lookup->isValidFile($fileName) && !empty($software)) {
            if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->config->get("trashDir"))) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
            }
            if (defined("DEBUG") && DEBUG>=2) {
                echo "YellowUpdate::removeSoftwareFile file:$fileName action:delete<br/>\n";
            }
        }
        return $statusCode;
    }
    
    // Process command to install pending software
    public function processCommandInstallationPending($args) {
        $statusCode = 0;
        if ($this->isSoftwarePending()) {
            $statusCode = $this->updateSoftware();
            if ($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
        }
        return $statusCode;
    }
    
    // Process request to install pending software
    public function processRequestInstallationPending($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName) && !$this->yellow->isCommandLine() && $this->isSoftwarePending()) {
            $statusCode = $this->updateSoftware();
            if ($statusCode==200) {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->yellow->sendStatus(303, $location);
            }
        }
        return $statusCode;
    }
    
    // Process request to install website
    public function processRequestInstallationMode($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName) && !$this->yellow->isCommandLine()) {
            $this->yellow->pages->pages["root/"] = array();
            $this->yellow->page = new YellowPage($this->yellow);
            $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
            $this->yellow->page->parseData($this->getRawDataInstallation(), false, 404);
            $this->yellow->page->parserSafeMode = false;
            $this->yellow->page->parseContent();
            $name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
            $email = trim($_REQUEST["email"]);
            $password = trim($_REQUEST["password"]);
            $language = trim($_REQUEST["language"]);
            $feature = trim($_REQUEST["feature"]);
            $status = trim($_REQUEST["status"]);
            if ($status=="install") {
                $serverVersion = $this->yellow->toolbox->getServerVersion(true);
                $status = $this->checkServerRewrite($scheme, $address, $base, $location, $fileName) ? "ok" : "error";
                if ($status=="error") $this->yellow->page->error(500, "Rewrite module not working on $serverVersion web server!");
            }
            if ($status=="ok") {
                if (!empty($email) && !empty($password) && $this->yellow->plugins->isExisting("edit")) {
                    $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
                    $status = $this->yellow->plugins->get("edit")->users->save($fileNameUser, $email, $password, $name, $language) ? "ok" : "error";
                    if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
                }
            }
            if ($status=="ok") {
                if (!empty($feature)) {
                    $status = $this->updateInstallationFeatures($feature)==200 ? "ok" : "error";
                    if ($status=="error") $this->yellow->page->error(500, "Can't install feature '$feature'!");
                }
            }
            if ($status=="ok") {
                $fileNameHome = $this->yellow->lookup->findFileFromLocation("/");
                $status = $this->updateInstallationPage($fileNameHome, "Home", $language)==200 ? "ok" : "error";
                if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameHome'!");
            }
            if ($status=="ok") {
                $fileNameAbout = $this->yellow->lookup->findFileFromLocation("/about/");
                $status = $this->updateInstallationPage($fileNameAbout, "About", $language)==200 ? "ok" : "error";
                if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameAbout'!");
            }
            if ($status=="ok") {
                if ($this->yellow->config->get("sitename")=="Yellow") $_REQUEST["sitename"] = $name;
                $fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
                $status = $this->yellow->config->save($fileNameConfig, $this->getConfigData()) ? "done" : "error";
                if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameConfig'!");
            }
            if ($status=="done") {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->yellow->sendStatus(303, $location);
            } else {
                $statusCode = $this->yellow->sendPage();
            }
        }
        return $statusCode;
    }
    
    // Check web server rewrite
    public function checkServerRewrite($scheme, $address, $base, $location, $fileName) {
        $curlHandle = curl_init();
        $location = $this->yellow->config->get("assetLocation").$this->yellow->page->get("theme").".css";
        $url = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowCore/".YellowCore::VERSION).")";
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
        $rawData = curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        return !empty($rawData) && $statusCode==200;
    }
    
    // Return raw data for installation page
    public function getRawDataInstallation() {
        $language = $this->yellow->toolbox->detectBrowserLanguage($this->yellow->text->getLanguages(), $this->yellow->config->get("language"));
        $fileName = strreplaceu("(.*)", "installation", $this->yellow->config->get("configDir").$this->yellow->config->get("newFile"));
        $rawData = $this->yellow->toolbox->readFile($fileName);
        if (empty($rawData)) {
            $this->yellow->text->setLanguage($language);
            $rawData = "---\nTitle:".$this->yellow->text->get("editInstallationTitle")."\nLanguage:$language\nNavigation:navigation\n---\n";
            $rawData .= "<form class=\"installation-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
            $rawData .= "<p><label for=\"name\">".$this->yellow->text->get("editSignupName")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"name\" id=\"name\" value=\"\"></p>\n";
            $rawData .= "<p><label for=\"email\">".$this->yellow->text->get("editSignupEmail")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"email\" id=\"email\" value=\"\"></p>\n";
            $rawData .= "<p><label for=\"password\">".$this->yellow->text->get("editSignupPassword")."</label><br /><input class=\"form-control\" type=\"password\" maxlength=\"64\" name=\"password\" id=\"password\" value=\"\"></p>\n";
            if (count($this->yellow->text->getLanguages())>1) {
                $rawData .= "<p>";
                foreach ($this->yellow->text->getLanguages() as $language) {
                    $checked = $language==$this->yellow->text->language ? " checked=\"checked\"" : "";
                    $rawData .= "<label for=\"$language\"><input type=\"radio\" name=\"language\" id=\"$language\" value=\"$language\"$checked> ".$this->yellow->text->getTextHtml("languageDescription", $language)."</label><br />";
                }
                $rawData .= "</p>\n";
            }
            if (count($this->getInstallationFeatures())>1) {
                $rawData .= "<p>".$this->yellow->text->get("editInstallationFeature")."<p>";
                foreach ($this->getInstallationFeatures() as $feature) {
                    $checked = $feature=="website" ? " checked=\"checked\"" : "";
                    $rawData .= "<label for=\"$feature\"><input type=\"radio\" name=\"feature\" id=\"$feature\" value=\"$feature\"$checked> ".ucfirst($feature)."</label><br />";
                }
                $rawData .= "</p>\n";
            }
            $rawData .= "<input class=\"btn\" type=\"submit\" value=\"".$this->yellow->text->get("editOkButton")."\" />\n";
            $rawData .= "<input type=\"hidden\" name=\"status\" value=\"install\" />\n";
            $rawData .= "</form>\n";
        }
        return $rawData;
    }
    
    // Return configuration data
    public function getConfigData() {
        $data = array();
        foreach ($_REQUEST as $key=>$value) {
            if (!$this->yellow->config->isExisting($key)) continue;
            $data[$key] = trim($value);
        }
        $data["timezone"] = $this->yellow->toolbox->getTimezone();
        $data["staticUrl"] = $this->yellow->toolbox->getServerUrl();
        $data["installationMode"] = "0";
        return $data;
    }

    // Return installation features
    public function getInstallationFeatures() {
        $data = array("website");
        $path = $this->yellow->config->get("pluginDir");
        $regex = "/^.*\.installation$/";
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, true, false, false) as $entry) {
            if (preg_match("/^(.*?)-(.*?)\./", $entry, $matches)) array_push($data, $matches[2]);
        }
        return $data;
    }
    
    // Return software version
    public function getSoftwareVersion($latest = false, $rawFormat = false) {
        $data = array();
        if ($latest) {
            $urlPlugins = $this->yellow->config->get("updatePluginsUrl")."/raw/master/".$this->yellow->config->get("updateVersionFile");
            $urlThemes = $this->yellow->config->get("updateThemesUrl")."/raw/master/".$this->yellow->config->get("updateVersionFile");
            list($statusCodePlugins, $fileDataPlugins) = $this->getSoftwareFile($urlPlugins);
            list($statusCodeThemes, $fileDataThemes) = $this->getSoftwareFile($urlThemes);
            $statusCode = max($statusCodePlugins, $statusCodeThemes);
            if ($statusCode==200) {
                foreach ($this->yellow->toolbox->getTextLines($fileDataPlugins."\n".$fileDataThemes) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (!empty($matches[1]) && !empty($matches[2])) {
                        list($version) = explode(",", $matches[2]);
                        $data[$matches[1]] = $rawFormat ? $matches[2] : $version;
                    }
                }
            }
        } else {
            $statusCode = 200;
            $data = array_merge($this->yellow->plugins->getData(), $this->yellow->themes->getData());
        }
        return array($statusCode, $data);
    }
    
    // Return software files
    public function getSoftwareFiles() {
        $data = array();
        $urlPlugins = $this->yellow->config->get("updatePluginsUrl")."/raw/master/".$this->yellow->config->get("updateResourceFile");
        $urlThemes = $this->yellow->config->get("updateThemesUrl")."/raw/master/".$this->yellow->config->get("updateResourceFile");
        list($statusCodePlugins, $fileDataPlugins) = $this->getSoftwareFile($urlPlugins);
        list($statusCodeThemes, $fileDataThemes) = $this->getSoftwareFile($urlThemes);
        $statusCode = max($statusCodePlugins, $statusCodeThemes);
        if ($statusCode==200) {
            foreach ($this->yellow->toolbox->getTextLines($fileDataPlugins."\n".$fileDataThemes) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    list($software) = explode("/", $matches[1]);
                    list($fileName, $flags) = explode(",", $matches[2], 2);
                    if (!is_null($data[$software])) $data[$software] .= ",";
                    $data[$software] .= $fileName;
                }
            }
        }
        return array($statusCode, $data);
    }

    // Return software modification
    public function getSoftwareModified() {
        $data = array();
        $dataCurrent = array_merge($this->yellow->plugins->getData(), $this->yellow->themes->getData());
        $urlPlugins = $this->yellow->config->get("updatePluginsUrl")."/raw/master/".$this->yellow->config->get("updateResourceFile");
        $urlThemes = $this->yellow->config->get("updateThemesUrl")."/raw/master/".$this->yellow->config->get("updateResourceFile");
        list($statusCodePlugins, $fileDataPlugins) = $this->getSoftwareFile($urlPlugins);
        list($statusCodeThemes, $fileDataThemes) = $this->getSoftwareFile($urlThemes);
        $statusCode = max($statusCodePlugins, $statusCodeThemes);
        if ($statusCode==200) {
            foreach ($this->yellow->toolbox->getTextLines($fileDataPlugins."\n".$fileDataThemes) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    list($softwareNew) = explode("/", $matches[1]);
                    list($fileName, $flags) = explode(",", $matches[2], 2);
                    if ($software!=$softwareNew) {
                        $software = $softwareNew;
                        $lastPublished = $this->yellow->toolbox->getFileModified($fileName);
                    }
                    if (!is_null($dataCurrent[$software])) {
                        $lastModified = $this->yellow->toolbox->getFileModified($fileName);
                        if (preg_match("/update/i", $flags) && preg_match("/careful/i", $flags) && $lastModified!=$lastPublished) {
                            $data[$software] = $dataCurrent[$software];
                            if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::getSoftwareModified detected file:$fileName<br/>\n";
                        }
                    }
                }
            }
        }
        return array($statusCode, $data);
    }
    
    // Return software file
    public function getSoftwareFile($url) {
        $urlRequest = $url;
        if (preg_match("#^https://github.com/(.+)/raw/(.+)$#", $url, $matches)) $urlRequest = "https://raw.githubusercontent.com/".$matches[1]."/".$matches[2];
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $urlRequest);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; DatenstromYellow/".YellowCore::VERSION."; SoftwareUpdater)");
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
        $rawData = curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
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
        if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::getSoftwareFile status:$statusCode url:$url<br/>\n";
        return array($statusCode, $fileData);
    }
    
    // Return command features
    public function getCommandFeatures($args) {
        $command = array_shift($args);
        $features = array_unique(array_filter($args, "strlen"));
        foreach ($features as $key=>$value) {
            if ($value=="force") {
                $force = true;
                unset($features[$key]);
            }
        }
        return array($command, $features, $force);
    }
    
    // Check if software pending
    public function isSoftwarePending() {
        $path = $this->yellow->config->get("pluginDir");
        $foundPlugins = count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false))>0;
        $path = $this->yellow->config->get("themeDir");
        $foundThemes = count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false))>0;
        return $foundPlugins || $foundThemes;
    }

    // Check if software exists
    public function isSoftwareExisting($software) {
        $data = array_merge($this->yellow->plugins->getData(), $this->yellow->themes->getData());
        return !is_null($data[$software]);
    }
}
    
$yellow->plugins->register("update", "YellowUpdate", YellowUpdate::VERSION, 1);
