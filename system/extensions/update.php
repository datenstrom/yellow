<?php
// Update extension, https://github.com/datenstrom/yellow-extensions/tree/master/features/update
// Copyright (c) 2013-2019 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowUpdate {
    const VERSION = "0.8.3";
    const TYPE = "feature";
    const PRIORITY = "2";
    public $yellow;                 //access to API
    public $updates;                //number of updates
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("updateExtensionUrl", "https://github.com/datenstrom/yellow-extensions");
        $this->yellow->system->setDefault("updateInformationFile", "update.ini");
        $this->yellow->system->setDefault("updateExtensionFile", "extension.ini");
        $this->yellow->system->setDefault("updateVersionFile", "version.ini");
        $this->yellow->system->setDefault("updateWaffleFile", "waffle.ini");
    }
    
    // Handle startup
    public function onStartup($update) {
        if ($update) {  //TODO: remove later, converts old API in layouts
            $fileNameError = $this->yellow->system->get("settingDir")."system-error.log";
            if ($this->yellow->system->isExisting("templateDir")) {
                $path = $this->yellow->system->get("layoutDir");
                foreach ($this->yellow->toolbox->getDirectoryEntriesRecursive($path, "/^.*\.html$/", true, false) as $entry) {
                    $fileData = $fileDataNew = $this->yellow->toolbox->readFile($entry);
                    $fileDataNew = str_replace("\$yellow->", "\$this->yellow->", $fileDataNew);
                    $fileDataNew = str_replace("yellow->config->", "yellow->system->", $fileDataNew);
                    $fileDataNew = str_replace("yellow->pages->", "yellow->content->", $fileDataNew);
                    $fileDataNew = str_replace("yellow->files->", "yellow->media->", $fileDataNew);
                    $fileDataNew = str_replace("yellow->plugins->", "yellow->extensions->", $fileDataNew);
                    $fileDataNew = str_replace("yellow->themes->", "yellow->extensions->", $fileDataNew);
                    $fileDataNew = str_replace("yellow->snippet(", "yellow->layout(", $fileDataNew);
                    $fileDataNew = str_replace("yellow->getSnippetArgs(", "yellow->getLayoutArgs(", $fileDataNew);
                    $fileDataNew = str_replace("\"template\"", "\"layout\"", $fileDataNew);
                    $fileDataNew = str_replace("\"page template-\"", "\"page layout-\"", $fileDataNew);
                    if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($entry, $fileDataNew)) {
                        $fileDataError .= "ERROR writing file '$entry'!\n";
                    }
                }
                if (!empty($fileDataError)) $this->yellow->toolbox->createFile($fileNameError, $fileDataError);
            }
        }
        if ($update) {  //TODO: remove later, converts old website icon
            $fileNameError = $this->yellow->system->get("settingDir")."system-error.log";
            if ($this->yellow->system->isExisting("siteicon")) {
                $theme = $this->yellow->system->get("theme");
                $fileName = $this->yellow->system->get("resourceDir")."icon.png";
                $fileNameNew = $this->yellow->system->get("resourceDir").$this->yellow->lookup->normaliseName($theme)."-icon.png";
                if (is_file($fileName) && !$this->yellow->toolbox->renameFile($fileName, $fileNameNew)) {
                    $fileDataError .= "ERROR renaming file '$fileName'!\n";
                }
                if (!empty($fileDataError)) $this->yellow->toolbox->createFile($fileNameError, $fileDataError);
            }
        }
        if ($update) {  //TODO: remove later, converts old language files
            $fileNameError = $this->yellow->system->get("settingDir")."system-error.log";
            if ($this->yellow->system->isExisting("languageFile")) {
                $path = $this->yellow->system->get("extensionDir");
                foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.txt$/", true, false) as $entry) {
                    preg_match("/^language-(.*)\.txt$/", basename($entry), $matches);
                    $languageName = $this->getLanguageName($matches[1]);
                    if (!empty($languageName)) {
                        $entryNew = $path.$languageName."-language.txt";
                        if (!$this->yellow->toolbox->renameFile($entry, $entryNew)) {
                            $fileDataError .= "ERROR renaming file '$entry'!\n";
                        }
                        $fileNameNew = $path.$languageName.".php";
                        $fileDataNew = "<?php\n\nclass Yellow".ucfirst($languageName)." {\nconst VERSION = \"0.8.2\";\nconst TYPE = \"language\";\n}\n";
                        if (!$this->yellow->toolbox->createFile($fileNameNew, $fileDataNew)) {
                            $fileDataError .= "ERROR writing file '$fileNameNew'!\n";
                        }
                    }
                }
                $fileName = $this->yellow->system->get("extensionDir")."language.php";
                if (is_file($fileName) && !$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("trashDir"))) {
                    $fileDataError .= "ERROR deleting file '$fileName'!\n";
                }
                if (!empty($fileDataError)) $this->yellow->toolbox->createFile($fileNameError, $fileDataError);
            }
        }
        if ($update) {  //TODO: remove later, converts old Markdown files
            $fileNameError = $this->yellow->system->get("settingDir")."system-error.log";
            if ($this->yellow->system->get("contentDefaultFile")=="page.txt") {
                $settings = array("contentDefaultFile" => "page.md", "contentExtension" => ".md", "editNewFile" => "page-new-(.*).md");
                $fileName = $this->yellow->system->get("settingDir").$this->yellow->system->get("systemFile");
                if (!$this->yellow->system->save($fileName, $settings)) {
                    $fileDataError .= "ERROR writing file '$fileName'!\n";
                }
                $path = $this->yellow->system->get("contentDir");
                foreach ($this->yellow->toolbox->getDirectoryEntriesRecursive($path, "/^.*\.txt$/", true, false) as $entry) {
                    if (!$this->yellow->toolbox->renameFile($entry, str_replace(".txt", ".md", $entry))) {
                        $fileDataError .= "ERROR renaming file '$entry'!\n";
                    }
                }
                $path = $this->yellow->system->get("settingDir");
                foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.txt$/", true, false) as $entry) {
                    if (!$this->yellow->toolbox->renameFile($entry, str_replace(".txt", ".md", $entry))) {
                        $fileDataError .= "ERROR renaming file '$entry!'\n";
                    }
                }
                if (!empty($fileDataError)) $this->yellow->toolbox->createFile($fileNameError, $fileDataError);
                $_GET["clean-url"] = "system-updated";
            }
        }
        if ($update) {  //TODO: remove later, converts old template setting
            $fileNameError = $this->yellow->system->get("settingDir")."system-error.log";
            if ($this->yellow->system->isExisting("template")) {
                $path = $this->yellow->system->get("contentDir");
                foreach ($this->yellow->toolbox->getDirectoryEntriesRecursive($path, "/^.*\.md$/", true, false) as $entry) {
                    $fileData = $fileDataNew = $this->yellow->toolbox->readFile($entry);
                    $fileDataNew = preg_replace("/Template:/i", "Layout:", $fileDataNew);
                    $fileDataNew = preg_replace("/TemplateNew:/i", "LayoutNew:", $fileDataNew);
                    if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($entry, $fileDataNew)) {
                        $fileDataError .= "ERROR writing file '$entry'!\n";
                    }
                }
                if (!empty($fileDataError)) $this->yellow->toolbox->createFile($fileNameError, $fileDataError);
                $_GET["clean-url"] = "system-updated";
            }
        }
        if ($update) {  //TODO: remove later, updates shared pages
            $fileNameError = $this->yellow->system->get("settingDir")."system-error.log";
            $pathSetting = $this->yellow->system->get("settingDir");
            $pathShared = $this->yellow->system->get("contentDir").$this->yellow->system->get("contentSharedDir");
            if (count($this->yellow->toolbox->getDirectoryEntries($pathSetting, "/.*/", false, false))>3) {
                $regex = "/^page-error-(.*)\.md$/";
                foreach ($this->yellow->toolbox->getDirectoryEntries($pathSetting, $regex, true, false) as $entry) {
                    if (!$this->yellow->toolbox->deleteFile($entry, $this->yellow->system->get("trashDir"))) {
                        $fileDataError .= "ERROR deleting file '$entry'!\n";
                    }
                }
                $regex = "/^page-new-(.*)\.md$/";
                foreach ($this->yellow->toolbox->getDirectoryEntries($pathSetting, $regex, true, false) as $entry) {
                    if (!$this->yellow->toolbox->renameFile($entry, str_replace($pathSetting, $pathShared, $entry), true)) {
                        $fileDataError .= "ERROR moving file '$entry'!\n";
                    }
                }
                $fileNameHeader = $pathShared."header.md";
                if (!is_file($fileNameHeader) && $this->yellow->system->isExisting("tagline")) {
                    $fileDataHeader = "---\nTitle: Header\nStatus: hidden\n---\n".$this->yellow->system->get("tagline");
                    if (!$this->yellow->toolbox->createFile($fileNameHeader, $fileDataHeader, true)) {
                        $fileDataError .= "ERROR writing file '$fileNameHeader'!\n";
                    }
                }
                $fileNameFooter = $pathShared."footer.md";
                if (!is_file($fileNameFooter)) {
                    $fileDataFooter = "---\nTitle: Footer\nStatus: hidden\n---\n";
                    $fileDataFooter .= $this->yellow->text->getText("InstallFooterText", $this->yellow->system->get("language"));
                    if (!$this->yellow->toolbox->createFile($fileNameFooter, $fileDataFooter, true)) {
                        $fileDataError .= "ERROR writing file '$fileNameFooter'!\n";
                    }
                }
                $this->updateContentMultiLanguage("shared-pages");
                if (!empty($fileDataError)) $this->yellow->toolbox->createFile($fileNameError, $fileDataError);
            }
        }
        if ($update) {
            $fileName = $this->yellow->system->get("settingDir").$this->yellow->system->get("systemFile");
            $fileData = $this->yellow->toolbox->readFile($fileName);
            $fileDataHeader = $fileDataSettings = $fileDataFooter = "";
            $settings = new YellowDataCollection();
            $settings->exchangeArray($this->yellow->system->settingsDefaults->getArrayCopy());
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (empty($fileDataHeader) && preg_match("/^\#/", $line)) {
                    $fileDataHeader = $line;
                } elseif (!empty($matches[1]) && !is_null($settings[$matches[1]])) {
                    $settings[$matches[1]] = $matches[2];
                } elseif (!empty($matches[1]) && $matches[1][0]!="#") {
                    $fileDataFooter .= "# $line";
                } elseif (!empty($matches[1])) {
                    $fileDataFooter .= $line;
                }
            }
            unset($settings["systemFile"]);
            foreach ($settings as $key=>$value) {
                $fileDataSettings .= ucfirst($key).(strempty($value) ? ":\n" : ": $value\n");
                if ($key=="updateWaffleFile") $fileDataSettings .= "\n";
            }
            if (!empty($fileDataHeader)) $fileDataHeader .= "\n";
            if (!empty($fileDataFooter)) $fileDataSettings .= "\n";
            $fileDataNew = $fileDataHeader.$fileDataSettings.$fileDataFooter;
            if ($fileData!=$fileDataNew) $this->yellow->toolbox->createFile($fileName, $fileDataNew);
        }
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
    public function onCommand($args) {
        $statusCode = 0;
        if ($this->isExtensionPending()) $statusCode = $this->processCommandPending();
        if ($statusCode==0) {
            list($command) = $args;
            switch ($command) {
                case "clean":       $statusCode = $this->processCommandClean($args); break;
                case "install":     $statusCode = $this->processCommandInstall($args); break;
                case "uninstall":   $statusCode = $this->processCommandUninstall($args); break;
                case "update":      $statusCode = $this->processCommandUpdate($args); break;
                default:            $statusCode = 0; break;
            }
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        $help .= "install [extension]\n";
        $help .= "uninstall [extension]\n";
        $help .= "update [extension]\n";
        return $help;
    }
    
    // Process command to clean downloads
    public function processCommandClean($args) {
        $statusCode = 0;
        list($command, $path) = $args;
        if ($path=="all") {
            $path = $this->yellow->system->get("extensionDir");
            $regex = "/^.*\\".$this->yellow->system->get("downloadExtension")."$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, false, false) as $entry) {
                if (!$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
            }
            if ($statusCode==500) echo "ERROR cleaning downloads: Can't delete files in directory '$path'!\n";
        }
        return $statusCode;
    }
    
    // Process command to install extensions
    public function processCommandInstall($args) {
        list($command, $extensions) = $this->getExtensionInformation($args);
        if (!empty($extensions)) {
            $this->updates = 0;
            list($statusCode, $data) = $this->getInstallInformation($extensions);
            if ($statusCode==200) $statusCode = $this->downloadExtensions($data);
            if ($statusCode==200) $statusCode = $this->updateExtensions();
            if ($statusCode>=400) echo "ERROR installing files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates extension".($this->updates!=1 ? "s" : "")." installed\n";
        } else {
            $statusCode = $this->showExtensions();
        }
        return $statusCode;
    }
    
    // Process command to uninstall extensions
    public function processCommandUninstall($args) {
        list($command, $extensions) = $this->getExtensionInformation($args);
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
    public function processCommandUpdate($args) {
        list($command, $extensions, $force) = $this->getExtensionInformation($args);
        list($statusCode, $data) = $this->getUpdateInformation($extensions, $force);
        if ($statusCode!=200 || !empty($data)) {
            $this->updates = 0;
            if ($statusCode==200) $statusCode = $this->downloadExtensions($data);
            if ($statusCode==200) $statusCode = $this->updateExtensions($force);
            if ($statusCode>=400) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates update".($this->updates!=1 ? "s" : "")." installed\n";
        } else {
            echo "Your website is up to date\n";
        }
        return $statusCode;
    }
    
    // Process command to update website with pending extension
    public function processCommandPending() {
        $statusCode = $this->updateExtensions();
        if ($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
        echo "Your website has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
        return $statusCode;
    }
    
    // Process request to update website with pending extension
    public function processRequestPending($scheme, $address, $base, $location, $fileName) {
        $statusCode = $this->updateExtensions();
        if ($statusCode==200) {
            $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
            $statusCode = $this->yellow->sendStatus(303, $location);
        }
        return $statusCode;
    }
    
    // Return extension information
    public function getExtensionInformation($args) {
        $command = array_shift($args);
        $extensions = array_unique(array_filter($args, "strlen"));
        foreach ($extensions as $key=>$value) {
            if ($value=="force") {
                $force = true;
                unset($extensions[$key]);
            }
        }
        return array($command, $extensions, $force);
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
                if (strtoloweru($key)==strtoloweru($extension) && !is_null($dataLatest[$key]) && !is_null($dataRelevant[$key])) {
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
                list($version) = explode(",", $dataLatest[$key]);
                if (strnatcasecmp($dataCurrent[$key], $version)<0) $data[$key] = $dataLatest[$key];
                if (!is_null($dataModified[$key]) && !empty($version) && $force) $data[$key] = $dataLatest[$key];
            }
        } else {
            foreach ($extensions as $extension) {
                $found = false;
                foreach ($dataCurrent as $key=>$value) {
                    list($version) = explode(",", $dataLatest[$key]);
                    if (strtoloweru($key)==strtoloweru($extension) && !empty($version)) {
                        $data[$key] = $dataLatest[$key];
                        $dataModified = array_intersect_key($dataModified, $data);
                        $found = true;
                        break;
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
                if (is_null($dataModified[$key]) || $force) {
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
        $path = $this->yellow->system->get("extensionDir");
        $fileExtension = $this->yellow->system->get("downloadExtension");
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
    public function updateExtensions($force = false) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->system->get("extensionDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            $statusCode = max($statusCode, $this->updateExtensionArchive($entry, $force));
            if (!$this->yellow->toolbox->deleteFile($entry)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
            }
        }
        return $statusCode;
    }

    // Update extension from archive
    public function updateExtensionArchive($path, $force = false) {
        $statusCode = 200;
        $zip = new ZipArchive();
        if ($zip->open($path)===true) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::updateExtensionArchive file:$path<br/>\n";
            if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
            $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
            if (empty($fileData)) $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateInformationFile")); //TODO: remove later, for backwards compatibility
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    list($fileName) = explode(",", $matches[2], 2);
                    if (is_file($fileName)) {
                        $lastPublished = filemtime($fileName);
                        break;
                    }
                }
            }
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (lcfirst($matches[1])=="extension") $extension = lcfirst($matches[2]);
                if (lcfirst($matches[1])=="plugin") $extension = lcfirst(substru($matches[2], 6)); //TODO: remove later, for backwards compatibility
                if (lcfirst($matches[1])=="theme") $extension = lcfirst(substru($matches[2], 11)); //TODO: remove later, for backwards compatibility
                if (lcfirst($matches[1])=="published") $modified = strtotime($matches[2]);
                if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                    list($dummy, $entry) = explode("/", $matches[1], 2);
                    list($fileName, $flags) = explode(",", $matches[2], 2);
                    $fileData = $zip->getFromName($pathBase.basename($entry));
                    $lastModified = $this->yellow->toolbox->getFileModified($fileName);
                    $statusCode = $this->updateExtensionFile($fileName, $fileData, $modified, $lastModified, $lastPublished, $flags, $force, $extension);
                    if ($statusCode!=200) break;
                }
            }
            $zip->close();
            if ($statusCode==200) $statusCode = $this->updateContentMultiLanguage($extension);
            if ($statusCode==200) $statusCode = $this->updateStartupNotification($extension);
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
            if (preg_match("/careful/i", $flags) && is_file($fileName) && $lastModified!=$lastPublished && !$force) $update = false;
            if (preg_match("/optional/i", $flags) && $this->yellow->extensions->isExisting($extension)) $create = $update = $delete = false;
            if ($create) {
                if (!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
            if ($update) {
                if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("trashDir")) ||
                    !$this->yellow->toolbox->createFile($fileName, $fileData) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
            if ($delete) {
                if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("trashDir"))) {
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

    // Update content for multi language mode
    public function updateContentMultiLanguage($extension) {
        $statusCode = 200;
        if ($this->yellow->system->get("multiLanguageMode") && !$this->yellow->extensions->isExisting($extension)) {
            $pathsSource = $pathsTarget = array();
            $pathBase = $this->yellow->system->get("contentDir");
            $fileExtension = $this->yellow->system->get("contentExtension");
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
                            if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::updateContentMultiLanguage file:$fileNameTarget<br/>\n";
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
    
    // Update notification for next startup
    public function updateStartupNotification($extension) {
        $statusCode = 200;
        $startupUpdate = $this->yellow->system->get("startupUpdate");
        if ($startupUpdate=="none") $startupUpdate = "update";
        if ($extension!="update") $startupUpdate .= ",$extension";
        $fileName = $this->yellow->system->get("settingDir").$this->yellow->system->get("systemFile");
        if (!$this->yellow->system->save($fileName, array("startupUpdate" => $startupUpdate))) {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Remove extensions
    public function removeExtensions($data) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        foreach ($data as $key=>$value) {
            foreach (preg_split("/\s*,\s*/", $value) as $fileName) {
                $statusCode = max($statusCode, $this->removeExtensionsFile($fileName, $key));
            }
            ++$this->updates;
        }
        if ($statusCode==200) $statusCode = $this->updateStartupNotification("update");
        return $statusCode;
    }
    
    // Remove extensions file
    public function removeExtensionsFile($fileName, $extension) {
        $statusCode = 200;
        $fileName = $this->yellow->toolbox->normaliseTokens($fileName);
        if ($this->yellow->lookup->isValidFile($fileName) && !empty($extension)) {
            if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("trashDir"))) {
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
                    list($extension) = explode("/", lcfirst($matches[1]));
                    list($fileName) = explode(",", $matches[2], 2);
                    if (!is_null($data[$extension])) $data[$extension] .= ",";
                    $data[$extension] .= $fileName;
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
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2])) {
                    list($extensionNew) = explode("/", lcfirst($matches[1]));
                    list($fileName, $flags) = explode(",", $matches[2], 2);
                    if ($extension!=$extensionNew) {
                        $extension = $extensionNew;
                        $lastPublished = $this->yellow->toolbox->getFileModified($fileName);
                    }
                    if (!is_null($dataCurrent[$extension])) {
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
    
    // Return human readable language name, TODO: remove later, for backwards compatibility
    public function getLanguageName($language) {
        $languageName = "";
        $languageNames = array("bn" => "bengali", "cs" => "czech", "da" => "danish", "de" => "german",
            "en" => "english", "es" => "spanish", "fr" => "french", "hu" => "hungarian", "id" => "indonesian",
            "it" => "italian", "ja" => "japanese", "ko" => "korean", "nl" => "dutch", "pl" => "polish",
            "pt" => "portuguese", "ru" => "russian", "sk" => "slovenian", "sv" => "swedish", "zh-CN" => "chinese");
        if (array_key_exists($language, $languageNames)) {
            $languageName = $languageNames[$language];
        }
        return $languageName;
    }

    // Check if extension pending
    public function isExtensionPending() {
        $path = $this->yellow->system->get("extensionDir");
        return count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", false, false))>0;
    }
}
