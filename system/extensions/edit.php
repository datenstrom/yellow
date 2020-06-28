<?php
// Edit extension, https://github.com/datenstrom/yellow-extensions/tree/master/features/edit
// Copyright (c) 2013-2020 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowEdit {
    const VERSION = "0.8.27";
    const TYPE = "feature";
    public $yellow;         //access to API
    public $response;       //web response
    public $users;          //user accounts
    public $merge;          //text merge

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->response = new YellowEditResponse($yellow);
        $this->users = new YellowEditUsers($yellow);
        $this->merge = new YellowEditMerge($yellow);
        $this->yellow->system->setDefault("editLocation", "/edit/");
        $this->yellow->system->setDefault("editUploadNewLocation", "/media/@group/@filename");
        $this->yellow->system->setDefault("editUploadExtensions", ".gif, .jpg, .pdf, .png, .svg, .zip");
        $this->yellow->system->setDefault("editKeyboardShortcuts", "ctrl+b bold, ctrl+i italic, ctrl+k strikethrough, ctrl+e code, ctrl+s save, ctrl+alt+p preview");
        $this->yellow->system->setDefault("editToolbarButtons", "auto");
        $this->yellow->system->setDefault("editEndOfLine", "auto");
        $this->yellow->system->setDefault("editNewFile", "page-new-(.*).md");
        $this->yellow->system->setDefault("editUserFile", "user.ini");
        $this->yellow->system->setDefault("editUserPasswordMinLength", "8");
        $this->yellow->system->setDefault("editUserHashAlgorithm", "bcrypt");
        $this->yellow->system->setDefault("editUserHashCost", "10");
        $this->yellow->system->setDefault("editUserHome", "/");
        $this->yellow->system->setDefault("editUserAccess", "create, edit, delete, upload");
        $this->yellow->system->setDefault("editLoginRestriction", "0");
        $this->yellow->system->setDefault("editLoginSessionTimeout", "2592000");
        $this->yellow->system->setDefault("editBruteForceProtection", "25");
        $this->users->load($this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile"));
    }
    
    // Handle request
    public function onRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->checkRequest($location)) {
            $scheme = $this->yellow->system->get("coreServerScheme");
            $address = $this->yellow->system->get("coreServerAddress");
            $base = rtrim($this->yellow->system->get("coreServerBase").$this->yellow->system->get("editLocation"), "/");
            list($scheme, $address, $base, $location, $fileName) = $this->yellow->getRequestInformation($scheme, $address, $base);
            $this->yellow->page->setRequestInformation($scheme, $address, $base, $location, $fileName);
            $statusCode = $this->processRequest($scheme, $address, $base, $location, $fileName);
        }
        return $statusCode;
    }
    
    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        if ($name=="edit" && $type=="inline") {
            $editText = "$name $text";
            if (substru($text, 0, 2)=="- ") $editText = trim(substru($text, 2));
            $output = "<a href=\"".$page->get("pageEdit")."\">".htmlspecialchars($editText)."</a>";
        }
        return $output;
    }
    
    // Handle page layout
    public function onParsePageLayout($page, $name) {
        if ($this->response->isActive()) {
            $this->response->processPageData($page);
        }
    }
    
    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header" && $this->response->isActive()) {
            $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}edit.css\" />\n";
            $output .= "<script type=\"text/javascript\" src=\"{$extensionLocation}edit.js\"></script>\n";
            $output .= "<script type=\"text/javascript\">\n";
            $output .= "// <![CDATA[\n";
            $output .= "yellow.page = ".json_encode($this->response->getPageData($page)).";\n";
            $output .= "yellow.system = ".json_encode($this->response->getSystemData()).";\n";
            $output .= "yellow.text = ".json_encode($this->response->getTextData()).";\n";
            $output .= "// ]]>\n";
            $output .= "</script>\n";
        }
        return $output;
    }
    
    // Handle command
    public function onCommand($command, $text) {
        switch ($command) {
            case "user":    $statusCode = $this->processCommandUser($command, $text); break;
            default:        $statusCode = 0;
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        return "user [option email password name]\n";
    }

    // Handle update
    public function onUpdate($action) {
        if ($action=="update") {
            $cleanup = false;
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $fileData = $this->yellow->toolbox->readFile($fileNameUser);
            $fileDataNew = "";
            foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
                if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                    if (lcfirst($matches[1])=="email" && !strempty($matches[2])) {
                        $status = $this->users->getUser($matches[2], "status");
                        $cleanup = !empty($status) && $status!="active" && $status!="inactive";
                    }
                }
                if (!$cleanup) $fileDataNew .= $line;
            }
            $fileDataNew = rtrim($fileDataNew)."\n";
            if ($fileData!=$fileDataNew && !$this->yellow->toolbox->createFile($fileNameUser, $fileDataNew)) {
                $this->yellow->log("error", "Can't write file '$fileNameUser'!");
            }
        }
    }
    
    // Process command to update user account
    public function processCommandUser($command, $text) {
        list($option) = $this->yellow->toolbox->getTextArguments($text);
        switch ($option) {
            case "":        $statusCode = $this->userShow($command, $text); break;
            case "add":     $statusCode = $this->userAdd($command, $text); break;
            case "change":  $statusCode = $this->userChange($command, $text); break;
            case "remove":  $statusCode = $this->userRemove($command, $text); break;
            default:        $statusCode = 400; echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Show user accounts
    public function userShow($command, $text) {
        foreach ($this->users->getData() as $line) {
            echo "$line\n";
        }
        if (!$this->users->getNumber()) echo "Yellow $command: No user accounts\n";
        return 200;
    }
    
    // Add user account
    public function userAdd($command, $text) {
        $status = "ok";
        list($option, $email, $password, $name) = $this->yellow->toolbox->getTextArguments($text);
        if (empty($email) || empty($password)) $status = $this->response->status = "incomplete";
        if (empty($name)) $name = $this->yellow->system->get("sitename");
        if ($status=="ok") $status = $this->getUserAccount($email, $password, "add");
        if ($status=="ok" && $this->users->isTaken($email)) $status = "taken";
        switch ($status) {
            case "incomplete":  echo "ERROR updating settings: Please enter email and password!\n"; break;
            case "invalid":     echo "ERROR updating settings: Please enter a valid email!\n"; break;
            case "taken":       echo "ERROR updating settings: Please enter a different email!\n"; break;
            case "weak":        echo "ERROR updating settings: Please enter a different password!\n"; break;
            case "short":       echo "ERROR updating settings: Please enter a longer password!\n"; break;
        }
        if ($status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array(
                "name" => $name,
                "language" => $this->yellow->system->get("language"),
                "home" => $this->yellow->system->get("editUserHome"),
                "access" => $this->yellow->system->get("editUserAccess"),
                "hash" => $this->users->createHash($password),
                "stamp" => $this->users->createStamp(),
                "pending" => "none",
                "failed" => "0",
                "modified" => date("Y-m-d H:i:s", time()),
                "status" => "active");
            $status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
            if ($status=="error") echo "ERROR updating settings: Can't write file '$fileNameUser'!\n";
            $this->yellow->log($status=="ok" ? "info" : "error", "Add user '".strtok($name, " ")."'");
        }
        if ($status=="ok") {
            $algorithm = $this->yellow->system->get("editUserHashAlgorithm");
            $status = substru($this->users->getUser($email, "hash"), 0, 10)!="error-hash" ? "ok" : "error";
            if ($status=="error") echo "ERROR updating settings: Hash algorithm '$algorithm' not supported!\n";
        }
        $statusCode = $status=="ok" ? 200 : 500;
        echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "")."added\n";
        return $statusCode;
    }
    
    // Change user account
    public function userChange($command, $text) {
        $status = "ok";
        list($option, $email, $password, $name) = $this->yellow->toolbox->getTextArguments($text);
        if (empty($email)) $status = $this->response->status = "invalid";
        if ($status=="ok") $status = $this->getUserAccount($email, $password, "change");
        if ($status=="ok" && !$this->users->isExisting($email)) $status = "unknown";
        switch ($status) {
            case "invalid": echo "ERROR updating settings: Please enter a valid email!\n"; break;
            case "unknown": echo "ERROR updating settings: Can't find email '$email'!\n"; break;
            case "weak":    echo "ERROR updating settings: Please enter a different password!\n"; break;
            case "short":   echo "ERROR updating settings: Please enter a longer password!\n"; break;
        }
        if ($status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array(
                "name" => empty($name) ? $this->users->getUser($email, "name") : $name,
                "hash" => empty($password) ? $this->users->getUser($email, "hash") : $this->users->createHash($password),
                "failed" => "0",
                "modified" => date("Y-m-d H:i:s", time()));
            $status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
            if ($status=="error") echo "ERROR updating settings: Can't write file '$fileNameUser'!\n";
        }
        $statusCode = $status=="ok" ? 200 : 500;
        echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "")."changed\n";
        return $statusCode;
    }

    // Remove user account
    public function userRemove($command, $text) {
        $status = "ok";
        list($option, $email) = $this->yellow->toolbox->getTextArguments($text);
        $name = $this->users->getUser($email, "name");
        if (empty($email)) $status = $this->response->status = "invalid";
        if (empty($name)) $name = $this->yellow->system->get("sitename");
        if ($status=="ok") $status = $this->getUserAccount($email, "", "remove");
        if ($status=="ok" && !$this->users->isExisting($email)) $status = "unknown";
        switch ($status) {
            case "invalid": echo "ERROR updating settings: Please enter a valid email!\n"; break;
            case "unknown": echo "ERROR updating settings: Can't find email '$email'!\n"; break;
        }
        if ($status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $status = $this->users->remove($fileNameUser, $email) ? "ok" : "error";
            if ($status=="error") echo "ERROR updating settings: Can't write file '$fileNameUser'!\n";
            $this->yellow->log($status=="ok" ? "info" : "error", "Remove user '".strtok($name, " ")."'");
        }
        $statusCode = $status=="ok" ? 200 : 500;
        echo "Yellow $command: User account ".($statusCode!=200 ? "not " : "")."removed\n";
        return $statusCode;
    }
    
    // Process request
    public function processRequest($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->checkUserAuth($scheme, $address, $base, $location, $fileName)) {
            switch ($this->yellow->page->getRequest("action")) {
                case "":            $statusCode = $this->processRequestShow($scheme, $address, $base, $location, $fileName); break;
                case "login":       $statusCode = $this->processRequestLogin($scheme, $address, $base, $location, $fileName); break;
                case "logout":      $statusCode = $this->processRequestLogout($scheme, $address, $base, $location, $fileName); break;
                case "quit":        $statusCode = $this->processRequestQuit($scheme, $address, $base, $location, $fileName); break;
                case "account":     $statusCode = $this->processRequestAccount($scheme, $address, $base, $location, $fileName); break;
                case "system":      $statusCode = $this->processRequestSystem($scheme, $address, $base, $location, $fileName); break;
                case "update":      $statusCode = $this->processRequestUpdate($scheme, $address, $base, $location, $fileName); break;
                case "create":      $statusCode = $this->processRequestCreate($scheme, $address, $base, $location, $fileName); break;
                case "edit":        $statusCode = $this->processRequestEdit($scheme, $address, $base, $location, $fileName); break;
                case "delete":      $statusCode = $this->processRequestDelete($scheme, $address, $base, $location, $fileName); break;
                case "preview":     $statusCode = $this->processRequestPreview($scheme, $address, $base, $location, $fileName); break;
                case "upload":      $statusCode = $this->processRequestUpload($scheme, $address, $base, $location, $fileName); break;
            }
        } elseif ($this->checkUserUnauth($scheme, $address, $base, $location, $fileName)) {
            $this->yellow->lookup->requestHandler = "core";
            switch ($this->yellow->page->getRequest("action")) {
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
        if ($statusCode==0) $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
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
                $this->yellow->page->error($this->response->isUserAccess("create", $location) ? 434 : 404);
                $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
            }
        }
        return $statusCode;
    }

    // Process request for user login
    public function processRequestLogin($scheme, $address, $base, $location, $fileName) {
        $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
        $settings = array("failed" => "0", "modified" => date("Y-m-d H:i:s", time()));
        if ($this->users->save($fileNameUser, $this->response->userEmail, $settings)) {
            $home = $this->users->getUser($this->response->userEmail, "home");
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
            $this->yellow->system->get("coreServerScheme"),
            $this->yellow->system->get("coreServerAddress"),
            $this->yellow->system->get("coreServerBase"),
            $location);
        $statusCode = $this->yellow->sendStatus(302, $location);
        return $statusCode;
    }

    // Process request for user signup
    public function processRequestSignup($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "signup";
        $this->response->status = "ok";
        $name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $this->yellow->page->getRequest("name")));
        $email = trim($this->yellow->page->getRequest("email"));
        $password = trim($this->yellow->page->getRequest("password"));
        $consent = trim($this->yellow->page->getRequest("consent"));
        if (empty($name) || empty($email) || empty($password) || empty($consent)) $this->response->status = "incomplete";
        if ($this->response->status=="ok") $this->response->status = $this->getUserAccount($email, $password, $this->response->action);
        if ($this->response->status=="ok" && $this->response->isLoginRestriction()) $this->response->status = "next";
        if ($this->response->status=="ok" && $this->users->isTaken($email)) $this->response->status = "next";
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array(
                "name" => $name,
                "language" => $this->yellow->lookup->findLanguageFromFile($fileName, $this->yellow->system->get("language")),
                "home" => $this->yellow->system->get("editUserHome"),
                "access" => $this->yellow->system->get("editUserAccess"),
                "hash" => $this->users->createHash($password),
                "stamp" => $this->users->createStamp(),
                "pending" => "none",
                "failed" => "0",
                "modified" => date("Y-m-d H:i:s", time()),
                "status" => "unconfirmed");
            $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok") {
            $algorithm = $this->yellow->system->get("editUserHashAlgorithm");
            $this->response->status = substru($this->users->getUser($email, "hash"), 0, 10)!="error-hash" ? "ok" : "error";
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
        $email = $this->yellow->page->getRequest("email");
        $this->response->status = $this->getUserStatus($email, $this->yellow->page->getRequest("action"));
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array("failed" => "0", "modified" => date("Y-m-d H:i:s", time()), "status" => "unapproved");
            $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
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
        $email = $this->yellow->page->getRequest("email");
        $this->response->status = $this->getUserStatus($email, $this->yellow->page->getRequest("action"));
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array("failed" => "0", "modified" => date("Y-m-d H:i:s", time()), "status" => "active");
            $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            $this->yellow->log($this->response->status=="ok" ? "info" : "error", "Add user '".strtok($this->users->getUser($email, "name"), " ")."'");
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
        $email = trim($this->yellow->page->getRequest("email"));
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
        $email = trim($this->yellow->page->getRequest("email"));
        $password = trim($this->yellow->page->getRequest("password"));
        $this->response->status = $this->getUserStatus($email, $this->yellow->page->getRequest("action"));
        if ($this->response->status=="ok") {
            if (empty($password)) $this->response->status = "password";
            if ($this->response->status=="ok") $this->response->status = $this->getUserAccount($email, $password, $this->response->action);
            if ($this->response->status=="ok") {
                $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
                $settings = array("hash" => $this->users->createHash($password), "failed" => "0", "modified" => date("Y-m-d H:i:s", time()));
                $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
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
        $email = $this->yellow->page->getRequest("email");
        $this->response->status = $this->getUserStatus($email, $this->yellow->page->getRequest("action"));
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array("failed" => "0", "modified" => date("Y-m-d H:i:s", time()), "status" => "active");
            $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "done" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
        return $statusCode;
    }
    
    // Process request to verify email
    public function processRequestVerify($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "verify";
        $this->response->status = "ok";
        $email = $emailSource = $this->yellow->page->getRequest("email");
        $this->response->status = $this->getUserStatus($email, $this->yellow->page->getRequest("action"));
        if ($this->response->status=="ok") {
            $emailSource = $this->users->getUser($email, "pending");
            if ($this->users->getUser($emailSource, "status")!="active") $this->response->status = "done";
        }
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array("failed" => "0", "modified" => date("Y-m-d H:i:s", time()), "status" => "unchanged");
            $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
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
        $email = $emailSource = trim($this->yellow->page->getRequest("email"));
        $this->response->status = $this->getUserStatus($email, $this->yellow->page->getRequest("action"));
        if ($this->response->status=="ok") {
            list($email, $hash) = $this->yellow->toolbox->getTextList($this->users->getUser($email, "pending"), ":", 2);
            if (!$this->users->isExisting($email) || empty($hash)) $this->response->status = "done";
        }
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array(
                "hash" => $hash,
                "pending" => "none",
                "failed" => "0",
                "modified" => date("Y-m-d H:i:s", time()),
                "status" => "active");
            $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
        }
        if ($this->response->status=="ok" && $email!=$emailSource) {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
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
    
    // Process request to quit account
    public function processRequestQuit($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "quit";
        $this->response->status = "ok";
        $name = trim($this->yellow->page->getRequest("name"));
        $email = $this->response->userEmail;
        if (empty($name)) $this->response->status = "none";
        if ($this->response->status=="ok" && $name!=$this->users->getUser($email, "name")) $this->response->status = "mismatch";
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
        $email = $this->yellow->page->getRequest("email");
        $this->response->status = $this->getUserStatus($email, $this->yellow->page->getRequest("action"));
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
            $settings = array("failed" => "0", "modified" => date("Y-m-d H:i:s", time()), "status" => "removed");
            $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            $this->yellow->log($this->response->status=="ok" ? "info" : "error", "Remove user '".strtok($this->users->getUser($email, "name"), " ")."'");
        }
        if ($this->response->status=="ok") {
            $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, "goodbye") ? "ok" : "error";
            if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
        }
        if ($this->response->status=="ok") {
            $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
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
    
    // Process request to change account settings
    public function processRequestAccount($scheme, $address, $base, $location, $fileName) {
        $this->response->action = "account";
        $this->response->status = "ok";
        $email = trim($this->yellow->page->getRequest("email"));
        $emailSource = $this->response->userEmail;
        $password = trim($this->yellow->page->getRequest("password"));
        $name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $this->yellow->page->getRequest("name")));
        $language = trim($this->yellow->page->getRequest("language"));
        if ($email!=$emailSource || !empty($password)) {
            if (empty($email)) $this->response->status = "invalid";
            if ($this->response->status=="ok") $this->response->status = $this->getUserAccount($email, $password, $this->response->action);
            if ($this->response->status=="ok" && $email!=$emailSource && $this->users->isTaken($email)) $this->response->status = "taken";
            if ($this->response->status=="ok" && $email!=$emailSource) {
                $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
                $settings = array(
                    "name" => $name,
                    "language" => $language,
                    "home" => $this->users->getUser($emailSource, "home"),
                    "access" => $this->users->getUser($emailSource, "access"),
                    "hash" => $this->users->createHash("none"),
                    "stamp" => $this->users->createStamp(),
                    "pending" => $emailSource,
                    "failed" => "0",
                    "modified" => date("Y-m-d H:i:s", time()),
                    "status" => "unverified");
                $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "ok" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            }
            if ($this->response->status=="ok") {
                $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
                $settings = array(
                    "name" => $name,
                    "language" => $language,
                    "pending" => $email.":".(empty($password) ? $this->users->getUser($emailSource, "hash") : $this->users->createHash($password)),
                    "failed" => "0",
                    "modified" => date("Y-m-d H:i:s", time()));
                $this->response->status = $this->users->save($fileNameUser, $emailSource, $settings) ? "ok" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
            }
            if ($this->response->status=="ok") {
                $action = $email!=$emailSource ? "verify" : "change";
                $this->response->status = $this->response->sendMail($scheme, $address, $base, $email, $action) ? "next" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't send email on this server!");
            }
        } else {
            if ($this->response->status=="ok") {
                $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
                $settings = array("name" => $name, "language" => $language, "failed" => "0", "modified" => date("Y-m-d H:i:s", time()));
                $this->response->status = $this->users->save($fileNameUser, $email, $settings) ? "done" : "error";
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
    
    // Process request to change system settings
    public function processRequestSystem($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->response->isUserAccess("system")) {
            $this->response->action = "system";
            $this->response->status = "ok";
            $sitename = trim($this->yellow->page->getRequest("sitename"));
            $author = trim($this->yellow->page->getRequest("author"));
            $email = trim($this->yellow->page->getRequest("email"));
            if ($email!=$this->yellow->system->get("email")) {
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $this->response->status = "invalid";
            }
            if ($this->response->status=="ok") {
                $fileName = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("coreSystemFile");
                $settings = array("sitename" => $sitename, "author" => $author, "email" => $email);
                $file = $this->response->getFileSystem($scheme, $address, $base, $location, $fileName, $settings);
                $this->response->status = (!$file->isError() && $this->yellow->system->save($fileName, $settings)) ? "done" : "error";
                if ($this->response->status=="error") $this->yellow->page->error(500, "Can't write file '$fileName'!");
            }
            if ($this->response->status=="done") {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->yellow->sendStatus(303, $location);
            } else {
                $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
            }
        }
        return $statusCode;
    }

    // Process request to update website
    public function processRequestUpdate($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->response->isUserAccess("update")) {
            $this->response->action = "update";
            $this->response->status = "ok";
            $extension = trim($this->yellow->page->getRequest("extension"));
            $option = trim($this->yellow->page->getRequest("option"));
            if ($option=="check") {
                list($statusCode, $updates, $rawData) = $this->response->getUpdateInformation();
                $this->response->status = $updates ? "updates" : "ok";
                $this->response->rawDataOutput = $rawData;
                if ($statusCode!=200) {
                    $this->response->status = "error";
                    $this->response->rawDataOutput = "";
                }
            } else {
                $this->response->status = $this->yellow->command("update $extension $option")==0 ? "done" : "error";
            }
            if ($this->response->status=="done") {
                $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                $statusCode = $this->yellow->sendStatus(303, $location);
            } else {
                $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
            }
        }
        return $statusCode;
    }
    
    // Process request to create page
    public function processRequestCreate($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->response->isUserAccess("create", $location) && !empty($this->yellow->page->getRequest("rawdataedit"))) {
            $this->response->rawDataSource = $this->yellow->page->getRequest("rawdatasource");
            $this->response->rawDataEdit = $this->yellow->page->getRequest("rawdatasource");
            $this->response->rawDataEndOfLine = $this->yellow->page->getRequest("rawdataendofline");
            $rawData = $this->yellow->page->getRequest("rawdataedit");
            $page = $this->response->getPageNew($scheme, $address, $base, $location, $fileName,
                $rawData, $this->response->getEndOfLine());
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
        if ($this->response->isUserAccess("edit", $location) && !empty($this->yellow->page->getRequest("rawdataedit"))) {
            $this->response->rawDataSource = $this->yellow->page->getRequest("rawdatasource");
            $this->response->rawDataEdit = $this->yellow->page->getRequest("rawdataedit");
            $this->response->rawDataEndOfLine = $this->yellow->page->getRequest("rawdataendofline");
            $rawDataFile = $this->yellow->toolbox->readFile($fileName);
            $page = $this->response->getPageEdit($scheme, $address, $base, $location, $fileName,
                $this->response->rawDataSource, $this->response->rawDataEdit, $rawDataFile, $this->response->rawDataEndOfLine);
            if (!$page->isError()) {
                if ($this->yellow->lookup->isFileLocation($location)) {
                    if ($this->yellow->toolbox->renameFile($fileName, $page->fileName, true) &&
                        $this->yellow->toolbox->createFile($page->fileName, $page->rawData)) {
                        $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $page->location);
                        $statusCode = $this->yellow->sendStatus(303, $location);
                    } else {
                        $this->yellow->page->error(500, "Can't write file '$page->fileName'!");
                        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
                    }
                } else {
                    if ($this->yellow->toolbox->renameDirectory(dirname($fileName), dirname($page->fileName), true) &&
                        $this->yellow->toolbox->createFile($page->fileName, $page->rawData)) {
                        $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $page->location);
                        $statusCode = $this->yellow->sendStatus(303, $location);
                    } else {
                        $this->yellow->page->error(500, "Can't write file '$page->fileName'!");
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

    // Process request to delete page
    public function processRequestDelete($scheme, $address, $base, $location, $fileName) {
        $statusCode = 0;
        if ($this->response->isUserAccess("delete", $location) && is_file($fileName)) {
            $this->response->rawDataSource = $this->yellow->page->getRequest("rawdatasource");
            $this->response->rawDataEdit = $this->yellow->page->getRequest("rawdatasource");
            $this->response->rawDataEndOfLine = $this->yellow->page->getRequest("rawdataendofline");
            $rawDataFile = $this->yellow->toolbox->readFile($fileName);
            $page = $this->response->getPageDelete($scheme, $address, $base, $location, $fileName,
                $rawDataFile, $this->response->rawDataEndOfLine);
            if (!$page->isError()) {
                if ($this->yellow->lookup->isFileLocation($location)) {
                    if ($this->yellow->toolbox->deleteFile($fileName, $this->yellow->system->get("coreTrashDirectory"))) {
                        $location = $this->yellow->lookup->normaliseUrl($scheme, $address, $base, $location);
                        $statusCode = $this->yellow->sendStatus(303, $location);
                    } else {
                        $this->yellow->page->error(500, "Can't delete file '$fileName'!");
                        $statusCode = $this->yellow->processRequest($scheme, $address, $base, $location, $fileName, false);
                    }
                } else {
                    if ($this->yellow->toolbox->deleteDirectory(dirname($fileName), $this->yellow->system->get("coreTrashDirectory"))) {
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
            $this->yellow->page->getRequest("rawdataedit"), $this->yellow->page->getRequest("rawdataendofline"));
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
        $extensions = preg_split("/\s*,\s*/", $this->yellow->system->get("editUploadExtensions"));
        if ($this->response->isUserAccess("upload", $location) && is_uploaded_file($fileNameTemp) &&
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
        $statusCode = $this->yellow->sendData(isset($data["error"]) ? 500 : 200, json_encode($data), "a.json", false);
        return $statusCode;
    }
    
    // Check request
    public function checkRequest($location) {
        $locationLength = strlenu($this->yellow->system->get("editLocation"));
        $this->response->active = substru($location, 0, $locationLength)==$this->yellow->system->get("editLocation");
        return $this->response->isActive();
    }
    
    // Check user authentication
    public function checkUserAuth($scheme, $address, $base, $location, $fileName) {
        $action = $this->yellow->page->getRequest("action");
        $authToken = $this->yellow->toolbox->getCookie("authtoken");
        $csrfToken = $this->yellow->toolbox->getCookie("csrftoken");
        if (empty($action) || $this->isRequestSameSite("POST", $scheme, $address)) {
            if ($action=="login") {
                $email = $this->yellow->page->getRequest("email");
                $password = $this->yellow->page->getRequest("password");
                if ($this->users->checkAuthLogin($email, $password)) {
                    $this->response->createCookies($scheme, $address, $base, $email);
                    $this->response->userEmail = $email;
                    $this->response->language = $this->getUserLanguage($email);
                } else {
                    $this->response->userFailedError = "login";
                    $this->response->userFailedEmail = $email;
                    $this->response->userFailedExpire = PHP_INT_MAX;
                }
            } elseif (!empty($authToken) && !empty($csrfToken)) {
                $csrfTokenReceived = isset($_POST["csrftoken"]) ? $_POST["csrftoken"] : "";
                $csrfTokenIrrelevant = empty($action);
                if ($this->users->checkAuthToken($authToken, $csrfToken, $csrfTokenReceived, $csrfTokenIrrelevant)) {
                    $this->response->userEmail = $email = $this->users->getAuthEmail($authToken);
                    $this->response->language = $this->getUserLanguage($email);
                } else {
                    $this->response->userFailedError = "auth";
                    $this->response->userFailedEmail = $this->users->getAuthEmail($authToken);
                    $this->response->userFailedExpire = $this->users->getAuthExpire($authToken);
                }
            }
        }
        return $this->response->isUser();
    }

    // Check user without authentication
    public function checkUserUnauth($scheme, $address, $base, $location, $fileName) {
        $ok = false;
        $action = $this->yellow->page->getRequest("action");
        if (empty($action) || $action=="signup" || $action=="forgot") {
            $ok = true;
        } elseif ($this->yellow->page->isRequest("actiontoken")) {
            $actionToken = $this->yellow->page->getRequest("actiontoken");
            $email = $this->yellow->page->getRequest("email");
            $action = $this->yellow->page->getRequest("action");
            $expire = $this->yellow->page->getRequest("expire");
            $langauge = $this->yellow->page->getRequest("language");
            if ($this->users->checkActionToken($actionToken, $email, $action, $expire)) {
                $ok = true;
                $this->response->language = $this->getActionLanguage($language);
            } else {
                $this->response->userFailedError = "action";
                $this->response->userFailedEmail = $email;
                $this->response->userFailedExpire = $expire;
            }
        }
        return $ok;
    }

    // Check user failed
    public function checkUserFailed($scheme, $address, $base, $location, $fileName) {
        if (!empty($this->response->userFailedError)) {
            if ($this->response->userFailedExpire>time() && $this->users->isExisting($this->response->userFailedEmail)) {
                $email = $this->response->userFailedEmail;
                $failed = $this->users->getUser($email, "failed")+1;
                $fileNameUser = $this->yellow->system->get("coreSettingDirectory").$this->yellow->system->get("editUserFile");
                $status = $this->users->save($fileNameUser, $email, array("failed" => $failed)) ? "ok" : "error";
                if ($status=="error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
                if ($failed==$this->yellow->system->get("editBruteForceProtection")) {
                    $statusBeforeProtection = $this->users->getUser($email, "status");
                    $statusAfterProtection = ($statusBeforeProtection=="active" || $statusBeforeProtection=="inactive") ? "inactive" : "failed";
                    if ($status=="ok") {
                        $status = $this->users->save($fileNameUser, $email, array("status" => $statusAfterProtection)) ? "ok" : "error";
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
        return $this->users->getUser($email, "status")==$statusExpected ? "ok" : "done";
    }

    // Return user account changes
    public function getUserAccount($email, $password, $action) {
        $status = null;
        foreach ($this->yellow->extensions->extensions as $key=>$value) {
            if (method_exists($value["obj"], "onEditUserAccount")) {
                $status = $value["obj"]->onEditUserAccount($email, $password, $action, $this->users);
                if (!is_null($status)) break;
            }
        }
        if (is_null($status)) {
            $status = "ok";
            if (!empty($password) && strlenu($password)<$this->yellow->system->get("editUserPasswordMinLength")) $status = "short";
            if (!empty($password) && $password==$email) $status = "weak";
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $status = "invalid";
        }
        return $status;
    }
    
    // Return user language
    public function getUserLanguage($email) {
        $language = $this->users->getUser($email, "language");
        if (!$this->yellow->text->isLanguage($language)) $language = $this->yellow->system->get("language");
        return $language;
    }

    // Return action language
    public function getActionLanguage($language) {
        if (!$this->yellow->text->isLanguage($language)) $language = $this->yellow->system->get("language");
        return $language;
    }
    
    // Check if request came from same site
    public function isRequestSameSite($method, $scheme, $address) {
        $origin = "";
        if (preg_match("#^(\w+)://([^/]+)(.*)$#", $this->yellow->toolbox->getServer("HTTP_REFERER"), $matches)) $origin = "$matches[1]://$matches[2]";
        if ($this->yellow->toolbox->getServer("HTTP_ORIGIN")) $origin = $this->yellow->toolbox->getServer("HTTP_ORIGIN");
        return $this->yellow->toolbox->getServer("REQUEST_METHOD")==$method && $origin=="$scheme://$address";
    }
}
    
class YellowEditResponse {
    public $yellow;             //access to API
    public $extension;          //access to extension
    public $active;             //location is active? (boolean)
    public $userEmail;          //user email
    public $userFailedError;    //error of failed authentication
    public $userFailedEmail;    //email of failed authentication
    public $userFailedExpire;   //expiration time of failed authentication
    public $rawDataSource;      //raw data of page for comparison
    public $rawDataEdit;        //raw data of page for editing
    public $rawDataOutput;      //raw data of dynamic output
    public $rawDataReadonly;    //raw data is read only? (boolean)
    public $rawDataEndOfLine;   //end of line format for raw data
    public $language;           //response language
    public $action;             //response action
    public $status;             //response status
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->extension = $yellow->extensions->get("edit");
    }
    
    // Process page data
    public function processPageData($page) {
        if ($this->isUser()) {
            if (empty($this->rawDataSource)) $this->rawDataSource = $page->rawData;
            if (empty($this->rawDataEdit)) $this->rawDataEdit = $page->rawData;
            if (empty($this->rawDataEndOfLine)) $this->rawDataEndOfLine = $this->getEndOfLine($page->rawData);
            if ($page->statusCode==404 || $this->yellow->toolbox->isLocationArguments()) {
                $this->rawDataEdit = $this->getRawDataGenerated($page);
                $this->rawDataReadonly = true;
            }
            if ($page->statusCode==434)  {
                $this->rawDataEdit = $this->getRawDataNew($page, true);
                $this->rawDataReadonly = false;
            }
        }
        if (empty($this->language)) $this->language = $page->get("language");
        if (empty($this->action)) $this->action = $this->isUser() ? "none" : "login";
        if (empty($this->status)) $this->status = "none";
        if ($this->status=="error") $this->action = "error";
    }
    
    // Return new page
    public function getPageNew($scheme, $address, $base, $location, $fileName, $rawData, $endOfLine) {
        $rawData = $this->yellow->toolbox->normaliseLines($rawData, $endOfLine);
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $page->parseData($rawData, false, 0);
        $this->editContentFile($page, "create");
        if ($this->yellow->content->find($page->location)) {
            $page->location = $this->getPageNewLocation($page->rawData, $page->location, $page->get("pageNewLocation"));
            $page->fileName = $this->getPageNewFile($page->location, $page->fileName, $page->get("published"));
            while ($this->yellow->content->find($page->location) || empty($page->fileName)) {
                $page->rawData = $this->yellow->toolbox->setMetaData($page->rawData, "title", $this->getTitleNext($page->rawData));
                $page->rawData = $this->yellow->toolbox->normaliseLines($page->rawData, $endOfLine);
                $page->location = $this->getPageNewLocation($page->rawData, $page->location, $page->get("pageNewLocation"));
                $page->fileName = $this->getPageNewFile($page->location, $page->fileName, $page->get("published"));
                if (++$pageCounter>999) break;
            }
            if ($this->yellow->content->find($page->location) || empty($page->fileName)) {
                $page->error(500, "Page '".$page->get("title")."' is not possible!");
            }
        } else {
            $page->fileName = $this->getPageNewFile($page->location);
        }
        if (!$this->isUserAccess("create", $page->location)) {
            $page->error(500, "Page '".$page->get("title")."' is restricted!");
        }
        return $page;
    }
    
    // Return modified page
    public function getPageEdit($scheme, $address, $base, $location, $fileName, $rawDataSource, $rawDataEdit, $rawDataFile, $endOfLine) {
        $rawDataSource = $this->yellow->toolbox->normaliseLines($rawDataSource, $endOfLine);
        $rawDataEdit = $this->yellow->toolbox->normaliseLines($rawDataEdit, $endOfLine);
        $rawDataFile = $this->yellow->toolbox->normaliseLines($rawDataFile, $endOfLine);
        $rawData = $this->extension->merge->merge($rawDataSource, $rawDataEdit, $rawDataFile);
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $page->parseData($rawData, false, 0);
        $pageSource = new YellowPage($this->yellow);
        $pageSource->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $pageSource->parseData(($rawDataSource), false, 0);
        $this->editContentFile($page, "edit");
        if ($this->isMetaModified($pageSource, $page) && $page->location!=$this->yellow->content->getHomeLocation($page->location)) {
            $page->location = $this->getPageNewLocation($page->rawData, $page->location, $page->get("pageNewLocation"), true);
            $page->fileName = $this->getPageNewFile($page->location, $page->fileName, $page->get("published"));
            if ($page->location!=$pageSource->location && ($this->yellow->content->find($page->location) || empty($page->fileName))) {
                $page->error(500, "Page '".$page->get("title")."' is not possible!");
            }
        }
        if (empty($page->rawData)) $page->error(500, "Page has been modified by someone else!");
        if (!$this->isUserAccess("edit", $page->location) ||
            !$this->isUserAccess("edit", $pageSource->location)) {
            $page->error(500, "Page '".$page->get("title")."' is restricted!");
        }
        return $page;
    }
    
    // Return deleted page
    public function getPageDelete($scheme, $address, $base, $location, $fileName, $rawData, $endOfLine) {
        $rawData = $this->yellow->toolbox->normaliseLines($rawData, $endOfLine);
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $page->parseData($rawData, false, 0);
        $this->editContentFile($page, "delete");
        if (!$this->isUserAccess("delete", $page->location)) {
            $page->error(500, "Page '".$page->get("title")."' is restricted!");
        }
        return $page;
    }

    // Return preview page
    public function getPagePreview($scheme, $address, $base, $location, $fileName, $rawData, $endOfLine) {
        $rawData = $this->yellow->toolbox->normaliseLines($rawData, $endOfLine);
        $page = new YellowPage($this->yellow);
        $page->setRequestInformation($scheme, $address, $base, $location, $fileName);
        $page->parseData($rawData, false, 200);
        $this->yellow->text->setLanguage($page->get("language"));
        $class = "page-preview layout-".$page->get("layout");
        $output = "<div class=\"".htmlspecialchars($class)."\"><div class=\"content\"><div class=\"main\">";
        if ($this->yellow->system->get("editToolbarButtons")!="none") $output .= "<h1>".$page->getHtml("titleContent")."</h1>\n";
        $output .= $page->getContent();
        $output .= "</div></div></div>";
        $page->setOutput($output);
        return $page;
    }
    
    // Return uploaded file
    public function getFileUpload($scheme, $address, $base, $pageLocation, $fileNameTemp, $fileNameShort) {
        $file = new YellowPage($this->yellow);
        $file->setRequestInformation($scheme, $address, $base, "/".$fileNameTemp, $fileNameTemp);
        $file->parseData(null, false, 0);
        $file->set("fileNameShort", $fileNameShort);
        $file->set("type", $this->yellow->toolbox->getFileType($fileNameShort));
        if ($file->get("type")=="html" || $file->get("type")=="svg") {
            $fileData = $this->yellow->toolbox->readFile($fileNameTemp);
            $fileData = $this->yellow->toolbox->normaliseData($fileData, $file->get("type"));
            if (empty($fileData) || !$this->yellow->toolbox->createFile($fileNameTemp, $fileData)) {
                $file->error(500, "Can't write file '$fileNameTemp'!");
            }
        }
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

    // Return system file
    public function getFileSystem($scheme, $address, $base, $pageLocation, $fileName, $settings) {
        $file = new YellowPage($this->yellow);
        $file->setRequestInformation($scheme, $address, $base, "/".$fileName, $fileName);
        $file->parseData(null, false, 0);
        foreach ($settings as $key=>$value) $file->set($key, $value);
        $this->editSystemFile($file, "system");
        return $file;
    }
    
    // Return page data including status information
    public function getPageData($page) {
        $data = array();
        if ($this->isUser()) {
            $data["title"] = $this->yellow->toolbox->getMetaData($this->rawDataEdit, "title");
            $data["rawDataSource"] = $this->rawDataSource;
            $data["rawDataEdit"] = $this->rawDataEdit;
            $data["rawDataNew"] = $this->getRawDataNew($page);
            $data["rawDataOutput"] = strval($this->rawDataOutput);
            $data["rawDataReadonly"] = intval($this->rawDataReadonly);
            $data["rawDataEndOfLine"] = $this->rawDataEndOfLine;
            $data["scheme"] = $this->yellow->page->scheme;
            $data["address"] = $this->yellow->page->address;
            $data["base"] = $this->yellow->page->base;
            $data["location"] = $this->yellow->page->location;
        }
        if ($this->action!="none") $data = array_merge($data, $this->getRequestData());
        $data["action"] = $this->action;
        $data["status"] = $this->status;
        $data["statusCode"] = $this->yellow->page->statusCode;
        return $data;
    }
    
    // Return system data including user information
    public function getSystemData() {
        $data = $this->yellow->system->getData("", "Location");
        if ($this->isUser()) {
            $data["userEmail"] = $this->userEmail;
            $data["userName"] = $this->extension->users->getUser($this->userEmail, "name");
            $data["userLanguage"] = $this->extension->users->getUser($this->userEmail, "language");
            $data["userStatus"] = $this->extension->users->getUser($this->userEmail, "status");
            $data["userHome"] = $this->extension->users->getUser($this->userEmail, "home");
            $data["userAccess"] = $this->extension->users->getUser($this->userEmail, "access");
            $data["coreServerScheme"] = $this->yellow->system->get("coreServerScheme");
            $data["coreServerAddress"] = $this->yellow->system->get("coreServerAddress");
            $data["coreServerBase"] = $this->yellow->system->get("coreServerBase");
            $data["coreFileSizeMax"] = $this->yellow->toolbox->getNumberBytes(ini_get("upload_max_filesize"));
            $data["coreVersion"] = "Datenstrom Yellow ".YellowCore::VERSION;
            $data["coreExtensions"] = array();
            foreach ($this->yellow->extensions->extensions as $key=>$value) {
                $data["coreExtensions"][$key] = $value["type"];
            }
            $data["coreLanguages"] = array();
            foreach ($this->yellow->text->getLanguages() as $language) {
                $data["coreLanguages"][$language] = $this->yellow->text->getTextHtml("languageDescription", $language);
            }
            $data["editSettingsActions"] = $this->getSettingsActions();
            $data["editUploadExtensions"] = $this->yellow->system->get("editUploadExtensions");
            $data["editKeyboardShortcuts"] = $this->yellow->system->get("editKeyboardShortcuts");
            $data["editToolbarButtons"] = $this->getToolbarButtons();
            $data["editStatusValues"] = $this->getStatusValues();
            $data["emojiawesomeToolbarButtons"] = $this->yellow->system->get("emojiawesomeToolbarButtons");
            $data["fontawesomeToolbarButtons"] = $this->yellow->system->get("fontawesomeToolbarButtons");
            if ($this->isUserAccess("system")) {
                $data["sitename"] = $this->yellow->system->get("sitename");
                $data["author"] = $this->yellow->system->get("author");
                $data["email"] = $this->yellow->system->get("email");
            }
        } else {
            $data["editLoginEmail"] = $this->yellow->page->get("editLoginEmail");
            $data["editLoginPassword"] = $this->yellow->page->get("editLoginPassword");
            $data["editLoginRestriction"] = intval($this->isLoginRestriction());
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
        return array_merge($textLanguage, $textEdit);
    }
    
    // Return settings actions
    public function getSettingsActions() {
        $settingsActions = "account";
        if ($this->isUserAccess("system")) $settingsActions .= ", system";
        if ($this->isUserAccess("update")) $settingsActions .= ", update";
        return $settingsActions=="account" ? "" : $settingsActions;
    }
    
    // Return toolbar buttons
    public function getToolbarButtons() {
        $toolbarButtons = $this->yellow->system->get("editToolbarButtons");
        if ($toolbarButtons=="auto") {
            $toolbarButtons = "";
            if ($this->yellow->extensions->isExisting("markdown")) $toolbarButtons = "format, bold, italic, strikethrough, code, separator, list, link, file";
            if ($this->yellow->extensions->isExisting("emojiawesome")) $toolbarButtons .= ", emojiawesome";
            if ($this->yellow->extensions->isExisting("fontawesome")) $toolbarButtons .= ", fontawesome";
            $toolbarButtons .= ", status, preview";
        }
        return $toolbarButtons;
    }
    
    // Return status values
    public function getStatusValues() {
        $statusValues = "";
        if ($this->yellow->extensions->isExisting("private")) $statusValues .= ", private";
        if ($this->yellow->extensions->isExisting("draft")) $statusValues .= ", draft";
        $statusValues .= ", unlisted";
        return ltrim($statusValues, ", ");
    }
    
    // Return end of line format
    public function getEndOfLine($rawData = "") {
        $endOfLine = $this->yellow->system->get("editEndOfLine");
        if ($endOfLine=="auto") {
            $rawData = empty($rawData) ? PHP_EOL : substru($rawData, 0, 4096);
            $endOfLine = strposu($rawData, "\r")===false ? "lf" : "crlf";
        }
        return $endOfLine;
    }
    
    // Return update information
    public function getUpdateInformation() {
        $statusCode = 200;
        $updates = 0;
        $rawData = "";
        if ($this->yellow->extensions->isExisting("update")) {
            list($statusCodeCurrent, $dataCurrent) = $this->yellow->extensions->get("update")->getExtensionsVersion();
            list($statusCodeLatest, $dataLatest) = $this->yellow->extensions->get("update")->getExtensionsVersion(true);
            list($statusCodeModified, $dataModified) = $this->yellow->extensions->get("update")->getExtensionsModified();
            $statusCode = max($statusCodeCurrent, $statusCodeLatest, $statusCodeModified);
            foreach ($dataCurrent as $key=>$value) {
                if (strnatcasecmp($dataCurrent[$key], $dataLatest[$key])<0) {
                    $rawData .= htmlspecialchars(ucfirst($key)." $dataLatest[$key]")."<br />\n";
                    ++$updates;
                }
            }
            if ($updates==0) {
                foreach ($dataCurrent as $key=>$value) {
                    if (isset($dataModified[$key]) && isset($dataLatest[$key])) {
                        $output = $this->yellow->text->getTextHtml("editUpdateModified", $this->language)." - <a href=\"#\" data-action=\"submit\" data-arguments=\"".$this->yellow->toolbox->normaliseArguments("action:update/extension:$key/option:force")."\">".$this->yellow->text->getTextHtml("editUpdateForce", $this->language)."</a><br />\n";
                        $rawData .= preg_replace("/@extension/i", htmlspecialchars(ucfirst($key)." $dataLatest[$key]"), $output);
                    }
                }
            }
        }
        return array($statusCode, $updates, $rawData);
    }

    // Return raw data for generated page
    public function getRawDataGenerated($page) {
        $title = $page->get("title");
        $text = $this->yellow->text->getText("editDataGenerated", $page->get("language"));
        return "---\nTitle: $title\n---\n$text";
    }
    
    // Return raw data for new page
    public function getRawDataNew($page, $customTitle = false) {
        $fileName = "";
        foreach ($this->yellow->content->path($page->location)->reverse() as $ancestor) {
            if ($ancestor->isExisting("layoutNew")) {
                $name = $this->yellow->lookup->normaliseName($ancestor->get("layoutNew"));
                $location = $this->yellow->content->getHomeLocation($page->location).$this->yellow->system->get("coreContentSharedDirectory");
                $fileName = $this->yellow->lookup->findFileFromLocation($location, true).$this->yellow->system->get("editNewFile");
                $fileName = strreplaceu("(.*)", $name, $fileName);
                if (is_file($fileName)) break;
            }
        }
        if (!is_file($fileName)) {
            $name = $this->yellow->lookup->normaliseName($this->yellow->system->get("layout"));
            $location = $this->yellow->content->getHomeLocation($page->location).$this->yellow->system->get("coreContentSharedDirectory");
            $fileName = $this->yellow->lookup->findFileFromLocation($location, true).$this->yellow->system->get("editNewFile");
            $fileName = strreplaceu("(.*)", $name, $fileName);
        }
        if (is_file($fileName)) {
            $rawData = $this->yellow->toolbox->readFile($fileName);
            $rawData = preg_replace("/@timestamp/i", time(), $rawData);
            $rawData = preg_replace("/@datetime/i", date("Y-m-d H:i:s"), $rawData);
            $rawData = preg_replace("/@date/i", date("Y-m-d"), $rawData);
            $rawData = preg_replace("/@usershort/i", strtok($this->extension->users->getUser($this->userEmail, "name"), " "), $rawData);
            $rawData = preg_replace("/@username/i", $this->extension->users->getUser($this->userEmail, "name"), $rawData);
            $rawData = preg_replace("/@userlanguage/i", $this->extension->users->getUser($this->userEmail, "language"), $rawData);
        } else {
            $rawData = "---\nTitle: Page\n---\n";
        }
        if ($customTitle) {
            $title = $this->yellow->toolbox->createTextTitle($page->location);
            $rawData = $this->yellow->toolbox->setMetaData($rawData, "title", $title);
        }
        return $rawData;
    }
    
    // Return location for new/modified page
    public function getPageNewLocation($rawData, $pageLocation, $pageNewLocation, $pageMatchLocation = false) {
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
            if ($this->yellow->lookup->isFileLocation($pageLocation) || !$pageMatchLocation) {
                $location = $this->yellow->lookup->getDirectoryLocation($pageLocation).$location;
            } else {
                $location = $this->yellow->lookup->getDirectoryLocation(rtrim($pageLocation, "/")).$location;
            }
        }
        if ($pageMatchLocation) {
            $location = rtrim($location, "/").($this->yellow->lookup->isFileLocation($pageLocation) ? "" : "/");
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
    
    // Return file name for new/modified page
    public function getPageNewFile($location, $pageFileName = "", $pagePrefix = "") {
        $fileName = $this->yellow->lookup->findFileFromLocation($location);
        if (!empty($fileName)) {
            if (!is_dir(dirname($fileName))) {
                $path = "";
                $tokens = explode("/", $fileName);
                for ($i=0; $i<count($tokens)-1; ++$i) {
                    if (!is_dir($path.$tokens[$i])) {
                        if (!preg_match("/^[\d\-\_\.]+(.*)$/", $tokens[$i])) {
                            $number = 1;
                            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^[\d\-\_\.]+(.*)$/", true, true, false) as $entry) {
                                if ($number!=1 && $number!=intval($entry)) break;
                                $number = intval($entry)+1;
                            }
                            $tokens[$i] = "$number-".$tokens[$i];
                        }
                        $tokens[$i] = $this->yellow->lookup->normaliseName($tokens[$i], false, false, true);
                    }
                    $path .= $tokens[$i]."/";
                }
                $fileName = $path.$tokens[$i];
                $pageFileName = empty($pageFileName) ? $fileName : $pageFileName;
            }
            $prefix = $this->getPageNewPrefix($location, $pageFileName, $pagePrefix);
            if ($this->yellow->lookup->isFileLocation($location)) {
                if (preg_match("#^(.*)\/(.+?)$#", $fileName, $matches)) {
                    $path = $matches[1];
                    $text = $this->yellow->lookup->normaliseName($matches[2], true, true);
                    if (preg_match("/^[\d\-\_\.]*$/", $text)) $prefix = "";
                    $fileName = $path."/".$prefix.$text.$this->yellow->system->get("coreContentExtension");
                }
            } else {
                if (preg_match("#^(.*)\/(.+?)$#", dirname($fileName), $matches)) {
                    $path = $matches[1];
                    $text = $this->yellow->lookup->normaliseName($matches[2], true, false);
                    if (preg_match("/^[\d\-\_\.]*$/", $text)) $prefix = "";
                    $fileName = $path."/".$prefix.$text."/".$this->yellow->system->get("coreContentDefaultFile");
                }
            }
        }
        return $fileName;
    }
    
    // Return prefix for new/modified page
    public function getPageNewPrefix($location, $pageFileName, $pagePrefix) {
        if (empty($pagePrefix)) {
            if ($this->yellow->lookup->isFileLocation($location)) {
                if (preg_match("#^(.*)\/(.+?)$#", $pageFileName, $matches)) $pagePrefix = $matches[2];
            } else {
                if (preg_match("#^(.*)\/(.+?)$#", dirname($pageFileName), $matches)) $pagePrefix = $matches[2];
            }
        }
        return $this->yellow->lookup->normalisePrefix($pagePrefix, true);
    }
    
    // Return location for new file
    public function getFileNewLocation($fileNameShort, $pageLocation, $fileNewLocation) {
        $location = empty($fileNewLocation) ? $this->yellow->system->get("editUploadNewLocation") : $fileNewLocation;
        $location = preg_replace("/@timestamp/i", time(), $location);
        $location = preg_replace("/@type/i", $this->yellow->toolbox->getFileType($fileNameShort), $location);
        $location = preg_replace("/@group/i", $this->getFileNewGroup($fileNameShort), $location);
        $location = preg_replace("/@folder/i", $this->getFileNewFolder($pageLocation), $location);
        $location = preg_replace("/@filename/i", strtoloweru($fileNameShort), $location);
        if (!preg_match("/^\//", $location)) {
            $location = $this->yellow->system->get("coreMediaLocation").$location;
        }
        return $location;
    }
    
    // Return group for new file
    public function getFileNewGroup($fileNameShort) {
        $group = "none";
        $path = $this->yellow->system->get("coreMediaDirectory");
        $fileType = $this->yellow->toolbox->getFileType($fileNameShort);
        $fileName = $this->yellow->system->get(preg_match("/(gif|jpg|png|svg)$/", $fileType) ? "coreImageDirectory" : "coreDownloadDirectory").$fileNameShort;
        if (preg_match("#^$path(.+?)\/#", $fileName, $matches)) $group = strtoloweru($matches[1]);
        return $group;
    }

    // Return folder for new file
    public function getFileNewFolder($pageLocation) {
        $parentTopLocation = $this->yellow->content->getParentTopLocation($pageLocation);
        if ($parentTopLocation==$this->yellow->content->getHomeLocation($pageLocation)) $parentTopLocation .= "home";
        return strtoloweru(trim($parentTopLocation, "/"));
    }
    
    // Return next file name
    public function getFileNext($fileNameShort) {
        $fileText = $fileNumber = $fileExtension = "";
        if (preg_match("/^(.*?)(\d*)(\..*?)?$/", $fileNameShort, $matches)) {
            $fileText = $matches[1];
            $fileNumber = strempty($matches[2]) ? "-2" : $matches[2]+1;
            $fileExtension = $matches[3];
        }
        return $fileText.$fileNumber.$fileExtension;
    }
    
    // Return next title
    public function getTitleNext($rawData) {
        $titleText = $titleNumber = "";
        if(preg_match("/^(.*?)(\d*)$/", $this->yellow->toolbox->getMetaData($rawData, "title"), $matches)) {
            $titleText = $matches[1];
            $titleNumber = strempty($matches[2]) ? " 2" : $matches[2]+1;
        }
        return $titleText.$titleNumber;
    }
    
    // Send mail to user
    public function sendMail($scheme, $address, $base, $email, $action) {
        if ($action=="approve") {
            $userName = $this->yellow->system->get("author");
            $userEmail = $this->yellow->system->get("email");
            $userLanguage = $this->extension->getUserLanguage($userEmail);
        } else {
            $userName = $this->extension->users->getUser($email, "name");
            $userEmail = $email;
            $userLanguage = $this->extension->getUserLanguage($email);
        }
        if ($action=="welcome" || $action=="goodbye") {
            $url = "$scheme://$address$base/";
        } else {
            $expire = time() + 60*60*24;
            $actionToken = $this->extension->users->createActionToken($email, $action, $expire);
            $url = "$scheme://$address$base"."/action:$action/email:$email/expire:$expire/language:$userLanguage/actiontoken:$actionToken/";
        }
        $prefix = "edit".ucfirst($action);
        $message = $this->yellow->text->getText("{$prefix}Message", $userLanguage);
        $message = strreplaceu("\\n", "\r\n", $message);
        $message = preg_replace("/@useraccount/i", $email, $message);
        $message = preg_replace("/@usershort/i", strtok($userName, " "), $message);
        $message = preg_replace("/@username/i", $userName, $message);
        $message = preg_replace("/@userlanguage/i", $userLanguage, $message);
        $sitename = $this->yellow->system->get("sitename");
        $footer = $this->yellow->text->getText("editMailFooter", $userLanguage);
        $footer = strreplaceu("\\n", "\r\n", $footer);
        $footer = preg_replace("/@sitename/i", $sitename, $footer);
        $mailTo = mb_encode_mimeheader("$userName")." <$userEmail>";
        $mailSubject = mb_encode_mimeheader($this->yellow->text->getText("{$prefix}Subject", $userLanguage));
        $mailHeaders = mb_encode_mimeheader("From: $sitename")." <noreply>\r\n";
        $mailHeaders .= mb_encode_mimeheader("X-Request-Url: $scheme://$address$base")."\r\n";
        $mailHeaders .= "Mime-Version: 1.0\r\n";
        $mailHeaders .= "Content-Type: text/plain; charset=utf-8\r\n";
        $mailMessage = "$message\r\n\r\n$url\r\n-- \r\n$footer";
        return mail($mailTo, $mailSubject, $mailMessage, $mailHeaders);
    }
    
    // Create browser cookies
    public function createCookies($scheme, $address, $base, $email) {
        $expire = time() + $this->yellow->system->get("editLoginSessionTimeout");
        $authToken = $this->extension->users->createAuthToken($email, $expire);
        $csrfToken = $this->extension->users->createCsrfToken();
        setcookie("authtoken", $authToken, $expire, "$base/", "", $scheme=="https", true);
        setcookie("csrftoken", $csrfToken, $expire, "$base/", "", $scheme=="https", false);
    }
    
    // Destroy browser cookies
    public function destroyCookies($scheme, $address, $base) {
        setcookie("authtoken", "", 1, "$base/", "", $scheme=="https", true);
        setcookie("csrftoken", "", 1, "$base/", "", $scheme=="https", false);
    }
    
    // Change content file
    public function editContentFile($page, $action) {
        if (!$page->isError()) {
            foreach ($this->yellow->extensions->extensions as $key=>$value) {
                if (method_exists($value["obj"], "onEditContentFile")) $value["obj"]->onEditContentFile($page, $action);
            }
        }
    }

    // Change media file
    public function editMediaFile($file, $action) {
        if (!$file->isError()) {
            foreach ($this->yellow->extensions->extensions as $key=>$value) {
                if (method_exists($value["obj"], "onEditMediaFile")) $value["obj"]->onEditMediaFile($file, $action);
            }
        }
    }
    
    // Change system file
    public function editSystemFile($file, $action) {
        if (!$file->isError()) {
            foreach ($this->yellow->extensions->extensions as $key=>$value) {
                if (method_exists($value["obj"], "onEditSystemFile")) $value["obj"]->onEditSystemFile($file, $action);
            }
        }
    }
    
    // Check if meta data has been modified
    public function isMetaModified($pageSource, $pageOther) {
        return substrb($pageSource->rawData, 0, $pageSource->metaDataOffsetBytes) !=
            substrb($pageOther->rawData, 0, $pageOther->metaDataOffsetBytes);
    }
    
    // Check if active
    public function isActive() {
        return $this->active;
    }
    
    // Check if user is logged in
    public function isUser() {
        return !empty($this->userEmail);
    }
    
    // Check if user with access
    public function isUserAccess($action, $location = "") {
        $userHome = $this->extension->users->getUser($this->userEmail, "home");
        $userAccess = preg_split("/\s*,\s*/", $this->extension->users->getUser($this->userEmail, "access"));
        return in_array($action, $userAccess) && (empty($location) || substru($location, 0, strlenu($userHome))==$userHome);
    }
    
    // Check if login with restriction
    public function isLoginRestriction() {
        return $this->yellow->system->get("editLoginRestriction");
    }
}

class YellowEditUsers {
    public $yellow;     //access to API
    public $users;      //registered users
    
    public function __construct($yellow) {
        $this->yellow = $yellow;
        $this->users = array();
    }

    // Load users from file
    public function load($fileName) {
        if (defined("DEBUG") && DEBUG>=2) echo "YellowEditUsers::load file:$fileName<br/>\n";
        $fileData = $this->yellow->toolbox->readFile($fileName);
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            if (preg_match("/^\#/", $line)) continue;
            if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                if (lcfirst($matches[1])=="email" && !strempty($matches[2])) {
                    $email = $matches[2];
                    if (defined("DEBUG") && DEBUG>=3) echo "YellowEditUsers::load email:$email<br/>\n";
                }
                if (!empty($email) && !empty($matches[1]) && !strempty($matches[2])) {
                    $this->setUser($email, $matches[1], $matches[2]);
                }
            }
        }
    }

    // Save user to file
    public function save($fileName, $email, $settings) {
        $scan = false;
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileDataStart = $fileDataMiddle = $fileDataEnd = "";
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                if (lcfirst($matches[1])=="email" && !strempty($matches[2])) {
                    $scan = $matches[2]==$email;
                }
            }
            if (!$scan && empty($fileDataMiddle)) {
                $fileDataStart .= $line;
            } elseif ($scan) {
                $fileDataMiddle .= $line;
            } else {
                $fileDataEnd .= $line;
            }
        }
        $settingsNew = new YellowDataCollection();
        $settingsNew["email"] = $email;
        foreach ($settings as $key=>$value) {
            if (!empty($key) && !strempty($value)) {
                $this->setUser($email, $key, $value);
                $settingsNew[$key] = $value;
            }
        }
        $fileDataSettings = "";
        foreach ($this->yellow->toolbox->getTextLines($fileDataMiddle) as $line) {
            if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                if (!empty($matches[1]) && isset($settingsNew[$matches[1]])) {
                    $fileDataSettings .= "$matches[1]: ".$settingsNew[$matches[1]]."\n";
                    unset($settingsNew[$matches[1]]);
                    continue;
                }
            }
            $fileDataSettings .= $line;
        }
        foreach ($settingsNew as $key=>$value) {
            $fileDataSettings .= ucfirst($key).": $value\n";
        }
        if (!empty($fileDataSettings)) {
            $fileDataSettings = preg_replace("/\n+/", "\n", $fileDataSettings);
            if (!empty($fileDataStart) && substr($fileDataStart, -2)!="\n\n") $fileDataSettings = "\n".$fileDataSettings;
            if (!empty($fileDataEnd)) $fileDataSettings .= "\n";
        }
        $fileDataNew = $fileDataStart.$fileDataSettings.$fileDataEnd;
        return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
    }
    
    // Remove user from file
    public function remove($fileName, $email) {
        $scan = false;
        $fileData = $this->yellow->toolbox->readFile($fileName);
        $fileDataStart = $fileDataMiddle = $fileDataEnd = "";
        foreach ($this->yellow->toolbox->getTextLines($fileData) as $line) {
            if (preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches)) {
                if (lcfirst($matches[1])=="email" && !strempty($matches[2])) {
                    $scan = $matches[2]==$email;
                }
            }
            if (!$scan && empty($fileDataMiddle)) {
                $fileDataStart .= $line;
            } elseif ($scan) {
                $fileDataMiddle .= $line;
            } else {
                $fileDataEnd .= $line;
            }
        }
        if (isset($this->users[$email])) unset($this->users[$email]);
        $fileDataNew = rtrim($fileDataStart.$fileDataEnd)."\n";
        return $this->yellow->toolbox->createFile($fileName, $fileDataNew);
    }
    
    // Set user setting
    public function setUser($email, $key, $value) {
        if (!isset($this->users[$email])) $this->users[$email] = new YellowDataCollection();
        $this->users[$email][$key] = $value;
    }
    
    // Return user setting
    public function getUser($email, $key) {
        return isset($this->users[$email]) && isset($this->users[$email][$key]) ? $this->users[$email][$key] : "";
    }
    
    // Check user authentication from email and password
    public function checkAuthLogin($email, $password) {
        $algorithm = $this->yellow->system->get("editUserHashAlgorithm");
        return $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
            $this->yellow->toolbox->verifyHash($password, $algorithm, $this->users[$email]["hash"]);
    }

    // Check user authentication from tokens
    public function checkAuthToken($authToken, $csrfTokenExpected, $csrfTokenReceived, $csrfTokenIrrelevant) {
        $signature = "$5y$".substrb($authToken, 0, 96);
        $email = $this->getAuthEmail($authToken);
        $expire = $this->getAuthExpire($authToken);
        return $expire>time() && $this->isExisting($email) && $this->users[$email]["status"]=="active" &&
            $this->yellow->toolbox->verifyHash($this->users[$email]["hash"]."auth".$expire, "sha256", $signature) &&
            ($this->yellow->toolbox->verifyToken($csrfTokenExpected, $csrfTokenReceived) || $csrfTokenIrrelevant);
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
        return substrb($signature, 4).$this->getUser($email, "stamp").dechex($expire);
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
        $algorithm = $this->yellow->system->get("editUserHashAlgorithm");
        $cost = $this->yellow->system->get("editUserHashCost");
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
        $email = "";
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
    
    // Return number of users
    public function getNumber() {
        return count($this->users);
    }

    // Return user data
    public function getData() {
        $data = array();
        foreach ($this->users as $key=>$value) {
            $name = $value["name"];
            if (preg_match("/\s/", $name)) $name = "\"$name\"";
            $data[$key] = "$value[email] $name $value[status]";
        }
        uksort($data, "strnatcasecmp");
        return $data;
    }
    
    // Check if user is taken
    public function isTaken($email) {
        $taken = false;
        if ($this->isExisting($email)) {
            $status = $this->users[$email]["status"];
            $reserved = strtotime($this->users[$email]["modified"]) + 60*60*24;
            if ($status=="active" || $status=="inactive" || $reserved>time()) $taken = true;
        }
        return $taken;
    }
    
    // Check if user exists
    public function isExisting($email) {
        return isset($this->users[$email]);
    }
}
    
class YellowEditMerge {
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
            array_push($diff, array(YellowEditMerge::SAME, $textSource[$pos], false));
        }
        $lcs = $this->buildDiffLCS($textSource, $textOther, $textStart, $sourceEnd-$textStart, $otherEnd-$textStart);
        for ($x=0,$y=0,$xEnd=$otherEnd-$textStart,$yEnd=$sourceEnd-$textStart; $x<$xEnd || $y<$yEnd;) {
            $max = $lcs[$y][$x];
            if ($y<$yEnd && $lcs[$y+1][$x]==$max) {
                array_push($diff, array(YellowEditMerge::REMOVE, $textSource[$textStart+$y], false));
                if ($lastRemove==-1) $lastRemove = count($diff)-1;
                ++$y;
                continue;
            }
            if ($x<$xEnd && $lcs[$y][$x+1]==$max) {
                if ($lastRemove==-1 || $diff[$lastRemove][0]!=YellowEditMerge::REMOVE) {
                    array_push($diff, array(YellowEditMerge::ADD, $textOther[$textStart+$x], false));
                    $lastRemove = -1;
                } else {
                    $diff[$lastRemove] = array(YellowEditMerge::MODIFY, $textOther[$textStart+$x], false);
                    ++$lastRemove;
                    if (count($diff)==$lastRemove) $lastRemove = -1;
                }
                ++$x;
                continue;
            }
            array_push($diff, array(YellowEditMerge::SAME, $textSource[$textStart+$y], false));
            $lastRemove = -1;
            ++$x;
            ++$y;
        }
        for ($pos=$sourceEnd;$pos<$sourceSize; ++$pos) {
            array_push($diff, array(YellowEditMerge::SAME, $textSource[$pos], false));
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
            if ($typeMine==YellowEditMerge::SAME) {
                array_push($diff, $diffYours[$posYours]);
            } elseif ($typeYours==YellowEditMerge::SAME) {
                array_push($diff, $diffMine[$posMine]);
            } elseif ($typeMine==YellowEditMerge::ADD && $typeYours==YellowEditMerge::ADD) {
                $this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
            } elseif ($typeMine==YellowEditMerge::MODIFY && $typeYours==YellowEditMerge::MODIFY) {
                $this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], false);
            } elseif ($typeMine==YellowEditMerge::REMOVE && $typeYours==YellowEditMerge::REMOVE) {
                array_push($diff, $diffMine[$posMine]);
            } elseif ($typeMine==YellowEditMerge::ADD) {
                array_push($diff, $diffMine[$posMine]);
            } elseif ($typeYours==YellowEditMerge::ADD) {
                array_push($diff, $diffYours[$posYours]);
            } else {
                $this->mergeConflict($diff, $diffMine[$posMine], $diffYours[$posYours], true);
            }
            if (defined("DEBUG") && DEBUG>=2) echo "YellowEditMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
            if ($typeMine==YellowEditMerge::ADD || $typeYours==YellowEditMerge::ADD) {
                if ($typeMine==YellowEditMerge::ADD) ++$posMine;
                if ($typeYours==YellowEditMerge::ADD) ++$posYours;
            } else {
                ++$posMine;
                ++$posYours;
            }
        }
        for (;$posMine<count($diffMine); ++$posMine) {
            array_push($diff, $diffMine[$posMine]);
            $typeMine = $diffMine[$posMine][0];
            $typeYours = " ";
            if (defined("DEBUG") && DEBUG>=2) echo "YellowEditMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
        }
        for (;$posYours<count($diffYours); ++$posYours) {
            array_push($diff, $diffYours[$posYours]);
            $typeYours = $diffYours[$posYours][0];
            $typeMine = " ";
            if (defined("DEBUG") && DEBUG>=2) echo "YellowEditMerge::mergeDiff $typeMine $typeYours pos:$posMine\t$posYours<br/>\n";
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
        $conflict = false;
        if (!$showDiff) {
            for ($i=0; $i<count($diff); ++$i) {
                if ($diff[$i][0]!=YellowEditMerge::REMOVE) $output .= $diff[$i][1];
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
