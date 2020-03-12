<?php
// Install extension, https://github.com/datenstrom/yellow
// Copyright (c) 2013-2020 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowInstall {
    const VERSION = "0.8.21";
    const TYPE = "feature";
    const PRIORITY = "1";
    public $yellow;                 //access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName) || empty($fileName)) {
            $statusCode = $this->processRequestInstall($scheme, $address, $base, $location, $fileName);
        }
        return $statusCode;
    }
    
    // Handle command
    public function onCommand($args) {
        return $this->processCommandInstall();
    }
    
    // Process request to install website
    public function processRequestInstall($scheme, $address, $base, $location, $fileName) {
        $this->checkServerRequirements();
        $author = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["author"]));
        $email = trim($_REQUEST["email"]);
        $password = trim($_REQUEST["password"]);
        $language = trim($_REQUEST["language"]);
        $extension = trim($_REQUEST["extension"]);
        $status = trim($_REQUEST["status"]);
        $statusCode = $this->updateLog();
        $statusCode = max($statusCode, $this->updateLanguage());
        $this->yellow->content->pages["root/"] = array();
        $this->yellow->page = new YellowPage($this->yellow);
        $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $this->yellow->page->parseData($this->getRawDataInstall(), false, $statusCode, $this->yellow->page->get("pageError"));
        $this->yellow->page->safeMode = false;
        if ($status=="install") $status = $this->updateExtension($extension)==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateUser($email, $password, $author, $language)==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "installHome", "/")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "installDefault", "/shared/page-new-default")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "installBlog", "/shared/page-new-blog")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "installWiki", "/shared/page-new-wiki")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "coreError404", "/shared/page-error-404")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateText($language)==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateSystem($this->getSystemData())==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->removeInstall()==200 ? "done" : "error";
        if ($status=="done") {
            $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
            $statusCode = $this->yellow->sendStatus(303, $location);
        } else {
            $statusCode = $this->yellow->sendPage();
        }
        return $statusCode;
    }
    
    // Process command to install website
    public function processCommandInstall() {
        $statusCode = $this->updateLog();
        if ($statusCode==200) $statusCode = $this->updateLanguage();
        if ($statusCode==200) $statusCode = $this->updateText("en");
        if ($statusCode==200) $statusCode = $this->updateSystem($this->getSystemData());
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
        $fileName = $this->yellow->system->get("coreExtensionDir").$this->yellow->system->get("coreLogFile");
        if (!is_file($fileName)) {
            $serverVersion = $this->yellow->toolbox->getServerVersion();
            $this->yellow->log("info", "Datenstrom Yellow ".YellowCore::VERSION.", PHP ".PHP_VERSION.", $serverVersion");
            if (!is_file($fileName)) {
                $statusCode = 500;
                $this->yellow->page->error(500, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update language
    public function updateLanguage() {
        $statusCode = 200;
        $path = $this->yellow->system->get("coreExtensionDir")."install-languages.zip";
        if (is_file($path) && $this->yellow->extensions->isExisting("update")) {
            $zip = new ZipArchive();
            if ($zip->open($path)===true) {
                $languages = $this->detectBrowserLanguages("en, de, fr");
                $languagesFound = array();
                foreach ($languages as $language) $languagesFound[$language] = "";
                if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
                $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                        list($dummy, $entry) = explode(",", $matches[2], 3);
                        $flags = explode(",", $matches[2]);
                        $language = array_pop($flags);
                        if (preg_match("/^(.*)\.php$/", basename($entry), $tokens) && in_array($language, $languages)) {
                            $languagesFound[$language] = $tokens[1];
                        }
                    }
                }
                $languagesFound = array_slice(array_filter($languagesFound, "strlen"), 0, 3);
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (lcfirst($matches[1])=="extension") $extension = lcfirst($matches[2]);
                    if (lcfirst($matches[1])=="published") $modified = strtotime($matches[2]);
                    if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                        $fileName = $matches[1];
                        list($dummy, $entry) = explode(",", $matches[2], 3);
                        $fileData = $zip->getFromName($pathBase.basename($entry));
                        if (preg_match("/^(.*).php$/", basename($entry), $tokens) && in_array($tokens[1], $languagesFound) && !is_file($fileName)) {
                            $statusCode = $this->yellow->extensions->get("update")->updateExtensionFile($fileName, $fileData, $modified, 0, 0, "create", false, $extension);
                        }
                        if (preg_match("/^(.*)-language\.txt$/", basename($entry), $tokens) && in_array($tokens[1], $languagesFound) && !is_file($fileName)) {
                            $statusCode = $this->yellow->extensions->get("update")->updateExtensionFile($fileName, $fileData, $modified, 0, 0, "create", false, $extension);
                            $this->yellow->log($statusCode==200 ? "info" : "error", "Install language '".ucfirst($tokens[1])."'");
                        }
                        if ($statusCode!=200) break;
                    }
                }
                $zip->close();
                if ($statusCode==200) {
                    $this->yellow->text->load($this->yellow->system->get("coreExtensionDir").$this->yellow->system->get("coreLanguageFile"), "");
                }
            } else {
                $statusCode = 500;
                $this->yellow->page->error(500, "Can't open file '$path'!");
            }
        }
        return $statusCode;
    }
    
    // Update extension
    public function updateExtension($extension) {
        $statusCode = 200;
        $path = $this->yellow->system->get("coreExtensionDir");
        if (!empty($extension) && $this->yellow->extensions->isExisting("update")) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
                if (preg_match("/^install-(.*?)\./", basename($entry), $matches)) {
                    if (strtoloweru($matches[1])==strtoloweru($extension)) {
                        $statusCode = $this->yellow->extensions->get("update")->updateExtensionArchive($entry, "install");
                        break;
                    }
                }
            }
        }
        return $statusCode;
    }
    
    // Update user
    public function updateUser($email, $password, $name, $language) {
        $statusCode = 200;
        if (!empty($email) && !empty($password) && $this->yellow->extensions->isExisting("edit")) {
            if (empty($name)) $name = $this->yellow->system->get("sitename");
            $fileNameUser = $this->yellow->system->get("coreSettingDir").$this->yellow->system->get("editUserFile");
            $settings = array(
                "name" => $name,
                "language" => $language,
                "home" => "/",
                "access" => "create, edit, delete, upload, system, update",
                "hash" => $this->yellow->extensions->get("edit")->users->createHash($password),
                "stamp" => $this->yellow->extensions->get("edit")->users->createStamp(),
                "pending" => "none",
                "failed" => "0",
                "modified" => time(),
                "status" => "active");
            if (!$this->yellow->extensions->get("edit")->users->save($fileNameUser, $email, $settings)) {
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
        $fileData = strreplaceu("\r\n", "\n", $this->yellow->toolbox->readFile($fileName));
        if (!empty($fileData) && $language!="en") {
            $titleOld = "Title: ".$this->yellow->text->getText("{$name}Title", "en");
            $titleNew = "Title: ".$this->yellow->text->getText("{$name}Title", $language);
            $textOld = strreplaceu("\\n", "\n", $this->yellow->text->getText("{$name}Text", "en"));
            $textNew = strreplaceu("\\n", "\n", $this->yellow->text->getText("{$name}Text", $language));
            $fileData = strreplaceu($titleOld, $titleNew, $fileData);
            $fileData = strreplaceu($textOld, $textNew, $fileData);
            if (!$this->yellow->toolbox->createFile($fileName, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update text settings
    public function updateText($language) {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("coreSettingDir").$this->yellow->system->get("coreTextFile");
        $fileData = $this->yellow->toolbox->readFile($fileName);
        if (count($this->yellow->toolbox->getTextLines($fileData))<4) {
            $fileData .= "Language: $language\n";
            $fileData .= "CoreDateFormatMedium: ".$this->yellow->text->getText("coreDateFormatMedium", $language)."\n";
            $fileData .= "picture.jpg: ".$this->yellow->text->getText("installExampleImage", $language)."\n";
            if (!$this->yellow->toolbox->createFile($fileName, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update system settings
    public function updateSystem($settings) {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("coreSettingDir").$this->yellow->system->get("coreSystemFile");
        if (!$this->yellow->system->save($fileName, $settings)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Remove files used by installation
    public function removeInstall() {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->system->get("coreExtensionDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            if (preg_match("/^install-(.*?)\./", basename($entry), $matches)) {
                if (!$this->yellow->toolbox->deleteFile($entry)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
                }
            }
        }
        $path = $this->yellow->system->get("coreExtensionDir")."install.php";
        if ($statusCode==200 && !$this->yellow->toolbox->deleteFile($path)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't delete file '$path'!");
        }
        if ($statusCode==200) unset($this->yellow->extensions->extensions["install"]);
        return $statusCode;
    }
    
    // Check web server requirements
    public function checkServerRequirements() {
        $serverVersion = $this->yellow->toolbox->getServerVersion(true);
        $troubleshooting = "<a href=\"https://datenstrom.se/yellow/help/troubleshooting\">See troubleshooting</a>.";
        $this->checkServerConfiguration() || die("Datenstrom Yellow requires a configuration file for $serverVersion! $troubleshooting\n");
        $this->checkServerRewrite() || die("Datenstrom Yellow requires rewrite support for $serverVersion! $troubleshooting\n");
        $this->checkServerWrite() || die("Datenstrom Yellow requires write access for $serverVersion! $troubleshooting\n");
        return true;
    }
    
    // Check web server configuration file
    public function checkServerConfiguration() {
        $serverVersion = $this->yellow->toolbox->getServerVersion(true);
        return strtoloweru($serverVersion)!="apache" || is_file(".htaccess");
    }
    
    // Check web server rewrite support
    public function checkServerRewrite() {
        $curlHandle = curl_init();
        list($scheme, $address, $base) = $this->yellow->getRequestInformation();
        $location = $this->yellow->system->get("coreResourceLocation").$this->yellow->lookup->normaliseName($this->yellow->system->get("theme")).".css";
        $url = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowCore/".YellowCore::VERSION).")";
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
        $rawData = curl_exec($curlHandle);
        $statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        return $statusCode==200;
    }
    
    // Check web server write access
    public function checkServerWrite() {
        $fileName = $this->yellow->system->get("coreSettingDir").$this->yellow->system->get("coreSystemFile");
        return $this->yellow->system->save($fileName, array());
    }

    // Detect web browser languages
    public function detectBrowserLanguages($languagesDefault) {
        $languages = array();
        if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
            foreach (preg_split("/\s*,\s*/", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $string) {
                list($language) = explode(";", $string);
                if (!empty($language)) array_push($languages, $language);
            }
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
        $data["coreStaticUrl"] = $this->yellow->toolbox->getServerUrl();
        $data["coreServerTimezone"] = $this->yellow->toolbox->getTimezone();
        if ($this->yellow->isCommandLine()) $data["coreStaticUrl"] = getenv("URL");
        return $data;
    }

    // Return raw data for install page
    public function getRawDataInstall() {
        $language = $this->yellow->toolbox->detectBrowserLanguage($this->yellow->text->getLanguages(), $this->yellow->system->get("language"));
        $this->yellow->text->setLanguage($language);
        $rawData = "---\nTitle:".$this->yellow->text->get("installTitle")."\nLanguage:$language\nNavigation:navigation\nHeader:none\nFooter:none\nSidebar:none\n---\n";
        $rawData .= "<form class=\"install-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
        $rawData .= "<p><label for=\"author\">".$this->yellow->text->get("editSignupName")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"author\" id=\"author\" value=\"\"></p>\n";
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
        if (count($this->getExtensionsInstall())>1) {
            $rawData .= "<p>".$this->yellow->text->get("installExtension")."<p>";
            foreach ($this->getExtensionsInstall() as $extension) {
                $checked = $extension=="website" ? " checked=\"checked\"" : "";
                $rawData .= "<label for=\"$extension\"><input type=\"radio\" name=\"extension\" id=\"$extension\" value=\"$extension\"$checked> ".$this->yellow->text->getHtml("installExtension".ucfirst($extension))."</label><br />";
            }
            $rawData .= "</p>\n";
        }
        $rawData .= "<input class=\"btn\" type=\"submit\" value=\"".$this->yellow->text->get("editOkButton")."\" />\n";
        $rawData .= "<input type=\"hidden\" name=\"status\" value=\"install\" />\n";
        $rawData .= "</form>\n";
        return $rawData;
    }
    
    // Return extensions for install page
    public function getExtensionsInstall() {
        $extensions = array("website");
        $path = $this->yellow->system->get("coreExtensionDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false, false) as $entry) {
            if (preg_match("/^install-(.*?)\./", $entry, $matches) && $matches[1]!="languages") array_push($extensions, $matches[1]);
        }
        return $extensions;
    }
}
