<?php
// Install extension, https://github.com/annaesvensson/yellow-install

class YellowInstall {
    const VERSION = "0.9.5";
    const PRIORITY = "1";
    public $yellow;                 // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        return $this->processRequestInstall($scheme, $address, $base, $location, $fileName);
    }
    
    // Handle command
    public function onCommand($command, $text) {
        return $this->processCommandInstall($command, $text);
    }
    
    // Process request to install website
    public function processRequestInstall($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName) || is_string_empty($fileName)) {
            if ($this->yellow->system->get("updateCurrentRelease")=="none") {
                $this->checkServerRequirements();
                $author = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $this->yellow->page->getRequest("author")));
                $email = trim($this->yellow->page->getRequest("email"));
                $password = trim($this->yellow->page->getRequest("password"));
                $language = trim($this->yellow->page->getRequest("language"));
                $extension = trim($this->yellow->page->getRequest("extension"));
                $status = trim($this->yellow->page->getRequest("status"));
                $statusCode = $this->updateLog();
                $statusCode = max($statusCode, $this->updateLanguages("small"));
                $errorMessage = $this->yellow->page->errorMessage;
                $this->yellow->content->pages["root/"] = array();
                $this->yellow->page = new YellowPage($this->yellow);
                $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName, false);
                $this->yellow->page->parseMeta($this->getRawDataInstall(), $statusCode, $errorMessage);
                $this->yellow->page->parseContent();
                $this->yellow->page->parsePage();
                if ($status=="install") $status = $this->updateExtensions("small", $extension)==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateUser($email, $password, $author, $language)==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateAuthentication($scheme, $address, $base, $email)==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateContent($language, "installHome", "/")==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateContent($language, "installAbout", "/about/")==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateContent($language, "installDefault", "/shared/page-new-default")==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateContent($language, "installWiki", "/shared/page-new-wiki")==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateContent($language, "installBlog", "/shared/page-new-blog")==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateContent($language, "coreError404", "/shared/page-error-404")==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->updateSettings()==200 ? "ok" : "error";
                if ($status=="ok") $status = $this->removeInstall()==200 ? "done" : "error";
            } else {
                $status = $this->removeInstall(true)==200 ? "done" : "error";
            }
            if ($status=="done") {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, "/");
                $statusCode = $this->yellow->sendStatus(303, $location);
            } else {
                $statusCode = $this->yellow->sendData($this->yellow->page->statusCode, $this->yellow->page->headerData, $this->yellow->page->outputData);
            }
        }
        return $statusCode;
    }
    
    // Process command to install website
    public function processCommandInstall($command, $text) {
        $statusCode = 0;
        if ($this->yellow->system->get("updateCurrentRelease")=="none") {
            $this->checkCommandRequirements();
            list($installation, $option) = $this->yellow->toolbox->getTextArguments($text);
            if (is_string_empty($command)) {
                $statusCode = 200;
                echo "Datenstrom Yellow is for people who make small websites. https://datenstrom.se/yellow/\n";
                echo "Syntax: php yellow.php\n";
                echo "        php yellow.php about [extension]\n";
                echo "        php yellow.php serve [url]\n";
                echo "        php yellow.php skip installation [option]\n";
            } elseif ($command=="about" || $command=="serve") {
                $statusCode = 0;
            } elseif ($command=="skip" && $installation=="installation") {
                $statusCode = $this->updateLog();
                if ($statusCode==200) $statusCode = $this->updateLanguages($option);
                if ($statusCode==200) $statusCode = $this->updateExtensions($option, "");
                if ($statusCode==200) $statusCode = $this->updateSettings(true);
                if ($statusCode==200) $statusCode = $this->removeInstall();
                if ($statusCode>=400) {
                    echo "ERROR installing files: ".$this->yellow->page->errorMessage."\n";
                    echo "The installation has not been completed. Please run command again.\n";
                } else {
                    $extensionsCount = $this->getExtensionsCount();
                    echo "Yellow $command: $extensionsCount extension".($extensionsCount!=1 ? "s" : "").", 0 errors\n";
                }
            } else {
                $statusCode = 500;
                echo "The installation has not been completed. Please type 'php yellow.php serve' or 'php yellow.php skip installation`.\n";
            }
        } else {
            $statusCode = $this->removeInstall(true);
            if ($statusCode==200) $statusCode = 0;
            if ($statusCode>=400) {
                echo "ERROR installing files: ".$this->yellow->page->errorMessage."\n";
                echo "Detected ZIP files, 0 extensions installed. Please run command again.\n";
            }
        }
        return $statusCode;
    }
    
    // Update log file
    public function updateLog() {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreWebsiteFile");
        if (!is_file($fileName)) {
            list($name, $version, $os) = $this->yellow->toolbox->detectServerInformation();
            $product = "Datenstrom Yellow ".YellowCore::RELEASE;
            $this->yellow->toolbox->log("info", "Install $product, PHP ".PHP_VERSION.", $name $version, $os");
            foreach ($this->yellow->extension->data as $key=>$value) {
                if ($key=="install") continue;
                $this->yellow->toolbox->log("info", "Install extension '".ucfirst($key)." $value[version]'");
            }
            if (!is_file($fileName)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update languages
    public function updateLanguages($option) {
        $statusCode = 200;
        $path = $this->yellow->system->get("coreWorkerDirectory")."install-language.bin";
        $zip = new ZipArchive();
        if ($zip->open($path)===true) {
            $pathBase = "";
            if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
            $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
            foreach ($this->getLanguageExtensionsRequired($fileData, $option) as $extension) {
                $fileDataPhp = $zip->getFromName($pathBase."translations/$extension/$extension.php");
                $fileDataIni = $zip->getFromName($pathBase."translations/$extension/extension.ini");
                $statusCode = max($statusCode, $this->updateLanguageArchive($fileDataPhp, $fileDataIni, $pathBase, "install"));
            }
            $this->yellow->extension->load($this->yellow->system->get("coreWorkerDirectory"));
            $this->yellow->language->load($this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreLanguageFile"));
            $zip->close();
        } else {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't open file '$path'!");
        }
        return $statusCode;
    }
    
    // Update language archive
    public function updateLanguageArchive($fileDataPhp, $fileDataIni, $pathBase, $action) {
        $statusCode = 200;
        if ($this->yellow->extension->isExisting("update")) {
            $settings = $this->yellow->toolbox->getTextSettings($fileDataIni, "");
            $extension = lcfirst($settings->get("extension"));
            $version = $settings->get("version");
            $modified = strtotime($settings->get("published"));
            $fileNamePhp = $this->yellow->system->get("coreWorkerDirectory").$extension.".php";
            if (!is_string_empty($extension) && !is_string_empty($version) && !is_file($fileNamePhp)) {
                $statusCode = max($statusCode, $this->yellow->extension->get("update")->updateExtensionSettings($extension, $action, $fileDataIni));
                $statusCode = max($statusCode, $this->yellow->extension->get("update")->updateExtensionFile(
                    $fileNamePhp, $fileDataPhp, $modified, 0, 0, "create", $extension));
                $this->yellow->toolbox->log($statusCode==200 ? "info" : "error", ucfirst($action)." extension '".ucfirst($extension)." $version'");
            }
        }
        return $statusCode;
    }
    
    // Update extensions
    public function updateExtensions($option, $extension) {
        $statusCode = 200;
        if ($this->yellow->extension->isExisting("update")) {
            if (!is_string_empty($option)) {
                if ($option=="medium" || $option=="large") {
                    $path = $this->yellow->system->get("coreExtensionDirectory");
                    $fileData = $this->yellow->toolbox->readFile($path.$this->yellow->system->get("updateAvailableFile"));
                    $settings = $this->yellow->toolbox->getTextSettings($fileData, "extension");
                    $extensions = $this->getAvailableExtensionsRequired($settings, $option);
                    $statusCode = $this->downloadExtensionsAvailable($settings, $extensions);
                    $path = $this->yellow->system->get("coreWorkerDirectory");
                    foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^install-.*\.bin$/", true, false) as $entry) {
                        if (basename($entry)=="install-language.bin") continue;
                        if (preg_match("/^install-(.*?)\.bin/", basename($entry), $matches) && !in_array($matches[1], $extensions)) continue;
                        $statusCode = max($statusCode, $this->yellow->extension->get("update")->updateExtensionArchive($entry, "install"));
                    }
                }
                if (!($option=="small" || $option=="medium" || $option=="large")) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Option '$option' not supported!");
                }
            }
            if (!is_string_empty($extension)) {
                $path = $this->yellow->system->get("coreWorkerDirectory")."install-".$extension.".bin";
                if (is_file($path)) {
                    $statusCode = $this->yellow->extension->get("update")->updateExtensionArchive($path, "install");
                }
            }
        }
        return $statusCode;
    }
    
    // Update user
    public function updateUser($email, $password, $name, $language) {
        $statusCode = 200;
        if ($this->yellow->extension->isExisting("edit") && !is_string_empty($email) && !is_string_empty($password)) {
            if (is_string_empty($name)) $name = $this->yellow->system->get("sitename");
            $fileNameUser = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreUserFile");
            $settings = array(
                "name" => $name,
                "description" => $this->yellow->language->getText("editUserDescription", $language),
                "language" => $language,
                "access" => "create, edit, delete, restore, upload, configure, update",
                "home" => "/",
                "hash" => $this->yellow->extension->get("edit")->response->createHash($password),
                "stamp" => $this->yellow->extension->get("edit")->response->createStamp(),
                "pending" => "none",
                "failed" => "0",
                "modified" => date("Y-m-d H:i:s", time()),
                "status" => "active");
            if (!$this->yellow->user->save($fileNameUser, $email, $settings)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileNameUser'!");
            }
            $this->yellow->toolbox->log($statusCode==200 ? "info" : "error", "Add user '".strtok($name, " ")."'");
        }
        return $statusCode;
    }
    
    // Update authentication
    public function updateAuthentication($scheme, $address, $base, $email) {
        if ($this->yellow->extension->isExisting("edit") && $this->yellow->user->isExisting($email)) {
            $base = rtrim($base.$this->yellow->system->get("editLocation"), "/");
            $this->yellow->extension->get("edit")->response->createCookies($scheme, $address, $base, $email);
        }
        return 200;
    }
    
    // Update content
    public function updateContent($language, $name, $location) {
        $statusCode = 200;
        $fileName = $this->yellow->lookup->findFileFromContentLocation($location);
        $fileData = str_replace("\r\n", "\n", $this->yellow->toolbox->readFile($fileName));
        if (!is_string_empty($fileData) && $language!="en") {
            $titleOld = "Title: ".$this->yellow->language->getText("{$name}Title", "en")."\n";
            $titleNew = "Title: ".$this->yellow->language->getText("{$name}Title", $language)."\n";
            $fileData = str_replace($titleOld, $titleNew, $fileData);
            $textOld = str_replace("\\n", "\n", $this->yellow->language->getText("{$name}Text", "en"));
            $textNew = str_replace("\\n", "\n", $this->yellow->language->getText("{$name}Text", $language));
            $fileData = str_replace($textOld, $textNew, $fileData);
            if (!$this->yellow->toolbox->writeFile($fileName, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update settings
    public function updateSettings($skipInstallation = false) {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        if (!$this->yellow->system->save($fileName, $this->getSystemSettings($skipInstallation))) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
        }
        $language = $this->yellow->system->get("language");
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreLanguageFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        if (strposu($fileData, "Language:")===false) {
            if (!is_string_empty($fileData)) $fileData .= "\n";
            $fileData .= "Language: $language\n";
            $fileData .= "media/images/photo.jpg: ".$this->yellow->language->getText("installExampleImage", $language)."\n";
            if (!$this->yellow->toolbox->writeFile($fileName, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Remove files used by installation
    public function removeInstall($log = false) {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->system->get("coreWorkerDirectory");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^install-.*\.bin$/", true, false) as $entry) {
            if (!$this->yellow->toolbox->deleteFile($entry)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
            }
        }
        $fileName = $this->yellow->system->get("coreWorkerDirectory")."install.php";
        if ($statusCode==200 && !$this->yellow->toolbox->deleteFile($fileName)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't delete file '$fileName'!");
        }
        if ($statusCode==200) unset($this->yellow->extension->data["install"]);
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateInstalledFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileDataNew = $this->yellow->toolbox->unsetTextSettings($fileData, "extension", "install");
        if ($statusCode==200 && !$this->yellow->toolbox->writeFile($fileName, $fileDataNew)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
        }
        if ($log) $this->yellow->toolbox->log($statusCode==200 ? "info" : "error", "Uninstall extension 'Install ".YellowInstall::VERSION."'");
        return $statusCode;
    }
    
    // Check web server requirements
    public function checkServerRequirements() {
        if ($this->yellow->system->get("coreDebugMode")>=1) {
            list($name, $version, $os) = $this->yellow->toolbox->detectServerInformation();
            echo "YellowInstall::checkServerRequirements for $name $version, $os<br/>\n";
        }
        if (!$this->checkServerComplete()) $this->yellow->exitFatalError("Datenstrom Yellow requires complete upload!");
        if (!$this->checkServerWrite()) $this->yellow->exitFatalError("Datenstrom Yellow requires write access!");
        if (!$this->checkServerConfiguration()) $this->yellow->exitFatalError("Datenstrom Yellow requires configuration file!");
        if (!$this->checkServerRewrite()) $this->yellow->exitFatalError("Datenstrom Yellow requires rewrite support!");
    }
    
    // Check command line requirements
    public function checkCommandRequirements() {
        if ($this->yellow->system->get("coreDebugMode")>=1) {
            list($name, $version, $os) = $this->yellow->toolbox->detectServerInformation();
            echo "YellowInstall::checkCommandRequirements for $name $version, $os<br/>\n";
        }
        if (!$this->checkServerComplete()) $this->yellow->exitFatalError("Datenstrom Yellow requires complete upload!");
        if (!$this->checkServerWrite()) $this->yellow->exitFatalError("Datenstrom Yellow requires write access!");
    }
    
    // Check web server complete upload
    public function checkServerComplete() {
        $complete = true;
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateInstalledFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $settings = $this->yellow->toolbox->getTextSettings($fileData, "extension");
        $fileNames = array($fileName);
        foreach ($settings as $extension=>$block) {
            foreach ($block as $key=>$value) {
                if (strposu($key, "/")) {
                    list($entry, $flags) = $this->yellow->toolbox->getTextList($value, ",", 2);
                    if (!preg_match("/create/i", $flags)) continue;
                    if (preg_match("/delete/i", $flags)) continue;
                    if (preg_match("/additional/i", $flags)) continue;
                    array_push($fileNames, $key);
                }
            }
        }
        foreach ($fileNames as $fileName) {
            if (!is_file($fileName) || filesize($fileName)==0) {
                $complete = false;
                if ($this->yellow->system->get("coreDebugMode")>=1) {
                    echo "YellowInstall::checkServerComplete detected missing file:$fileName<br/>\n";
                }
            }
        }
        return $complete;
    }
    
    // Check web server write access
    public function checkServerWrite() {
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        return $this->yellow->system->save($fileName, array());
    }
    
    // Check web server configuration file
    public function checkServerConfiguration() {
        list($name) = $this->yellow->toolbox->detectServerInformation();
        return strtoloweru($name)!="apache" || is_file(".htaccess");
    }
    
    // Check web server rewrite support
    public function checkServerRewrite() {
        $rewrite = true;
        if (!$this->isServerBuiltin()) {
            $curlHandle = curl_init();
            list($scheme, $address, $base) = $this->yellow->lookup->getRequestInformation();
            $location = $this->yellow->system->get("coreAssetLocation").$this->yellow->lookup->normaliseName($this->yellow->system->get("theme")).".css";
            $url = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
            curl_setopt($curlHandle, CURLOPT_URL, $url);
            curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowInstall/".YellowInstall::VERSION).")";
            curl_setopt($curlHandle, CURLOPT_NOBODY, 1);
            curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($curlHandle);
            $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);
            if ($statusCode!=200) {
                $rewrite = false;
                if ($this->yellow->system->get("coreDebugMode")>=1 && !$rewrite) {
                    echo "YellowInstall::checkServerRewrite detected failed url:$url<br/>\n";
                }
            }
        }
        return $rewrite;
    }
    
    // Download available extension files
    public function downloadExtensionsAvailable($settings, $extensions) {
        $statusCode = 200;
        if ($this->yellow->extension->isExisting("update")) {
            $path = $this->yellow->system->get("coreWorkerDirectory");
            $extensionsNow = 0;
            $extensionsTotal = count($extensions);
            $curlHandle = curl_init();
            foreach ($extensions as $extension) {
                echo "\rDownloading available extensions ".$this->getProgressPercent(++$extensionsNow, $extensionsTotal, 5, 95)."%... ";
                $fileName = $path."install-".$this->yellow->lookup->normaliseName($extension, true, false, true).".bin";
                if (is_file($fileName)) continue;
                $url = $settings[$extension]->get("downloadUrl");
                curl_setopt($curlHandle, CURLOPT_URL, $this->yellow->extension->get("update")->getExtensionDownloadUrl($url));
                curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowInstall/".YellowInstall::VERSION).")";
                curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
                $fileData = curl_exec($curlHandle);
                $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
                $redirectUrl = ($statusCode>=300 && $statusCode<=399) ? curl_getinfo($curlHandle, CURLINFO_REDIRECT_URL) : "";
                if ($statusCode==0) {
                    $statusCode = 450;
                    $this->yellow->page->error($statusCode, "Can't connect to the update server!");
                }
                if ($statusCode!=450 && $statusCode!=200) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't download file '$url'!");
                }
                if ($statusCode==200 && !$this->yellow->toolbox->writeFile($fileName, $fileData)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
                }
                if ($this->yellow->system->get("coreDebugMode")>=2 && !is_string_empty($redirectUrl)) {
                    echo "YellowInstall::downloadExtensionsAvailable redirected to url:$redirectUrl<br/>\n";
                }
                if ($this->yellow->system->get("coreDebugMode")>=2) {
                    echo "YellowInstall::downloadExtensionsAvailable status:$statusCode url:$url<br/>\n";
                }
                if ($statusCode!=200) break;
            }
            curl_close($curlHandle);
            echo "\rDownloading available extensions 100%... done\n";
        }
        return $statusCode;
    }
    
    // Return available extensions required
    public function getAvailableExtensionsRequired($settings, $option) {
        $extensions = array();
        if ($option=="medium") {
            $text = "help highlight search toc";
            $extensions = array_unique(array_filter($this->yellow->toolbox->getTextArguments($text), "strlen"));
        } elseif ($option=="large") {
            foreach ($settings as $key=>$value) {
                if (preg_match("/language/i", $value->get("tag"))) continue;
                array_push($extensions, strtoloweru($key));
            }
        }
        return $extensions;
    }
    
    // Return language extensions required
    public function getLanguageExtensionsRequired($fileData, $option) {
        $extensions = array();
        $languages = array();
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                if (!is_string_empty($matches[1]) && !is_string_empty($matches[2]) && strposu($matches[1], "/")) {
                    $extension = basename($matches[1]);
                    $extension = $this->yellow->lookup->normaliseName($extension, true, true);
                    list($entry, $flags) = $this->yellow->toolbox->getTextList($matches[2], ",", 2);
                    $arguments = preg_split("/\s*,\s*/", trim($flags));
                    $language = array_pop($arguments);
                    if (preg_match("/^(.*)\.php$/", basename($entry))) {
                        $languages[$language] = $extension;
                    }
                }
            }
        }
        if ($option=="large") {
            foreach ($languages as $language=>$extension) {
                array_push($extensions, $extension);
            }
        } else {
            foreach ($this->getSystemLanguages("en, de, sv") as $language) {
                if (isset($languages[$language])) array_push($extensions, $languages[$language]);
            }
            $extensions = array_slice($extensions, 0, 3);
        }
        return $extensions;
    }
    
    // Return extensions installed
    public function getExtensionsCount() {
        $fileNameCurrent = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("updateInstalledFile");
        $fileData = $this->yellow->toolbox->readFile($fileNameCurrent);
        $settings = $this->yellow->toolbox->getTextSettings($fileData, "extension");
        return count($settings);
    }
    
    // Return system languages
    public function getSystemLanguages($languagesDefault) {
        $languages = array();
        foreach (preg_split("/\s*,\s*/", $this->yellow->toolbox->getServer("HTTP_ACCEPT_LANGUAGE")) as $string) {
            list($language, $dummy) = $this->yellow->toolbox->getTextList($string, ";", 2);
            if (!is_string_empty($language)) array_push($languages, $language);
        }
        foreach (preg_split("/\s*,\s*/", $languagesDefault) as $language) {
            if (!is_string_empty($language)) array_push($languages, $language);
        }
        return array_unique($languages);
    }
    
    // Return system settings
    public function getSystemSettings($skipInstallation) {
        $settings = array();
        foreach ($_REQUEST as $key=>$value) {
            if (!$this->yellow->system->isExisting($key)) continue;
            if ($key=="password" || $key=="status") continue;
            $settings[$key] = trim($value);
        }
        if ($this->yellow->system->get("sitename")=="Datenstrom Yellow") $settings["sitename"] = $this->yellow->toolbox->detectServerSitename();
        if ($this->yellow->system->get("generateStaticUrl")=="auto" && getenv("URL")!==false) $settings["generateStaticUrl"] = getenv("URL");
        if ($this->yellow->system->get("generateStaticUrl")=="auto" && $skipInstallation) $settings["generateStaticUrl"] = "http://localhost:8000/";
        if ($this->yellow->system->get("coreTimezone")=="UTC") $settings["coreTimezone"] = $this->yellow->toolbox->detectServerTimezone();
        if ($this->yellow->system->get("updateEventPending")=="none") {
            $settings["updateEventPending"] = "website/install";
        } else {
            $themeStandard = ",".$this->yellow->system->get("theme")."/install";
            $settings["updateEventPending"] = $this->yellow->system->get("updateEventPending").$themeStandard;
        }
        $settings["updateCurrentRelease"] = YellowCore::RELEASE;
        return $settings;
    }

    // Return raw data for install page
    public function getRawDataInstall() {
        $languages = $this->yellow->system->getAvailable("language");
        $language = $this->yellow->toolbox->detectBrowserLanguage($languages, $this->yellow->system->get("language"));
        $this->yellow->language->set($language);
        $rawData = "---\nTitle:".$this->yellow->language->getText("installTitle")."\nLanguage:$language\nNavigation:navigation\nHeader:none\nFooter:none\nSidebar:none\n---\n";
        $rawData .= "<form class=\"install-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
        $rawData .= "<p><label for=\"author\">".$this->yellow->language->getText("editSignupName")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"author\" id=\"author\" value=\"\"></p>\n";
        $rawData .= "<p><label for=\"email\">".$this->yellow->language->getText("editSignupEmail")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"email\" id=\"email\" value=\"\"></p>\n";
        $rawData .= "<p><label for=\"password\">".$this->yellow->language->getText("editSignupPassword")."</label><br /><input class=\"form-control\" type=\"password\" maxlength=\"64\" name=\"password\" id=\"password\" value=\"\"></p>\n";
        $rawData .= "<p>".$this->yellow->language->getText("installLanguage")."</p>\n<p>";
        foreach ($languages as $language) {
            $checked = $language==$this->yellow->language->language ? " checked=\"checked\"" : "";
            $rawData .= "<label for=\"{$language}-language\"><input type=\"radio\" name=\"language\" id=\"{$language}-language\" value=\"$language\"$checked> ".$this->yellow->language->getTextHtml("languageDescription", $language)."</label><br />";
        }
        $rawData .= "</p>\n";
        $rawData .= "<p>".$this->yellow->language->getText("installExtension")."</p>\n<p>";
        foreach (array("website", "wiki", "blog") as $extension) {
            $checked = $extension=="website" ? " checked=\"checked\"" : "";
            $rawData .= "<label for=\"{$extension}-extension\"><input type=\"radio\" name=\"extension\" id=\"{$extension}-extension\" value=\"$extension\"$checked> ".$this->yellow->language->getTextHtml("installExtension".ucfirst($extension))."</label><br />";
        }
        $rawData .= "</p>\n";
        $rawData .= "<input class=\"btn\" type=\"submit\" value=\"".$this->yellow->language->getText("installButton")."\" />\n";
        $rawData .= "<input type=\"hidden\" name=\"status\" value=\"install\" />\n";
        $rawData .= "</form>\n";
        return $rawData;
    }
    
    // Return progress in percent
    public function getProgressPercent($now, $total, $increments, $max) {
        $max = intval($max/$increments) * $increments;
        $percent = intval(($max/$total) * $now);
        if ($increments>1) $percent = intval($percent/$increments) * $increments;
        return min($max, $percent);
    }
    
    // Check if running built-in web server
    public function isServerBuiltin() {
        list($name) = $this->yellow->toolbox->detectServerInformation();
        return strtoloweru($name)=="built-in";
    }
}
