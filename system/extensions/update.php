<?php
// Update extension, https://github.com/datenstrom/yellow-extensions/tree/master/source/update

class YellowUpdate {
    const VERSION = "0.8.30";
    const PRIORITY = "2";
    public $yellow;                 // access to API
    public $updates;                // number of updates
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("updateExtensionUrl", "https://github.com/datenstrom/yellow-extensions");
        $this->yellow->system->setDefault("updateExtensionFile", "extension.ini");
        $this->yellow->system->setDefault("updateCurrentFile", "update-current.ini");
        $this->yellow->system->setDefault("updateLatestFile", "update-latest.ini");
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
                case "about":       $statusCode = $this->processCommandAbout($command, $text); break;
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
        $help = "about\n";
        $help .= "install [extension]\n";
        $help .= "uninstall [extension]\n";
        $help .= "update [extension]\n";
        return $help;
    }

    // Handle update
    public function onUpdate($action) {
        if ($action=="update") {  // TODO: remove later, converts old content settings
            if ($this->yellow->system->isExisting("multiLanguageMode")) {
                $coreMultiLanguageMode = $this->yellow->system->get("multiLanguageMode");
                $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
                if (!$this->yellow->system->save($fileName, array("coreMultiLanguageMode" => $coreMultiLanguageMode))) {
                    $this->yellow->log("error", "Can't write file '$fileName'!");
                }
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
        if ($action=="update") {  // TODO: remove later, converts old language settings
            if ($this->yellow->system->isExisting("coreTextFile")) {
                $fileNameSource = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreTextFile");
                $fileNameDestination = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreLanguageFile");
                if (is_file($fileNameSource) && !is_file($fileNameDestination)) {
                    if (!$this->yellow->toolbox->renameFile($fileNameSource, $fileNameDestination)) {
                        $this->yellow->log("error", "Can't write file '$fileNameDestination'!");
                    }
                }
                $imageDirectoryLength = strlenu($this->yellow->system->get("coreImageDirectory"));
                $fileData = $this->yellow->toolbox->readFile($fileNameDestination);
                $fileDataNew = "";
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                        if (strposu($matches[1], ".") &&
                            substru($matches[1], 0, $imageDirectoryLength)!=$this->yellow->system->get("coreImageDirectory")) {
                            $line = $this->yellow->system->get("coreImageDirectory").$line;
                        }
                    }
                    $fileDataNew .= $line;
                }
                if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($fileNameDestination, $fileDataNew)) {
                    $this->yellow->log("error", "Can't write file '$fileNameDestination'!");
                }
                $path = $this->yellow->system->get("coreExtensionDirectory");
                foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*-language\.txt/", false, false) as $entry) {
                    $entryShort = str_replace("-language.txt", ".txt", $entry);
                    if (is_file($entryShort) && !$this->yellow->toolbox->deleteFile($entry, $this->yellow->system->get("coreTrashDirectory"))) {
                        $this->yellow->log("error", "Can't delete file '$entry'!");
                    }
                }
            }
        }
        if ($action=="update") {  // TODO: remove later, converts old layout files
            if ($this->yellow->system->isExisting("coreLayoutDir")) {
                $path = $this->yellow->system->get("coreLayoutDir");
                foreach ($this->yellow->toolbox->getDirectoryEntriesRecursive($path, "/^.*\.html$/", true, false) as $entry) {
                    $fileData = $fileDataNew = $this->yellow->toolbox->readFile($entry);
                    $fileDataNew = str_replace("yellow->getLayoutArgs", "yellow->getLayoutArguments", $fileDataNew);
                    $fileDataNew = str_replace("toolbox->getLocationArgs", "toolbox->getLocationArguments", $fileDataNew);
                    $fileDataNew = str_replace("toolbox->getTextArgs", "toolbox->getTextArguments", $fileDataNew);
                    $fileDataNew = str_replace("toolbox->normaliseArgs", "toolbox->normaliseArguments", $fileDataNew);
                    $fileDataNew = str_replace("toolbox->isLocationArgs", "toolbox->isLocationArguments", $fileDataNew);
                    $fileDataNew = str_replace("text->getHtml", "language->getTextHtml", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"navigation\")", "\"navigation\"", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"header\")", "\"header\"", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"sidebar\")", "\"sidebar\"", $fileDataNew);
                    $fileDataNew = str_replace("\$this->yellow->page->get(\"footer\")", "\"footer\"", $fileDataNew);
                    $fileDataNew = str_replace("<link rel=\"icon\" type=\"image/png\" href=\"<?php echo \$resourceLocation.\$this->yellow->page->getHtml(\"theme\").\"-icon.png\" ?>\" />", "<?php /* Add icons and files here */ ?>", $fileDataNew);
                    if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($entry, $fileDataNew)) {
                        $this->yellow->log("error", "Can't write file '$entry'!");
                    }
                }
            }
        }
        if ($action=="update") {  // TODO: remove later, converts old resource files
            if ($this->yellow->system->isExisting("coreResourceDir")) {
                $pathSource = "system/resources/";
                $pathDestination = $this->yellow->system->get("coreThemeDirectory");
                if (is_dir($pathSource) && !is_dir($pathDestination)) {
                    if (!$this->yellow->toolbox->renameDirectory($pathSource, $pathDestination)) {
                        $this->yellow->log("error", "Can't write directory '$pathDestination'!");
                    }
                }
                if (is_dir($pathSource) && is_dir($pathDestination)) {
                    foreach ($this->yellow->toolbox->getDirectoryEntries($pathSource, "/.*/", true, false, false) as $entry) {
                        $entrySource = $pathSource.$entry;
                        $entryDestination = $pathDestination.$entry;
                        $dataSource = $this->yellow->toolbox->readFile($entrySource);
                        $dataDestination = $this->yellow->toolbox->readFile($entryDestination);
                        if ($dataSource!=$dataDestination && !$this->yellow->toolbox->copyFile($entrySource, $entryDestination)) {
                            $this->yellow->log("error", "Can't write file '$entryDestination'!");
                        }
                    }
                    if (!$this->yellow->toolbox->deleteDirectory($pathSource, $this->yellow->system->get("coreTrashDirectory"))) {
                        $this->yellow->log("error", "Can't delete directory '$pathSource'!");
                    }
                }
                foreach ($this->yellow->toolbox->getDirectoryEntries($pathDestination, "/^.*\-icon\.png$/", true, false) as $entry) {
                    $entryShort = str_replace("-icon.png", ".png", $entry);
                    if (!is_file($entryShort) && !$this->yellow->toolbox->copyFile($entry, $entryShort)) {
                        $this->yellow->log("error", "Can't write file '$entryShort'!");
                    }
                }
                foreach ($this->yellow->toolbox->getDirectoryEntries($pathDestination, "/bundle-.*/", true, false) as $entry) {
                    if (!$this->yellow->toolbox->deleteFile($entry)) {
                        $this->yellow->log("error", "Can't delete file '$entry'!");
                    }
                }
            }
            if ($this->yellow->system->get("metaDefaultImage")=="icon") {
                $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
                if (!$this->yellow->system->save($fileName, array("metaDefaultImage" => "favicon"))) {
                    $this->yellow->log("error", "Can't write file '$fileName'!");
                }
            }
        }
        if ($action=="update") {  // TODO: remove later, converts old commandline
            if ($this->yellow->system->isExisting("coreStaticDir")) {
                $fileName = "yellow.php";
                $fileData = $fileDataNew = $this->yellow->toolbox->readFile($fileName);
                $fileDataNew = str_replace("command(\$argv[1], \$argv[2], \$argv[3], \$argv[4], \$argv[5], \$argv[6], \$argv[7])", "command()", $fileDataNew);
                if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($fileName, $fileDataNew)) {
                    $this->yellow->log("error", "Can't write file '$fileName'!");
                }
            }
        }
        if ($action=="startup") {
            if ($this->yellow->system->get("updateNotification")!="none") {
                foreach (explode(",", $this->yellow->system->get("updateNotification")) as $token) {
                    list($extension, $action) = $this->yellow->toolbox->getTextList($token, "/", 2);
                    if ($this->yellow->extension->isExisting($extension) && ($action!="startup" && $action!="uninstall")) {
                        $value = $this->yellow->extension->data[$extension];
                        if (method_exists($value["object"], "onUpdate")) $value["object"]->onUpdate($action);
                    }
                }
                $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
                if (!$this->yellow->system->save($fileName, array("updateNotification" => "none"))) {
                    $this->yellow->log("error", "Can't write file '$fileName'!");
                }
                $this->updateSystemSettings();
                $this->updateLanguageSettings();
            }
        }
    }
    
    // Update system settings after update notification
    public function updateSystemSettings() {
        $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
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
    }
    
    // Update language settings after update notification
    public function updateLanguageSettings() {
        $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreLanguageFile");
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

    // Create extension settings from scratch
    public function createExtensionSettings() {
        $fileNameCurrent = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateCurrentFile");
        $fileNameLatest = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateLatestFile");
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
            if(!$this->yellow->toolbox->createFile($fileNameCurrent, $fileDataCurrent)) {
                $this->yellow->log("error", "Can't write file '$fileNameCurrent'!");
            }
            if(!$this->yellow->toolbox->createFile($fileNameLatest, $fileDataLatest)) {
                 $this->yellow->log("error", "Can't write file '$fileNameLatest'!");
            }
        }
        return $statusCode;
    }

    // Update extension settings
    public function updateExtensionSettings($extension, $settings, $action) {
        $statusCode = 200;
        $fileNameCurrent = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateCurrentFile");
        $fileData = $this->yellow->toolbox->readFile($fileNameCurrent);
        if ($action=="install" || $action=="update") {
            $settingsNew = new YellowArray();
            foreach($settings as $key=>$value) $settingsNew[$key] = $value;
            $fileData = $this->yellow->toolbox->setTextSettings($fileData, "extension", $extension, $settingsNew);
        } else {
            $fileData = $this->yellow->toolbox->unsetTextSettings($fileData, "extension", $extension);
        }
        if (!$this->yellow->toolbox->createFile($fileNameCurrent, $fileData)) {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't write file '$fileNameCurrent'!");
        }
        return $statusCode;
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
        if ($this->yellow->extension->isExisting($extension) && $action=="uninstall") {
            $value = $this->yellow->extension->data[$extension];
            if (method_exists($value["object"], "onUpdate")) $value["object"]->onUpdate($action);
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
            $languages = $this->getExtensionArchiveLanguages($zip, $pathBase);
            $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
            $settings = $this->yellow->toolbox->getTextSettings($fileData, "");
            list($extension, $version, $newModified, $oldModified) = $this->getExtensionInformation($settings);
            if (!empty($extension) && !empty($version)) {
                $statusCode = $this->updateExtensionSettings($extension, $settings, $action);
                if ($statusCode==200) {
                    foreach ($this->getExtensionFileNames($settings) as $fileName) {
                        list($entry, $flags) = $this->yellow->toolbox->getTextList($settings[$fileName], ",", 2);
                        if (strposu($entry, ".")===false) {
                            list($dummy, $entry, $flags) = $this->yellow->toolbox->getTextList($settings[$fileName], ",", 3);
                        }
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
                    $statusCode = max($statusCode, $this->processUpdateNotification($extension, $action));
                }
                $this->yellow->log($statusCode==200 ? "info" : "error", ucfirst($action)." extension '".ucfirst($extension)." $version'");
                ++$this->updates;
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
            $statusCode = max($statusCode, $this->processUpdateNotification($extension, $action));
            foreach ($fileNames as $fileName) {
                $statusCode = max($statusCode, $this->removeExtensionFile($fileName));
            }
            if ($statusCode==200) $statusCode = $this->updateExtensionSettings($extension, $settings, $action);
            $version = $settings->get("version");
            $this->yellow->log($statusCode==200 ? "info" : "error", ucfirst($action)." extension '".ucfirst($extension)." $version'");
            ++$this->updates;
        } else {
            $statusCode = 500;
            $this->yellow->page->error(500, "Can't find information about extension '$extension'!");
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
            $force = false;
            foreach ($extensions as $extension) {
                $found = false;
                if ($extension=="force") { $force = true; continue; }
                foreach ($settingsCurrent as $key=>$value) {
                    if (strtoloweru($key)==strtoloweru($extension) && $settingsLatest->isExisting($key)) {
                        $settings[$key] = $settingsLatest[$key];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't find extension '$extension'!");
                }
            }
            if (!$force) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Please use 'force' to update an extension!");
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
            if (!is_file($fileNameCurrent)) $statusCode = $this->createExtensionSettings();
            $fileData = $this->yellow->toolbox->readFile($fileNameCurrent);
            $settings = $this->yellow->toolbox->getTextSettings($fileData, "extension");
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
        foreach ($settings as $key=>$value) {
            if (strposu($key, "/") && is_file($key)) {
                $oldModified = filemtime($key);
                break;
            }
        }
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

    // Check if extension pending
    public function isExtensionPending() {
        $path = $this->yellow->system->get("coreExtensionDirectory");
        return count($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", false, false))>0;
    }
}
