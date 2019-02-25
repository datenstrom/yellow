<?php
// Install extension, https://github.com/datenstrom/yellow
// Copyright (c) 2013-2019 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowInstall {
    const VERSION = "0.8.2";
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
        if ($this->yellow->lookup->isContentFile($fileName)) {
            $server = $this->yellow->toolbox->getServerVersion(true);
            $this->checkServerRewrite($scheme, $address, $base, $location, $fileName) || die("Datenstrom Yellow requires $server rewrite module!");
            $this->checkServerAccess() || die("Datenstrom Yellow requires $server read/write access!");
            $statusCode = $this->processRequestInstall($scheme, $address, $base, $location, $fileName);
        }
        return $statusCode;
    }
    
    // Handle command
    public function onCommand($args) {
        return $this->processCommandInstall();
    }
    
    // Process command to install website
    public function processCommandInstall() {
        $statusCode = $this->updateLanguages();
        if ($statusCode==200) $statusCode = $this->updateSettings($this->getSystemData());
        if ($statusCode==200) $statusCode = $this->removeFiles();
        if ($statusCode==200) {
            $statusCode = 0;
        } else {
            echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Your website has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
        }
        return $statusCode;
    }
    
    // Process request to install website
    public function processRequestInstall($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        $name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
        $email = trim($_REQUEST["email"]);
        $password = trim($_REQUEST["password"]);
        $language = trim($_REQUEST["language"]);
        $extension = trim($_REQUEST["extension"]);
        $status = trim($_REQUEST["status"]);
        $this->yellow->content->pages["root/"] = array();
        $this->yellow->page = new YellowPage($this->yellow);
        $statusCode = $this->updateLanguages();
        $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $this->yellow->page->parseData($this->getRawDataInstall(), false, $statusCode, $this->yellow->page->get("pageError"));
        $this->yellow->page->safeMode = false;
        if ($status=="install") $status = $this->updateUser($email, $password, $name, $language)==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateExtension($extension)==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "Home", "/")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "About", "/about/")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "Footer", "/shared/footer")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateSettings($this->getSystemData()) ? "ok" : "error";
        if ($status=="ok") $status = $this->removeFiles() ? "done" : "error";
        if ($status=="done") {
            $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
            $statusCode = $this->yellow->sendStatus(303, $location);
        } else {
            $statusCode = $this->yellow->sendPage();
        }
        return $statusCode;
    }
    
    // Update languages
    public function updateLanguages() {
        $statusCode = 200;
        $path = $this->yellow->system->get("extensionDir")."install-languages.zip";
        if (is_file($path) && $this->yellow->extensions->isExisting("update")) {
            $zip = new ZipArchive();
            if ($zip->open($path)===true) {
                if (defined("DEBUG") && DEBUG>=2) echo "YellowInstall::updateLanguages file:$path<br/>\n";
                $languages = $this->detectBrowserLanguages("en, de, fr");
                $languagesFound = array();
                foreach ($languages as $language) $languagesFound[$language] = "";
                if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
                $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateExtensionFile"));
                if (empty($fileData)) $fileData = $zip->getFromName($pathBase.$this->yellow->system->get("updateInformationFile")); //TODO: remove later, for backwards compatibility
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                        list($dummy, $entry) = explode("/", $matches[1], 2);
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
                        list($dummy, $entry) = explode("/", $matches[1], 2);
                        list($fileName) = explode(",", $matches[2], 2);
                        $fileData = $zip->getFromName($pathBase.basename($entry));
                        if (preg_match("/^(.*).php$/", basename($entry), $tokens) && in_array($tokens[1], $languagesFound)) {
                            $statusCode = $this->yellow->extensions->get("update")->updateExtensionFile($fileName, $fileData,
                                $modified, 0, 0, "create,update", false, $extension);
                        }
                        if (preg_match("/^(.*)-language\.txt$/", basename($entry), $tokens) && in_array($tokens[1], $languagesFound)) {
                            $statusCode = $this->yellow->extensions->get("update")->updateExtensionFile($fileName, $fileData,
                                $modified, 0, 0, "create,update", false, $extension);
                        }
                    }
                }
                $zip->close();
                if ($statusCode==200) {
                    $this->yellow->text->load($this->yellow->system->get("extensionDir").$this->yellow->system->get("languageFile"), "");
                }
            } else {
                $statusCode = 500;
                $this->yellow->page->error(500, "Can't open file '$path'!");
            }
        }
        return $statusCode;
    }
    
    // Update user
    public function updateUser($email, $password, $name, $language) {
        $statusCode = 200;
        if (!empty($email) && !empty($password) && $this->yellow->extensions->isExisting("edit")) {
            $fileNameUser = $this->yellow->system->get("settingDir").$this->yellow->system->get("editUserFile");
            $status = $this->yellow->extensions->get("edit")->users->save($fileNameUser, $email, $password, $name, $language) ? "ok" : "error";
            if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        return $statusCode;
    }
    
    // Update extension
    public function updateExtension($extension) {
        $statusCode = 200;
        $path = $this->yellow->system->get("extensionDir");
        if (!empty($extension) && $this->yellow->extensions->isExisting("update")) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
                if (preg_match("/^install-(.*?)\./", basename($entry), $matches)) {
                    if (strtoloweru($matches[1])==strtoloweru($extension)) {
                        $statusCode = $this->yellow->extensions->get("update")->updateExtensionArchive($entry);
                        break;
                    }
                }
            }
        }
        return $statusCode;
    }
    
    // Update content
    public function updateContent($language, $name, $location) {
        $statusCode = 200;
        if ($language!="en") {
            $titleOld = "Title: ".$this->yellow->text->getText("install{$name}Title", "en");
            $titleNew = "Title: ".$this->yellow->text->getText("install{$name}Title", $language);
            $textOld = strreplaceu("\\n", "\n", $this->yellow->text->getText("install{$name}Text", "en"));
            $textNew = strreplaceu("\\n", "\n", $this->yellow->text->getText("install{$name}Text", $language));
            $fileName = $this->yellow->lookup->findFileFromLocation($location);
            $fileData = strreplaceu("\r\n", "\n", $this->yellow->toolbox->readFile($fileName));
            $fileData = strreplaceu($titleOld, $titleNew, $fileData);
            $fileData = strreplaceu($textOld, $textNew, $fileData);
            if (!$this->yellow->toolbox->createFile($fileName, $fileData)) {
                $statusCode = 500;
                $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
            }
        }
        return $statusCode;
    }
    
    // Update settings
    public function updateSettings($settings) {
        $statusCode = 200;
        $fileName = $this->yellow->system->get("settingDir").$this->yellow->system->get("systemFile");
        if (!$this->yellow->system->save($fileName, $settings)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't write file '$fileName'!");
        }
        return $statusCode;
    }
    
    // Remove files used by installation
    public function removeFiles() {
        $statusCode = 200;
        if (function_exists("opcache_reset")) opcache_reset();
        $path = $this->yellow->system->get("extensionDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
            if (preg_match("/^install-(.*?)\./", basename($entry), $matches)) {
                if (!$this->yellow->toolbox->deleteFile($entry)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
                }
            }
        }
        $path = $this->yellow->system->get("extensionDir")."install.php";
        if ($statusCode==200 && !$this->yellow->toolbox->deleteFile($path)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't delete file '$path'!");
        }
        if ($statusCode==200) unset($this->yellow->extensions->extensions["install"]);
        return $statusCode;
    }
    
    // Check web server rewrite
    public function checkServerRewrite($scheme, $address, $base, $location, $fileName) {
        $curlHandle = curl_init();
        $location = $this->yellow->system->get("resourceLocation").$this->yellow->lookup->normaliseName($this->yellow->system->get("theme")).".css";
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
    
    // Check web server read/write access
    public function checkServerAccess() {
        $fileName = $this->yellow->system->get("settingDir").$this->yellow->system->get("systemFile");
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
    
    // Return system data, detect server URL
    public function getSystemData() {
        $data = array();
        foreach ($_REQUEST as $key=>$value) {
            if (!$this->yellow->system->isExisting($key)) continue;
            $data[$key] = trim($value);
        }
        $data["timezone"] = $this->yellow->toolbox->getTimezone();
        $data["staticUrl"] = $this->yellow->toolbox->getServerUrl();
        if ($this->yellow->isCommandLine()) $data["staticUrl"] = getenv("URL");
        return $data;
    }

    // Return raw data for install page
    public function getRawDataInstall() {
        $language = $this->yellow->toolbox->detectBrowserLanguage($this->yellow->text->getLanguages(), $this->yellow->system->get("language"));
        $this->yellow->text->setLanguage($language);
        $rawData = "---\nTitle:".$this->yellow->text->get("installTitle")."\nLanguage:$language\nNavigation:navigation\n---\n";
        $rawData .= "<form class=\"install-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
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
        if (count($this->getExtensionsInstall())>1) {
            $rawData .= "<p>".$this->yellow->text->get("installExtension")."<p>";
            foreach ($this->getExtensionsInstall() as $extension) {
                $checked = $extension=="website" ? " checked=\"checked\"" : "";
                $rawData .= "<label for=\"$extension\"><input type=\"radio\" name=\"extension\" id=\"$extension\" value=\"$extension\"$checked> ".ucfirst($extension)."</label><br />";
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
        $path = $this->yellow->system->get("extensionDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false, false) as $entry) {
            if (preg_match("/^install-(.*?)\./", $entry, $matches) && $matches[1]!="languages") array_push($extensions, $matches[1]);
        }
        return $extensions;
    }
}
