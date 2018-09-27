<?php
// Setup plugin, https://github.com/datenstrom/yellow
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowSetup {
    const VERSION = "0.7.2";
    public $yellow;                 //access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->lookup->isContentFile($fileName) && $this->yellow->config->get("setupMode")) {
            $server = $this->yellow->toolbox->getServerVersion(true);
            $this->checkServerRewrite($scheme, $address, $base, $location, $fileName) || die("Datenstrom Yellow requires $server rewrite module!");
            $this->checkServerAccess() || die("Datenstrom Yellow requires $server read/write access!");
            $statusCode = $this->processRequestSetup($scheme, $address, $base, $location, $fileName);
        }
        return $statusCode;
    }
    
    // Handle command
    public function onCommand($args) {
        $statusCode = 0;
        if ($this->yellow->config->get("setupMode")) $statusCode = $this->processCommandSetup();
        return $statusCode;
    }
    
    // Process command to set up website
    public function processCommandSetup() {
        $statusCode = $this->updateLanguage();
        if ($statusCode==200) $statusCode = $this->updateFeature("none");
        if ($statusCode==200) $statusCode = $this->updateConfig(array("setupMode" => "0"));
        if ($statusCode==200) {
            $statusCode = 0;
        } else {
            echo "ERROR updating files: ".$this->yellow->page->get("pageError")."\n";
            echo "Your website has ".($statusCode!=200 ? "not " : "")."been updated: Please run command again\n";
        }
        return $statusCode;
    }
    
    // Process request to set up website
    public function processRequestSetup($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        $name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
        $email = trim($_REQUEST["email"]);
        $password = trim($_REQUEST["password"]);
        $language = trim($_REQUEST["language"]);
        $feature = trim($_REQUEST["feature"]);
        $status = trim($_REQUEST["status"]);
        $this->yellow->pages->pages["root/"] = array();
        $this->yellow->page = new YellowPage($this->yellow);
        $statusCode = $this->updateLanguage();
        $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $this->yellow->page->parseData($this->getRawDataSetup(), false, $statusCode, $this->yellow->page->get("pageError"));
        $this->yellow->page->parserSafeMode = false;
        if ($status=="setup") $status = $this->updateUser($email, $password, $name, $language)==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateFeature($feature)==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "Home", "/")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateContent($language, "About", "/about/")==200 ? "ok" : "error";
        if ($status=="ok") $status = $this->updateConfig($this->getConfigData()) ? "done" : "error";
        if ($status=="done") {
            $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
            $statusCode = $this->yellow->sendStatus(303, $location);
        } else {
            $statusCode = $this->yellow->sendPage();
        }
        return $statusCode;
    }
    
    // Update language
    public function updateLanguage() {
        $statusCode = 200;
        $path = $this->yellow->config->get("pluginDir")."setup-language.zip";
        if (is_file($path) && $this->yellow->plugins->isExisting("update")) {
            $zip = new ZipArchive();
            if ($zip->open($path)===true) {
                if (defined("DEBUG") && DEBUG>=2) echo "YellowSetup::updateLanguage file:$path<br/>\n";
                $languages = $this->getLanguageData("en, de, fr");
                if (preg_match("#^(.*\/).*?$#", $zip->getNameIndex(0), $matches)) $pathBase = $matches[1];
                $fileData = $zip->getFromName($pathBase.$this->yellow->config->get("updateInformationFile"));
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                        list($dummy, $entry) = explode("/", $matches[1], 2);
                        if (preg_match("/^language-(.*)\.txt$/", $entry, $tokens) && !is_null($languages[$tokens[1]])) {
                            $languages[$tokens[1]] = $entry;
                        }
                    }
                }
                $languages = array_slice(array_filter($languages, "strlen"), 0, 3);
                foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                    preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                    if (lcfirst($matches[1])=="plugin" || lcfirst($matches[1])=="theme") $software = $matches[2];
                    if (lcfirst($matches[1])=="published") $modified = strtotime($matches[2]);
                    if (!empty($matches[1]) && !empty($matches[2]) && strposu($matches[1], "/")) {
                        list($dummy, $entry) = explode("/", $matches[1], 2);
                        list($fileName) = explode(",", $matches[2], 2);
                        $fileData = $zip->getFromName($pathBase.$entry);
                        if (preg_match("/^language.php$/", $entry)) {
                            $statusCode = $this->yellow->plugins->get("update")->updateSoftwareFile($fileName, $fileData,
                                $modified, 0, 0, "create,update", false, $software);
                        }
                        if (preg_match("/^language-(.*)\.txt$/", $entry, $tokens) && !is_null($languages[$tokens[1]])) {
                            $statusCode = $this->yellow->plugins->get("update")->updateSoftwareFile($fileName, $fileData,
                                $modified, 0, 0, "create,update", false, $software);
                        }
                    }
                }
                $zip->close();
                if ($statusCode==200) {
                    $this->yellow->text->load($this->yellow->config->get("pluginDir").$this->yellow->config->get("languageFile"), "");
                }
                if ($statusCode==200 && !$this->yellow->toolbox->deleteFile($path)) {
                    $statusCode = 500;
                    $this->yellow->page->error($statusCode, "Can't delete file '$path'!");
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
        if (!empty($email) && !empty($password) && $this->yellow->plugins->isExisting("edit")) {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $status = $this->yellow->plugins->get("edit")->users->save($fileNameUser, $email, $password, $name, $language) ? "ok" : "error";
            if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        return $statusCode;
    }
    
    // Update feature
    public function updateFeature($feature) {
        $statusCode = 200;
        $path = $this->yellow->config->get("pluginDir");
        if (!empty($feature) && $this->yellow->plugins->isExisting("update")) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
                if (preg_match("/^setup-(.*?)\./", basename($entry), $matches)) {
                    if (strtoloweru($matches[1])==strtoloweru($feature)) {
                        $statusCode = $this->yellow->plugins->get("update")->updateSoftwareArchive($entry);
                        break;
                    }
                }
            }
        }
        if ($statusCode==200) {
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false) as $entry) {
                if (preg_match("/^setup-(.*?)\./", basename($entry), $matches)) {
                    if (!$this->yellow->toolbox->deleteFile($entry)) {
                        $statusCode = 500;
                        $this->yellow->page->error($statusCode, "Can't delete file '$entry'!");
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
            $titleOld = "Title: ".$this->yellow->text->getText("setup{$name}Title", "en");
            $titleNew = "Title: ".$this->yellow->text->getText("setup{$name}Title", $language);
            $textOld = strreplaceu("\\n", "\n", $this->yellow->text->getText("setup{$name}Text", "en"));
            $textNew = strreplaceu("\\n", "\n", $this->yellow->text->getText("setup{$name}Text", $language));
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
    
    // Update config
    public function updateConfig($config) {
        $statusCode = 200;
        $fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
        if (!$this->yellow->config->save($fileNameConfig, $config)) {
            $statusCode = 500;
            $this->yellow->page->error($statusCode, "Can't write file '$fileNameConfig'!");
        }
        return $statusCode;
    }
    
    // Check web server rewrite
    public function checkServerRewrite($scheme, $address, $base, $location, $fileName) {
        $curlHandle = curl_init();
        $location = $this->yellow->config->get("assetLocation").$this->yellow->config->get("theme").".css";
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
        $fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
        return $this->yellow->config->save($fileNameConfig, array());
    }

    // Return language data, detect browser languages
    public function getLanguageData($languagesDefault) {
        $data = array();
        if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
            foreach (preg_split("/\s*,\s*/", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $string) {
                list($language) = explode(";", $string);
                if (!empty($language)) $data[$language] = "";
            }
        }
        foreach (preg_split("/\s*,\s*/", $languagesDefault) as $language) {
            if (!empty($language)) $data[$language] = "";
        }
        return $data;
    }
    
    // Return configuration data, detect server URL
    public function getConfigData() {
        $data = array();
        foreach ($_REQUEST as $key=>$value) {
            if (!$this->yellow->config->isExisting($key)) continue;
            $data[$key] = trim($value);
        }
        if ($this->yellow->config->get("sitename")=="Datenstrom Yellow") $data["sitename"] = $_REQUEST["name"];
        $data["timezone"] = $this->yellow->toolbox->getTimezone();
        $data["staticUrl"] = $this->yellow->toolbox->getServerUrl();
        $data["setupMode"] = "0";
        return $data;
    }

    // Return raw data for setup page
    public function getRawDataSetup() {
        $language = $this->yellow->toolbox->detectBrowserLanguage($this->yellow->text->getLanguages(), $this->yellow->config->get("language"));
        $fileName = strreplaceu("(.*)", "setup", $this->yellow->config->get("configDir").$this->yellow->config->get("newFile"));
        $rawData = $this->yellow->toolbox->readFile($fileName);
        if (empty($rawData)) {
            $this->yellow->text->setLanguage($language);
            $rawData = "---\nTitle:".$this->yellow->text->get("setupTitle")."\nLanguage:$language\nNavigation:navigation\n---\n";
            $rawData .= "<form class=\"setup-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
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
            if (count($this->getFeaturesSetup())>1) {
                $rawData .= "<p>".$this->yellow->text->get("setupFeature")."<p>";
                foreach ($this->getFeaturesSetup() as $feature) {
                    $checked = $feature=="website" ? " checked=\"checked\"" : "";
                    $rawData .= "<label for=\"$feature\"><input type=\"radio\" name=\"feature\" id=\"$feature\" value=\"$feature\"$checked> ".ucfirst($feature)."</label><br />";
                }
                $rawData .= "</p>\n";
            }
            $rawData .= "<input class=\"btn\" type=\"submit\" value=\"".$this->yellow->text->get("editOkButton")."\" />\n";
            $rawData .= "<input type=\"hidden\" name=\"status\" value=\"setup\" />\n";
            $rawData .= "</form>\n";
        }
        return $rawData;
    }
    
    // Return features for setup page
    public function getFeaturesSetup() {
        $features = array("website");
        $path = $this->yellow->config->get("pluginDir");
        foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^.*\.zip$/", true, false, false) as $entry) {
            if (preg_match("/^setup-(.*?)\./", $entry, $matches) && $matches[1]!="language") array_push($features, $matches[1]);
        }
        return $features;
    }
}
    
$yellow->plugins->register("setup", "YellowSetup", YellowSetup::VERSION, 1);
