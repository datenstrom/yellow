// Edit plugin, https://github.com/datenstrom/yellow-plugins/tree/master/edit
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

var yellow = {
    
    // Main event handlers
    action: function(action, status, args) { yellow.edit.action(action, status, args); },
    onLoad: function() { yellow.edit.load(); },
    onClickAction: function(e) { yellow.edit.clickAction(e); },
    onClick: function(e) { yellow.edit.click(e); },
    onKeydown: function(e) { yellow.edit.keydown(e); },
    onDrag: function(e) { yellow.edit.drag(e); },
    onDrop: function(e) { yellow.edit.drop(e); },
    onUpdate: function() { yellow.edit.updatePane(yellow.edit.paneId, yellow.edit.paneAction, yellow.edit.paneStatus); },
    onResize: function() { yellow.edit.resizePane(yellow.edit.paneId, yellow.edit.paneAction, yellow.edit.paneStatus); }
};

yellow.edit = {
    paneId: 0,          //visible pane ID
    paneActionOld: 0,   //previous pane action
    paneAction: 0,      //current pane action
    paneStatus: 0,      //current pane status
    popupId: 0,         //visible popup ID
    intervalId: 0,      //timer interval ID

    // Handle initialisation
    load: function() {
        var body = document.getElementsByTagName("body")[0];
        if (body && body.firstChild && !document.getElementById("yellow-bar")) {
            this.createBar("yellow-bar");
            this.createPane("yellow-pane-edit", "none", "none");
            this.action(yellow.page.action, yellow.page.status);
            clearInterval(this.intervalId);
        }
    },
    
    // Handle action
    action: function(action, status, args) {
        status = status ? status : "none";
        args = args ? args : "none";
        switch (action) {
            case "login":       this.showPane("yellow-pane-login", action, status); break;
            case "logout":      this.sendPane("yellow-pane-logout", action); break;
            case "signup":      this.showPane("yellow-pane-signup", action, status); break;
            case "confirm":     this.showPane("yellow-pane-signup", action, status); break;
            case "approve":     this.showPane("yellow-pane-signup", action, status); break;
            case "forgot":      this.showPane("yellow-pane-forgot", action, status); break;
            case "recover":     this.showPane("yellow-pane-recover", action, status); break;
            case "reactivate":  this.showPane("yellow-pane-settings", action, status); break;
            case "settings":    this.showPane("yellow-pane-settings", action, status); break;
            case "verify":      this.showPane("yellow-pane-settings", action, status); break;
            case "change":      this.showPane("yellow-pane-settings", action, status); break;
            case "version":     this.showPane("yellow-pane-version", action, status); break;
            case "update":      this.sendPane("yellow-pane-update", action, status, args); break;
            case "quit":        this.showPane("yellow-pane-quit", action, status); break;
            case "remove":      this.showPane("yellow-pane-quit", action, status); break;
            case "create":      this.showPane("yellow-pane-edit", action, status, true); break;
            case "edit":        this.showPane("yellow-pane-edit", action, status, true); break;
            case "delete":      this.showPane("yellow-pane-edit", action, status, true); break;
            case "user":        this.showPane("yellow-pane-user", action, status); break;
            case "send":        this.sendPane(this.paneId, this.paneAction); break;
            case "close":       this.hidePane(this.paneId); break;
            case "toolbar":     this.processToolbar(status, args); break;
            case "help":        this.processHelp(); break;
        }
    },
    
    // Handle action clicked
    clickAction: function(e) {
        e.stopPropagation();
        e.preventDefault();
        var element = e.target;
        for (; element; element=element.parentNode) {
            if (element.tagName=="A") break;
        }
        this.action(element.getAttribute("data-action"), element.getAttribute("data-status"), element.getAttribute("data-args"));
    },
    
    // Handle mouse clicked
    click: function(e) {
        if (this.popupId && !document.getElementById(this.popupId).contains(e.target)) this.hidePopup(this.popupId, true);
        if (this.paneId && !document.getElementById(this.paneId).contains(e.target)) this.hidePane(this.paneId, true);
    },
    
    // Handle keyboard
    keydown: function(e) {
        if (this.paneId=="yellow-pane-edit") this.processShortcut(e);
        if (this.paneId && e.keyCode==27) this.hidePane(this.paneId);
    },
    
    // Handle drag
    drag: function(e) {
        e.stopPropagation();
        e.preventDefault();
    },
    
    // Handle drop
    drop: function(e) {
        e.stopPropagation();
        e.preventDefault();
        var elementText = document.getElementById("yellow-pane-edit-text");
        var files = e.dataTransfer ? e.dataTransfer.files : e.target.files;
        for (var i=0; i<files.length; i++) this.uploadFile(elementText, files[i]);
    },
    
    // Create bar
    createBar: function(barId) {
        if (yellow.config.debug) console.log("yellow.edit.createBar id:"+barId);
        var elementBar = document.createElement("div");
        elementBar.className = "yellow-bar";
        elementBar.setAttribute("id", barId);
        if (barId=="yellow-bar") {
            yellow.toolbox.addEvent(document, "click", yellow.onClick);
            yellow.toolbox.addEvent(document, "keydown", yellow.onKeydown);
            yellow.toolbox.addEvent(window, "resize", yellow.onResize);
        }
        var elementDiv = document.createElement("div");
        elementDiv.setAttribute("id", barId+"-content");
        if (yellow.config.userName) {
            elementDiv.innerHTML =
                "<div class=\"yellow-bar-left\">"+
                "<a href=\"#\" id=\"yellow-pane-edit-link\" data-action=\"edit\">"+this.getText("Edit")+"</a>"+
                "</div>"+
                "<div class=\"yellow-bar-right\">"+
                "<a href=\"#\" id=\"yellow-pane-create-link\" data-action=\"create\">"+this.getText("Create")+"</a>"+
                "<a href=\"#\" id=\"yellow-pane-delete-link\" data-action=\"delete\">"+this.getText("Delete")+"</a>"+
                "<a href=\"#\" id=\"yellow-pane-user-link\" data-action=\"user\">"+yellow.toolbox.encodeHtml(yellow.config.userName)+"</a>"+
                "</div>"+
                "<div class=\"yellow-bar-banner\"></div>";
        }
        elementBar.appendChild(elementDiv);
        yellow.toolbox.insertBefore(elementBar, document.getElementsByTagName("body")[0].firstChild);
        this.bindActions(elementBar);
    },
    
    // Create pane
    createPane: function(paneId, paneAction, paneStatus) {
        if (yellow.config.debug) console.log("yellow.edit.createPane id:"+paneId);
        var elementPane = document.createElement("div");
        elementPane.className = "yellow-pane";
        elementPane.setAttribute("id", paneId);
        elementPane.style.display = "none";
        if (paneId=="yellow-pane-edit") {
            yellow.toolbox.addEvent(elementPane, "input", yellow.onUpdate);
            yellow.toolbox.addEvent(elementPane, "dragenter", yellow.onDrag);
            yellow.toolbox.addEvent(elementPane, "dragover", yellow.onDrag);
            yellow.toolbox.addEvent(elementPane, "drop", yellow.onDrop);
        }
        if (paneId=="yellow-pane-edit" || paneId=="yellow-pane-user") {
            var elementArrow = document.createElement("span");
            elementArrow.className = "yellow-arrow";
            elementArrow.setAttribute("id", paneId+"-arrow");
            elementPane.appendChild(elementArrow);
        }
        var elementDiv = document.createElement("div");
        elementDiv.className = "yellow-content";
        elementDiv.setAttribute("id", paneId+"-content");
        switch (paneId) {
            case "yellow-pane-login":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("LoginTitle")+"</h1></div>"+
                "<div class=\"yellow-fields\" id=\"yellow-pane-login-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"login\" />"+
                "<p><label for=\"yellow-pane-login-email\">"+this.getText("LoginEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-login-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.config.editLoginEmail)+"\" /></p>"+
                "<p><label for=\"yellow-pane-login-password\">"+this.getText("LoginPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-login-password\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.config.editLoginPassword)+"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-actions\" id=\"yellow-pane-login-actions\">"+
                "<p><a href=\"#\" id=\"yellow-pane-login-forgot\" data-action=\"forgot\">"+this.getText("LoginForgot")+"</a><br /><a href=\"#\" id=\"yellow-pane-login-signup\" data-action=\"signup\">"+this.getText("LoginSignup")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-signup":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("SignupTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-signup-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\" id=\"yellow-pane-signup-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"signup\" />"+
                "<p><label for=\"yellow-pane-signup-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-signup-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-signup-email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-signup-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-signup-password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-signup-password\" maxlength=\"64\" value=\"\" /></p>"+
                "<p><input type=\"checkbox\" name=\"consent\" value=\"consent\" id=\"consent\""+(this.getRequest("consent") ? " checked=\"checked\"" : "")+"> <label for=\"consent\">"+this.getText("SignupConsent")+"</label></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("SignupButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-signup-buttons\">"+
                "<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-forgot":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("ForgotTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-forgot-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\" id=\"yellow-pane-forgot-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"forgot\" />"+
                "<p><label for=\"yellow-pane-forgot-email\">"+this.getText("ForgotEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-forgot-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-forgot-buttons\">"+
                "<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-recover":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("RecoverTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-recover-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\" id=\"yellow-pane-recover-fields\">"+
                "<p><label for=\"yellow-pane-recover-password\">"+this.getText("RecoverPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-recover-password\" maxlength=\"64\" value=\"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-recover-buttons\">"+
                "<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-settings":
                var rawDataLanguages = "";
                if (yellow.config.serverLanguages && Object.keys(yellow.config.serverLanguages).length>1) {
                    rawDataLanguages += "<p>";
                    for (var language in yellow.config.serverLanguages) {
                        var checked = language==this.getRequest("language") ? " checked=\"checked\"" : "";
                        rawDataLanguages += "<label for=\"yellow-pane-settings-"+language+"\"><input type=\"radio\" name=\"language\" id=\"yellow-pane-settings-"+language+"\" value=\""+language+"\""+checked+"> "+yellow.toolbox.encodeHtml(yellow.config.serverLanguages[language])+"</label><br />";
                    }
                    rawDataLanguages += "</p>";
                }
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\"></i></a>"+
                "<div class=\"yellow-title\"><h1 id=\"yellow-pane-settings-title\">"+this.getText("SettingsTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-settings-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\" id=\"yellow-pane-settings-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"settings\" />"+
                "<input type=\"hidden\" name=\"csrftoken\" value=\""+yellow.toolbox.encodeHtml(this.getCookie("csrftoken"))+"\" />"+
                "<p><label for=\"yellow-pane-settings-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-settings-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-settings-email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-settings-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-settings-password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-settings-password\" maxlength=\"64\" value=\"\" /></p>"+rawDataLanguages+
                "<p>"+this.getText("SettingsQuit")+" <a href=\"#\" data-action=\"quit\">"+this.getText("SettingsMore")+"</a></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-settings-buttons\">"+
                "<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-version":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\"></i></a>"+
                "<div class=\"yellow-title\"><h1 id=\"yellow-pane-version-title\">"+yellow.toolbox.encodeHtml(yellow.config.serverVersion)+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-version-status\" class=\""+paneStatus+"\">"+this.getText("VersionStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-output\" id=\"yellow-pane-version-output\">"+yellow.page.rawDataOutput+"</div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-version-buttons\">"+
                "<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-quit":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("QuitTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-quit-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\" id=\"yellow-pane-quit-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"quit\" />"+
                "<input type=\"hidden\" name=\"csrftoken\" value=\""+yellow.toolbox.encodeHtml(this.getCookie("csrftoken"))+"\" />"+
                "<p><label for=\"yellow-pane-quit-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-quit-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("DeleteButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-quit-buttons\">"+
                "<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-edit":
                var rawDataButtons = "";
                if (yellow.config.editToolbarButtons && yellow.config.editToolbarButtons!="none") {
                    var tokens = yellow.config.editToolbarButtons.split(",");
                    for (var i=0; i<tokens.length; i++) {
                        var token = tokens[i].trim();
                        if (token!="separator") {
                            rawDataButtons += "<li><a href=\"#\" id=\"yellow-toolbar-"+yellow.toolbox.encodeHtml(token)+"\" class=\"yellow-toolbar-btn-icon yellow-toolbar-tooltip\" data-action=\"toolbar\" data-status=\""+yellow.toolbox.encodeHtml(token)+"\" aria-label=\""+this.getText("Toolbar", "", token)+"\"><i class=\"yellow-icon yellow-icon-"+yellow.toolbox.encodeHtml(token)+"\"></i></a></li>";
                        } else {
                            rawDataButtons += "<li><a href=\"#\" class=\"yellow-toolbar-btn-separator\"></a></li>";
                        }
                    }
                    if (yellow.config.debug) console.log("yellow.edit.createPane buttons:"+yellow.config.editToolbarButtons);
                }
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<div id=\"yellow-pane-edit-toolbar\">"+
                "<h1 id=\"yellow-pane-edit-toolbar-title\" class=\"yellow-toolbar yellow-toolbar-left\">"+this.getText("Edit")+"</h1>"+
                "<ul id=\"yellow-pane-edit-toolbar-buttons\" class=\"yellow-toolbar yellow-toolbar-left\">"+rawDataButtons+"</ul>"+
                "<ul id=\"yellow-pane-edit-toolbar-main\" class=\"yellow-toolbar yellow-toolbar-right\">"+
                "<li><a href=\"#\" id=\"yellow-pane-edit-cancel\" class=\"yellow-toolbar-btn\" data-action=\"close\">"+this.getText("CancelButton")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-pane-edit-send\" class=\"yellow-toolbar-btn\" data-action=\"send\">"+this.getText("EditButton")+"</a></li>"+
                "</ul>"+
                "<ul class=\"yellow-toolbar yellow-toolbar-banner\"></ul>"+
                "</div>"+
                "<textarea id=\"yellow-pane-edit-text\" class=\"yellow-form-control\"></textarea>"+
                "<div id=\"yellow-pane-edit-preview\"></div>"+
                "</form>";
                break;
            case "yellow-pane-user":
                elementDiv.innerHTML =
                "<ul class=\"yellow-dropdown\">"+
                "<li><span>"+yellow.toolbox.encodeHtml(yellow.config.userEmail)+"</span></li>"+
                "<li><a href=\"#\" data-action=\"settings\">"+this.getText("SettingsTitle")+"</a></li>" +
                "<li><a href=\"#\" data-action=\"help\">"+this.getText("UserHelp")+"</a></li>" +
                "<li><a href=\"#\" data-action=\"logout\">"+this.getText("UserLogout")+"</a></li>"+
                "</ul>";
                break;
        }
        elementPane.appendChild(elementDiv);
        yellow.toolbox.insertAfter(elementPane, document.getElementsByTagName("body")[0].firstChild);
        this.bindActions(elementPane);
    },

    // Update pane
    updatePane: function(paneId, paneAction, paneStatus, init) {
        if (yellow.config.debug) console.log("yellow.edit.updatePane id:"+paneId);
        var showFields = paneStatus!="next" && paneStatus!="done";
        switch (paneId) {
            case "yellow-pane-login":
                if (yellow.config.editLoginRestrictions) {
                    yellow.toolbox.setVisible(document.getElementById("yellow-pane-login-signup"), false);
                }
                break;
            case "yellow-pane-signup":
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-signup-fields"), showFields);
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-signup-buttons"), !showFields);
                break;
            case "yellow-pane-forgot":
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-forgot-fields"), showFields);
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-forgot-buttons"), !showFields);
                break;
            case "yellow-pane-recover":
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-recover-fields"), showFields);
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-recover-buttons"), !showFields);
                break;
            case "yellow-pane-settings":
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-settings-fields"), showFields);
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-settings-buttons"), !showFields);
                if (paneStatus=="none") {
                    document.getElementById("yellow-pane-settings-status").innerHTML = "<a href=\"#\" data-action=\"version\">"+this.getText("VersionTitle")+"</a>";
                    document.getElementById("yellow-pane-settings-name").value = yellow.config.userName;
                    document.getElementById("yellow-pane-settings-email").value = yellow.config.userEmail;
                    document.getElementById("yellow-pane-settings-"+yellow.config.userLanguage).checked = true;
                }
                break;
            case "yellow-pane-version":
                if (paneStatus=="none" && this.isPlugin("update")) {
                    document.getElementById("yellow-pane-version-status").innerHTML = this.getText("VersionStatusCheck");
                    document.getElementById("yellow-pane-version-output").innerHTML = "";
                    setTimeout("yellow.action('send');", 500);
                }
                if (paneStatus=="updates" && this.isPlugin("update")) {
                    document.getElementById("yellow-pane-version-status").innerHTML = "<a href=\"#\" data-action=\"update\">"+this.getText("VersionStatusUpdates")+"</a>";
                }
                break;
            case "yellow-pane-quit":
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-quit-fields"), showFields);
                yellow.toolbox.setVisible(document.getElementById("yellow-pane-quit-buttons"), !showFields);
                if (paneStatus=="none") {
                    document.getElementById("yellow-pane-quit-status").innerHTML = this.getText("QuitStatusNone");
                    document.getElementById("yellow-pane-quit-name").value = "";
                }
                break;
            case "yellow-pane-edit":
                document.getElementById("yellow-pane-edit-text").focus();
                if (init) {
                    yellow.toolbox.setVisible(document.getElementById("yellow-pane-edit-text"), true);
                    yellow.toolbox.setVisible(document.getElementById("yellow-pane-edit-preview"), false);
                    document.getElementById("yellow-pane-edit-toolbar-title").innerHTML = yellow.toolbox.encodeHtml(yellow.page.title);
                    document.getElementById("yellow-pane-edit-text").value = paneAction=="create" ? yellow.page.rawDataNew : yellow.page.rawDataEdit;
                    var matches = document.getElementById("yellow-pane-edit-text").value.match(/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+/);
                    var position = document.getElementById("yellow-pane-edit-text").value.indexOf("\n", matches ? matches[0].length : 0);
                    document.getElementById("yellow-pane-edit-text").setSelectionRange(position, position);
                    if (yellow.config.editToolbarButtons!="none") {
                        yellow.toolbox.setVisible(document.getElementById("yellow-pane-edit-toolbar-title"), false);
                        this.updateToolbar(0, "yellow-toolbar-checked");
                    }
                    if (yellow.config.userRestrictions) {
                        yellow.toolbox.setVisible(document.getElementById("yellow-pane-edit-send"), false);
                        document.getElementById("yellow-pane-edit-text").readOnly = true;
                    }
                }
                if (!yellow.config.userRestrictions) {
                    var key, className;
                    switch (this.getAction(paneId, paneAction)) {
                        case "create":    key = "CreateButton"; className = "yellow-toolbar-btn yellow-toolbar-btn-create"; break;
                        case "edit":    key = "EditButton"; className = "yellow-toolbar-btn yellow-toolbar-btn-edit"; break;
                        case "delete":    key = "DeleteButton"; className = "yellow-toolbar-btn yellow-toolbar-btn-delete"; break;
                    }
                    if (document.getElementById("yellow-pane-edit-send").className != className) {
                        document.getElementById("yellow-pane-edit-send").innerHTML = this.getText(key);
                        document.getElementById("yellow-pane-edit-send").className = className;
                        this.resizePane(paneId, paneAction, paneStatus);
                    }
                }
                break;
        }
        this.bindActions(document.getElementById(paneId));
    },

    // Resize pane
    resizePane: function(paneId, paneAction, paneStatus) {
        var elementBar = document.getElementById("yellow-bar-content");
        var paneLeft = yellow.toolbox.getOuterLeft(elementBar);
        var paneTop = yellow.toolbox.getOuterTop(elementBar) + yellow.toolbox.getOuterHeight(elementBar) + 10;
        var paneWidth = yellow.toolbox.getOuterWidth(elementBar);
        var paneHeight = yellow.toolbox.getWindowHeight() - paneTop - Math.min(yellow.toolbox.getOuterHeight(elementBar) + 10, (yellow.toolbox.getWindowWidth()-yellow.toolbox.getOuterWidth(elementBar))/2);
        switch (paneId) {
            case "yellow-pane-login":
            case "yellow-pane-signup":
            case "yellow-pane-forgot":
            case "yellow-pane-recover":
            case "yellow-pane-settings":
            case "yellow-pane-version":
            case "yellow-pane-quit":
                yellow.toolbox.setOuterLeft(document.getElementById(paneId), paneLeft);
                yellow.toolbox.setOuterTop(document.getElementById(paneId), paneTop);
                yellow.toolbox.setOuterWidth(document.getElementById(paneId), paneWidth);
                break;
            case "yellow-pane-edit":
                yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-edit"), paneLeft);
                yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-edit"), paneTop);
                yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit"), paneHeight);
                yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit"), paneWidth);
                var elementWidth = yellow.toolbox.getWidth(document.getElementById("yellow-pane-edit"));
                yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit-text"), elementWidth);
                yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit-preview"), elementWidth);
                var buttonsWidth = 0;
                var buttonsWidthMax = yellow.toolbox.getOuterWidth(document.getElementById("yellow-pane-edit-toolbar")) -
                    yellow.toolbox.getOuterWidth(document.getElementById("yellow-pane-edit-toolbar-main")) - 1;
                var element = document.getElementById("yellow-pane-edit-toolbar-buttons").firstChild;
                for (; element; element=element.nextSibling) {
                    element.removeAttribute("style");
                    buttonsWidth += yellow.toolbox.getOuterWidth(element);
                    if (buttonsWidth>buttonsWidthMax) yellow.toolbox.setVisible(element, false);
                }
                yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit-toolbar-title"), buttonsWidthMax);
                var height1 = yellow.toolbox.getHeight(document.getElementById("yellow-pane-edit"));
                var height2 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-toolbar"));
                yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit-text"), height1 - height2);
                yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit-preview"), height1 - height2);
                var elementLink = document.getElementById("yellow-pane-"+paneAction+"-link");
                var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
                position -= yellow.toolbox.getOuterLeft(document.getElementById("yellow-pane-edit")) + 1;
                yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-edit-arrow"), position);
                break;
            case "yellow-pane-user":
                yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-user"), paneLeft + paneWidth - yellow.toolbox.getOuterWidth(document.getElementById("yellow-pane-user")));
                yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-user"), paneTop);
                var elementLink = document.getElementById("yellow-pane-user-link");
                var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
                position -= yellow.toolbox.getOuterLeft(document.getElementById("yellow-pane-user"));
                yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-user-arrow"), position);
                break;
        }
    },
    
    // Show or hide pane
    showPane: function(paneId, paneAction, paneStatus, modal) {
        if (this.paneId!=paneId || this.paneAction!=paneAction) {
            this.hidePane(this.paneId);
            if (!document.getElementById(paneId)) this.createPane(paneId, paneAction, paneStatus);
            var element = document.getElementById(paneId);
            if (!yellow.toolbox.isVisible(element)) {
                if (yellow.config.debug) console.log("yellow.edit.showPane id:"+paneId);
                yellow.toolbox.setVisible(element, true);
                if (modal) {
                    yellow.toolbox.addClass(document.body, "yellow-body-modal-open");
                    yellow.toolbox.addValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
                }
                this.paneId = paneId;
                this.paneAction = paneAction;
                this.paneStatus = paneStatus;
                this.updatePane(paneId, paneAction, paneStatus, this.paneActionOld!=this.paneAction);
                this.resizePane(paneId, paneAction, paneStatus);
            }
        } else {
            this.hidePane(this.paneId, true);
        }
    },

    // Hide pane
    hidePane: function(paneId, fadeout) {
        var element = document.getElementById(paneId);
        if (yellow.toolbox.isVisible(element)) {
            yellow.toolbox.removeClass(document.body, "yellow-body-modal-open");
            yellow.toolbox.removeValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
            yellow.toolbox.setVisible(element, false, fadeout);
            this.paneId = 0;
            this.paneActionOld = this.paneAction;
            this.paneAction = 0;
            this.paneStatus = 0;
        }
        this.hidePopup(this.popupId);
    },

    // Send pane
    sendPane: function(paneId, paneAction, paneStatus, paneArgs) {
        if (yellow.config.debug) console.log("yellow.edit.sendPane id:"+paneId);
        var args = { "action":paneAction, "csrftoken":this.getCookie("csrftoken") };
        if (paneId=="yellow-pane-edit") {
            args.action = this.getAction(paneId, paneAction);
            args.rawdatasource = yellow.page.rawDataSource;
            args.rawdataedit = document.getElementById("yellow-pane-edit-text").value;
            args.rawdataendofline = yellow.page.rawDataEndOfLine;
        }
        if (paneArgs) {
            var tokens = paneArgs.split("/");
            for (var i=0; i<tokens.length; i++) {
                var pair = tokens[i].split(/[:=]/);
                if (!pair[0] || !pair[1]) continue;
                args[pair[0]] = pair[1];
            }
        }
        yellow.toolbox.submitForm(args);
    },
    
    // Process help
    processHelp: function() {
        this.hidePane(this.paneId);
        window.open(this.getText("HelpUrl", "yellow"), "_self");
    },
    
    // Process shortcut
    processShortcut: function(e) {
        var shortcut = yellow.toolbox.getEventShortcut(e);
        if (shortcut) {
            var tokens = yellow.config.editKeyboardShortcuts.split(",");
            for (var i=0; i<tokens.length; i++) {
                var pair = tokens[i].trim().split(" ");
                if (shortcut==pair[0] || shortcut.replace("meta+", "ctrl+")==pair[0]) {
                    e.stopPropagation();
                    e.preventDefault();
                    this.processToolbar(pair[1]);
                }
            }
        }
    },
    
    // Process toolbar
    processToolbar: function(status, args) {
        if (yellow.config.debug) console.log("yellow.edit.processToolbar status:"+status);
        var elementText = document.getElementById("yellow-pane-edit-text");
        var elementPreview = document.getElementById("yellow-pane-edit-preview");
        if (!yellow.config.userRestrictions && this.paneAction!="delete" && !yellow.toolbox.isVisible(elementPreview)) {
            switch (status) {
                case "h1":              yellow.editor.setMarkdown(elementText, "# ", "insert-multiline-block", true); break;
                case "h2":              yellow.editor.setMarkdown(elementText, "## ", "insert-multiline-block", true); break;
                case "h3":              yellow.editor.setMarkdown(elementText, "### ", "insert-multiline-block", true); break;
                case "paragraph":       yellow.editor.setMarkdown(elementText, "", "remove-multiline-block");
                                        yellow.editor.setMarkdown(elementText, "", "remove-fenced-block"); break;
                case "quote":           yellow.editor.setMarkdown(elementText, "> ", "insert-multiline-block", true); break;
                case "pre":             yellow.editor.setMarkdown(elementText, "```\n", "insert-fenced-block", true); break;
                case "bold":            yellow.editor.setMarkdown(elementText, "**", "insert-inline", true); break;
                case "italic":          yellow.editor.setMarkdown(elementText, "*", "insert-inline", true); break;
                case "strikethrough":   yellow.editor.setMarkdown(elementText, "~~", "insert-inline", true); break;
                case "code":            yellow.editor.setMarkdown(elementText, "`", "insert-autodetect", true); break;
                case "ul":              yellow.editor.setMarkdown(elementText, "* ", "insert-multiline-block", true); break;
                case "ol":              yellow.editor.setMarkdown(elementText, "1. ", "insert-multiline-block", true); break;
                case "tl":              yellow.editor.setMarkdown(elementText, "- [ ] ", "insert-multiline-block", true); break;
                case "link":            yellow.editor.setMarkdown(elementText, "[link](url)", "insert", false, yellow.editor.getMarkdownLink); break;
                case "text":            yellow.editor.setMarkdown(elementText, args, "insert"); break;
                case "draft":           yellow.editor.setMetaData(elementText, "status", "draft", true); break;
                case "file":            this.showFileDialog(); break;
                case "undo":            yellow.editor.undo(); break;
                case "redo":            yellow.editor.redo(); break;
            }
        }
        if (status=="preview") this.showPreview(elementText, elementPreview);
        if (status=="save" && !yellow.config.userRestrictions && this.paneAction!="delete") this.action("send");
        if (status=="help") window.open(this.getText("HelpUrl", "yellow"), "_blank");
        if (status=="markdown") window.open(this.getText("MarkdownUrl", "yellow"), "_blank");
        if (status=="format" || status=="heading" || status=="list" || status=="emojiawesome" || status=="fontawesome") {
            this.showPopup("yellow-popup-"+status, status);
        } else {
            this.hidePopup(this.popupId);
        }
    },
    
    // Update toolbar
    updateToolbar: function(status, name) {
        if (status) {
            var element = document.getElementById("yellow-toolbar-"+status);
            if (element) yellow.toolbox.addClass(element, name);
        } else {
            var elements = document.getElementsByClassName(name);
            for (var i=0, l=elements.length; i<l; i++) {
                yellow.toolbox.removeClass(elements[i], name);
            }
        }
    },
    
    // Create popup
    createPopup: function(popupId) {
        if (yellow.config.debug) console.log("yellow.edit.createPopup id:"+popupId);
        var elementPopup = document.createElement("div");
        elementPopup.className = "yellow-popup";
        elementPopup.setAttribute("id", popupId);
        elementPopup.style.display = "none";
        var elementDiv = document.createElement("div");
        elementDiv.setAttribute("id", popupId+"-content");
        switch (popupId) {
            case "yellow-popup-format":
                elementDiv.innerHTML =
                "<ul class=\"yellow-dropdown yellow-dropdown-menu\">"+
                "<li><a href=\"#\" id=\"yellow-popup-format-h1\" data-action=\"toolbar\" data-status=\"h1\">"+this.getText("ToolbarH1")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-format-h2\" data-action=\"toolbar\" data-status=\"h2\">"+this.getText("ToolbarH2")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-format-h3\" data-action=\"toolbar\" data-status=\"h3\">"+this.getText("ToolbarH3")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-format-paragraph\" data-action=\"toolbar\" data-status=\"paragraph\">"+this.getText("ToolbarParagraph")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-format-pre\" data-action=\"toolbar\" data-status=\"pre\">"+this.getText("ToolbarPre")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-format-quote\" data-action=\"toolbar\" data-status=\"quote\">"+this.getText("ToolbarQuote")+"</a></li>"+
                "</ul>";
                break;
            case "yellow-popup-heading":
                elementDiv.innerHTML =
                "<ul class=\"yellow-dropdown yellow-dropdown-menu\">"+
                "<li><a href=\"#\" id=\"yellow-popup-heading-h1\" data-action=\"toolbar\" data-status=\"h1\">"+this.getText("ToolbarH1")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-heading-h2\" data-action=\"toolbar\" data-status=\"h2\">"+this.getText("ToolbarH2")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-heading-h3\" data-action=\"toolbar\" data-status=\"h3\">"+this.getText("ToolbarH3")+"</a></li>"+
                "</ul>";
                break;
            case "yellow-popup-list":
                elementDiv.innerHTML =
                "<ul class=\"yellow-dropdown yellow-dropdown-menu\">"+
                "<li><a href=\"#\" id=\"yellow-popup-list-ul\" data-action=\"toolbar\" data-status=\"ul\">"+this.getText("ToolbarUl")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-popup-list-ol\" data-action=\"toolbar\" data-status=\"ol\">"+this.getText("ToolbarOl")+"</a></li>"+
                "</ul>";
                break;
            case "yellow-popup-emojiawesome":
                var rawDataEmojis = "";
                if (yellow.config.emojiawesomeToolbarButtons && yellow.config.emojiawesomeToolbarButtons!="none") {
                    var tokens = yellow.config.emojiawesomeToolbarButtons.split(" ");
                    for (var i=0; i<tokens.length; i++) {
                        var token = tokens[i].replace(/[\:]/g,"");
                        var className = token.replace("+1", "plus1").replace("-1", "minus1").replace(/_/g, "-");
                        rawDataEmojis += "<li><a href=\"#\" id=\"yellow-popup-list-"+yellow.toolbox.encodeHtml(token)+"\" data-action=\"toolbar\" data-status=\"text\" data-args=\":"+yellow.toolbox.encodeHtml(token)+":\"><i class=\"ea ea-"+yellow.toolbox.encodeHtml(className)+"\"></i></a></li>";
                    }
                }
                elementDiv.innerHTML = "<ul class=\"yellow-dropdown yellow-dropdown-menu\">"+rawDataEmojis+"</ul>";
                break;
            case "yellow-popup-fontawesome":
                var rawDataIcons = "";
                if (yellow.config.fontawesomeToolbarButtons && yellow.config.fontawesomeToolbarButtons!="none") {
                    var tokens = yellow.config.fontawesomeToolbarButtons.split(" ");
                    for (var i=0; i<tokens.length; i++) {
                        var token = tokens[i].replace(/[\:]/g,"");
                        rawDataIcons += "<li><a href=\"#\" id=\"yellow-popup-list-"+yellow.toolbox.encodeHtml(token)+"\" data-action=\"toolbar\" data-status=\"text\" data-args=\":"+yellow.toolbox.encodeHtml(token)+":\"><i class=\"fa "+yellow.toolbox.encodeHtml(token)+"\"></i></a></li>";
                    }
                }
                elementDiv.innerHTML = "<ul class=\"yellow-dropdown yellow-dropdown-menu\">"+rawDataIcons+"</ul>";
                break;
        }
        elementPopup.appendChild(elementDiv);
        yellow.toolbox.insertAfter(elementPopup, document.getElementsByTagName("body")[0].firstChild);
        this.bindActions(elementPopup);
    },
    
    // Show or hide popup
    showPopup: function(popupId, status) {
        if (this.popupId!=popupId) {
            this.hidePopup(this.popupId);
            if (!document.getElementById(popupId)) this.createPopup(popupId);
            var element = document.getElementById(popupId);
            if (yellow.config.debug) console.log("yellow.edit.showPopup id:"+popupId);
            yellow.toolbox.setVisible(element, true);
            this.popupId = popupId;
            this.updateToolbar(status, "yellow-toolbar-selected");
            var elementParent = document.getElementById("yellow-toolbar-"+status);
            var popupLeft = yellow.toolbox.getOuterLeft(elementParent);
            var popupTop = yellow.toolbox.getOuterTop(elementParent) + yellow.toolbox.getOuterHeight(elementParent) - 1;
            yellow.toolbox.setOuterLeft(document.getElementById(popupId), popupLeft);
            yellow.toolbox.setOuterTop(document.getElementById(popupId), popupTop);
        } else {
            this.hidePopup(this.popupId, true);
        }
    },
    
    // Hide popup
    hidePopup: function(popupId, fadeout) {
        var element = document.getElementById(popupId);
        if (yellow.toolbox.isVisible(element)) {
            yellow.toolbox.setVisible(element, false, fadeout);
            this.popupId = 0;
            this.updateToolbar(0, "yellow-toolbar-selected");
        }
    },
    
    // Show or hide preview
    showPreview: function(elementText, elementPreview) {
        if (!yellow.toolbox.isVisible(elementPreview)) {
            var thisObject = this;
            var formData = new FormData();
            formData.append("action", "preview");
            formData.append("csrftoken", this.getCookie("csrftoken"));
            formData.append("rawdataedit", elementText.value);
            formData.append("rawdataendofline", yellow.page.rawDataEndOfLine);
            var request = new XMLHttpRequest();
            request.open("POST", window.location.pathname, true);
            request.onload = function() { if (this.status==200) thisObject.showPreviewDone.call(thisObject, elementText, elementPreview, this.responseText); };
            request.send(formData);
        } else {
            this.showPreviewDone(elementText, elementPreview, "");
        }
    },
    
    // Preview done
    showPreviewDone: function(elementText, elementPreview, responseText) {
        var showPreview = responseText.length!=0;
        yellow.toolbox.setVisible(elementText, !showPreview);
        yellow.toolbox.setVisible(elementPreview, showPreview);
        if (showPreview) {
            this.updateToolbar("preview", "yellow-toolbar-checked");
            elementPreview.innerHTML = responseText;
            dispatchEvent(new Event("load"));
        } else {
            this.updateToolbar(0, "yellow-toolbar-checked");
            elementText.focus();
        }
    },
    
    // Show file dialog and trigger upload
    showFileDialog: function() {
        var element = document.createElement("input");
        element.setAttribute("id", "yellow-file-dialog");
        element.setAttribute("type", "file");
        element.setAttribute("accept", yellow.config.editUploadExtensions);
        element.setAttribute("multiple", "multiple");
        yellow.toolbox.addEvent(element, "change", yellow.onDrop);
        element.click();
    },
    
    // Upload file
    uploadFile: function(elementText, file) {
        var extension = (file.name.lastIndexOf(".")!=-1 ? file.name.substring(file.name.lastIndexOf("."), file.name.length) : "").toLowerCase();
        var extensions = yellow.config.editUploadExtensions.split(/\s*,\s*/);
        if (file.size<=yellow.config.serverFileSizeMax && extensions.indexOf(extension)!=-1) {
            var text = this.getText("UploadProgress")+"\u200b";
            yellow.editor.setMarkdown(elementText, text, "insert");
            var thisObject = this;
            var formData = new FormData();
            formData.append("action", "upload");
            formData.append("csrftoken", this.getCookie("csrftoken"));
            formData.append("file", file);
            var request = new XMLHttpRequest();
            request.open("POST", window.location.pathname, true);
            request.onload = function() { if (this.status==200) { thisObject.uploadFileDone.call(thisObject, elementText, this.responseText); } else { thisObject.uploadFileError.call(thisObject, elementText, this.responseText); } };
            request.send(formData);
        }
    },
    
    // Upload done
    uploadFileDone: function(elementText, responseText) {
        var result = JSON.parse(responseText);
        if (result) {
            var textOld = this.getText("UploadProgress")+"\u200b";
            var textNew;
            if (result.location.substring(0, yellow.config.imageLocation.length)==yellow.config.imageLocation) {
                textNew = "[image "+result.location.substring(yellow.config.imageLocation.length)+"]";
            } else {
                textNew = "[link]("+result.location+")";
            }
            yellow.editor.replace(elementText, textOld, textNew);
        }
    },
    
    // Upload error
    uploadFileError: function(elementText, responseText) {
        var result = JSON.parse(responseText);
        if (result) {
            var textOld = this.getText("UploadProgress")+"\u200b";
            var textNew = "["+result.error+"]";
            yellow.editor.replace(elementText, textOld, textNew);
        }
    },

    // Bind actions to links
    bindActions: function(element) {
        var elements = element.getElementsByTagName("a");
        for (var i=0, l=elements.length; i<l; i++) {
            if (elements[i].getAttribute("data-action")) elements[i].onclick = yellow.onClickAction;
            if (elements[i].getAttribute("data-action")=="toolbar") elements[i].onmousedown = function(e) { e.preventDefault(); };
        }
    },
    
    // Return action
    getAction: function(paneId, paneAction) {
        var action = "";
        if (paneId=="yellow-pane-edit") {
            switch (paneAction) {
                case "create":  action = "create"; break;
                case "edit":    action = document.getElementById("yellow-pane-edit-text").value.length!=0 ? "edit" : "delete"; break;
                case "delete":  action = "delete"; break;
            }
            if (yellow.page.statusCode==434 && paneAction!="delete") action = "create";
        }
        return action;
    },
    
    // Return request string
    getRequest: function(key, prefix) {
        if (!prefix) prefix = "request";
        key = prefix + yellow.toolbox.toUpperFirst(key);
        return (key in yellow.page) ? yellow.page[key] : "";
    },

    // Return text string
    getText: function(key, prefix, postfix) {
        if (!prefix) prefix = "edit";
        if (!postfix) postfix = "";
        key = prefix + yellow.toolbox.toUpperFirst(key) + yellow.toolbox.toUpperFirst(postfix);
        return (key in yellow.text) ? yellow.text[key] : "["+key+"]";
    },

    // Return cookie string
    getCookie: function(name) {
        return yellow.toolbox.getCookie(name);
    },

    // Check if plugin exists
    isPlugin: function(name) {
        return name in yellow.config.serverPlugins;
    }
};

yellow.editor = {

    // Set Markdown formatting
    setMarkdown: function(element, prefix, type, toggle, callback) {
        var information = this.getMarkdownInformation(element, prefix, type);
        var selectionStart = (information.type.indexOf("block")!=-1) ? information.top : information.start;
        var selectionEnd = (information.type.indexOf("block")!=-1) ? information.bottom : information.end;
        if (information.found && toggle) information.type = information.type.replace("insert", "remove");
        if (information.type=="remove-fenced-block" || information.type=="remove-inline") {
            selectionStart -= information.prefix.length; selectionEnd += information.prefix.length;
        }
        var text = information.text;
        var textSelectionBefore = text.substring(0, selectionStart);
        var textSelection = text.substring(selectionStart, selectionEnd);
        var textSelectionAfter = text.substring(selectionEnd, text.length);
        var textSelectionNew, selectionStartNew, selectionEndNew;
        switch (information.type) {
            case "insert-multiline-block":
                textSelectionNew = this.getMarkdownMultilineBlock(textSelection, information);
                selectionStartNew = information.start + this.getMarkdownDifference(textSelection, textSelectionNew, true);
                selectionEndNew = information.end + this.getMarkdownDifference(textSelection, textSelectionNew);
                if (information.start==information.top && information.start!=information.end) selectionStartNew = information.top;
                if (information.end==information.top && information.start!=information.end) selectionEndNew = information.top;
                break;
            case "remove-multiline-block":
                textSelectionNew = this.getMarkdownMultilineBlock(textSelection, information);
                selectionStartNew = information.start + this.getMarkdownDifference(textSelection, textSelectionNew, true);
                selectionEndNew = information.end + this.getMarkdownDifference(textSelection, textSelectionNew);
                if (selectionStartNew<=information.top) selectionStartNew = information.top;
                if (selectionEndNew<=information.top) selectionEndNew = information.top;
                break;
            case "insert-fenced-block":
                textSelectionNew = this.getMarkdownFencedBlock(textSelection, information);
                selectionStartNew = information.start + information.prefix.length;
                selectionEndNew = information.end + this.getMarkdownDifference(textSelection, textSelectionNew) - information.prefix.length;
                break;
            case "remove-fenced-block":
                textSelectionNew = this.getMarkdownFencedBlock(textSelection, information);
                selectionStartNew = information.start - information.prefix.length;
                selectionEndNew = information.end + this.getMarkdownDifference(textSelection, textSelectionNew) + information.prefix.length;
                break;
            case "insert-inline":
                textSelectionNew = information.prefix + textSelection + information.prefix;
                selectionStartNew = information.start + information.prefix.length;
                selectionEndNew = information.end + information.prefix.length;
                break;
            case "remove-inline":
                textSelectionNew = text.substring(information.start, information.end);
                selectionStartNew = information.start - information.prefix.length;
                selectionEndNew = information.end - information.prefix.length;
                break;
            case "insert":
                textSelectionNew = callback ? callback(textSelection, information) : information.prefix;
                selectionStartNew = information.start + textSelectionNew.length;
                selectionEndNew = selectionStartNew;
        }
        if (textSelection!=textSelectionNew || selectionStart!=selectionStartNew || selectionEnd!=selectionEndNew) {
            element.focus();
            element.setSelectionRange(selectionStart, selectionEnd);
            document.execCommand("insertText", false, textSelectionNew);
            element.value = textSelectionBefore + textSelectionNew + textSelectionAfter;
            element.setSelectionRange(selectionStartNew, selectionEndNew);
        }
        if (yellow.config.debug) console.log("yellow.editor.setMarkdown type:"+information.type);
    },
    
    // Return Markdown formatting information
    getMarkdownInformation: function(element, prefix, type) {
        var text = element.value;
        var start = element.selectionStart;
        var end = element.selectionEnd;
        var top = start, bottom = end;
        while (text.charAt(top-1)!="\n" && top>0) top--;
        if (bottom==top && bottom<text.length) bottom++;
        while (text.charAt(bottom-1)!="\n" && bottom<text.length) bottom++;
        if (type=="insert-autodetect") {
            if (text.substring(start, end).indexOf("\n")!=-1) {
                type = "insert-fenced-block"; prefix = "```\n";
            } else {
                type = "insert-inline"; prefix = "`";
            }
        }
        var found = false;
        if (type.indexOf("multiline-block")!=-1) {
            if (text.substring(top, top+prefix.length)==prefix) found = true;
        } else if (type.indexOf("fenced-block")!=-1) {
            if (text.substring(top-prefix.length, top)==prefix && text.substring(bottom, bottom+prefix.length)==prefix) {
                found = true;
            }
        } else {
            if (text.substring(start-prefix.length, start)==prefix && text.substring(end, end+prefix.length)==prefix) {
                if (prefix=="*") {
                    var lettersBefore = 0, lettersAfter = 0;
                    for (var index=start-1; text.charAt(index)=="*"; index--) lettersBefore++;
                    for (var index=end; text.charAt(index)=="*"; index++) lettersAfter++;
                    found = lettersBefore!=2 && lettersAfter!=2;
                } else {
                    found = true;
                }
            }
        }
        return { "text":text, "prefix":prefix, "type":type, "start":start, "end":end, "top":top, "bottom":bottom, "found":found };
    },
    
    // Return Markdown length difference
    getMarkdownDifference: function(textSelection, textSelectionNew, firstTextLine) {
        var textSelectionLength, textSelectionLengthNew;
        if (firstTextLine) {
            var position = textSelection.indexOf("\n");
            var positionNew = textSelectionNew.indexOf("\n");
            textSelectionLength = position!=-1 ? position+1 : textSelection.length+1;
            textSelectionLengthNew = positionNew!=-1 ? positionNew+1 : textSelectionNew.length+1;
        } else {
            var position = textSelection.indexOf("\n");
            var positionNew = textSelectionNew.indexOf("\n");
            textSelectionLength = position!=-1 ? textSelection.length : textSelection.length+1;
            textSelectionLengthNew = positionNew!=-1 ? textSelectionNew.length : textSelectionNew.length+1;
        }
        return textSelectionLengthNew - textSelectionLength;
    },
    
    // Return Markdown for multiline block
    getMarkdownMultilineBlock: function(textSelection, information) {
        var textSelectionNew = "";
        var lines = yellow.toolbox.getTextLines(textSelection);
        for (var i=0; i<lines.length; i++) {
            var matches = lines[i].match(/^(\s*[\#\*\-\>\s]+)?(\s+\[.\]|\s*\d+\.)?[ \t]+/);
            if (matches) {
                textSelectionNew += lines[i].substring(matches[0].length);
            } else {
                textSelectionNew += lines[i];
            }
        }
        textSelection = textSelectionNew;
        if (information.type.indexOf("remove")==-1) {
            textSelectionNew = "";
            var linePrefix = information.prefix;
            lines = yellow.toolbox.getTextLines(textSelection.length!=0 ? textSelection : "\n");
            for (var i=0; i<lines.length; i++) {
                textSelectionNew += linePrefix+lines[i];
                if (information.prefix=="1. ") {
                    var matches = linePrefix.match(/^(\d+)\.\s/);
                    if (matches) linePrefix = (parseInt(matches[1])+1)+". ";
                }
            }
            textSelection = textSelectionNew;
        }
        return textSelection;
    },
    
    // Return Markdown for fenced block
    getMarkdownFencedBlock: function(textSelection, information) {
        var textSelectionNew = "";
        var lines = yellow.toolbox.getTextLines(textSelection);
        for (var i=0; i<lines.length; i++) {
            var matches = lines[i].match(/^```/);
            if (!matches) textSelectionNew += lines[i];
        }
        textSelection = textSelectionNew;
        if (information.type.indexOf("remove")==-1) {
            if (textSelection.length==0) textSelection = "\n";
            textSelection = information.prefix + textSelection + information.prefix;
        }
        return textSelection;
    },
    
    // Return Markdown for link
    getMarkdownLink: function(textSelection, information) {
        return textSelection.length!=0 ? information.prefix.replace("link", textSelection) : information.prefix;
    },
    
    // Set meta data
    setMetaData: function(element, key, value, toggle) {
        var information = this.getMetaDataInformation(element, key);
        if (information.bottom!=0) {
            var selectionStart = information.found ? information.start : information.bottom;
            var selectionEnd = information.found ? information.end : information.bottom;
            var text = information.text;
            var textSelectionBefore = text.substring(0, selectionStart);
            var textSelection = text.substring(selectionStart, selectionEnd);
            var textSelectionAfter = text.substring(selectionEnd, text.length);
            var textSelectionNew = yellow.toolbox.toUpperFirst(key)+": "+value+"\n";
            if (information.found && information.value==value && toggle) textSelectionNew = "";
            var selectionStartNew = selectionStart;
            var selectionEndNew = selectionStart + textSelectionNew.trim().length;
            element.focus();
            element.setSelectionRange(selectionStart, selectionEnd);
            document.execCommand("insertText", false, textSelectionNew);
            element.value = textSelectionBefore + textSelectionNew + textSelectionAfter;
            element.setSelectionRange(selectionStartNew, selectionEndNew);
            element.scrollTop = 0;
            if (yellow.config.debug) console.log("yellow.editor.setMetaData key:"+key);
        }
    },
    
    // Return meta data information
    getMetaDataInformation: function(element, key) {
        var text = element.value;
        var value = "";
        var start = 0, end = 0, top = 0, bottom = 0;
        var found = false;
        var parts = text.match(/^(\xEF\xBB\xBF)?(\-\-\-[\r\n]+)([\s\S]+?)\-\-\-[\r\n]+/);
        if (parts) {
            key = yellow.toolbox.toLowerFirst(key);
            start = end = top = ((parts[1] ? parts[1] : "")+parts[2]).length;
            bottom = ((parts[1] ? parts[1] : "")+parts[2]+parts[3]).length;
            var lines = yellow.toolbox.getTextLines(parts[3]);
            for (var i=0; i<lines.length; i++) {
                var matches = lines[i].match(/^\s*(.*?)\s*:\s*(.*?)\s*$/);
                if (matches && yellow.toolbox.toLowerFirst(matches[1])==key && matches[2].length!=0) {
                    value = matches[2];
                    end = start + lines[i].length;
                    found = true;
                    break;
                }
                start = end = start + lines[i].length;
            }
        }
        return { "text":text, "value":value, "start":start, "end":end, "top":top, "bottom":bottom, "found":found };
    },
    
    // Replace text
    replace: function(element, textOld, textNew) {
        var text = element.value;
        var selectionStart = element.selectionStart;
        var selectionEnd = element.selectionEnd;
        var selectionStartFound = text.indexOf(textOld);
        var selectionEndFound = selectionStartFound + textOld.length;
        if (selectionStartFound!=-1) {
            var selectionStartNew = selectionStart<selectionStartFound ? selectionStart : selectionStart+textNew.length-textOld.length;
            var selectionEndNew = selectionEnd<selectionEndFound ? selectionEnd : selectionEnd+textNew.length-textOld.length;
            var textBefore = text.substring(0, selectionStartFound);
            var textAfter = text.substring(selectionEndFound, text.length);
            if (textOld!=textNew) {
                element.focus();
                element.setSelectionRange(selectionStartFound, selectionEndFound);
                document.execCommand("insertText", false, textNew);
                element.value = textBefore + textNew + textAfter;
                element.setSelectionRange(selectionStartNew, selectionEndNew);
            }
        }
    },
    
    // Undo changes
    undo: function() {
        document.execCommand("undo");
    },

    // Redo changes
    redo: function() {
        document.execCommand("redo");
    }
};

yellow.toolbox = {

    // Insert element before reference element
    insertBefore: function(element, elementReference) {
        elementReference.parentNode.insertBefore(element, elementReference);
    },

    // Insert element after reference element
    insertAfter: function(element, elementReference) {
        elementReference.parentNode.insertBefore(element, elementReference.nextSibling);
    },

    // Add element class
    addClass: function(element, name) {
        element.classList.add(name);
    },
    
    // Remove element class
    removeClass: function(element, name) {
        element.classList.remove(name);
    },

    // Add attribute information
    addValue: function(selector, name, value) {
        var element = document.querySelector(selector);
        element.setAttribute(name, element.getAttribute(name) + value);
    },

    // Remove attribute information
    removeValue: function(selector, name, value) {
        var element = document.querySelector(selector);
        element.setAttribute(name, element.getAttribute(name).replace(value, ""));
    },
    
    // Add event handler
    addEvent: function(element, type, handler) {
        element.addEventListener(type, handler, false);
    },
    
    // Remove event handler
    removeEvent: function(element, type, handler) {
        element.removeEventListener(type, handler, false);
    },
    
    // Return shortcut from keyboard event, alphanumeric only
    getEventShortcut: function(e) {
        var shortcut = "";
        if (e.keyCode>=48 && e.keyCode<=90) {
            shortcut += (e.ctrlKey ? "ctrl+" : "")+(e.metaKey ? "meta+" : "")+(e.altKey ? "alt+" : "")+(e.shiftKey ? "shift+" : "");
            shortcut += String.fromCharCode(e.keyCode).toLowerCase();
        }
        return shortcut;
    },
    
    // Return element width in pixel
    getWidth: function(element) {
        return element.offsetWidth - this.getBoxSize(element).width;
    },
    
    // Return element height in pixel
    getHeight: function(element) {
        return element.offsetHeight - this.getBoxSize(element).height;
    },
    
    // Set element width in pixel, including padding and border
    setOuterWidth: function(element, width) {
        element.style.width = Math.max(0, width - this.getBoxSize(element).width) + "px";
    },
    
    // Set element height in pixel, including padding and border
    setOuterHeight: function(element, height) {
        element.style.height = Math.max(0, height - this.getBoxSize(element).height) + "px";
    },
    
    // Return element width in pixel, including padding and border
    getOuterWidth: function(element, includeMargin) {
        var width = element.offsetWidth;
        if (includeMargin) width += this.getMarginSize(element).width;
        return width;
    },

    // Return element height in pixel, including padding and border
    getOuterHeight: function(element, includeMargin) {
        var height = element.offsetHeight;
        if (includeMargin) height += this.getMarginSize(element).height;
        return height;
    },
    
    // Set element left position in pixel
    setOuterLeft: function(element, left) {
        element.style.left = Math.max(0, left) + "px";
    },
    
    // Set element top position in pixel
    setOuterTop: function(element, top) {
        element.style.top = Math.max(0, top) + "px";
    },
    
    // Return element left position in pixel
    getOuterLeft: function(element) {
        return element.getBoundingClientRect().left + window.pageXOffset;
    },
    
    // Return element top position in pixel
    getOuterTop: function(element) {
        return element.getBoundingClientRect().top + window.pageYOffset;
    },
    
    // Return window width in pixel
    getWindowWidth: function() {
        return window.innerWidth;
    },
    
    // Return window height in pixel
    getWindowHeight: function() {
        return window.innerHeight;
    },
    
    // Return element CSS property
    getStyle: function(element, property) {
        return window.getComputedStyle(element).getPropertyValue(property);
    },
    
    // Return element CSS padding and border
    getBoxSize: function(element) {
        var paddingLeft = parseFloat(this.getStyle(element, "padding-left")) || 0;
        var paddingRight = parseFloat(this.getStyle(element, "padding-right")) || 0;
        var borderLeft = parseFloat(this.getStyle(element, "border-left-width")) || 0;
        var borderRight = parseFloat(this.getStyle(element, "border-right-width")) || 0;
        var width = paddingLeft + paddingRight + borderLeft + borderRight;
        var paddingTop = parseFloat(this.getStyle(element, "padding-top")) || 0;
        var paddingBottom = parseFloat(this.getStyle(element, "padding-bottom")) || 0;
        var borderTop = parseFloat(this.getStyle(element, "border-top-width")) || 0;
        var borderBottom = parseFloat(this.getStyle(element, "border-bottom-width")) || 0;
        var height = paddingTop + paddingBottom + borderTop + borderBottom;
        return { "width":width, "height":height };
    },
    
    // Return element CSS margin
    getMarginSize: function(element) {
        var marginLeft = parseFloat(this.getStyle(element, "margin-left")) || 0;
        var marginRight = parseFloat(this.getStyle(element, "margin-right")) || 0;
        var width = marginLeft + marginRight;
        var marginTop = parseFloat(this.getStyle(element, "margin-top")) || 0;
        var marginBottom = parseFloat(this.getStyle(element, "margin-bottom")) || 0;
        var height = marginTop + marginBottom;
        return { "width":width, "height":height };
    },
    
    // Set element visibility
    setVisible: function(element, show, fadeout) {
        if (fadeout && !show) {
            var opacity = 1;
            function renderFrame() {
                opacity -= .1;
                if (opacity<=0) {
                    element.style.opacity = "initial";
                    element.style.display = "none";
                } else {
                    element.style.opacity = opacity;
                    requestAnimationFrame(renderFrame);
                }
            }
            renderFrame();
        } else {
            element.style.display = show ? "block" : "none";
        }
    },

    // Check if element exists and is visible
    isVisible: function(element) {
        return element && element.style.display!="none";
    },
    
    // Convert first letter to lowercase
    toLowerFirst: function(string) {
        return string.charAt(0).toLowerCase()+string.slice(1);
    },

    // Convert first letter to uppercase
    toUpperFirst: function(string) {
        return string.charAt(0).toUpperCase()+string.slice(1);
    },
    
    // Return lines from text string, including newline
    getTextLines: function(string) {
        var lines = string.split("\n");
        for (var i=0; i<lines.length; i++) lines[i] = lines[i]+"\n";
        if (string.length==0 || string.charAt(string.length-1)=="\n") lines.pop();
        return lines;
    },
    
    // Return cookie string
    getCookie: function(name) {
        var matches = document.cookie.match("(^|; )"+name+"=([^;]+)");
        return matches ? unescape(matches[2]) : "";
    },
    
    // Encode HTML special characters
    encodeHtml: function(string) {
        return string
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    },
    
    // Submit form with post method
    submitForm: function(args) {
        var elementForm = document.createElement("form");
        elementForm.setAttribute("method", "post");
        for (var key in args) {
            if (!args.hasOwnProperty(key)) continue;
            var elementInput = document.createElement("input");
            elementInput.setAttribute("type", "hidden");
            elementInput.setAttribute("name", key);
            elementInput.setAttribute("value", args[key]);
            elementForm.appendChild(elementInput);
        }
        document.body.appendChild(elementForm);
        elementForm.submit();
    }
};

yellow.edit.intervalId = setInterval("yellow.onLoad()", 1);
