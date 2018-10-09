<?php
// Edit plugin, https://github.com/datenstrom/yellow-plugins/tree/master/edit
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowEdit {
    const VERSION = "0.7.31";
    public $yellow;         //access to API
    public $response;       //web response
    public $users;          //user accounts
    public $merge;          //text merge

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->response = new YellowResponse($yellow);
        $this->users = new YellowUsers($yellow);
        $this->merge = new YellowMerge($yellow);
        $this->yellow->config->setDefault("editLocation", "/edit/");
        $this->yellow->config->setDefault("editUploadNewLocation", "/media/@group/@filename");
        $this->yellow->config->setDefault("editUploadExtensions", ".gif, .jpg, .pdf, .png, .svg, .tgz, .zip");
        $this->yellow->config->setDefault("editKeyboardShortcuts", "ctrl+b bold, ctrl+i italic, ctrl+e code, ctrl+k link, ctrl+s save, ctrl+shift+p preview");
        $this->yellow->config->setDefault("editToolbarButtons", "auto");
        $this->yellow->config->setDefault("editEndOfLine", "auto");
        $this->yellow->config->setDefault("editUserFile", "user.ini");
        $this->yellow->config->setDefault("editUserPasswordMinLength", "8");
        $this->yellow->config->setDefault("editUserHashAlgorithm", "bcrypt");
        $this->yellow->config->setDefault("editUserHashCost", "10");
        $this->yellow->config->setDefault("editUserHome", "/");
        $this->yellow->config->setDefault("editLoginRestrictions", "0");
        $this->yellow->config->setDefault("editLoginSessionTimeout", "2592000");
        $this->yellow->config->setDefault("editBruteForceProtection", "25");
        $this->users->load($this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile"));
    }

    // Handle startup
    public function onStartup($update) {
        if ($update) {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $fileData = $this->yellow->toolbox->readFile($fileNameUser);
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
                if (!empty($matches[1]) && !empty($matches[2]) && $matches[1][0]!="#") {
                    list($hash, $name, $language, $status, $stamp, $modified, $errors, $pending, $home) = explode(",", $matches[2]);
                    if ($errors=="none") { $home=$pending; $pending=$errors; $errors=$modified; $modified=$stamp; $stamp=""; } //TODO: remove later
                    if (strlenb($stamp)!=20) $stamp=$this->users->createStamp(); //TODO: remove later, converts old file format
                    if ($status!="active" && $status!="inactive") {
                        unset($this->users->users[$matches[1]]);
                        continue;
                    }
                    $pending = "none";
                    $this->users->set($matches[1], $hash, $name, $language, $status, $stamp, $modified, $errors, $pending, $home);
                    $fileDataNew .= "$matches[1]: $hash,$name,$language,$status,$stamp,$modified,$errors,$pending,$home\n";
                } else {
                    $fileDataNew .= $line;
                }
            }
            if ($fileData!=$fileDataNew) $this->yellow->toolbox->createFile($fileNameUser, $fileDataNew);
        }
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->checkRequest($location)) {
            $scheme = $this->yellow->config->get("serverScheme");
            $address = $this->yellow->config->get("serverAddress");
            $base = rtrim($this->yellow->config->get("serverBase").$this->yellow->config->get("editLocation"), "/");
            list($scheme, $address, $base, $location, $fileName) = $this->yellow->getRequestInformation($scheme, $address, $base);
            $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
            $statusCode = $this->processRequest($scheme, $address, $base, $location, $fileName);
        }
        return $statusCode;
    }
    
    // Handle page meta data
    public function onParseMeta($page) {
        if ($page==$this->yellow->page && $this->response->isActive()) {
            if ($this->response->isUser()) {
                if (empty($this->response->rawDataSource)) $this->response->rawDataSource = $page->rawData;
                if (empty($this->response->rawDataEdit)) $this->response->rawDataEdit = $page->rawData;
                if (empty($this->response->rawDataEndOfLine)) $this->response->rawDataEndOfLine = $this->response->getEndOfLine($page->rawData);
                if ($page->statusCode==434) $this->response->rawDataEdit = $this->response->getRawDataNew($page->location);
            }
            if (empty($this->response->language)) $this->response->language = $page->get("language");
            if (empty($this->response->action)) $this->response->action = $this->response->isUser() ? "none" : "login";
            if (empty($this->response->status)) $this->response->status = "none";
            if ($this->response->status=="error") $this->response->action = "error";
        }
    }
    
    // Handle page content of custom block
    public function onParseContentBlock($page, $name, $text, $shortcut) {
        $output = null;
        if ($name=="edit" && $shortcut) {
            $editText = "$name $text";
            if (substru($text, 0, 2)=="- ") $editText = trim(substru($text, 2));
            $output = "<a href=\"".$page->get("pageEdit")."\">".htmlspecialchars($editText)."</a>";
        }
        return $output;
    }
    
    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header" && $this->response->isActive()) {
            $pluginLocation = $this->yellow->config->get("serverBase").$this->yellow->config->get("pluginLocation");
            $output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" data-bundle=\"none\" href=\"{$pluginLocation}edit.css\" />\n";
            $output .= "<script type=\"text/javascript\" data-bundle=\"none\" src=\"{$pluginLocation}edit.js\"></script>\n";
            $output .= "<script type=\"text/javascript\">\n";
            $output .= "// <![CDATA[\n";
            $output .= "yellow.page = ".json_encode($this->response->getPageData()).";\n";
            $output .= "yellow.config = ".json_encode($this->response->getConfigData()).";\n";
            $output .= "yellow.text = ".json_encode($this->response->getTextData()).";\n";
            $output .= "// ]]>\n";
            $output .= "</script>\n";
        }
        return $output;
    }
    
    // Handle command
    public function onCommand($args) {
        list($command) = $args;
        switch ($command) {
            case "user":    $statusCode = $this->processCommandUser($args); break;
            default:        $statusCode = 0;
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        return "user [option email password name]\n";
    }

    // Process command to update user account
    public function processCommandUser($args) {
        list($command, $option) = $args;
        switch ($option) {
            case "":        $statusCode = $this->userShow($args); break;
            case "add":     $statusCode = $this->userAdd($args); break;
            case "change":  $statusCode = $this->userChange($args); break;
            case "remove":  $statusCode = $this->userRemove($args); break;
            default:        $statusCode = 400; echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Show user accounts
    public function userShow($args) {
        list($command) = $args;
        foreach ($this->users->getData() as $line) {
            echo "$line\n";
        }
        if (!$this->users->getNumber()) echo "Yellow $command: No user accounts\n";
        return 200;
    }
    
    // Add user account
    public function userAdd($args) {
        $status = "ok";
        list($command, $option, $email, $password, $name) = $args;
        if (empty($email) || empty($password)) $status = $this->response->status = "incomplete";
        if ($status=="ok") $status = $this->getUserAccount($email, $password, "add");
        if ($status=="ok" && $this->users->isTaken($email)) $status = "taken";
        switch ($status) {
            case "incomplete":  echo "ERROR updating configuration: Please enter email and password!\n"; break;
            case "invalid":     echo "ERROR updating configuration: Please enter a valid email!\n"; break;
            case "taken":       echo "ERROR updating configuration: Please enter a different email!\n"; break;
            case "weak":        echo "ERROR updating configuration: Please enter a different password!\n"; break;
        }
        if ($status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $status = $this->users->save($fileNameUser, $email, $password, $name, "", "active") ? "ok" : "error";
            if ($status=="error") echo "ERROR updating configuration: Can't write file '$fileNameUser'!\n";
        }
        if ($status=="ok") {
            $algorithm = $this->yellow->config->get("editUserHashAlgorithm");
            $status = substru($this->users->getHash($email), 0, 10)!="error-hash" ? "ok" : "error";
            if ($status=="error") echo "ERROR updating configuration: Hash algorithm '$algorithm' not supported!\n";
        }
        $statusCode = $status=="ok" ? 200 : 500;
        echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "")."added\n";
        return $statusCode;
    }
    
    // Change user account
    public function userChange($args) {
        $status = "ok";
        list($command, $option, $email, $password, $name) = $args;
        if (empty($email)) $status = $this->response->status = "invalid";
        if ($status=="ok") $status = $this->getUserAccount($email, $password, "change");
        if ($status=="ok" && !$this->users->isExisting($email)) $status = "unknown";
        switch ($status) {
            case "invalid": echo "ERROR updating configuration: Please enter a valid email!\n"; break;
            case "unknown": echo "ERROR updating configuration: Can't find email '$email'!\n"; break;
            case "weak":    echo "ERROR updating configuration: Please enter a different password!\n"; break;
        }
        if ($status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $status = $this->users->save($fileNameUser, $email, $password, $name) ? "ok" : "error";
            if ($status=="error") echo "ERROR updating configuration: Can't write file '$fileNameUser'!\n";
        }
        $statusCode = $status=="ok" ? 200 : 500;
        echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "")."changed\n";
        return $statusCode;
    }

    // Remove user account
    public function userRemove($args) {
        $status = "ok";
        list($command, $option, $email) = $args;
        if (empty($email)) $status = $this->response->status = "invalid";
        if ($status=="ok") $status = $this->getUserAccount($email, "", "remove");
        if ($status=="ok" && !$this->users->isExisting($email)) $status = "unknown";
        switch ($status) {
            case "invalid": echo "ERROR updating configuration: Please enter a valid email!\n"; break;
            case "unknown": echo "ERROR updating configuration: Can't find email '$email'!\n"; break;
        }
        if ($status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $status = $this->users->remove($fileNameUser, $email) ? "ok" : "error";
            if ($status=="error") echo "ERROR updating configuration: Can't write file '$fileNameUser'!\n";
        }
        $statusCode = $status=="ok" ? 200 : 500;
        echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "")."removed\n";
        return $statusCode;
    }
    
    // Process request
    public function processRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->checkUserAuth($scheme, $address, $base, $location, $fileName)) {
            switch ($_REQUEST["action"]) {
                case "":            $statusCode = $this->processRequestShow($scheme, $address, $base, $location, $fileName); break;
                case "login":       $statusCode = $this->processRequestLogin($scheme, $address, $base, $location, $fileName); break;
                case "logout":      $statusCode = $this->processRequestLogout($scheme, $address, $base, $location, $fileName); break;
                case "settings":    $statusCode = $this->processRequestSettings($scheme, $address, $base, $location, $fileName); break;
                case "version":     $statusCode = $this->processRequestVersion($scheme, $address, $base, $location, $fileName); break;
                case "update":      $statusCode = $this->processRequestUpdate($scheme, $address, $base, $location, $fileName); break;
                case "quit":        $statusCode = $this->processRequestQuit($scheme, $address, $base, $location, $fileName); break;
                case "create":      $statusCode = $this->processRequestCreate($scheme, $address, $base, $location, $fileName); break;
                case "edit":        $statusCode = $this->processRequestEdit($scheme, $address, $base, $location, $fileName); break;
                case "delete":      $statusCode = $this->processRequestDelete($scheme, $address, $base, $location, $fileName); break;
                case "preview":     $statusCode = $this->processRequestPreview($scheme, $address, $base, $location, $fileName); break;
                case "upload":      $statusCode = $this->processRequestUpload($scheme, $address, $base, $location, $fileName); break;
            }
        } elseif ($this->checkUserUnauth($scheme, $address, $base, $location, $fileName)) {
            $this->yellow->lookup->requestHandler = "core";
            switch ($_REQUEST["action"]) {
                case "":            $statusCode = $this->processRequestShow($scheme, $address, $base, $location, $fileName); break;
                case "signup":      $statusCode = $this->processRequestSignup($scheme, $address, $base, $location, $fileName); break;
                case "forgot":      $statusCode = $this->processRequestForgot($scheme, $address, $base, $location, $fileName); break;
                case "confirm":     $statusCode = $this->processRequestConfirm($scheme, $address, $base, $location, $fileName); break;
                case "approve":     $statusCode = $this->processRequestApprove($scheme, $address, $base, $location, $fileName); break;
                case "recover":     $statusCode = $this->processRequestRecover($scheme, $address, $base, $location, $fileName); break;
                case "reactivate":  $statusCode = $this->processRequestReactivate($scheme, $address, $base, $location, $fileName); break;
                case "verify":      $statusCode = $this->processRequestVerify($scheme, $address, $base, $location, $fileName); break;
                case "change":      $statusCode = $this->processRequestChange($scheme, $address, $base, $location, $fileName); break;
                case "remove":      $statusCode = $this->processRequestRemove($scheme, $address, $base, $location, $fileName); break;
            }
        }
        $this->checkUserFailed($scheme, $address, $base, $location, $fileName);
        return $statusCode;
    }
    
    // Process request to show file
    public function processRequestShow($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if (is_readable($fileName)) {
            $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        } else {
            if ($this->yellow->lookup->isRedirectLocation($location)) {
                $location = $this->yellow->lookup->isFileLocation($location) ? "$location/" : "/".$this->yellow->getRequestLanguage()."/";
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->yellow->sendStatus(301, $location);
            } else {
                $this->yellow->page->error($this->response->isUserRestrictions() ? 404 : 434);
                $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
            }
        }
        return $statusCode;
    }

    // Process request for user login
    public function processRequestLogin($scheme, $address, $base, $location, $fileName) {
        $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
        if ($this->users->save($fileNameUser, $this->response->userEmail)) {
            $home = $this->users->getHome($this->response->userEmail);
            if (substru($location, 0, strlenu($home))==$home) {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->yellow->sendStatus(303, $location);
            } else {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $home);
                $statusCode = $this->yellow->sendStatus(302, $location);
            }
        } else {
            $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        }
        return $statusCode;
    }
    
    // Process request for user logout
    public function processRequestLogout($scheme, $address, $base, $location, $fileName) {
        $this->response->userEmail = "";
        $this->response->destroyCookies($scheme, $address, $base);
        $location = $this->yellow->lookup->normaliseUrl(
            $this->yellow->config->get("serverScheme"),
            $this->yellow->config->get("serverAddress"),
            $this->yellow->config->get("serverBase"),
            $location);
        $statusCode = $this->yellow->sendStatus(302, $location);
        return $statusCode;
    }

    // Process request for user signup
    public function processRequestSignup($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "signup";
        $this->response->status = "ok";
        $name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
        $email = trim($_REQUEST["email"]);
        $password = trim($_REQUEST["password"]);
        $consent = trim($_REQUEST["consent"]);
        if (empty($name) || empty($email) || empty($password) || empty($consent)) $this->response->status = "incomplete";
        if ($this->response->status=="ok") $this->response->status = $this->getUserAccount($email, $password, $this->response->action);
        if ($this->response->status=="ok" && $this->response->isLoginRestrictions()) $this->response->status = "next";
        if ($this->response->status=="ok" && $this->users->isTaken($email)) $this->response->status = "next";
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->save($fileNameUser, $email, $password, $name, "", "unconfirmed") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $algorithm = $this->yellow->config->get("editUserHashAlgorithm");
            $this->response->status = substru($this->users->getHash($email), 0, 10)!="error-hash" ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Hash algorithm '$algorithm' not supported!");
        }
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, "confirm") ? "next" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to confirm user signup
    public function processRequestConfirm($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "confirm";
        $this->response->status = "ok";
        $email = $_REQUEST["email"];
        $this->response->status = $this->getUserStatus($email, $_REQUEST["action"]);
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->save($fileNameUser, $email, "", "", "", "unapproved") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, "approve") ? "done" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to approve user signup
    public function processRequestApprove($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "approve";
        $this->response->status = "ok";
        $email = $_REQUEST["email"];
        $this->response->status = $this->getUserStatus($email, $_REQUEST["action"]);
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->save($fileNameUser, $email, "", "", "", "active") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, "welcome") ? "done" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }

    // Process request for forgotten password
    public function processRequestForgot($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "forgot";
        $this->response->status = "ok";
        $email = trim($_REQUEST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->response->status = "invalid";
        if ($this->response->status=="ok" && !$this->users->isExisting($email)) $this->response->status = "next";
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, "recover") ? "next" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to recover password
    public function processRequestRecover($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "recover";
        $this->response->status = "ok";
        $email = trim($_REQUEST["email"]);
        $password = trim($_REQUEST["password"]);
        $this->response->status = $this->getUserStatus($email, $_REQUEST["action"]);
        if ($this->response->status=="ok") {
            if (empty($password)) $this->response->status = "password";
            if ($this->response->status=="ok") $this->response->status = $this->getUserAccount($email, $password, $this->response->action);
            if ($this->response->status=="ok") {
                $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
                $this->response->status = $this->users->save($fileNameUser, $email, $password) ? "ok" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            }
            if ($this->response->status=="ok") {
                $this->response->destroyCookies($scheme, $address, $base);
                $this->response->status = "done";
            }
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to reactivate account
    public function processRequestReactivate($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "reactivate";
        $this->response->status = "ok";
        $email = $_REQUEST["email"];
        $this->response->status = $this->getUserStatus($email, $_REQUEST["action"]);
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->save($fileNameUser, $email, "", "", "", "active") ? "done" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to change settings
    public function processRequestSettings($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "settings";
        $this->response->status = "ok";
        $email = trim($_REQUEST["email"]);
        $emailSource = $this->response->userEmail;
        $password = trim($_REQUEST["password"]);
        $name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
        $language = trim($_REQUEST["language"]);
        if ($email!=$emailSource || !empty($password)) {
            if (empty($email)) $this->response->status = "invalid";
            if ($this->response->status=="ok") $this->response->status = $this->getUserAccount($email, $password, $this->response->action);
            if ($this->response->status=="ok" && $email!=$emailSource && $this->users->isTaken($email)) $this->response->status = "taken";
            if ($this->response->status=="ok" && $email!=$emailSource) {
                $pending = $emailSource;
                $home = $this->users->getHome($emailSource);
                $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
                $this->response->status = $this->users->save($fileNameUser, $email, "no", $name, $language, "unverified", "", "", "", $pending, $home) ? "ok" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            }
            if ($this->response->status=="ok") {
                $pending = $email.":".(empty($password) ? $this->users->getHash($emailSource) : $this->users->createHash($password));
                $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
                $this->response->status = $this->users->save($fileNameUser, $emailSource, "", $name, $language, "", "", "", "", $pending) ? "ok" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            }
            if ($this->response->status=="ok") {
                $action = $email!=$emailSource ? "verify" : "change";
                $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, $action) ? "next" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
            }
        } else {
            if ($this->response->status=="ok") {
                $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
                $this->response->status = $this->users->save($fileNameUser, $email, "", $name, $language) ? "done" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            }
        }
        if ($this->response->status=="done") {
            $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
            $statusCode = $this->yellow->sendStatus(303, $location);
        } else {
            $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        }
        return $statusCode;
    }

    // Process request to verify email
    public function processRequestVerify($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "verify";
        $this->response->status = "ok";
        $email = $emailSource = $_REQUEST["email"];
        $this->response->status = $this->getUserStatus($email, $_REQUEST["action"]);
        if ($this->response->status=="ok") {
            $emailSource = $this->users->getPending($email);
            if ($this->users->getStatus($emailSource)!="active") $this->response->status = "done";
        }
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->save($fileNameUser, $email, "", "", "", "unchanged") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $emailSource, "change") ? "done" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to change email or password
    public function processRequestChange($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "change";
        $this->response->status = "ok";
        $email = $emailSource = trim($_REQUEST["email"]);
        $this->response->status = $this->getUserStatus($email, $_REQUEST["action"]);
        if ($this->response->status=="ok") {
            list($email, $hash) = explode(":", $this->users->getPending($email), 2);
            if (!$this->users->isExisting($email) || empty($hash)) $this->response->status = "done";
        }
        if ($this->response->status=="ok") {
            $this->users->users[$email]["hash"] = $hash;
            $this->users->users[$email]["pending"] = "none";
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->save($fileNameUser, $email, "", "", "", "active") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok" && $email!=$emailSource) {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->remove($fileNameUser, $emailSource) ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $this->response->destroyCookies($scheme, $address, $base);
            $this->response->status = "done";
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to show software version
    public function processRequestVersion($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "version";
        $this->response->status = "ok";
        if ($this->yellow->plugins->isExisting("update")) {
            list($statusCodeCurrent, $dataCurrent) = $this->yellow->plugins->get("update")->getSoftwareVersion();
            list($statusCodeLatest, $dataLatest) = $this->yellow->plugins->get("update")->getSoftwareVersion(true);
            list($statusCodeModified, $dataModified) = $this->yellow->plugins->get("update")->getSoftwareModified();
            $statusCode = max($statusCodeCurrent, $statusCodeLatest, $statusCodeModified);
            if ($this->response->isUserWebmaster()) {
                foreach ($dataCurrent as $key=>$value) {
                    if (strnatcasecmp($dataCurrent[$key], $dataLatest[$key])<0) {
                        ++$updates;
                        $rawData = htmlspecialchars("$key $dataLatest[$key]")."<br />\n";
                        $this->response->rawDataOutput .= $rawData;
                    }
                }
                if ($updates==0) {
                    foreach ($dataCurrent as $key=>$value) {
                        if (!is_null($dataModified[$key]) && !is_null($dataLatest[$key])) {
                            $rawData = $this->yellow->text->getTextHtml("editVersionUpdateModified", $this->response->language)." - <a href=\"#\" data-action=\"update\" data-status=\"update\" data-args=\"".$this->yellow->toolbox->normaliseArgs("feature:$key/option:force")."\">".$this->yellow->text->getTextHtml("editVersionUpdateForce", $this->response->language)."</a><br />\n";
                            $rawData = preg_replace("/@software/i", htmlspecialchars("$key $dataLatest[$key]"), $rawData);
                            $this->response->rawDataOutput .= $rawData;
                        }
                    }
                }
                $this->response->status = $updates ? "updates" : "done";
            } else {
                foreach ($dataCurrent as $key=>$value) {
                    if (strnatcasecmp($dataCurrent[$key], $dataLatest[$key])<0) ++$updates;
                }
                $this->response->status = $updates ? "warning" : "done";
            }
            if ($statusCode!=200) $this->response->status = "error";
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to update website
    public function processRequestUpdate($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->yellow->plugins->isExisting("update") && $this->response->isUserWebmaster()) {
            $feature = trim($_REQUEST["feature"]);
            $option = trim($_REQUEST["option"]);
            $statusCode = $this->yellow->command("update", $feature, $option);
            if ($statusCode==200) {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->yellow->sendStatus(303, $location);
            }
        }
        return $statusCode;
    }
    
    // Process request to quit account
    public function processRequestQuit($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "quit";
        $this->response->status = "ok";
        $name = trim($_REQUEST["name"]);
        $email = $this->response->userEmail;
        if (empty($name)) $this->response->status = "none";
        if ($this->response->status=="ok" && $name!=$this->users->getName($email)) $this->response->status = "mismatch";
        if ($this->response->status=="ok") $this->response->status = $this->getUserAccount($email, "", $this->response->action);
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, "remove") ? "next" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to remove account
    public function processRequestRemove($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "remove";
        $this->response->status = "ok";
        $email = $_REQUEST["email"];
        $this->response->status = $this->getUserStatus($email, $_REQUEST["action"]);
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->save($fileNameUser, $email, "", "", "", "removed") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, "goodbye") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
            $this->response->status = $this->users->remove($fileNameUser, $email) ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $this->response->destroyCookies($scheme, $address, $base);
            $this->response->status = "done";
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to create page
    public function processRequestCreate($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if (!$this->response->isUserRestrictions() && !empty($_REQUEST["rawdataedit"])) {
            $this->response->rawDataSource = $_REQUEST["rawdatasource"];
            $this->response->rawDataEdit = $_REQUEST["rawdatasource"];
            $this->response->rawDataEndOfLine = $_REQUEST["rawdataendofline"];
            $rawData = $_REQUEST["rawdataedit"];
            $page = $this->response->getPageNew($scheme, $address, $base, $location, $fileName, $rawData, $this->response->getEndOfLine());
            if (!$page->isError()) {
                if ($this->yellow->toolbox->createFile($page->fileName, $page->rawData, true)) {
                    $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $page->location);
                    $statusCode = $this->yellow->sendStatus(303, $location);
                } else {
                    $this->yellow->page->error(500, "Can't write file '$page->fileName'!");
                    $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
                }
            } else {
                $this->yellow->page->error(500, $page->get("pageError"));
                $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
            }
        }
        return $statusCode;
    }
    
    // Process request to edit page
    public function processRequestEdit($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if (!$this->response->isUserRestrictions() && !empty($_REQUEST["rawdataedit"])) {
            $this->response->rawDataSource = $_REQUEST["rawdatasource"];
            $this->response->rawDataEdit = $_REQUEST["rawdataedit"];
            $this->response->rawDataEndOfLine = $_REQUEST["rawdataendofline"];
            $rawDataFile = $this->yellow->toolbox->readFile($fileName);
            $page = $this->response->getPageEdit($scheme, $address, $base, $location, $fileName,
                $this->response->rawDataSource, $this->response->rawDataEdit, $rawDataFile, $this->response->rawDataEndOfLine);
            if (!$page->isError()) {
                if ($this->yellow->toolbox->renameFile($fileName, $page->fileName, true) &&
                        $this->yellow->toolbox->createFile($page->fileName, $page->rawData)) {
                    $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $page->location);
                    $statusCode = $this->yellow->sendStatus(303, $location);
                } else {
                    $this->yellow->page->error(500, "Can't write file '$page->fileName'!");
                    $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
                }
            } else {
                $this->yellow->page->error(500, $page->get("pageError"));
                $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
            }
        }
        return $statusCode;
    }

    // Process request to delete page
    public function processRequestDelete($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if (!$this->response->isUserRestrictions() && is_file($fileName)) {
            $this->response->rawDataSource = $_REQUEST["rawdatasource"];
            $this->response->rawDataEdit = $_REQUEST["rawdatasource"];
            $this->response->rawDataEndOfLine = $_REQUEST["rawdataendofline"];
            $rawDataFile = $this->yellow->toolbox->readFile($fileName);
            $page = $this->response->getPageDelete($scheme, $address, $base, $location, $fileName,
                $rawDataFile, $this->response->rawDataEndOfLine);
            if (!$page->isError()) {
                if ($this->yellow->lookup->isFileLocation($location)) {
                    if ($this->yellow->toolbox->deleteFile($fileName, $this->yellow->config->get("trashDir"))) {
                        $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                        $statusCode = $this->yellow->sendStatus(303, $location);
                    } else {
                        $this->yellow->page->error(500, "Can't delete file '$fileName'!");
                        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
                    }
                } else {
                    if ($this->yellow->toolbox->deleteDirectory(dirname($fileName), $this->yellow->config->get("trashDir"))) {
                        $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                        $statusCode = $this->yellow->sendStatus(303, $location);
                    } else {
                        $this->yellow->page->error(500, "Can't delete file '$fileName'!");
                        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
                    }
                }
            } else {
                $this->yellow->page->error(500, $page->get("pageError"));
                $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
            }
        }
        return $statusCode;
    }

    // Process request to show preview
    public function processRequestPreview($scheme, $address, $base, $location, $fileName) {
        $page = $this->response->getPagePreview($scheme, $address, $base, $location, $fileName,
            $_REQUEST["rawdataedit"], $_REQUEST["rawdataendofline"]);
        $statusCode = $this->yellow->sendData(200, $page->outputData, "", false);
        if (defined("DEBUG") && DEBUG>=1) {
            $parser = $page->get("parser");
            echo "YellowEdit::processRequestPreview parser:$parser<br/>\n";
        }
        return $statusCode;
    }
    
    // Process request to upload file
    public function processRequestUpload($scheme, $address, $base, $location, $fileName) {
        $data = array();
        $fileNameTemp = $_FILES["file"]["tmp_name"];
        $fileNameShort = preg_replace("/[^\pL\d\-\.]/u", "-", basename($_FILES["file"]["name"]));
        $fileSizeMax = $this->yellow->toolbox->getNumberBytes(ini_get("upload_max_filesize"));
        $extension = strtoloweru(($pos = strrposu($fileNameShort, ".")) ? substru($fileNameShort, $pos) : "");
        $extensions = preg_split("/\s*,\s*/", $this->yellow->config->get("editUploadExtensions"));
        if (!$this->response->isUserRestrictions() && is_uploaded_file($fileNameTemp) &&
           filesize($fileNameTemp)<=$fileSizeMax && in_array($extension, $extensions)) {
            $file = $this->response->getFileUpload($scheme, $address, $base, $location, $fileNameTemp, $fileNameShort);
            if (!$file->isError() && $this->yellow->toolbox->copyFile($fileNameTemp, $file->fileName, true)) {
                $data["location"] = $file->getLocation();
            } else {
                $data["error"] = "Can't write file '$file->fileName'!";
            }
        } else {
            $data["error"] = "Can't write file '$fileNameShort'!";
        }
        $statusCode = $this->yellow->sendData(is_null($data["error"]) ? 200 : 500, json_encode($data), "a.json", false);
        return $statusCode;
    }
    
    // Check request
    public function checkRequest($location) {
        $locationLength = strlenu($this->yellow->config->get("editLocation"));
        $this->response->active = substru($location, 0, $locationLength)==$this->yellow->config->get("editLocation");
        return $this->response->isActive();
    }
    
    // Check user authentication
    public function checkUserAuth($scheme, $address, $base, $location, $fileName) {
        if ($this->isRequestSameSite("POST", $scheme, $address) || $_REQUEST["action"]=="") {
            if ($_REQUEST["action"]=="login") {
                $email = $_REQUEST["email"];
                $password = $_REQUEST["password"];
                if ($this->users->checkAuthLogin($email, $password)) {
                    $this->response->createCookies($scheme, $address, $base, $email);
                    $this->response->userEmail = $email;
                    $this->response->userRestrictions = $this->getUserRestrictions($email, $location, $fileName);
                    $this->response->language = $this->getUserLanguage($email);
                } else {
                    $this->response->userFailedError = "login";
                    $this->response->userFailedEmail = $email;
                    $this->response->userFailedExpire = PHP_INT_MAX;
                }
            } elseif (isset($_COOKIE["authtoken"]) && isset($_COOKIE["csrftoken"])) {
                if ($this->users->checkAuthToken($_COOKIE["authtoken"], $_COOKIE["csrftoken"], $_POST["csrftoken"], $_REQUEST["action"]=="")) {
                    $this->response->userEmail = $email = $this->users->getAuthEmail($_COOKIE["authtoken"]);
                    $this->response->userRestrictions = $this->getUserRestrictions($email, $location, $fileName);
                    $this->response->language = $this->getUserLanguage($email);
                } else {
                    $this->response->userFailedError = "auth";
                    $this->response->userFailedEmail = $this->users->getAuthEmail($_COOKIE["authtoken"]);
                    $this->response->userFailedExpire = $this->users->getAuthExpire($_COOKIE["authtoken"]);
                }
            }
        }
        return $this->response->isUser();
    }

    // Check user without authentication
    public function checkUserUnauth($scheme, $address, $base, $location, $fileName) {
        $ok = false;
        if ($_REQUEST["action"]=="" || $_REQUEST["action"]=="signup" || $_REQUEST["action"]=="forgot") {
            $ok = true;
        } elseif (isset($_REQUEST["actiontoken"])) {
            if ($this->users->checkActionToken($_REQUEST["actiontoken"], $_REQUEST["email"], $_REQUEST["action"], $_REQUEST["expire"])) {
                $ok = true;
                $this->response->language = $this->getUserLanguage($_REQUEST["email"]);
            } else {
                $this->response->userFailedError = "action";
                $this->response->userFailedEmail = $_REQUEST["email"];
                $this->response->userFailedExpire = $_REQUEST["expire"];
            }
        }
        return $ok;
    }

    // Check user failed
    public function checkUserFailed($scheme, $address, $base, $location, $fileName) {
        if (!empty($this->response->userFailedError)) {
            if ($this->response->userFailedExpire>time() && $this->users->isExisting($this->response->userFailedEmail)) {
                $email = $this->response->userFailedEmail;
                $modified = $this->users->getModified($email);
                $errors = $this->users->getErrors($email)+1;
                $fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("editUserFile");
                $status = $this->users->save($fileNameUser, $email, "", "", "", "", "", $modified, $errors) ? "ok" : "error";
                if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
                if ($errors==$this->yellow->config->get("editBruteForceProtection")) {
                    $statusBeforeProtection = $this->users->getStatus($email);
                    $statusAfterProtection = ($statusBeforeProtection=="active" || $statusBeforeProtection=="inactive") ? "inactive" : "failed";
                    if ($status=="ok") {
                        $status = $this->users->save($fileNameUser, $email, "", "", "", $statusAfterProtection, "", $modified, $errors) ? "ok" : "error";
                        if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
                    }
                    if ($status=="ok" && $statusBeforeProtection=="active") {
                        $status = $this->response->sendMail($scheme, $address, $base, $email, "reactivate") ? "done" : "error";
                        if ($status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
                    }
                }
            }
            if ($this->response->userFailedError=="login" || $this->response->userFailedError=="auth") {
                $this->response->destroyCookies($scheme, $address, $base);
                $this->response->status = "error";
                $this->yellow->page->error(430);
            } else {
                $this->response->status = "error";
                $this->yellow->page->error(500, "Link has expired!");
            }
        }
    }
    
    // Return user status changes
    public function getUserStatus($email, $action) {
        switch ($action) {
            case "confirm":     $statusExpected = "unconfirmed"; break;
            case "approve":     $statusExpected = "unapproved"; break;
            case "recover":     $statusExpected = "active"; break;
            case "reactivate":  $statusExpected = "inactive"; break;
            case "verify":      $statusExpected = "unverified"; break;
            case "change":      $statusExpected = "active"; break;
            case "remove":      $statusExpected = "active"; break;
        }
        return $this->users->getStatus($email)==$statusExpected ? "ok" : "done";
    }

    // Return user account changes
    public function getUserAccount($email, $password, $action) {
        $status = null;
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onEditUserAccount")) {
                $status = $value["obj"]->onEditUserAccount($email, $password, $action, $this->users);
                if (!is_null($status)) break;
            }
        }
        if (is_null($status)) {
            $status = "ok";
            if (!empty($password) && strlenu($password)<$this->yellow->config->get("editUserPasswordMinLength")) $status = "weak";
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $status = "invalid";
        }
        return $status;
    }
    
    // Return user restrictions
    public function getUserRestrictions($email, $location, $fileName) {
        $userRestrictions = null;
        foreach ($this->yellow->plugins->plugins as $key=>$value) {
            if (method_exists($value["obj"], "onEditUserRestrictions")) {
                $userRestrictions = $value["obj"]->onEditUserRestrictions($email, $location, $fileName, $this->users);
                if (!is_null($userRestrictions)) break;
            }
        }
        if (is_null($userRestrictions)) {
            $userRestrictions = substru($location, 0, strlenu($this->users->getHome($email)))!=$this->users->getHome($email);
            $userRestrictions |= empty($fileName) || strlenu(dirname($fileName))>128 || strlenu(basename($fileName))>128;
        }
        return $userRestrictions;
    }
    
    // Return user language
    public function getUserLanguage($email) {
        $language = $this->users->getLanguage($email);
        if (!$this->yellow->text->isLanguage($language)) $language = $this->yellow->config->get("language");
        return $language;
    }
    
    // Check if request came from same site
    public function isRequestSameSite($method, $scheme, $address) {
        if (preg_match("#^(\w+)://([^/]+)(.*)$#", $_SERVER["HTTP_REFERER"], $matches)) $origin = "$matches[1]://$matches[2]";
        if (isset($_SERVER["HTTP_ORIGIN"])) $origin = $_SERVER["HTTP_ORIGIN"];
        return $_SERVER["REQUEST_METHOD"]==$method && $origin=="$scheme://$address";
    }
}
    
class YellowResponse {
    public $yellow;             //access to API
    public $plugin;             //access to plugin
    public $active;             //location is active? (boolean)
    public $userEmail;          //user email
    public $userRestrictions;   //user can change page? (boolean)
    public $userFailedError;    //error of failed authentication
    public $userFailedEmail;    //email of failed authentication
    public $userFailedExpire;   //expiration time of failed authentication
    public $rawDataSource;      //raw data of page for comparison
    public $rawDataEdit;        //raw data of page for editing
    public $rawDataOutput;      //raw data of dynamic output
    public $rawDataEndOfLine;   //end of line format for raw data
    public $language;           //response language
    public $action;             //response action
    public $status;             //response status
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->plugin = $yellow->plugins->get("edit");
    }
    
    // Return new page
    public function getPageNew($scheme, $address, $base, $location, $fileName, $rawData, $endOfLine) {
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $page->parseData($this->normaliseLines($rawData, $endOfLine), false, 0);
        $this->editContentFile($page, "create");
        if ($this->yellow->lookup->isFileLocation($location) || $this->yellow->pages->find($page->location)) {
            $page->location = $this->getPageNewLocation($page->rawData, $page->location, $page->get("pageNewLocation"));
            $page->fileName = $this->yellow->lookup->findFileNew($page->location, $page->get("published"));
            while ($this->yellow->pages->find($page->location) || empty($page->fileName)) {
                $rawData = $this->yellow->toolbox->setMetaData($page->rawData, "title", $this->getTitleNext($page->rawData));
                $page->rawData = $this->normaliseLines($rawData, $endOfLine);
                $page->location = $this->getPageNewLocation($page->rawData, $page->location, $page->get("pageNewLocation"));
                $page->fileName = $this->yellow->lookup->findFileNew($page->location, $page->get("published"));
                if (++$pageCounter>999) break;
            }
            if ($this->yellow->pages->find($page->location) || empty($page->fileName)) {
                $page->error(500, "Page '".$page->get("title")."' is not possible!");
            }
        } else {
            $page->fileName = $this->yellow->lookup->findFileNew($page->location);
        }
        if ($this->plugin->getUserRestrictions($this->userEmail, $page->location, $page->fileName)) {
            $page->error(500, "Page '".$page->get("title")."' is restricted!");
        }
        return $page;
    }
    
    // Return modified page
    public function getPageEdit($scheme, $address, $base, $location, $fileName, $rawDataSource, $rawDataEdit, $rawDataFile, $endOfLine) {
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $rawData = $this->plugin->merge->merge(
            $this->normaliseLines($rawDataSource, $endOfLine),
            $this->normaliseLines($rawDataEdit, $endOfLine),
            $this->normaliseLines($rawDataFile, $endOfLine));
        $page->parseData($this->normaliseLines($rawData, $endOfLine), false, 0);
        $this->editContentFile($page, "edit");
        if (empty($page->rawData)) $page->error(500, "Page has been modified by someone else!");
        if ($this->yellow->lookup->isFileLocation($location) && !$page->isError()) {
            $pageSource = new YellowPage($this->yellow);
            $pageSource->setRequestInformation($scheme, $address, $base, $location, $fileName);
            $pageSource->parseData($this->normaliseLines($rawDataSource, $endOfLine), false, 0);
            if (substrb($pageSource->rawData, 0, $pageSource->metaDataOffsetBytes) !=
               substrb($page->rawData, 0, $page->metaDataOffsetBytes)) {
                $page->location = $this->getPageNewLocation($page->rawData, $page->location, $page->get("pageNewLocation"));
                $page->fileName = $this->yellow->lookup->findFileNew($page->location, $page->get("published"));
                if ($page->location!=$pageSource->location) {
                    if (!$this->yellow->lookup->isFileLocation($page->location) || empty($page->fileName)) {
                        $page->error(500, "Page '".$page->get("title")."' is not possible!");
                    } elseif ($this->yellow->pages->find($page->location)) {
                        $page->error(500, "Page '".$page->get("title")."' already exists!");
                    }
                }
            }
        }
        if ($this->plugin->getUserRestrictions($this->userEmail, $page->location, $page->fileName)) {
            $page->error(500, "Page '".$page->get("title")."' is restricted!");
        }
        return $page;
    }
    
    // Return deleted page
    public function getPageDelete($scheme, $address, $base, $location, $fileName, $rawData, $endOfLine) {
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $page->parseData($this->normaliseLines($rawData, $endOfLine), false, 0);
        $this->editContentFile($page, "delete");
        if ($this->plugin->getUserRestrictions($this->userEmail, $page->location, $page->fileName)) {
            $page->error(500, "Page '".$page->get("title")."' is restricted!");
        }
        return $page;
    }

    // Return preview page
    public function getPagePreview($scheme, $address, $base, $location, $fileName, $rawData, $endOfLine) {
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $page->parseData($this->normaliseLines($rawData, $endOfLine), false, 200);
        $this->yellow->text->setLanguage($page->get("language"));
        $page->set("pageClass", "page-preview");
        $page->set("pageClass", $page->get("pageClass")." template-".$page->get("template"));
        $output = "<div class=\"".$page->getHtml("pageClass")."\"><div class=\"content\">";
        if ($this->yellow->config->get("editToolbarButtons")!="none") $output .= "<h1>".$page->getHtml("titleContent")."</h1>\n";
        $output .= $page->getContent();
        $output .= "</div></div>";
        $page->setOutput($output);
        return $page;
    }
    
    // Return uploaded file
    public function getFileUpload($scheme, $address, $base, $pageLocation, $fileNameTemp, $fileNameShort) {
        $file = new YellowPage($this->yellow);
        $file->setRequestInformation($scheme, $address, $base, "/".$fileNameTemp, $fileNameTemp);
        $file->parseData(null, false, 0);
        $file->set("fileNameShort", $fileNameShort);
        $this->editMediaFile($file, "upload");
        $file->location = $this->getFileNewLocation($fileNameShort, $pageLocation, $file->get("fileNewLocation"));
        $file->fileName = substru($file->location, 1);
        while (is_file($file->fileName)) {
            $fileNameShort = $this->getFileNext(basename($file->fileName));
            $file->location = $this->getFileNewLocation($fileNameShort, $pageLocation, $file->get("fileNewLocation"));
            $file->fileName = substru($file->location, 1);
            if (++$fileCounter>999) break;
        }
        if (is_file($file->fileName)) $file->error(500, "File '".$file->get("fileNameShort")."' is not possible!");
        return $file;
    }

    // Return page data including status information
    public function getPageData() {
        $data = array();
        if ($this->isUser()) {
            $data["title"] = $this->yellow->toolbox->getMetaData($this->rawDataEdit, "title");
            $data["rawDataSource"] = $this->rawDataSource;
            $data["rawDataEdit"] = $this->rawDataEdit;
            $data["rawDataNew"] = $this->getRawDataNew();
            $data["rawDataOutput"] = strval($this->rawDataOutput);
            $data["rawDataEndOfLine"] = $this->rawDataEndOfLine;
            $data["scheme"] = $this->yellow->page->scheme;
            $data["address"] = $this->yellow->page->address;
            $data["base"] = $this->yellow->page->base;
            $data["location"] = $this->yellow->page->location;
            $data["safeMode"] = $this->yellow->page->safeMode;
        }
        if ($this->action!="none") $data = array_merge($data, $this->getRequestData());
        $data["action"] = $this->action;
        $data["status"] = $this->status;
        $data["statusCode"] = $this->yellow->page->statusCode;
        return $data;
    }
    
    // Return configuration data including user information
    public function getConfigData() {
        $data = $this->yellow->config->getData("", "Location");
        if ($this->isUser()) {
            $data["userEmail"] = $this->userEmail;
            $data["userName"] = $this->plugin->users->getName($this->userEmail);
            $data["userLanguage"] = $this->plugin->users->getLanguage($this->userEmail);
            $data["userStatus"] = $this->plugin->users->getStatus($this->userEmail);
            $data["userHome"] = $this->plugin->users->getHome($this->userEmail);
            $data["userRestrictions"] = intval($this->isUserRestrictions());
            $data["userWebmaster"] = intval($this->isUserWebmaster());
            $data["serverScheme"] = $this->yellow->config->get("serverScheme");
            $data["serverAddress"] = $this->yellow->config->get("serverAddress");
            $data["serverBase"] = $this->yellow->config->get("serverBase");
            $data["serverFileSizeMax"] = $this->yellow->toolbox->getNumberBytes(ini_get("upload_max_filesize"));
            $data["serverVersion"] = "Datenstrom Yellow ".YellowCore::VERSION;
            $data["serverPlugins"] = array();
            foreach ($this->yellow->plugins->plugins as $key=>$value) {
                $data["serverPlugins"][$key] = $value["plugin"];
            }
            $data["serverLanguages"] = array();
            foreach ($this->yellow->text->getLanguages() as $language) {
                $data["serverLanguages"][$language] = $this->yellow->text->getTextHtml("languageDescription", $language);
            }
            $data["editUploadExtensions"] = $this->yellow->config->get("editUploadExtensions");
            $data["editKeyboardShortcuts"] = $this->yellow->config->get("editKeyboardShortcuts");
            $data["editToolbarButtons"] = $this->getToolbarButtons("edit");
            $data["emojiawesomeToolbarButtons"] =  $this->getToolbarButtons("emojiawesome");
            $data["fontawesomeToolbarButtons"] =  $this->getToolbarButtons("fontawesome");
        } else {
            $data["editLoginEmail"] = $this->yellow->page->get("editLoginEmail");
            $data["editLoginPassword"] = $this->yellow->page->get("editLoginPassword");
            $data["editLoginRestrictions"] = intval($this->isLoginRestrictions());
        }
        if (defined("DEBUG") && DEBUG>=1) $data["debug"] = DEBUG;
        return $data;
    }
    
    // Return request strings
    public function getRequestData() {
        $data = array();
        foreach ($_REQUEST as $key=>$value) {
            if ($key=="password" || $key=="authtoken" || $key=="csrftoken" || $key=="actiontoken" || substru($key, 0, 7)=="rawdata") continue;
            $data["request".ucfirst($key)] = trim($value);
        }
        return $data;
    }
    
    // Return text strings
    public function getTextData() {
        $textLanguage = $this->yellow->text->getData("language", $this->language);
        $textEdit = $this->yellow->text->getData("edit", $this->language);
        $textYellow = $this->yellow->text->getData("yellow", $this->language);
        return array_merge($textLanguage, $textEdit, $textYellow);
    }
    
    // Return toolbar buttons
    public function getToolbarButtons($name) {
        if ($name=="edit") {
            $toolbarButtons = $this->yellow->config->get("editToolbarButtons");
            if ($toolbarButtons=="auto") {
                $toolbarButtons = "";
                if ($this->yellow->plugins->isExisting("markdown")) $toolbarButtons = "preview, format, bold, italic, code, list, link, file";
                if ($this->yellow->plugins->isExisting("emojiawesome")) $toolbarButtons .= ", emojiawesome";
                if ($this->yellow->plugins->isExisting("fontawesome")) $toolbarButtons .= ", fontawesome";
                if ($this->yellow->plugins->isExisting("draft")) $toolbarButtons .= ", draft";
                if ($this->yellow->plugins->isExisting("markdown")) $toolbarButtons .= ", markdown";
            }
        } else {
            $toolbarButtons = $this->yellow->config->get("{$name}ToolbarButtons");
        }
        return $toolbarButtons;
    }
    
    // Return end of line format
    public function getEndOfLine($rawData = "") {
        $endOfLine = $this->yellow->config->get("editEndOfLine");
        if ($endOfLine=="auto") {
            $rawData = empty($rawData) ? PHP_EOL : substru($rawData, 0, 4096);
            $endOfLine = strposu($rawData, "\r")===false ? "lf" : "crlf";
        }
        return $endOfLine;
    }
    
    // Return raw data for new page
    public function getRawDataNew($location = "") {
        foreach ($this->yellow->pages->path($this->yellow->page->location)->reverse() as $page) {
            if ($page->isExisting("templateNew")) {
                $name = $this->yellow->lookup->normaliseName($page->get("templateNew"));
                $fileName = strreplaceu("(.*)", $name, $this->yellow->config->get("configDir").$this->yellow->config->get("newFile"));
                if (is_file($fileName)) break;
            }
        }
        if (!is_file($fileName)) {
            $name = $this->yellow->lookup->normaliseName($this->yellow->config->get("template"));
            $fileName = strreplaceu("(.*)", $name, $this->yellow->config->get("configDir").$this->yellow->config->get("newFile"));
        }
        $rawData = $this->yellow->toolbox->readFile($fileName);
        $rawData = preg_replace("/@timestamp/i", time(), $rawData);
        $rawData = preg_replace("/@datetime/i", date("Y-m-d H:i:s"), $rawData);
        $rawData = preg_replace("/@date/i", date("Y-m-d"), $rawData);
        $rawData = preg_replace("/@usershort/i", strtok($this->plugin->users->getName($this->userEmail), " "), $rawData);
        $rawData = preg_replace("/@username/i", $this->plugin->users->getName($this->userEmail), $rawData);
        $rawData = preg_replace("/@userlanguage/i", $this->plugin->users->getLanguage($this->userEmail), $rawData);
        if (!empty($location)) {
            $rawData = $this->yellow->toolbox->setMetaData($rawData, "title", $this->yellow->toolbox->createTextTitle($location));
        }
        return $rawData;
    }
    
    // Return location for new/modified page
    public function getPageNewLocation($rawData, $pageLocation, $pageNewLocation) {
        $location = empty($pageNewLocation) ? "@title" : $pageNewLocation;
        $location = preg_replace("/@title/i", $this->getPageNewTitle($rawData), $location);
        $location = preg_replace("/@timestamp/i", $this->getPageNewData($rawData, "published", true, "U"), $location);
        $location = preg_replace("/@date/i", $this->getPageNewData($rawData, "published", true, "Y-m-d"), $location);
        $location = preg_replace("/@year/i", $this->getPageNewData($rawData, "published", true, "Y"), $location);
        $location = preg_replace("/@month/i", $this->getPageNewData($rawData, "published", true, "m"), $location);
        $location = preg_replace("/@day/i", $this->getPageNewData($rawData, "published", true, "d"), $location);
        $location = preg_replace("/@tag/i", $this->getPageNewData($rawData, "tag", true), $location);
        $location = preg_replace("/@author/i", $this->getPageNewData($rawData, "author", true), $location);
        if (!preg_match("/^\//", $location)) {
            $location = $this->yellow->lookup->getDirectoryLocation($pageLocation).$location;
        }
        return $location;
    }
    
    // Return title for new/modified page
    public function getPageNewTitle($rawData) {
        $title = $this->yellow->toolbox->getMetaData($rawData, "title");
        $titleSlug = $this->yellow->toolbox->getMetaData($rawData, "titleSlug");
        $value = empty($titleSlug) ? $title : $titleSlug;
        $value = $this->yellow->lookup->normaliseName($value, true, false, true);
        return trim(preg_replace("/-+/", "-", $value), "-");
    }
    
    // Return data for new/modified page
    public function getPageNewData($rawData, $key, $filterFirst = false, $dateFormat = "") {
        $value = $this->yellow->toolbox->getMetaData($rawData, $key);
        if ($filterFirst && preg_match("/^(.*?)\,(.*)$/", $value, $matches)) $value = $matches[1];
        if (!empty($dateFormat)) $value = date($dateFormat, strtotime($value));
        if (strempty($value)) $value = "none";
        $value = $this->yellow->lookup->normaliseName($value, true, false, true);
        return trim(preg_replace("/-+/", "-", $value), "-");
    }

    // Return location for new file
    public function getFileNewLocation($fileNameShort, $pageLocation, $fileNewLocation) {
        $location = empty($fileNewLocation) ? $this->yellow->config->get("editUploadNewLocation") : $fileNewLocation;
        $location = preg_replace("/@timestamp/i", time(), $location);
        $location = preg_replace("/@type/i", $this->yellow->toolbox->getFileType($fileNameShort), $location);
        $location = preg_replace("/@group/i", $this->getFileNewGroup($fileNameShort), $location);
        $location = preg_replace("/@folder/i", $this->getFileNewFolder($pageLocation), $location);
        $location = preg_replace("/@filename/i", strtoloweru($fileNameShort), $location);
        if (!preg_match("/^\//", $location)) {
            $location = $this->yellow->config->get("mediaLocation").$location;
        }
        return $location;
    }
    
    // Return group for new file
    public function getFileNewGroup($fileNameShort) {
        $path = $this->yellow->config->get("mediaDir");
        $fileType = $this->yellow->toolbox->getFileType($fileNameShort);
        $fileName = $this->yellow->config->get(preg_match("/(gif|jpg|png|svg)$/", $fileType) ? "imageDir" : "downloadDir").$fileNameShort;
        preg_match("#^$path(.+?)\/#", $fileName, $matches);
        return strtoloweru($matches[1]);
    }

    // Return folder for new file
    public function getFileNewFolder($pageLocation) {
        $parentTopLocation = $this->yellow->pages->getParentTopLocation($pageLocation);
        if ($parentTopLocation==$this->yellow->pages->getHomeLocation($pageLocation)) $parentTopLocation .= "home";
        return strtoloweru(trim($parentTopLocation, "/"));
    }
    
    // Return next title
    public function getTitleNext($rawData) {
        preg_match("/^(.*?)(\d*)$/", $this->yellow->toolbox->getMetaData($rawData, "title"), $matches);
        $titleText = $matches[1];
        $titleNumber = strempty($matches[2]) ? " 2" : $matches[2]+1;
        return $titleText.$titleNumber;
    }
    
    // Return next file name
    public function getFileNext($fileNameShort) {
        preg_match("/^(.*?)(\d*)(\..*?)?$/", $fileNameShort, $matches);
        $fileText = $matches[1];
        $fileNumber = strempty($matches[2]) ? "-2" : $matches[2]+1;
        $fileExtension = $matches[3];
        return $fileText.$fileNumber.$fileExtension;
    }

    // Normalise text lines, convert line endings
    public function normaliseLines($text, $endOfLine = "lf") {
        if ($endOfLine=="lf") {
            $text = preg_replace("/\R/u", "\n", $text);
        } else {
            $text = preg_replace("/\R/u", "\r\n", $text);
        }
        return $text;
    }
    
    // Create browser cookies
    public function createCookies($scheme, $address, $base, $email) {
        $expire = time() + $this->yellow->config->get("editLoginSessionTimeout");
        $authToken = $this->plugin->users->createAuthToken($email, $expire);
        $csrfToken = $this->plugin->users->createCsrfToken();
        setcookie("authtoken", $authToken, $expire, "$base/", "", $scheme=="https", true);
        setcookie("csrftoken", $csrfToken, $expire, "$base/", "", $scheme=="https", false);
    }
    
    // Destroy browser cookies
    public function destroyCookies($scheme, $address, $base) {
        setcookie("authtoken", "", 1, "$base/", "", $scheme=="https", true);
        setcookie("csrftoken", "", 1, "$base/", "", $scheme=="https", false);
    }
    
    // Send mail to user
    public function sendMail($scheme, $address, $base, $email, $action) {
        if ($action=="welcome" || $action=="goodbye") {
            $url = "$scheme://$address$base/";
        } else {
            $expire = time() + 60*60*24;
            $actionToken = $this->plugin->users->createActionToken($email, $action, $expire);
            $url = "$scheme://$address$base"."/action:$action/email:$email/expire:$expire/actiontoken:$actionToken/";
        }
        if ($action=="approve") {
            $account = $email;
            $name = $this->yellow->config->get("author");
            $email = $this->yellow->config->get("email");
        } else {
            $account = $email;
            $name = $this->plugin->users->getName($email);
        }
        $language = $this->plugin->users->getLanguage($email);
        if (!$this->yellow->text->isLanguage($language)) $language = $this->yellow->config->get("language");
        $sitename = $this->yellow->config->get("sitename");
        $prefix = "edit".ucfirst($action);
        $message = $this->yellow->text->getText("{$prefix}Message", $language);
        $message = strreplaceu("\\n", "\n", $message);
        $message = preg_replace("/@useraccount/i", $account, $message);
        $message = preg_replace("/@usershort/i", strtok($name, " "), $message);
        $message = preg_replace("/@username/i", $name, $message);
        $message = preg_replace("/@userlanguage/i", $language, $message);
        $mailTo = mb_encode_mimeheader("$name")." <$email>";
        $mailSubject = mb_encode_mimeheader($this->yellow->text->getText("{$prefix}Subject", $language));
        $mailHeaders = mb_encode_mimeheader("From: $sitename")." <noreply>\r\n";
        $mailHeaders .= mb_encode_mimeheader("X-Request-Url: $scheme://$address$base")."\r\n";
        $mailHeaders .= "Mime-Version: 1.0\r\n";
        $mailHeaders .= "Content-Type: text/plain; charset=utf-8\r\n";
        $mailMessage = "$message\r\n\r\n$url\r\n-- \r\n$sitename";
        return mail($mailTo, $mailSubject, $mailMessage, $mailHeaders);
    }
    
    // Change content file
    public function editContentFile($page, $action) {
        if (!$page->isError()) {
            foreach ($this->yellow->plugins->plugins as $key=>$value) {
                if (method_exists($value["obj"], "onEditContentFile")) $value["obj"]->onEditContentFile($page, $action);
            }
        }
    }

    // Change media file
    public function editMediaFile($file, $action) {
        if (!$file->isError()) {
            foreach ($this->yellow->plugins->plugins as $key=>$value) {
                if (method_exists($value["obj"], "onEditMediaFile")) $value["obj"]->onEditMediaFile($file, $action);
            }
        }
    }
    
    // Check if active
    public function isActive() {
        return $this->active;
    }
    
    // Check if user is logged in
    public function isUser() {
        return !empty($this->userEmail);
    }
    
    // Check if user has restrictions
    public function isUserRestrictions() {
        return empty($this->userEmail) || $this->userRestrictions;
    }
    
    // Check if user is webmaster
    public function isUserWebmaster() {
        return !empty($this->userEmail) && $this->userEmail==$this->yellow->config->get("email");
    }
    
    // Check if login has restrictions
    public function isLoginRestrictions() {
        return $this->yellow->config->get("editLoginRestrictions");
    }
}

class YellowUsers {
    public $yellow;     //access to API
    public $users;      //registered users
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->users = array();
    }

    // Load users from file
    public function load($fileName) {
        if (defined("DEBUG") && DEBUG>=2) echo "YellowUsers::load file:$fileName<br/>\n";
        $fileData = $this->yellow->toolbox->readFile($fileName);
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            if (preg_match("/^\#/", $line)) continue;
            preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
            if (!empty($matches[1]) && !empty($matches[2])) {
                list($hash, $name, $language, $status, $stamp, $modified, $errors, $pending, $home) = explode(",", $matches[2]);
                $this->set($matches[1], $hash, $name, $language, $status, $stamp, $modified, $errors, $pending, $home);
                if (defined("DEBUG") && DEBUG>=3) echo "YellowUsers::load email:$matches[1]<br/>\n";
            }
        }
    }

    // Save user to file
    public function save($fileName, $email, $password = "", $name = "", $language = "", $status = "", $stamp = "", $modified = "", $errors = "", $pending = "", $home = "") {
        if (!empty($password)) $hash = $this->createHash($password);
        if ($this->isExisting($email)) {
            $email = strreplaceu(",", "-", $email);
            $hash = strreplaceu(",", "-", empty($hash) ? $this->users[$email]["hash"] : $hash);
            $name = strreplaceu(",", "-", empty($name) ? $this->users[$email]["name"] : $name);
            $language = strreplaceu(",", "-", empty($language) ? $this->users[$email]["language"] : $language);
            $status = strreplaceu(",", "-", empty($status) ? $this->users[$email]["status"] : $status);
            $stamp = strreplaceu(",", "-", empty($stamp) ? $this->users[$email]["stamp"] : $stamp);
            $modified = strreplaceu(",", "-", empty($modified) ? time() : $modified);
            $errors = strreplaceu(",", "-", empty($errors) ? "0" : $errors);
            $pending = strreplaceu(",", "-", empty($pending) ? $this->users[$email]["pending"] : $pending);
            $home = strreplaceu(",", "-", empty($home) ? $this->users[$email]["home"] : $home);
        } else {
            $email = strreplaceu(",", "-", empty($email) ? "none" : $email);
            $hash = strreplaceu(",", "-", empty($hash) ? "none" : $hash);
            $name = strreplaceu(",", "-", empty($name) ? $this->yellow->config->get("sitename") : $name);
            $language = strreplaceu(",", "-", empty($language) ? $this->yellow->config->get("language") : $language);
            $status = strreplaceu(",", "-", empty($status) ? "active" : $status);
            $stamp = strreplaceu(",", "-", empty($stamp) ? $this->createStamp() : $stamp);
            $modified = strreplaceu(",", "-", empty($modified) ? time() : $modified);
            $errors = strreplaceu(",", "-", empty($errors) ? "0" : $errors);
            $pending = strreplaceu(",", "-", empty($pending) ? "none" : $pending);
            $home = strreplaceu(",", "-", empty($home) ? $this->yellow->config->get("editUserHome") : $home);
        }
        $this->set($email, $hash, $name, $language, $status, $stamp, $modified, $errors, $pending, $home);
        $fileData = $this->yellow->toolbox->readFile($fileName);
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
            if (!empty($matches[1]) && $matches[1]==$email) {
                $fileDataNew .= "$email: $hash,$name,$language,$status,$stamp,$modified,$errors,$pending,$home\n";
                $found = true;
            } else {
                $fileDataNew .= $line;
            }
        }
        if (!$found) $fileDataNew .= "$email: $hash,$name,$language,$status,$stamp,$modified,$errors,$pending,$home\n";
        return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
    }
    
    // Remove user from file
    public function remove($fileName, $email) {
        unset($this->users[$email]);
        $fileData = $this->yellow->toolbox->readFile($fileName);
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
            if (!empty($matches[1]) && !empty($matches[2]) && $matches[1]!=$email) $fileDataNew .= $line;
        }
        return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
    }
    
    // Set user data
    public function set($email, $hash, $name, $language, $status, $stamp, $modified, $errors, $pending, $home) {
        $this->users[$email] = array();
        $this->users[$email]["email"] = $email;
        $this->users[$email]["hash"] = $hash;
        $this->users[$email]["name"] = $name;
        $this->users[$email]["language"] = $language;
        $this->users[$email]["status"] = $status;
        $this->users[$email]["stamp"] = $stamp;
        $this->users[$email]["modified"] = $modified;
        $this->users[$email]["errors"] = $errors;
        $this->users[$email]["pending"] = $pending;
        $this->users[$email]["home"] = $home;
    }
    
    // Check user authentication from email and password
    public function checkAuthLogin($email, $password) {
        $algorithm = $this->yellow->config->get("editUserHashAlgorithm");
        return $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
            $this->yellow->toolbox->verifyHash($password, $algorithm, $this->users[$email]["hash"]);
    }

    // Check user authentication from tokens
    public function checkAuthToken($authToken, $csrfTokenExpected, $csrfTokenReceived, $ignoreCsrfToken) {
        $signature = "$5y$".substrb($authToken, 0, 96);
        $email = $this->getAuthEmail($authToken);
        $expire = $this->getAuthExpire($authToken);
        return $expire>time() && $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
            $this->yellow->toolbox->verifyHash($this->users[$email]["hash"]."auth".$expire, "sha256", $signature) &&
            ($this->yellow->toolbox->verifyToken($csrfTokenExpected, $csrfTokenReceived) || $ignoreCsrfToken);
    }
    
    // Check action token
    public function checkActionToken($actionToken, $email, $action, $expire) {
        $signature = "$5y$".$actionToken;
        return $expire>time() && $this->isExisting($email) &&
            $this->yellow->toolbox->verifyHash($this->users[$email]["hash"].$action.$expire, "sha256", $signature);
    }
           
    // Create authentication token
    public function createAuthToken($email, $expire) {
        $signature = $this->yellow->toolbox->createHash($this->users[$email]["hash"]."auth".$expire, "sha256");
        if (empty($signature)) $signature = "padd"."error-hash-algorithm-sha256";
        return substrb($signature, 4).$this->getStamp($email).dechex($expire);
    }
    
    // Create action token
    public function createActionToken($email, $action, $expire) {
        $signature = $this->yellow->toolbox->createHash($this->users[$email]["hash"].$action.$expire, "sha256");
        if (empty($signature)) $signature = "padd"."error-hash-algorithm-sha256";
        return substrb($signature, 4);
    }
    
    // Create CSRF token
    public function createCsrfToken() {
        return $this->yellow->toolbox->createSalt(64);
    }
    
    // Create password hash
    public function createHash($password) {
        $algorithm = $this->yellow->config->get("editUserHashAlgorithm");
        $cost = $this->yellow->config->get("editUserHashCost");
        $hash = $this->yellow->toolbox->createHash($password, $algorithm, $cost);
        if (empty($hash)) $hash = "error-hash-algorithm-$algorithm";
        return $hash;
    }
    
    // Create user stamp
    public function createStamp() {
        $stamp = $this->yellow->toolbox->createSalt(20);
        while ($this->getAuthEmail("none", $stamp)) {
            $stamp = $this->yellow->toolbox->createSalt(20);
        }
        return $stamp;
    }
    
    // Return user email from authentication, timing attack safe email lookup
    public function getAuthEmail($authToken, $stamp = "") {
        if (empty($stamp)) $stamp = substrb($authToken, 96, 20);
        foreach ($this->users as $key=>$value) {
            if ($this->yellow->toolbox->verifyToken($value["stamp"], $stamp)) $email = $key;
        }
        return $email;
    }
    
    // Return expiration time from authentication
    public function getAuthExpire($authToken) {
        return hexdec(substrb($authToken, 96+20));
    }
    
    // Return user hash
    public function getHash($email) {
        return $this->isExisting($email) ? $this->users[$email]["hash"] : "";
    }
    
    // Return user name
    public function getName($email) {
        return $this->isExisting($email) ? $this->users[$email]["name"] : "";
    }

    // Return user language
    public function getLanguage($email) {
        return $this->isExisting($email) ? $this->users[$email]["language"] : "";
    }
    
    // Return user status
    public function getStatus($email) {
        return $this->isExisting($email) ? $this->users[$email]["status"] : "";
    }
    
    // Return user stamp
    public function getStamp($email) {
        return $this->isExisting($email) ? $this->users[$email]["stamp"] : "";
    }
    
    // Return user modified
    public function getModified($email) {
        return $this->isExisting($email) ? $this->users[$email]["modified"] : "";
    }

    // Return user errors
    public function getErrors($email) {
        return $this->isExisting($email) ? $this->users[$email]["errors"] : "";
    }

    // Return user pending
    public function getPending($email) {
        return $this->isExisting($email) ? $this->users[$email]["pending"] : "";
    }
    
    // Return user home
    public function getHome($email) {
        return $this->isExisting($email) ? $this->users[$email]["home"] : "";
    }
    
    // Return number of users
    public function getNumber() {
        return count($this->users);
    }

    // Return user data
    public function getData() {
        $data = array();
        foreach ($this->users as $key=>$value) {
            $name = $value["name"];
            $status = $value["status"];
            if (preg_match("/\s/", $name)) $name = "\"$name\"";
            if (preg_match("/\s/", $status)) $status = "\"$status\"";
            $data[$key] = "$value[email] $name $status";
        }
        uksort($data, "strnatcasecmp");
        return $data;
    }
    
    // Check if user is taken
    public function isTaken($email) {
        $taken = false;
        if ($this->isExisting($email)) {
            $status = $this->users[$email]["status"];
            $reserved = $this->users[$email]["modified"] + 60*60*24;
            if ($status=="active" || $status=="inactive" || $reserved>time()) $taken = true;
        }
        return $taken;
    }
    
    // Check if user exists
    public function isExisting($email) {
        return !is_null($this->users[$email]);
    }
}
    
class YellowMerge {
    public $yellow;     //access to API
    const ADD = "+";    //merge types
    const MODIFY = "*";
    const REMOVE = "-";
    const SAME = " ";
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
    }
    
    // Merge text, null if not possible
    public function merge($textSource, $textMine, $textYours, $showDiff = false) {
        if ($textMine!=$textYours) {
            $diffMine = $this->buildDiff($textSource, $textMine);
            $diffYours = $this->buildDiff($textSource, $textYours);
            $diff = $this->mergeDiff($diffMine, $diffYours);
            $output = $this->getOutput($diff, $showDiff);
        } else {
            $output = $textMine;
        }
        return $output;
    }
    
    // Build differences to common source
    public function buildDiff($textSource, $textOther) {
        $diff = array();
        $lastRemove = -1;
        $textStart = 0;
        $textSource = $this->yellow->toolbox->getTextLines($textSource);
        $textOther = $this->yellow->toolbox->getTextLines($textOther);
        $sourceEnd = $sourceSize = count($textSource);
        $otherEnd = $otherSize = count($textOther);
        while ($textStart<$sourceEnd && $textStart<$otherEnd && $textSource[$textStart]==$textOther[$textStart]) {
            ++$textStart;
        }
        while ($textStart<$sourceEnd && $textStart<$otherEnd && $textSource[$sourceEnd-1]==$textOther[$otherEnd-1]) {
            --$sourceEnd;
            --$otherEnd;
        }
        for ($pos=0; $pos<$textStart; ++$pos) {
            array_push($diff, array(YellowMerge::SAME, $textSource[$pos], false));
        }
        $lcs = $this->buildDiffLCS($textSource, $textOther, $textStart, $sourceEnd-$textStart, $otherEnd-$textStart);
        for ($x=0,$y=0,$xEnd=$otherEnd-$textStart,$yEnd=$sourceEnd-$textStart; $x<$xEnd || $y<$yEnd;) {
            $max = $lcs[$y][$x];
            if ($y<$yEnd && $lcs[$y+1][$x]==$max) {
                array_push($diff, array(YellowMerge::REMOVE, $textSource[$textStart+$y], false));
                if ($lastRemove==-1) $lastRemove = count($diff)-1;
                ++$y;
                continue;
            }
            if ($x<$xEnd && $lcs[$y][$x+1]==$max) {
                if ($lastRemove==-1 || $diff[$lastRemove][0]!=YellowMerge::REMOVE) {
                    array_push($diff, array(YellowMerge::ADD, $textOther[$textStart+$x], false));
                    $lastRemove = -1;
                } else {
                    $diff[$lastRemove] = array(YellowMerge::MODIFY, $textOther[$textStart+$x], false);
                    ++$lastRemove;
                    if (count($diff)==$lastRemove) $lastRemove = -1;
                }
                ++$x;
                continue;
            }
            array_push($diff, array(YellowMerge::SAME, $textSource[$textStart+$y], false));
            $lastRemove = -1;
            ++$x;
            ++$y;
        }
        for ($pos=$sourceEnd;$pos<$sourceSize; ++$pos) {
            array_push($diff, array(YellowMerge::SAME, $textSource[$pos], false));
        }
        return $diff;
    }
    
    // Build longest common subsequence
    public function buildDiffLCS($textSource, $textOther, $textStart, $yEnd, $xEnd) {
        $lcs = array_fill(0, $yEnd+1, array_fill(0, $xEnd+1, 0));
        for ($y=$yEnd-1; $y>=0; --$y) {
            for ($x=$xEnd-1; $x>=0; --$x) {
                if ($textSource[$textStart+$y]==$textOther[$textStart+$x]) {
                    $lcs[$y][$x] = $lcs[$y+1][$x+1]+1;
                } else {
                    $lcs[$y][$x] = max($lcs[$y][$x+1], $lcs[$y+1][$x]);
                }
            }
        }
        return $lcs;
    }
    
    // Merge differences
    public function mergeDiff($diffMine, $diffYours) {
        $diff = array();
        $posMine = $posYours = 0;
        while ($posMine<count($diffMine) && $posYours<count($diffYours)) {
            $typeMine = $diffMine[$posMine][0];
            $typeYours = $diffYours[$posYours][0];
            if ($typeMine==YellowMerge::SAME) {
                array_push($diff, $diffYours[$posYours]);
            } elseif ($typeYours==YellowMerge::SAME) {
                array_push($diff, $diffMine[$posMine]);
            } elseif ($typeMine==YellowMerge::ADD && $typeYours==YellowMerge::ADD) {
                $this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
            } elseif ($typeMine==YellowMerge::MODIFY && $typeYours==YellowMerge::MODIFY) {
                $this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
            } elseif ($typeMine==YellowMerge::REMOVE && $typeYours==YellowMerge::REMOVE) {
                array_push($diff, $diffMine[$posMine]);
            } elseif ($typeMine==YellowMerge::ADD) {
                array_push($diff, $diffMine[$posMine]);
            } elseif ($typeYours==YellowMerge::ADD) {
                array_push($diff, $diffYours[$posYours]);
            } else {
                $this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], true);
            }
            if (defined("DEBUG") && DEBUG>=2) echo "YellowMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
            if ($typeMine==YellowMerge::ADD || $typeYours==YellowMerge::ADD) {
                if ($typeMine==YellowMerge::ADD) ++$posMine;
                if ($typeYours==YellowMerge::ADD) ++$posYours;
            } else {
                ++$posMine;
                ++$posYours;
            }
        }
        for (;$posMine<count($diffMine); ++$posMine) {
            array_push($diff, $diffMine[$posMine]);
            $typeMine = $diffMine[$posMine][0];
            $typeYours = " ";
            if (defined("DEBUG") && DEBUG>=2) echo "YellowMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
        }
        for (;$posYours<count($diffYours); ++$posYours) {
            array_push($diff, $diffYours[$posYours]);
            $typeYours = $diffYours[$posYours][0];
            $typeMine = " ";
            if (defined("DEBUG") && DEBUG>=2) echo "YellowMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
        }
        return $diff;
    }
    
    // Merge potential conflict
    public function mergeConflict(&$diff, $diffMine, $diffYours, $conflict) {
        if (!$conflict && $diffMine[1]==$diffYours[1]) {
            array_push($diff, $diffMine);
        } else {
            array_push($diff, array($diffMine[0], $diffMine[1], true));
            array_push($diff, array($diffYours[0], $diffYours[1], true));
        }
    }
    
    // Return merged text, null if not possible
    public function getOutput($diff, $showDiff = false) {
        $output = "";
        if (!$showDiff) {
            for ($i=0; $i<count($diff); ++$i) {
                if ($diff[$i][0]!=YellowMerge::REMOVE) $output .= $diff[$i][1];
                $conflict |= $diff[$i][2];
            }
        } else {
            for ($i=0; $i<count($diff); ++$i) {
                $output .= $diff[$i][2] ? "! " : $diff[$i][0]." ";
                $output .= $diff[$i][1];
            }
        }
        return !$conflict ? $output : null;
    }
}
