<?php
// Update extension, https://github.com/datenstrom/yellow-extensions/tree/master/source/update

class YellowUpdate {
    const VERSION = "0.8.50";
    const PRIORITY = "2";
    public $yellow;                 // access to API
    public $updates;                // number of updates
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("updateExtensionUrl", "https://github.com/datenstrom/yellow-extensions");
        $this->yellow->system->setDefault("updateExtensionFile", "extension.ini");
        $this->yellow->system->setDefault("updateLatestFile", "update-latest.ini");
        $this->yellow->system->setDefault("updateCurrentFile", "update-current.ini");
        $this->yellow->system->setDefault("updateCurrentRelease", "0");
        $this->yellow->system->setDefault("updateEventPending", "none");
        $this->yellow->system->setDefault("updateEventDaily", "0");
    }
    
    // Handle update
    public function onUpdate($action) {
        if ($action=="clean" || $action=="daily") {
            $statusCode = 200;
            $path = $this->yellow->system->get("coreExtensionDirectory");
            $regex = "/^.*\\".$this->yellow->system->get("coreDownloadExtension")."$/";
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, $regex, false, false) as $entry) {
                if (!$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
            }
            if ($statusCode==500) $this->yellow->log("error", "Can't delete files in directory '$path'!\n");
            $statusCode = 200;
            $path = $this->yellow->system->get("coreTrashDirectory");
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", false, false) as $entry) {
                $expire = $this->yellow->toolbox->getFileDeleted($entry) + $this->yellow->system->get("coreTrashTimeout");
                if ($expire<=time() && !$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
            }
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", false, true) as $entry) {
                $expire = $this->yellow->toolbox->getFileDeleted($entry) + $this->yellow->system->get("coreTrashTimeout");
                if ($expire<=time() && !$this->yellow->toolbox->deleteDirectory($entry)) $statusCode = 500;
            }
            if ($statusCode==500) $this->yellow->log("error", "Can't delete files in directory '$path'!\n");
        }
        if ($action=="update") { // TODO: remove later, convert layout files
            $path = $this->yellow->system->get("coreLayoutDirectory");
            foreach ($this->yellow->toolbox->getDirectoryEntriesRecursive($path, "/^.*\.html$/", true, false) as $entry) {
                $key = str_replace("pages", "", $this->yellow->lookup->normaliseName(basename($entry), true, true));
                $fileData = $fileDataNew = $this->yellow->toolbox->readFile($entry);
                $fileDataNew = str_replace("text->getHtml", "language->getTextHtml", $fileDataNew);
                $fileDataNew = str_replace("yellow->page->getPages()", "yellow->page->getPages(\"$key\")", $fileDataNew);
                $fileDataNew = str_replace("\$page = \$this->yellow->content->shared(\"header\")", "\$page = null", $fileDataNew);
                $fileDataNew = str_replace("\$page = \$this->yellow->content->shared(\"footer\")", "\$page = null", $fileDataNew);
                $fileDataNew = str_replace("\$page = \$this->yellow->content->shared(\"sidebar\")", "\$page = null", $fileDataNew);
                $fileDataNew = str_replace("\$this->yellow->content->shared(\"sidebar\")", "\$this->yellow->page->isPage(\"sidebar\")", $fileDataNew);
                $fileDataNew = str_replace("php if (\$page = null)", "php /* Remove this line */ if (\$page = null)", $fileDataNew);
                if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($entry, $fileDataNew)) {
                    $this->yellow->log("error", "Can't write file '$entry'!");
                }
            }
        }
        if ($action=="update") { // TODO: remove later, create settings files when missing
            $fileNameCurrent = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateCurrentFile");
            $fileNameLatest = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateLatestFile");
            if (!is_file($fileNameCurrent) || !is_file($fileNameLatest)) {
                $url = $this->yellow->system->get("updateExtensionUrl")."/raw/master/".$this->yellow->system->get("updateLatestFile");
                list($statusCode, $fileData) = $this->getExtensionFile($url);
                if ($statusCode==200) {
                    $fileDataCurrent = $fileDataLatest = $fileData;
                    $settings = $this->yellow->toolbox->getTextSettings($fileDataCurrent, "extension");
                    foreach ($settings as $key=>$value) {
                        if ($this->yellow->extension->isExisting($key)) {
                            $settingsNew = new YellowArray();
                            $settingsNew["extension"] = ucfirst($key);
                            $settingsNew["version"] = $this->yellow->extension->data[$key]["version"];
                            $fileDataCurrent = $this->yellow->toolbox->setTextSettings($fileDataCurrent, "extension", $key, $settingsNew);
                        } else {
                            $fileDataCurrent = $this->yellow->toolbox->unsetTextSettings($fileDataCurrent, "extension", $key);
                        }
                    }
                    if(!is_file($fileNameCurrent) && !$this->yellow->toolbox->createFile($fileNameCurrent, $fileDataCurrent)) {
                        $this->yellow->log("error", "Can't write file '$fileNameCurrent'!");
                    }
                    if(!is_file($fileNameLatest) && !$this->yellow->toolbox->createFile($fileNameLatest, $fileDataLatest)) {
                        $this->yellow->log("error", "Can't write file '$fileNameLatest'!");
                    }
                }
            }
        }
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        return $this->processRequestPending($scheme, $address, $base, $location, $fileName);
    }
    
    // Handle command
    public function onCommand($command, $text) {
        $statusCode = $this->processCommandPending();
        if ($statusCode==0) {
            switch ($command) {
                case "about":       $statusCode = $this->processCommandAbout($command, $text); break;
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
        $help = "about\n";
        $help .= "install [extension]\n";
        $help .= "uninstall [extension]\n";
        $help .= "update [extension]\n";
        return $help;
    }

    // Process command to show website version and updates
    public function processCommandAbout($command, $text) {
        echo "Datenstrom Yellow ".YellowCore::RELEASE."\n";
        list($statusCode, $settingsCurrent) = $this->getExtensionSettings(false);
        list($statusCode, $settingsLatest) = $this->getExtensionSettings(true);
        foreach ($settingsCurrent as $key=>$value) {
            $versionCurrent = $versionLatest = $settingsCurrent[$key]->get("version");
            if ($settingsLatest->isExisting($key)) $versionLatest = $settingsLatest[$key]->get("version");
            if (strnatcasecmp($versionCurrent, $versionLatest)<0) {
                echo ucfirst($key)." $versionCurrent - Update available\n";
            } else {
                echo ucfirst($key)." $versionCurrent\n";
            }
        }
        if ($statusCode!=200) echo "ERROR checking updates: ".$this->yellow->page->get("pageError")."\n";
        return $statusCode;
    }
    
    // Process command to install extensions
    public function processCommandInstall($command, $text) {
        $extensions = $this->getExtensionsFromText($text);
        if (!empty($extensions)) {
            $this->updates = 0;
            list($statusCode, $settings) = $this->getExtensionInstallInformation($extensions);
            if ($statusCode==200) $statusCode = $this->downloadExtensions($settings);
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
        $extensions = $this->getExtensionsFromText($text);
        if (!empty($extensions)) {
            $this->updates = 0;
            list($statusCode, $settings) = $this->getExtensionUninstallInformation($extensions, "core, update");
            if ($statusCode==200) $statusCode = $this->removeExtensions($settings);
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
        $extensions = $this->getExtensionsFromText($text);
        list($statusCode, $settings) = $this->getExtensionUpdateInformation($extensions);
        if ($statusCode!=200 || !empty($settings)) {
            $this->updates = 0;
            if ($statusCode==200) $statusCode = $this->downloadExtensions($settings);
            if ($statusCode==200) $statusCode = $this->updateExtensions("update");
            if ($statusCode>=400) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Yellow $command: Website ".($statusCode!=200 ? "not " : "")."updated";
            echo ", $this->updates update".($this->updates!=1 ? "s" : "")." installed\n";
        } else {
            echo "Your website is up to date\n";
        }
        return $statusCode;
    }
    
    // Process command for pending events
    public function processCommandPending() {
        $statusCode = 0;
        $this->updateEventPending();
        if ($this->isExtensionPending()) {
            $statusCode = $this->updateExtensions("install");
            if ($statusCode!=200) echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Your website has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
        }
        return $statusCode;
    }
    
    // Process request for pending events
    public function processRequestPending($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName)) {
            $this->updateEventPending();
            if($this->isExtensionPending()) {
                $statusCode = $this->updateExtensions("install");
                if ($statusCode==200) {
                    $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                    $statusCode = $this->yellow->sendStatus(303, $location);
                }
            }
        }
        return $statusCode;
    }
    
    // Show extensions
    public function showExtensions() {
        list($statusCode, $settingsLatest) = $this->getExtensionSettings(true);
        foreach ($settingsLatest as $key=>$value) {
            $text = $description = $value->get("description");
            if ($value->isExisting("developer")) $text = "$description Developed by ".$value["developer"].".";
            if ($value->isExisting("translator")) $text = "$description Translated by ".$value["translator"].".";
            if ($value->isExisting("designer")) $text = "$description Designed by ".$value["designer"].".";
            echo ucfirst($key).": $text\n";
        }
        if ($statusCode!=200) echo "ERROR checking extensions: ".$this->yellow->page->get("pageError")."\n";
        return $statusCode;
    }
    
    // Download extensions
    public function downloadExtensions($settings) {
        $statusCode = 200;
        $path = $this->yellow->system->get("coreExtensionDirectory");
        $fileExtension = $this->yellow->system->get("coreDownloadExtension");
        foreach ($settings as $key=>$value) {
            $fileName = $path.$this->yellow->lookup->normaliseName($key, true, false, true).".zip";
            list($statusCode, $fileData) = $this->getExtensionFile($value->get("downloadUrl"));
            if (empty($fileData) || !$this->yellow->toolbox->createFile($fileName.$fileExtension, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                break;
            }
        }
        if ($statusCode==200) {
            foreach ($settings as $key=>$value) {
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
    public function updateExtensions($action) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->system->get("coreExtensionDirectory");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            $statusCode = max($statusCode, $this->updateExtensionArchive($entry, $action));
            if (!$this->yellow->toolbox->deleteFile($entry)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
            }
        }
        return $statusCode;
    }

    // Update extension from archive
    public function updateExtensionArchive($path, $action) {
        $statusCode = 200;
        $zip = new ZipArchive();
        if ($zip->open($path)===true) {
            if (defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::updateExtensionArchive file:$path<br/>\n";
            $pathBase = "";
            if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
            $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
            $settings = $this->yellow->toolbox->getTextSettings($fileData, "");
            list($extension, $version, $newModified, $oldModified) = $this->getExtensionInformation($settings);
            if (!empty($extension) && !empty($version)) {
                $statusCode = $this->updateExtensionSettings($extension, $settings, $action);
                if ($statusCode==200) {
                    $languages = $this->getExtensionArchiveLanguages($zip, $pathBase);
                    foreach ($this->getExtensionFileNames($settings) as $fileName) {
                        list($entry, $flags) = $this->yellow->toolbox->getTextList($settings[$fileName], ",", 2);
                        if (!$this->yellow->lookup->isContentFile($fileName)) {
                            $fileData = $zip->getFromName($pathBase.$entry);
                            $lastModified = $this->yellow->toolbox->getFileModified($fileName);
                            $statusCode = $this->updateExtensionFile($fileName, $fileData,
                                $newModified, $oldModified, $lastModified, $flags, $extension);
                        } else {
                            foreach ($this->getExtensionContentRootPages() as $page) {
                                list($fileNameSource, $fileNameDestination) = $this->getExtensionContentFileNames(
                                    $fileName, $pathBase, $entry, $flags, $languages, $page);
                                $fileData = $zip->getFromName($fileNameSource);
                                $lastModified = $this->yellow->toolbox->getFileModified($fileNameDestination);
                                $statusCode = $this->updateExtensionFile($fileNameDestination, $fileData,
                                    $newModified, $oldModified, $lastModified, $flags, $extension);
                            }
                        }
                        if ($statusCode!=200) break;
                    }
                    $statusCode = max($statusCode, $this->updateExtensionNotification($extension, $action));
                }
                $this->yellow->log($statusCode==200 ? "info" : "error", ucfirst($action)." extension '".ucfirst($extension)." $version'");
                ++$this->updates;
            } else {
                $statusCode = 500;
                $this->yellow->page->error(500, "Can't detect file '$path'!");
            }
            $zip->close();
        } else {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't open file '$path'!");
        }
        return $statusCode;
    }
    
    // Update extension from file
    public function updateExtensionFile($fileName, $fileData, $newModified, $oldModified, $lastModified, $flags, $extension) {
        $statusCode = 200;
        $fileName = $this->yellow->toolbox->normaliseTokens($fileName);
        if ($this->yellow->lookup->isValidFile($fileName)) {
            $create = $update = $delete = false;
            if (preg_match("/create/i", $flags) && !is_file($fileName) && !empty($fileData)) $create = true;
            if (preg_match("/update/i", $flags) && is_file($fileName) && !empty($fileData)) $update = true;
            if (preg_match("/delete/i", $flags) && is_file($fileName)) $delete = true;
            if (preg_match("/optional/i", $flags) && $this->yellow->extension->isExisting($extension)) $create = $update = $delete = false;
            if (preg_match("/careful/i", $flags) && is_file($fileName) && $lastModified!=$oldModified) $update = false;
            if ($create) {
                if (!$this->yellow->toolbox->createFile($fileName, $fileData, true) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $newModified)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
            }
            if ($update) {
                if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("coreTrashDirectory")) ||
                    !$this->yellow->toolbox->createFile($fileName, $fileData) ||
                    !$this->yellow->toolbox->modifyFile($fileName, $newModified)) {
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

    // Update pending events
    public function updateEventPending() {
        $this->updateSystemFiles();
        if ($this->yellow->system->get("updateEventPending")!="none") {
            foreach (explode(",", $this->yellow->system->get("updateEventPending")) as $token) {
                list($extension, $action) = $this->yellow->toolbox->getTextList($token, "/", 2);
                if ($this->yellow->extension->isExisting($extension) && ($action!="ready" && $action!="uninstall")) {
                    $value = $this->yellow->extension->data[$extension];
                    if (method_exists($value["object"], "onUpdate")) $value["object"]->onUpdate($action);
                }
            }
            $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
            if (!$this->yellow->system->save($fileName, array("updateEventPending" => "none"))) {
                $this->yellow->log("error", "Can't write file '$fileName'!");
            }
            $this->updateSystemSettings();
            $this->updateLanguageSettings();
        }
        if ($this->yellow->system->get("updateEventDaily")<=time()) {
            foreach ($this->yellow->extension->data as $key=>$value) {
                if (method_exists($value["object"], "onUpdate")) $value["object"]->onUpdate("daily");
            }
            $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
            if (!$this->yellow->system->save($fileName, array("updateEventDaily" => $this->getTimestampDaily()))) {
                $this->yellow->log("error", "Can't write file '$fileName'!");
            }
        }
    }
    
    // Update system files from a previous release
    public function updateSystemFiles() {
        if (is_dir("system/settings/")) {   // TODO: remove later, convert settings files
            $fileNameSource = "system/settings/system.ini";
            $fileNameDestination = "system/extensions/yellow-system.ini";
            if (is_file($fileNameSource)) {
                $fileData = $fileDataNew = $this->yellow->toolbox->readFile($fileNameSource);
                $fileDataNew = str_replace("user.ini", "yellow-user.ini", $fileDataNew);
                $fileDataNew = str_replace("language.ini", "yellow-language.ini", $fileDataNew);
                if (!$this->yellow->toolbox->createFile($fileNameDestination, $fileDataNew)) {
                    $this->yellow->log("error", "Can't write file '$fileNameDestination'!");
                }
            }
            $fileNameSource = "system/settings/user.ini";
            $fileNameDestination = "system/extensions/yellow-user.ini";
            if (is_file($fileNameSource) && !$this->yellow->toolbox->copyFile($fileNameSource, $fileNameDestination)) {
                $this->yellow->log("error", "Can't write file '$fileNameDestination'!");
            }
            $fileNameSource = "system/settings/language.ini";
            $fileNameDestination = "system/extensions/yellow-language.ini";
            if (is_file($fileNameSource) && !$this->yellow->toolbox->copyFile($fileNameSource, $fileNameDestination)) {
                $this->yellow->log("error", "Can't write file '$fileNameDestination'!");
            }
            if (!$this->yellow->toolbox->deleteDirectory("system/settings/", $this->yellow->system->get("coreTrashDirectory"))) {
                $this->yellow->log("error", "Can't delete directory 'system/settings/'!");
            }
            $this->yellow->system->load("system/extensions/yellow-system.ini");
            $this->yellow->user->load("system/extensions/yellow-user.ini");
            $this->yellow->language->load("system/extensions/yellow-language.ini");
            $this->yellow->page->error(503, "Flux capacitor is charging to 1.21 gigawatt, please reload page!");
        }
        if ($this->yellow->system->isExisting("updateNotification")) {  // TODO: remove later, convert old notification
            $updateEventPending = $this->yellow->system->get("updateNotification");
            $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
            if (!$this->yellow->system->save($fileName, array("updateEventPending" => $updateEventPending))) {
                $this->yellow->log("error", "Can't write file '$fileName'!");
            }
        }
    }
    
    // Update system settings
    public function updateSystemSettings() {
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileDataStart = $fileDataSettings = $fileDataComments = "";
        $settings = new YellowArray();
        $settings->exchangeArray($this->yellow->system->settingsDefaults->getArrayCopy());
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
            if (empty($fileDataStart) && preg_match("/^\#/", $line)) {
                $fileDataStart = $line;
            } elseif (!empty($matches[1]) && isset($settings[$matches[1]])) {
                $settings[$matches[1]] = $matches[2];
            } elseif (!empty($matches[1]) && substru($matches[1], 0, 1)!="#") {
                $fileDataComments .= "# $line";
            } elseif (!empty($matches[1])) {
                $fileDataComments .= $line;
            }
        }
        unset($settings["coreSystemFile"]);
        foreach ($settings as $key=>$value) {
            $fileDataSettings .= ucfirst($key).(strempty($value) ? ":\n" : ": $value\n");
        }
        if (!empty($fileDataStart)) $fileDataStart .= "\n";
        if (!empty($fileDataComments)) $fileDataSettings .= "\n";
        $fileDataNew = $fileDataStart.$fileDataSettings.$fileDataComments;
        if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($fileName, $fileDataNew)) {
            $this->yellow->log("error", "Can't write file '$fileName'!");
        }
        if ($this->yellow->system->get("updateCurrentRelease")!=YellowCore::RELEASE) {
            if (!$this->yellow->system->save($fileName, array("updateCurrentRelease" => YellowCore::RELEASE))) {
                $this->yellow->log("error", "Can't write file '$fileName'!");
            } else {
                list($name, $version, $os) = $this->yellow->toolbox->detectServerInformation();
                $product = "Datenstrom Yellow ".YellowCore::RELEASE;
                $this->yellow->log("info", "Update $product, PHP ".PHP_VERSION.", $name $version, $os");
            }
        }
    }
    
    // Update language settings
    public function updateLanguageSettings() {
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreLanguageFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileDataStart = $fileDataSettings = $language = "";
        $settings = new YellowArray();
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
            if (empty($fileDataStart) && preg_match("/^\#/", $line)) {
                $fileDataStart = $line;
            } elseif (!empty($matches[1]) && !empty($matches[2])) {
                if (lcfirst($matches[1])=="language" && !strempty($matches[2])) {
                    if (!empty($settings)) {
                        if (!empty($fileDataSettings)) $fileDataSettings .= "\n";
                        foreach ($settings as $key=>$value) {
                            $fileDataSettings .= (strposu($key, "/") ? $key : ucfirst($key)).": $value\n";
                        }
                    }
                    $language = $matches[2];
                    $settings = new YellowArray();
                    $settings["language"] = $language;
                    foreach ($this->yellow->language->settingsDefaults as $key=>$value) {
                        if ($this->yellow->language->isText($key, $language)) {
                            $settings[$key] = $this->yellow->language->getText($key, $language);
                        }
                    }
                }
                if (!empty($language)) {
                    $settings[$matches[1]] = $matches[2];
                }
            }
        }
        if (!empty($fileDataStart)) $fileDataStart .= "\n";
        if (!empty($fileDataSettings)) $fileDataSettings .= "\n";
        foreach ($settings as $key=>$value) {
            $fileDataSettings .= (strposu($key, "/") ? $key : ucfirst($key)).": $value\n";
        }
        $fileDataNew = $fileDataStart.$fileDataSettings;
        if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($fileName, $fileDataNew)) {
            $this->yellow->log("error", "Can't write file '$fileName'!");
        }
    }
    
    // Update extension settings
    public function updateExtensionSettings($extension, $settings, $action) {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateCurrentFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        if ($action=="install" || $action=="update") {
            $settingsCurrent = $this->yellow->toolbox->getTextSettings($fileData, "extension");
            if (!$settingsCurrent->isExisting($extension)) $settingsCurrent[$extension] = new YellowArray();
            foreach ($settings as $key=>$value) $settingsCurrent[$extension][$key] = $value;
            $settingsCurrent->uksort("strnatcasecmp");
            $fileDataNew = "";
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                if (preg_match("/^\#/", $line)) $fileDataNew = $line;
                break;
            }
            foreach ($settingsCurrent as $extension=>$block) {
                if (!empty($fileDataNew)) $fileDataNew .= "\n";
                foreach ($block as $key=>$value) {
                    $fileDataNew .= (strposu($key, "/") ? $key : ucfirst($key)).": $value\n";
                }
            }
        } else {
            $fileDataNew = $this->yellow->toolbox->unsetTextSettings($fileData, "extension", $extension);
        }
        if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($fileName, $fileDataNew)) {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Update extension notification
    public function updateExtensionNotification($extension, $action) {
        $statusCode = 200;
        if ($this->yellow->extension->isExisting($extension) && $action=="uninstall") {
            $value = $this->yellow->extension->data[$extension];
            if (method_exists($value["object"], "onUpdate")) $value["object"]->onUpdate($action);
        }
        $updateEventPending = $this->yellow->system->get("updateEventPending");
        if ($updateEventPending=="none") $updateEventPending = "";
        if (!empty($updateEventPending)) $updateEventPending .= ",";
        $updateEventPending .= "$extension/$action";
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        if (!$this->yellow->system->save($fileName, array("updateEventPending" => $updateEventPending))) {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Remove extensions
    public function removeExtensions($settings) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        foreach ($settings as $extension=>$block) {
            $statusCode = max($statusCode, $this->removeExtensionArchive($extension, $block, "uninstall"));
        }
        return $statusCode;
    }

    // Remove extension archive
    public function removeExtensionArchive($extension, $settings, $action) {
        $statusCode = 200;
        $fileNames = $this->getExtensionFileNames($settings, true);
        if (count($fileNames)) {
            $statusCode = max($statusCode, $this->updateExtensionNotification($extension, $action));
            foreach ($fileNames as $fileName) {
                $statusCode = max($statusCode, $this->removeExtensionFile($fileName));
            }
            if ($statusCode==200) $statusCode = $this->updateExtensionSettings($extension, $settings, $action);
            $version = $settings->get("version");
            $this->yellow->log($statusCode==200 ? "info" : "error", ucfirst($action)." extension '".ucfirst($extension)." $version'");
            ++$this->updates;
        } else {
            $statusCode = 500;
            $this->yellow->page->error(500, "Please delete extension '$extension' manually!");
        }
        return $statusCode;
    }

    // Remove extension file
    public function removeExtensionFile($fileName) {
        $statusCode = 200;
        $fileName = $this->yellow->toolbox->normaliseTokens($fileName);
        if ($this->yellow->lookup->isValidFile($fileName) && is_file($fileName)) {
            if (!$this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("coreTrashDirectory"))) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
            }
            if (defined("DEBUG") && DEBUG>=2) {
                echo "YellowUpdate::removeExtensionFile file:$fileName action:delete<br/>\n";
            }
        }
        return $statusCode;
    }

    // Return extensions from text, space separated
    public function getExtensionsFromText($text) {
        return array_unique(array_filter($this->yellow->toolbox->getTextArguments($text), "strlen"));
    }

    // Return extension install information
    public function getExtensionInstallInformation($extensions) {
        $settings = array();
        list($statusCodeCurrent, $settingsCurrent) = $this->getExtensionSettings(false);
        list($statusCodeLatest, $settingsLatest) = $this->getExtensionSettings(true);
        $statusCode = max($statusCodeCurrent, $statusCodeLatest);
        foreach ($extensions as $extension) {
            $found = false;
            foreach ($settingsLatest as $key=>$value) {
                if (strtoloweru($key)==strtoloweru($extension)) {
                    if (!$settingsCurrent->isExisting($key)) $settings[$key] = $settingsLatest[$key];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't find extension '$extension'!");
            }
        }
        return array($statusCode, $settings);
    }

    // Return extension uninstall information
    public function getExtensionUninstallInformation($extensions, $extensionsProtected) {
        $settings = array();
        list($statusCode, $settingsCurrent) = $this->getExtensionSettings(false);
        foreach ($extensions as $extension) {
            $found = false;
            foreach ($settingsCurrent as $key=>$value) {
                if (strtoloweru($key)==strtoloweru($extension)) {
                    $settings[$key] = $settingsCurrent[$key];
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
        foreach ($settings as $key=>$value) {
            if (in_array($key, $protected)) unset($settings[$key]);
        }
        return array($statusCode, $settings);
    }

    // Return extension update information
    public function getExtensionUpdateInformation($extensions) {
        $settings = array();
        list($statusCodeCurrent, $settingsCurrent) = $this->getExtensionSettings(false);
        list($statusCodeLatest, $settingsLatest) = $this->getExtensionSettings(true);
        $statusCode = max($statusCodeCurrent, $statusCodeLatest);
        if (empty($extensions)) {
            foreach ($settingsCurrent as $key=>$value) {
                if ($settingsLatest->isExisting($key)) {
                    $versionCurrent = $settingsCurrent[$key]->get("version");
                    $versionLatest = $settingsLatest[$key]->get("version");
                    if (strnatcasecmp($versionCurrent, $versionLatest)<0) {
                        $settings[$key] = $settingsLatest[$key];
                    }
                }
            }
        } else {
            foreach ($extensions as $extension) {
                $found = false;
                foreach ($settingsCurrent as $key=>$value) {
                    if (strtoloweru($key)==strtoloweru($extension) && $settingsLatest->isExisting($key)) {
                        $versionCurrent = $settingsCurrent[$key]->get("version");
                        $versionLatest = $settingsLatest[$key]->get("version");
                        if (strnatcasecmp($versionCurrent, $versionLatest)<0) {
                            $settings[$key] = $settingsLatest[$key];
                        }
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
            foreach ($settings as $key=>$value) {
                echo ucfirst($key)." ".$value->get("version")."\n";
            }
        }
        return array($statusCode, $settings);
    }

    // Return extension settings
    public function getExtensionSettings($latest) {
        $statusCode = 200;
        $settings = array();
        if (!$latest) {
            $fileNameCurrent = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateCurrentFile");
            $fileData = $this->yellow->toolbox->readFile($fileNameCurrent);
            $settings = $this->yellow->toolbox->getTextSettings($fileData, "extension");
            foreach ($settings->getArrayCopy() as $key=>$value) {
                if (!$this->yellow->extension->isExisting($key)) unset($settings[$key]);
            }
            foreach ($this->yellow->extension->data as $key=>$value) {
                if (!$settings->isExisting($key)) $settings[$key] = new YellowArray();
                $settings[$key]["extension"] = ucfirst($key);
                $settings[$key]["version"] = $value["version"];
            }
        } else {
            $fileNameLatest = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateLatestFile");
            $expire = $this->yellow->toolbox->getFileModified($fileNameLatest) + 60*10;
            if ($expire<=time()) {
                $url = $this->yellow->system->get("updateExtensionUrl")."/raw/master/".$this->yellow->system->get("updateLatestFile");
                list($statusCode, $fileData) = $this->getExtensionFile($url);
                if ($statusCode==200 && !$this->yellow->toolbox->createFile($fileNameLatest, $fileData)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileNameLatest'!");
                }
            }
            $fileData = $this->yellow->toolbox->readFile($fileNameLatest);
            $settings = $this->yellow->toolbox->getTextSettings($fileData, "extension");
        }
        $settings->uksort("strnatcasecmp");
        return array($statusCode, $settings);
    }

    // Return extension archive languages
    public function getExtensionArchiveLanguages($zip, $pathBase) {
        $languages = array();
        for ($index=0; $index<$zip->numFiles; ++$index) {
            $entry = substru($zip->getNameIndex($index), strlenu($pathBase));
            if (preg_match("#^(.*)\/.*?$#", $entry, $matches)) {
                array_push($languages, $matches[1]);
            }
        }
        return array_unique($languages);
    }

    // Return extension information
    public function getExtensionInformation($settings) {
        $extension = lcfirst($settings->get("extension"));
        $version = $settings->get("version");
        $newModified = strtotime($settings->get("published"));
        $oldModified = 0;
        $invalid = false;
        foreach ($settings as $key=>$value) {
            if (strposu($key, "/")) {
                if (!$this->yellow->lookup->isValidFile($key)) $invalid = true;
                list($entry, $flags) = $this->yellow->toolbox->getTextList($value, ",", 2);
                if (strposu($entry, ".")===false) $invalid = true; // TODO: remove later, detect old format
                if ($oldModified==0) $oldModified = $this->yellow->toolbox->getFileModified($key);
            }
        }
        if ($invalid) $extension = $version = "";
        return array($extension, $version, $newModified, $oldModified);
    }

    // Return extension file names
    public function getExtensionFileNames($settings, $reverse = false) {
        $fileNames = array();
        foreach ($settings as $key=>$value) {
            if (strposu($key, "/")) array_push($fileNames, $key);
        }
        if ($reverse) $fileNames = array_reverse($fileNames);
        return $fileNames;
    }

    // Return extension root pages for content files
    public function getExtensionContentRootPages() {
        $rootPages = array();
        foreach ($this->yellow->content->scanLocation("") as $page) {
            if ($page->isAvailable() && $page->isVisible()) array_push($rootPages, $page);
        }
        return $rootPages;
    }

    // Return extension files names for content files
    public function getExtensionContentFileNames($fileName, $pathBase, $entry, $flags, $languages, $page) {
        if (preg_match("/multi-language/i", $flags)) {
            $languageFound = "";
            $languagesWanted = array($page->get("language"), "en");
            foreach ($languagesWanted as $language) {
                if (in_array($language, $languages)) {
                    $languageFound = $language;
                    break;
                }
            }
            $pathLanguage = $languageFound ? "$languageFound/" : "";
            $fileNameSource = $pathBase.$pathLanguage.$entry;
        } else {
            $fileNameSource = $pathBase.$entry;
        }
        if ($this->yellow->system->get("coreMultiLanguageMode")) {
            $contentDirectoryLength = strlenu($this->yellow->system->get("coreContentDirectory"));
            $fileNameDestination = $page->fileName.substru($fileName, $contentDirectoryLength);
        } else {
            $fileNameDestination = $fileName;
        }
        return array($fileNameSource, $fileNameDestination);
    }

    // Return extension file
    public function getExtensionFile($url) {
        $urlRequest = $url;
        if (preg_match("#^https://github.com/(.+)/raw/(.+)$#", $url, $matches)) $urlRequest = "https://raw.githubusercontent.com/".$matches[1]."/".$matches[2];
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $urlRequest);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowUpdate/".YellowUpdate::VERSION."; SoftwareUpdater)");
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
    
    // Return time of next daily update
    public function getTimestampDaily() {
        $timeOffset = 0;
        foreach (str_split($this->yellow->system->get("sitename")) as $char) {
            $timeOffset = ($timeOffset+ord($char)) % 60;
        }
        return mktime(0, 0, 0) + 60*60*24 + $timeOffset;
    }

    // Check if extension pending
    public function isExtensionPending() {
        $path = $this->yellow->system->get("coreExtensionDirectory");
        return count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", false, false))>0;
    }
}
