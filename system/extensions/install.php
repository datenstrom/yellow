<?php
// Install extension, https://github.com/datenstrom/yellow

class YellowInstall {
    const VERSION = "0.8.46";
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
        return $this->processCommandInstall();
    }
    
    // Process request to install website
    public function processRequestInstall($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName) || empty($fileName)) {
            $this->checkServerRequirements();
            $author = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $this->yellow->page->getRequest("author")));
            $email = trim($this->yellow->page->getRequest("email"));
            $password = trim($this->yellow->page->getRequest("password"));
            $language = trim($this->yellow->page->getRequest("language"));
            $extension = trim($this->yellow->page->getRequest("extension"));
            $status = trim($this->yellow->page->getRequest("status"));
            $statusCode = $this->updateLog();
            $statusCode = max($statusCode, $this->updateLanguages());
            $this->yellow->content->pages["root/"] = array();
            $this->yellow->page = new YellowPage($this->yellow);
            $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
            $this->yellow->page->parseData($this->getRawDataInstall(), false, $statusCode, $this->yellow->page->get("pageError"));
            if ($status=="install") $status = $this->updateExtension($extension)==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->updateUser($email, $password, $author, $language)==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->updateContent($language, "installHome", "/")==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->updateContent($language, "installDefault", "/shared/page-new-default")==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->updateContent($language, "installBlog", "/shared/page-new-blog")==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->updateContent($language, "installWiki", "/shared/page-new-wiki")==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->updateContent($language, "coreError404", "/shared/page-error-404")==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->updateSettings($language)==200 ? "ok" : "error";
            if ($status=="ok") $status = $this->removeInstall()==200 ? "done" : "error";
            if ($status=="done") {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, "/");
                $statusCode = $this->yellow->sendStatus(303, $location);
            } else {
                $statusCode = $this->yellow->sendPage();
            }
        }
        return $statusCode;
    }
    
    // Process command to install website
    public function processCommandInstall() {
        $statusCode = $this->updateLog();
        if ($statusCode==200) $statusCode = $this->updateLanguages();
        if ($statusCode==200) $statusCode = $this->updateSettings("en");
        if ($statusCode==200) $statusCode = $this->removeInstall();
        if ($statusCode==200) {
            $statusCode = 0;
        } else {
            echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Your website has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
        }
        return $statusCode;
    }
    
    // Update log
    public function updateLog() {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreLogFile");
        if (!is_file($fileName)) {
            list($name, $version, $os) = $this->yellow->toolbox->detectServerInformation();
            $product = "Datenstrom Yellow ".YellowCore::RELEASE;
            $this->yellow->log("info", "Install $product, PHP ".PHP_VERSION.", $name $version, $os");
            if (!is_file($fileName)) {
                $statusCode = 500;
                $this->yellow->page->error(500, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update languages
    public function updateLanguages() {
        $statusCode = 200;
        $path = $this->yellow->system->get("coreExtensionDirectory")."install-languages.zip";
        if (is_file($path) && $this->yellow->extension->isExisting("update")) {
            $zip = new ZipArchive();
            if ($zip->open($path)===true) {
                $pathBase = "";
                if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
                $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
                foreach ($this->getExtensionsRequired($fileData) as $extension) {
                    $fileDataPhp = $zip->getFromName($pathBase."$extension/$extension.php");
                    $fileDataTxt = $zip->getFromName($pathBase."$extension/$extension.txt");
                    $fileDataIni = $zip->getFromName($pathBase."$extension/extension.ini");
                    $statusCode = max($statusCode, $this->updateLanguageArchive($fileDataPhp, $fileDataTxt, $fileDataIni, $pathBase, "install"));
                }
                $this->yellow->language->load($this->yellow->system->get("coreExtensionDirectory"));
                $this->yellow->extension->load($this->yellow->system->get("coreExtensionDirectory"));
                $zip->close();
            } else {
                $statusCode = 500;
                $this->yellow->page->error(500, "Can't open file '$path'!");
            }
        }
        return $statusCode;
    }
    
    // Update language archive
    public function updateLanguageArchive($fileDataPhp, $fileDataTxt, $fileDataIni, $pathBase, $action) {
        $statusCode = 200;
        if ($this->yellow->extension->isExisting("update")) {
            $settings = $this->yellow->toolbox->getTextSettings($fileDataIni, "");
            $extension = lcfirst($settings->get("extension"));
            $version = $settings->get("version");
            $modified = strtotime($settings->get("published"));
            $fileNamePhp = $this->yellow->system->get("coreExtensionDirectory").$extension.".php";
            $fileNameTxt = $this->yellow->system->get("coreExtensionDirectory").$extension.".txt";
            if (!empty($extension) && !empty($version) && !is_file($fileNamePhp)) {
                $statusCode = $this->yellow->extension->get("update")->updateExtensionSettings($extension, $settings, $action);
                if ($statusCode==200) $statusCode = $this->yellow->extension->get("update")->updateExtensionFile(
                    $fileNamePhp, $fileDataPhp, $modified, 0, 0, "create", $extension);
                if ($statusCode==200) $statusCode = $this->yellow->extension->get("update")->updateExtensionFile(
                    $fileNameTxt, $fileDataTxt, $modified, 0, 0, "create", $extension);
                $this->yellow->log($statusCode==200 ? "info" : "error", ucfirst($action)." extension '".ucfirst($extension)." $version'");
            }
        }
        return $statusCode;
    }
    
    // Update extension
    public function updateExtension($extension) {
        $statusCode = 200;
        $path = $this->yellow->system->get("coreExtensionDirectory")."install-".$extension.".zip";
        if (is_file($path) && $this->yellow->extension->isExisting("update")) {
            $statusCode = $this->yellow->extension->get("update")->updateExtensionArchive($path, "install");
        }
        return $statusCode;
    }
    
    // Update user
    public function updateUser($email, $password, $name, $language) {
        $statusCode = 200;
        if (!empty($email) && !empty($password) && $this->yellow->extension->isExisting("edit")) {
            if (empty($name)) $name = $this->yellow->system->get("sitename");
            $fileNameUser = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreUserFile");
            $settings = array(
                "name" => $name,
                "language" => $language,
                "home" => "/",
                "access" => "create, edit, delete, restore, upload, configure, update",
                "hash" => $this->yellow->extension->get("edit")->response->createHash($password),
                "stamp" => $this->yellow->extension->get("edit")->response->createStamp(),
                "pending" => "none",
                "failed" => "0",
                "modified" => time(),
                "status" => "active");
            if (!$this->yellow->user->save($fileNameUser, $email, $settings)) {
                $statusCode = 500;
                $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            }
            $this->yellow->log($statusCode==200 ? "info" : "error", "Add user '".strtok($name, " ")."'");
        }
        return $statusCode;
    }
    
    // Update content
    public function updateContent($language, $name, $location) {
        $statusCode = 200;
        $fileName = $this->yellow->lookup->findFileFromLocation($location);
        $fileData = str_replace("\r\n", "\n", $this->yellow->toolbox->readFile($fileName));
        if (!empty($fileData) && $language!="en") {
            $settingsOld = "Title: ".$this->yellow->language->getText("{$name}Title", "en")."\n";
            $settingsNew = "Title: ".$this->yellow->language->getText("{$name}Title", $language)."\n";
            $fileData = str_replace($settingsOld, $settingsNew, $fileData);
            $settingsOld = "TitleContent: ".$this->yellow->language->getText("{$name}TitleContent", "en")."\n";
            $settingsNew = "TitleContent: ".$this->yellow->language->getText("{$name}TitleContent", $language)."\n";
            $fileData = str_replace($settingsOld, $settingsNew, $fileData);
            $textOld = str_replace("\\n", "\n", $this->yellow->language->getText("{$name}Text", "en"));
            $textNew = str_replace("\\n", "\n", $this->yellow->language->getText("{$name}Text", $language));
            $fileData = str_replace($textOld, $textNew, $fileData);
            if (!$this->yellow->toolbox->createFile($fileName, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update settings
    public function updateSettings($language) {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        if (!$this->yellow->system->save($fileName, $this->getSystemData())) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
        }
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreLanguageFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        if (strposu($fileData, "Language:")===false) {
            if (!empty($fileData)) $fileData .= "\n";
            $fileData .= "Language: $language\n";
            $fileData .= "media/images/photo.jpg: ".$this->yellow->language->getText("installExampleImage", $language)."\n";
            if (!$this->yellow->toolbox->createFile($fileName, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Remove files used by installation
    public function removeInstall() {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->system->get("coreExtensionDirectory");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            if (preg_match("/^install-(.*?)\./", basename($entry), $matches)) {
                if (!$this->yellow->toolbox->deleteFile($entry)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
                }
            }
        }
        $path = $this->yellow->system->get("coreExtensionDirectory")."install.php";
        if ($statusCode==200 && !$this->yellow->toolbox->deleteFile($path)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't delete file '$path'!");
        }
        if ($statusCode==200) unset($this->yellow->extension->data["install"]);
        return $statusCode;
    }
    
    // Check web server requirements
    public function checkServerRequirements() {
        list($name) = $this->yellow->toolbox->detectServerInformation();
        $troubleshooting = "<a href=\"".$this->yellow->getTroubleshootingUrl()."\">See troubleshooting</a>.";
        $this->checkServerConfiguration() || die("Datenstrom Yellow requires configuration file for $name! $troubleshooting\n");
        $this->checkServerRewrite() || die("Datenstrom Yellow requires rewrite support for $name! $troubleshooting\n");
        $this->checkServerWrite() || die("Datenstrom Yellow requires write access for $name! $troubleshooting\n");
        return true;
    }
    
    // Check web server configuration file
    public function checkServerConfiguration() {
        list($name) = $this->yellow->toolbox->detectServerInformation();
        return strtoloweru($name)!="apache" || is_file(".htaccess");
    }
    
    // Check web server rewrite support
    public function checkServerRewrite() {
        $curlHandle = curl_init();
        list($scheme, $address, $base) = $this->yellow->getRequestInformation();
        $location = $this->yellow->system->get("coreThemeLocation").$this->yellow->lookup->normaliseName($this->yellow->system->get("theme")).".css";
        $url = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowCore/".YellowCore::VERSION).")";
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
        $rawData = curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        return $statusCode==200;
    }
    
    // Check web server write access
    public function checkServerWrite() {
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        return $this->yellow->system->save($fileName, array());
    }

    // Detect browser languages
    public function detectBrowserLanguages($languagesDefault) {
        $languages = array();
        foreach (preg_split("/\s*,\s*/", $this->yellow->toolbox->getServer("HTTP_ACCEPT_LANGUAGE")) as $string) {
            list($language, $dummy) = $this->yellow->toolbox->getTextList($string, ";", 2);
            if (!empty($language)) array_push($languages, $language);
        }
        foreach (preg_split("/\s*,\s*/", $languagesDefault) as $language) {
            if (!empty($language)) array_push($languages, $language);
        }
        return array_unique($languages);
    }
    
    // Return system data including static information
    public function getSystemData() {
        $data = array();
        foreach ($_REQUEST as $key=>$value) {
            if (!$this->yellow->system->isExisting($key)) continue;
            if ($key=="password" || $key=="status") continue;
            $data[$key] = trim($value);
        }
        $data["coreServerTimezone"] = $this->yellow->toolbox->detectServerTimezone();
        $data["coreStaticUrl"] = $this->yellow->toolbox->detectServerUrl();
        if ($this->yellow->isCommandLine()) $data["coreStaticUrl"] = getenv("URL");
        if ($this->yellow->system->get("updateNotification")=="none") $data["updateNotification"] = "website/install";
        $data["updateCurrentRelease"] = YellowCore::RELEASE;
        return $data;
    }

    // Return raw data for install page
    public function getRawDataInstall() {
        $languages = $this->yellow->system->getValues("language");
        $language = $this->yellow->toolbox->detectBrowserLanguage($languages, $this->yellow->system->get("language"));
        $this->yellow->language->set($language);
        $rawData = "---\nTitle:".$this->yellow->language->getText("installTitle")."\nLanguage:$language\nNavigation:navigation\nHeader:none\nFooter:none\nSidebar:none\n---\n";
        $rawData .= "<form class=\"install-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
        $rawData .= "<p><label for=\"author\">".$this->yellow->language->getText("editSignupName")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"author\" id=\"author\" value=\"\"></p>\n";
        $rawData .= "<p><label for=\"email\">".$this->yellow->language->getText("editSignupEmail")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"email\" id=\"email\" value=\"\"></p>\n";
        $rawData .= "<p><label for=\"password\">".$this->yellow->language->getText("editSignupPassword")."</label><br /><input class=\"form-control\" type=\"password\" maxlength=\"64\" name=\"password\" id=\"password\" value=\"\"></p>\n";
        if (count($languages)>1) {
            $rawData .= "<p>";
            foreach ($languages as $language) {
                $checked = $language==$this->yellow->language->language ? " checked=\"checked\"" : "";
                $rawData .= "<label for=\"$language\"><input type=\"radio\" name=\"language\" id=\"$language\" value=\"$language\"$checked> ".$this->yellow->language->getTextHtml("languageDescription", $language)."</label><br />";
            }
            $rawData .= "</p>\n";
        }
        if (count($this->getExtensionsInstall())>1) {
            $rawData .= "<p>".$this->yellow->language->getText("installExtension")."<p>";
            foreach ($this->getExtensionsInstall() as $extension) {
                $checked = $extension=="website" ? " checked=\"checked\"" : "";
                $rawData .= "<label for=\"$extension\"><input type=\"radio\" name=\"extension\" id=\"$extension\" value=\"$extension\"$checked> ".$this->yellow->language->getTextHtml("installExtension".ucfirst($extension))."</label><br />";
            }
            $rawData .= "</p>\n";
        }
        $rawData .= "<input class=\"btn\" type=\"submit\" value=\"".$this->yellow->language->getText("editOkButton")."\" />\n";
        $rawData .= "<input type=\"hidden\" name=\"status\" value=\"install\" />\n";
        $rawData .= "</form>\n";
        return $rawData;
    }
    
    // Return extensions for install page
    public function getExtensionsInstall() {
        $extensions = array("website");
        $path = $this->yellow->system->get("coreExtensionDirectory");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false, false) as $entry) {
            if (preg_match("/^install-(.*?)\./", $entry, $matches) && $matches[1]!="languages") array_push($extensions, $matches[1]);
        }
        return $extensions;
    }
    
    // Return extensions required
    public function getExtensionsRequired($fileData) {
        $extensions = array();
        $languages = array();
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                    $extension = basename($matches[1]);
                    $extension = $this->yellow->lookup->normaliseName($extension, true, true);
                    list($entry, $flags) = $this->yellow->toolbox->getTextList($matches[2], ",", 2);
                    $arguments = preg_split("/\s*,\s*/", $flags);
                    $language = array_pop($arguments);
                    if (preg_match("/^(.*)\.php$/", basename($entry))) {
                        $languages[$language] = $extension;
                    }
                }
            }
        }
        foreach ($this->detectBrowserLanguages("en, de, fr") as $language) {
            if (isset($languages[$language])) array_push($extensions, $languages[$language]);
        }
        return array_slice($extensions, 0, 3);
    }
}
