// Edit extension, https://github.com/annaesvensson/yellow-edit

var yellow = {
    onLoad: function(e) { yellow.edit.load(e); },
    onKeydown: function(e) { yellow.edit.keydown(e); },
    onDrag: function(e) { yellow.edit.drag(e); },
    onDrop: function(e) { yellow.edit.drop(e); },
    onClick: function(e) { yellow.edit.click(e); },
    onClickAction: function(e) { yellow.edit.clickAction(e); },
    onPageShow: function(e) { yellow.edit.pageShow(e); },
    onUpdatePane: function() { yellow.edit.updatePane(yellow.edit.paneId, yellow.edit.paneAction, yellow.edit.paneStatus); },
    onResizePane: function() { yellow.edit.resizePane(yellow.edit.paneId, yellow.edit.paneAction, yellow.edit.paneStatus); },
    action: function(action, status, arguments) { yellow.edit.processAction(action, status, arguments); }
};

yellow.edit = {
    paneId: 0,          // visible pane ID
    paneAction: 0,      // current pane action
    paneStatus: 0,      // current pane status
    popupId: 0,         // visible popup ID
    intervalId: 0,      // timer interval ID

    // Handle initialisation
    load: function(e) {
        var body = document.getElementsByTagName("body")[0];
        if (body && body.firstChild && !document.getElementById("yellow-bar")) {
            this.createBar("yellow-bar");
            this.processAction(yellow.page.action, yellow.page.status);
            clearInterval(this.intervalId);
        }
        if (e.type=="DOMContentLoaded") {
            var page = document.getElementsByClassName("page")[0];
            if (page) this.bindActions(page);
        }
    },
    
    // Handle keyboard
    keydown: function(e) {
        if (this.paneId=="yellow-pane-create" || this.paneId=="yellow-pane-edit" || this.paneId=="yellow-pane-delete") this.processShortcut(e);
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
        var elementText = document.getElementById(this.paneId+"-text");
        var files = e.dataTransfer ? e.dataTransfer.files : e.target.files;
        for (var i=0; i<files.length; i++) this.uploadFile(elementText, files[i]);
    },
    
    // Handle mouse clicked
    click: function(e) {
        if (this.popupId && !document.getElementById(this.popupId).contains(e.target)) this.hidePopup(this.popupId, true);
        if (this.paneId && !document.getElementById(this.paneId).contains(e.target)) this.hidePane(this.paneId, true);
    },
    
    // Handle action clicked
    clickAction: function(e) {
        e.stopPropagation();
        e.preventDefault();
        var element = e.target;
        for (; element; element=element.parentNode) {
            if (element.tagName=="A") break;
        }
        this.processAction(element.getAttribute("data-action"), element.getAttribute("data-status"), element.getAttribute("data-arguments"));
    },
    
    // Handle page cache
    pageShow: function(e) {
        if (e.persisted && yellow.user.email && !this.getCookie("csrftoken")) {
            window.location.reload();
        }
    },
    
    // Create bar
    createBar: function(barId) {
        var elementBar = document.createElement("div");
        elementBar.className = "yellow-bar";
        elementBar.setAttribute("id", barId);
        if (barId=="yellow-bar") {
            yellow.toolbox.addEvent(document, "click", yellow.onClick);
            yellow.toolbox.addEvent(document, "keydown", yellow.onKeydown);
            yellow.toolbox.addEvent(window, "pageshow", yellow.onPageShow);
            yellow.toolbox.addEvent(window, "resize", yellow.onResizePane);
        }
        var elementDiv = document.createElement("div");
        elementDiv.setAttribute("id", barId+"-content");
        if (yellow.user.name) {
            elementDiv.innerHTML =
                "<div class=\"yellow-bar-left\">"+
                this.getRawDataPaneAction("edit")+
                "</div>"+
                "<div class=\"yellow-bar-right\">"+
                this.getRawDataPaneAction("create")+
                this.getRawDataPaneAction("delete")+
                this.getRawDataPaneAction("menu", yellow.user.name, true)+
                "</div>"+
                "<div class=\"yellow-bar-banner\"></div>";
        } else {
            elementDiv.innerHTML = "&nbsp;";
        }
        elementBar.appendChild(elementDiv);
        yellow.toolbox.insertBefore(elementBar, document.getElementsByTagName("body")[0].firstChild);
        this.bindActions(elementBar);
    },
    
    // Update bar
    updateBar: function(paneId, name) {
        if (paneId) {
            var element = document.getElementById(paneId+"-bar");
            if (element) {
                if (name.indexOf("selected")!=-1) element.setAttribute("aria-expanded", "true");
                yellow.toolbox.addClass(element, name);
            }
        } else {
            var elements = document.getElementsByClassName(name);
            for (var i=0, l=elements.length; i<l; i++) {
                if (name.indexOf("selected")!=-1) elements[i].setAttribute("aria-expanded", "false");
                yellow.toolbox.removeClass(elements[i], name);
            }
        }
    },
    
    // Create pane
    createPane: function(paneId, paneAction, paneStatus) {
        if (yellow.system.coreDebugMode) console.log("yellow.edit.createPane id:"+paneId);
        var elementPane = document.createElement("div");
        elementPane.className = "yellow-pane";
        elementPane.setAttribute("id", paneId);
        elementPane.style.display = "none";
        if (paneId=="yellow-pane-create" || paneId=="yellow-pane-edit") {
            yellow.toolbox.addEvent(elementPane, "input", yellow.onUpdatePane);
            yellow.toolbox.addEvent(elementPane, "dragenter", yellow.onDrag);
            yellow.toolbox.addEvent(elementPane, "dragover", yellow.onDrag);
            yellow.toolbox.addEvent(elementPane, "drop", yellow.onDrop);
        }
        if (paneId=="yellow-pane-create" || paneId=="yellow-pane-edit" || paneId=="yellow-pane-delete" || paneId=="yellow-pane-menu") {
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
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("LoginTitle")+"</h1></div>"+
                "<div class=\"yellow-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"login\" />"+
                "<p><label for=\"yellow-pane-login-email\">"+this.getText("LoginEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-login-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.system.editLoginEmail)+"\" /></p>"+
                "<p><label for=\"yellow-pane-login-password\">"+this.getText("LoginPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-login-password\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.system.editLoginPassword)+"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\" /></p>"+
                "<p><a href=\"#\" id=\"yellow-pane-login-forgot\" class=\"yellow-center\" data-action=\"forgot\">"+this.getText("LoginForgot")+"</a><br /><a href=\"#\" id=\"yellow-pane-login-signup\" class=\"yellow-center\" data-action=\"signup\">"+this.getText("LoginSignup")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-signup":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("SignupTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-signup-status\" class=\""+paneStatus+"\">"+this.getText("SignupStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"signup\" />"+
                "<p><label for=\"yellow-pane-signup-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-signup-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-signup-email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-signup-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-signup-password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-signup-password\" maxlength=\"64\" value=\"\" /></p>"+
                "<p><input type=\"checkbox\" name=\"consent\" value=\"consent\" id=\"yellow-pane-signup-consent\""+(this.getRequest("consent") ? " checked=\"checked\"" : "")+"> <label for=\"yellow-pane-signup-consent\">"+this.getText("SignupConsent")+"</label></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("SignupButton")+"\" /></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-forgot":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("ForgotTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-forgot-status\" class=\""+paneStatus+"\">"+this.getText("ForgotStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"forgot\" />"+
                "<p><label for=\"yellow-pane-forgot-email\">"+this.getText("ForgotEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-forgot-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-recover":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("RecoverTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-recover-status\" class=\""+paneStatus+"\">"+this.getText("RecoverStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\">"+
                "<p><label for=\"yellow-pane-recover-password\">"+this.getText("RecoverPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-recover-password\" maxlength=\"64\" value=\"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-quit":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1>"+this.getText("QuitTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-quit-status\" class=\""+paneStatus+"\">"+this.getText("QuitStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"quit\" />"+
                "<input type=\"hidden\" name=\"csrftoken\" value=\""+yellow.toolbox.encodeHtml(this.getCookie("csrftoken"))+"\" />"+
                "<p><label for=\"yellow-pane-quit-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-quit-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("DeleteButton")+"\" /></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-account":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1 id=\"yellow-pane-account-title\">"+this.getText("AccountTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-account-status\" class=\""+paneStatus+"\">"+this.getText("AccountStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-settings\">"+
                "<div id=\"yellow-pane-account-settings-actions\" class=\"yellow-settings-left\"><p>"+this.getRawDataSettingsActions(paneAction)+"</p></div>"+
                "<div id=\"yellow-pane-account-settings-separator\" class=\"yellow-settings-left yellow-settings-separator\">&nbsp;</div>"+
                "<div id=\"yellow-pane-account-settings-fields\" class=\"yellow-settings-right yellow-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"account\" />"+
                "<input type=\"hidden\" name=\"csrftoken\" value=\""+yellow.toolbox.encodeHtml(this.getCookie("csrftoken"))+"\" />"+
                "<p><label for=\"yellow-pane-account-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-account-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-account-email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-account-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-account-password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-account-password\" maxlength=\"64\" value=\"\" /></p>"+
                "<p>"+this.getRawDataLanguages(paneId)+"</p>"+
                "<p>"+this.getText("AccountInformation")+" <a href=\"#\" data-action=\"quit\">"+this.getText("AccountMore")+"</a></p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("ChangeButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-settings yellow-settings-banner\"></div>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-configure":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1 id=\"yellow-pane-configure-title\">"+this.getText("ConfigureTitle")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-configure-status\" class=\""+paneStatus+"\">"+this.getText("ConfigureStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-settings\">"+
                "<div id=\"yellow-pane-configure-settings-actions\" class=\"yellow-settings-left\"><p>"+this.getRawDataSettingsActions(paneAction)+"</p></div>"+
                "<div id=\"yellow-pane-configure-settings-separator\" class=\"yellow-settings-left yellow-settings-separator\">&nbsp;</div>"+
                "<div id=\"yellow-pane-configure-settings-fields\" class=\"yellow-settings-right yellow-fields\">"+
                "<input type=\"hidden\" name=\"action\" value=\"configure\" />"+
                "<input type=\"hidden\" name=\"csrftoken\" value=\""+yellow.toolbox.encodeHtml(this.getCookie("csrftoken"))+"\" />"+
                "<p><label for=\"yellow-pane-configure-sitename\">"+this.getText("ConfigureSitename")+"</label><br /><input class=\"yellow-form-control\" name=\"sitename\" id=\"yellow-pane-configure-sitename\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("sitename"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-configure-author\">"+this.getText("ConfigureAuthor")+"</label><br /><input class=\"yellow-form-control\" name=\"author\" id=\"yellow-pane-configure-author\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("author"))+"\" /></p>"+
                "<p><label for=\"yellow-pane-configure-email\">"+this.getText("ConfigureEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-configure-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
                "<p>"+this.getText("ConfigureInformation")+"</p>"+
                "<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("ChangeButton")+"\" /></p>"+
                "</div>"+
                "<div class=\"yellow-settings yellow-settings-banner\"></div>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-update":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1 id=\"yellow-pane-update-title\">"+yellow.toolbox.encodeHtml(yellow.system.coreProductRelease)+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-update-status\" class=\""+paneStatus+"\">"+this.getText("UpdateStatus", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-output\" id=\"yellow-pane-update-output\">"+yellow.page.rawDataOutput+"</div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-update-buttons\">"+
                "<p><a href=\"#\" id=\"yellow-pane-update-submit\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            case "yellow-pane-create":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<div id=\"yellow-pane-create-toolbar\">"+
                "<div class=\"yellow-toolbar yellow-toolbar-left\"><h1 id=\"yellow-pane-create-toolbar-title\">"+this.getText("Create")+"</h1></div>"+
                "<ul id=\"yellow-pane-create-toolbar-buttons\" class=\"yellow-toolbar yellow-toolbar-left\">"+this.getRawDataButtons(paneId)+"</ul>"+
                "<ul id=\"yellow-pane-create-toolbar-main\" class=\"yellow-toolbar yellow-toolbar-right\">"+
                "<li><a href=\"#\" id=\"yellow-pane-create-cancel\" class=\"yellow-toolbar-btn\" data-action=\"close\">"+this.getText("CancelButton")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-pane-create-submit\" class=\"yellow-toolbar-btn\" data-action=\"submit\">"+this.getText("CreateButton")+"</a></li>"+
                "</ul>"+
                "<ul class=\"yellow-toolbar yellow-toolbar-banner\"></ul>"+
                "</div>"+
                "<textarea id=\"yellow-pane-create-text\" class=\"yellow-edit-text\"></textarea>"+
                "<div id=\"yellow-pane-create-preview\" class=\"yellow-edit-preview\"></div>"+
                "</form>";
                break;
            case "yellow-pane-edit":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<div id=\"yellow-pane-edit-toolbar\">"+
                "<div class=\"yellow-toolbar yellow-toolbar-left\"><h1 id=\"yellow-pane-edit-toolbar-title\">"+this.getText("Edit")+"</h1></div>"+
                "<ul id=\"yellow-pane-edit-toolbar-buttons\" class=\"yellow-toolbar yellow-toolbar-left\">"+this.getRawDataButtons(paneId)+"</ul>"+
                "<ul id=\"yellow-pane-edit-toolbar-main\" class=\"yellow-toolbar yellow-toolbar-right\">"+
                "<li><a href=\"#\" id=\"yellow-pane-edit-cancel\" class=\"yellow-toolbar-btn\" data-action=\"close\">"+this.getText("CancelButton")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-pane-edit-submit\" class=\"yellow-toolbar-btn\" data-action=\"submit\">"+this.getText("EditButton")+"</a></li>"+
                "</ul>"+
                "<ul class=\"yellow-toolbar yellow-toolbar-banner\"></ul>"+
                "</div>"+
                "<textarea id=\"yellow-pane-edit-text\" class=\"yellow-edit-text\"></textarea>"+
                "<div id=\"yellow-pane-edit-preview\" class=\"yellow-edit-preview\"></div>"+
                "</form>";
                break;
            case "yellow-pane-delete":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<div id=\"yellow-pane-delete-toolbar\">"+
                "<div class=\"yellow-toolbar yellow-toolbar-left\"><h1 id=\"yellow-pane-delete-toolbar-title\">"+this.getText("Delete")+"</h1></div>"+
                "<ul id=\"yellow-pane-delete-toolbar-buttons\" class=\"yellow-toolbar yellow-toolbar-left\">"+this.getRawDataButtons(paneId)+"</ul>"+
                "<ul id=\"yellow-pane-delete-toolbar-main\" class=\"yellow-toolbar yellow-toolbar-right\">"+
                "<li><a href=\"#\" id=\"yellow-pane-delete-cancel\" class=\"yellow-toolbar-btn\" data-action=\"close\">"+this.getText("CancelButton")+"</a></li>"+
                "<li><a href=\"#\" id=\"yellow-pane-delete-submit\" class=\"yellow-toolbar-btn\" data-action=\"submit\">"+this.getText("DeleteButton")+"</a></li>"+
                "</ul>"+
                "<ul class=\"yellow-toolbar yellow-toolbar-banner\"></ul>"+
                "</div>"+
                "<textarea id=\"yellow-pane-delete-text\" class=\"yellow-edit-text\"></textarea>"+
                "<div id=\"yellow-pane-delete-preview\" class=\"yellow-edit-preview\"></div>"+
                "</form>";
                break;
            case "yellow-pane-menu":
                elementDiv.innerHTML =
                "<ul class=\"yellow-dropdown\">"+
                "<li><span>"+yellow.toolbox.encodeHtml(yellow.user.email)+"</span></li>"+
                "<li><a href=\"#\" data-action=\"settings\">"+this.getText("MenuSettings")+"</a></li>" +
                "<li><a href=\"#\" data-action=\"help\">"+this.getText("MenuHelp")+"</a></li>" +
                "<li><a href=\"#\" data-action=\"submit\" data-arguments=\"action:logout\">"+this.getText("MenuLogout")+"</a></li>"+
                "</ul>";
                break;
            case "yellow-pane-information":
                elementDiv.innerHTML =
                "<form method=\"post\">"+
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-title\"><h1 id=\"yellow-pane-information-title\">"+this.getText(paneAction+"Title")+"</h1></div>"+
                "<div class=\"yellow-status\"><p id=\"yellow-pane-information-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</p></div>"+
                "<div class=\"yellow-buttons\" id=\"yellow-pane-information-buttons\">"+
                "<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
                "</div>"+
                "</form>";
                break;
            default: elementDiv.innerHTML =
                "<a href=\"#\" class=\"yellow-close\" data-action=\"close\"><i class=\"yellow-icon yellow-icon-close\" aria-label=\""+this.getText("CancelButton")+"\"></i></a>"+
                "<div class=\"yellow-error\">Pane '"+paneId+"' was not found. Oh no...</div>";
        }
        elementPane.appendChild(elementDiv);
        yellow.toolbox.insertAfter(elementPane, document.getElementsByTagName("body")[0].firstChild);
        this.bindActions(elementPane);
    },

    // Update pane
    updatePane: function(paneId, paneAction, paneStatus, paneInit) {
        switch (paneId) {
            case "yellow-pane-login":
                if (paneInit && yellow.system.editLoginRestriction) {
                    yellow.toolbox.setVisible(document.getElementById("yellow-pane-login-signup"), false);
                }
                break;
            case "yellow-pane-quit":
                if (paneStatus=="none") {
                    document.getElementById("yellow-pane-quit-status").innerHTML = this.getText("QuitStatusNone");
                    document.getElementById("yellow-pane-quit-name").value = "";
                }
                break;
            case "yellow-pane-account":
                if (paneInit && yellow.system.editSettingsActions=="none") {
                    document.getElementById("yellow-pane-account-title").innerHTML = this.getText("MenuSettings");
                }
                if (paneStatus=="none") {
                    document.getElementById("yellow-pane-account-status").innerHTML = this.getText("AccountStatusNone");
                    document.getElementById("yellow-pane-account-name").value = yellow.user.name;
                    document.getElementById("yellow-pane-account-email").value = yellow.user.email;
                    document.getElementById("yellow-pane-account-password").value = "";
                    if (document.getElementById("yellow-pane-account-"+yellow.user.language)) {
                        document.getElementById("yellow-pane-account-"+yellow.user.language).checked = true;
                    }
                }
                break;
            case "yellow-pane-configure":
                if (paneStatus=="none") {
                    document.getElementById("yellow-pane-configure-status").innerHTML = this.getText("ConfigureStatusNone");
                    document.getElementById("yellow-pane-configure-sitename").value = yellow.system.sitename;
                    document.getElementById("yellow-pane-configure-author").value = yellow.system.author;
                    document.getElementById("yellow-pane-configure-email").value = yellow.system.email;
                }
                break;
            case "yellow-pane-update":
                if (paneStatus=="none") {
                    document.getElementById("yellow-pane-update-status").innerHTML = this.getText("UpdateStatusCheck");
                    document.getElementById("yellow-pane-update-output").innerHTML = "";
                    setTimeout("yellow.action('submit', '', 'action:update/option:check/');", 500);
                }
                if (paneStatus=="updates") {
                    document.getElementById(paneId+"-submit").innerHTML = this.getText("UpdateButton");
                    document.getElementById(paneId+"-submit").setAttribute("data-action", "submit");
                    document.getElementById(paneId+"-submit").setAttribute("data-arguments", "action:update");
                }
                break;
            case "yellow-pane-create":
            case "yellow-pane-edit":
            case "yellow-pane-delete":
                document.getElementById(paneId+"-text").focus();
                if (paneInit) {
                    yellow.toolbox.setVisible(document.getElementById(paneId+"-text"), true);
                    yellow.toolbox.setVisible(document.getElementById(paneId+"-preview"), false);
                    document.getElementById(paneId+"-toolbar-title").innerHTML = yellow.toolbox.encodeHtml(yellow.page.title);
                    document.getElementById(paneId+"-text").value = paneId=="yellow-pane-create" ? yellow.page.rawDataNew : yellow.page.rawDataEdit;
                    var matches = document.getElementById(paneId+"-text").value.match(/^(\xEF\xBB\xBF)?\-\-\-[\r\n]+/);
                    var position = document.getElementById(paneId+"-text").value.indexOf("\n", matches ? matches[0].length : 0);
                    document.getElementById(paneId+"-text").setSelectionRange(position, position);
                    if (yellow.system.editToolbarButtons!="none") {
                        yellow.toolbox.setVisible(document.getElementById(paneId+"-toolbar-title"), false);
                        this.updateToolbar(0, "yellow-toolbar-checked");
                    }
                    if (!this.isUserAccess(paneAction, yellow.page.location) || (yellow.page.rawDataReadonly && paneId!="yellow-pane-create")) {
                        document.getElementById(paneId+"-text").readOnly = true;
                        var elements = document.getElementsByClassName("yellow-toolbar-btn-icon");
                        for (var i=0, l=elements.length; i<l; i++) {
                            yellow.toolbox.addClass(elements[i], "yellow-toolbar-disabled");
                        }
                        yellow.toolbox.setVisible(document.getElementById(paneId+"-submit"), false);
                    }
                }
                if (!document.getElementById(paneId+"-text").readOnly) {
                    paneAction = this.paneAction = this.getPaneAction(paneId);
                    var className = "yellow-toolbar-btn yellow-toolbar-btn-"+paneAction;
                    if (document.getElementById(paneId+"-submit").className != className) {
                        document.getElementById(paneId+"-submit").className = className;
                        document.getElementById(paneId+"-submit").innerHTML = this.getText(paneAction+"Button");
                        document.getElementById(paneId+"-submit").setAttribute("data-arguments", "action:"+paneAction);
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
            case "yellow-pane-account":
            case "yellow-pane-configure":
                yellow.toolbox.setOuterLeft(document.getElementById(paneId), paneLeft);
                yellow.toolbox.setOuterTop(document.getElementById(paneId), paneTop);
                yellow.toolbox.setOuterWidth(document.getElementById(paneId), paneWidth);
                var elementWidth = yellow.toolbox.getWidth(document.getElementById(paneId));
                var actionsWidth = yellow.toolbox.getOuterWidth(document.getElementById(paneId+"-settings-actions"));
                var fieldsWidth = yellow.toolbox.getOuterWidth(document.getElementById(paneId+"-settings-fields"));
                var separatorWidth = Math.max(10, ((elementWidth-fieldsWidth)/2)-actionsWidth);
                yellow.toolbox.setOuterWidth(document.getElementById(paneId+"-settings-separator"), separatorWidth);
                break;
            case "yellow-pane-create":
            case "yellow-pane-edit":
            case "yellow-pane-delete":
                yellow.toolbox.setOuterLeft(document.getElementById(paneId), paneLeft);
                yellow.toolbox.setOuterTop(document.getElementById(paneId), paneTop);
                yellow.toolbox.setOuterHeight(document.getElementById(paneId), paneHeight);
                yellow.toolbox.setOuterWidth(document.getElementById(paneId), paneWidth);
                var elementWidth = yellow.toolbox.getWidth(document.getElementById(paneId));
                yellow.toolbox.setOuterWidth(document.getElementById(paneId+"-text"), elementWidth);
                yellow.toolbox.setOuterWidth(document.getElementById(paneId+"-preview"), elementWidth);
                var buttonsWidth = 0;
                var buttonsWidthMax = yellow.toolbox.getOuterWidth(document.getElementById(paneId+"-toolbar")) -
                    yellow.toolbox.getOuterWidth(document.getElementById(paneId+"-toolbar-main")) - 1;
                var element = document.getElementById(paneId+"-toolbar-buttons").firstChild;
                for (; element; element=element.nextSibling) {
                    element.removeAttribute("style");
                    buttonsWidth += yellow.toolbox.getOuterWidth(element);
                    if (buttonsWidth>buttonsWidthMax) yellow.toolbox.setVisible(element, false);
                }
                yellow.toolbox.setOuterWidth(document.getElementById(paneId+"-toolbar-title"), buttonsWidthMax);
                var height1 = yellow.toolbox.getHeight(document.getElementById(paneId));
                var height2 = yellow.toolbox.getOuterHeight(document.getElementById(paneId+"-toolbar"));
                yellow.toolbox.setOuterHeight(document.getElementById(paneId+"-text"), height1 - height2);
                yellow.toolbox.setOuterHeight(document.getElementById(paneId+"-preview"), height1 - height2);
                var elementLink = document.getElementById(paneId+"-bar");
                var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
                position -= yellow.toolbox.getOuterLeft(document.getElementById(paneId)) + 1;
                yellow.toolbox.setOuterLeft(document.getElementById(paneId+"-arrow"), position);
                break;
            case "yellow-pane-menu":
                yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-menu"), paneLeft + paneWidth - yellow.toolbox.getOuterWidth(document.getElementById("yellow-pane-menu")));
                yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-menu"), paneTop);
                var elementLink = document.getElementById("yellow-pane-menu-bar");
                var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
                position -= yellow.toolbox.getOuterLeft(document.getElementById("yellow-pane-menu"));
                yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-menu-arrow"), position);
                break;
            default:
                yellow.toolbox.setOuterLeft(document.getElementById(paneId), paneLeft);
                yellow.toolbox.setOuterTop(document.getElementById(paneId), paneTop);
                yellow.toolbox.setOuterWidth(document.getElementById(paneId), paneWidth);
                break;
        }
    },
    
    // Show or hide pane
    showPane: function(paneId, paneAction, paneStatus, paneModal) {
        if (this.paneId!=paneId || this.paneAction!=paneAction) {
            this.hidePane(this.paneId);
            var paneInit = !document.getElementById(paneId);
            if (!document.getElementById(paneId)) this.createPane(paneId, paneAction, paneStatus);
            var element = document.getElementById(paneId);
            if (!yellow.toolbox.isVisible(element)) {
                if (yellow.system.coreDebugMode) console.log("yellow.edit.showPane id:"+paneId);
                yellow.toolbox.setVisible(element, true);
                if (paneModal) {
                    yellow.toolbox.addClass(document.body, "yellow-body-modal-open");
                    yellow.toolbox.addValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
                }
                this.paneId = paneId;
                this.paneAction = paneAction;
                this.paneStatus = paneStatus;
                this.updatePane(paneId, paneAction, paneStatus, paneInit);
                this.resizePane(paneId, paneAction, paneStatus);
                this.updateBar(paneId, "yellow-bar-selected");
            }
        } else {
            this.hidePane(this.paneId, true);
        }
    },

    // Hide pane
    hidePane: function(paneId, fadeout) {
        var element = document.getElementById(paneId);
        if (yellow.toolbox.isVisible(element)) {
            if (yellow.system.coreDebugMode) console.log("yellow.edit.hidePane id:"+paneId);
            yellow.toolbox.removeClass(document.body, "yellow-body-modal-open");
            yellow.toolbox.removeValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
            yellow.toolbox.setVisible(element, false, fadeout);
            this.paneId = 0;
            this.paneAction = 0;
            this.paneStatus = 0;
            this.updateBar(0, "yellow-bar-selected");
        }
        this.hidePopup(this.popupId);
    },
    
    // Process action
    processAction: function(action, status, arguments) {
        action = action ? action : "none";
        status = status ? status : "none";
        arguments = arguments ? arguments : "none";
        if (action!="none") {
            if (yellow.system.coreDebugMode) console.log("yellow.edit.processAction action:"+action+" status:"+status);
            var paneId = (status!="next" && status!="done") ? "yellow-pane-"+action : "yellow-pane-information";
            switch(action) {
                case "login":       this.showPane(paneId, action, status); break;
                case "signup":      this.showPane(paneId, action, status); break;
                case "confirm":     this.showPane(paneId, action, status); break;
                case "approve":     this.showPane(paneId, action, status); break;
                case "forgot":      this.showPane(paneId, action, status); break;
                case "recover":     this.showPane(paneId, action, status); break;
                case "reactivate":  this.showPane(paneId, action, status); break;
                case "verify":      this.showPane(paneId, action, status); break;
                case "change":      this.showPane(paneId, action, status); break;
                case "quit":        this.showPane(paneId, action, status); break;
                case "remove":      this.showPane(paneId, action, status); break;
                case "account":     this.showPane(paneId, action, status); break;
                case "configure":   this.showPane(paneId, action, status); break;
                case "update":      this.showPane(paneId, action, status); break;
                case "create":      this.showPane(paneId, action, status, true); break;
                case "edit":        this.showPane(paneId, action, status, true); break;
                case "delete":      this.showPane(paneId, action, status, true); break;
                case "menu":        this.showPane(paneId, action, status); break;
                case "toolbar":     this.processToolbar(status, arguments); break;
                case "settings":    this.processSettings(arguments); break;
                case "submit":      this.processSubmit(arguments); break;
                case "restore":     this.processSubmit("action:"+action); break;
                case "help":        this.processHelp(); break;
                case "close":       this.processClose(); break;
            }
        }
    },
    
    // Process toolbar
    processToolbar: function(status, arguments) {
        if (yellow.system.coreDebugMode) console.log("yellow.edit.processToolbar status:"+status);
        var elementText = document.getElementById(this.paneId+"-text");
        var elementPreview = document.getElementById(this.paneId+"-preview");
        if (!yellow.toolbox.isVisible(elementPreview) && !elementText.readOnly) {
            switch (status) {
                case "h1":              yellow.editor.setMarkdown(elementText, "# ", "insert-multiline-block", true); break;
                case "h2":              yellow.editor.setMarkdown(elementText, "## ", "insert-multiline-block", true); break;
                case "h3":              yellow.editor.setMarkdown(elementText, "### ", "insert-multiline-block", true); break;
                case "paragraph":       yellow.editor.setMarkdown(elementText, "", "remove-multiline-block");
                                        yellow.editor.setMarkdown(elementText, "", "remove-fenced-block"); break;
                case "notice":          yellow.editor.setMarkdown(elementText, "! ", "insert-multiline-block", true); break;
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
                case "text":            yellow.editor.setMarkdown(elementText, arguments, "insert"); break;
                case "status":          yellow.editor.setMetaData(elementText, "status", true); break;
                case "file":            this.showFileDialog(); break;
                case "undo":            yellow.editor.undo(); break;
                case "redo":            yellow.editor.redo(); break;
            }
            if (this.isExpandable(status)) {
                this.showPopup("yellow-popup-"+status, status);
            } else {
                this.hidePopup(this.popupId);
            }
        }
        if (!elementText.readOnly) {
            if (status=="preview") this.showPreview(elementText, elementPreview);
            if (status=="save" && this.paneAction!="delete") this.processSubmit("action:"+this.paneAction);
            if (status=="help") window.open(this.getText("YellowHelpUrl"), "_blank");
        }
    },
    
    // Update toolbar
    updateToolbar: function(status, name) {
        if (status) {
            var element = document.getElementById(this.paneId+"-toolbar-"+status);
            if (element) {
                if (name.indexOf("selected")!=-1) element.setAttribute("aria-expanded", "true");
                yellow.toolbox.addClass(element, name);
            }
        } else {
            var elements = document.getElementsByClassName(name);
            for (var i=0, l=elements.length; i<l; i++) {
                if (name.indexOf("selected")!=-1) elements[i].setAttribute("aria-expanded", "false");
                yellow.toolbox.removeClass(elements[i], name);
            }
        }
    },
    
    // Process shortcut
    processShortcut: function(e) {
        var shortcut = yellow.toolbox.getEventShortcut(e);
        if (shortcut) {
            var tokens = yellow.system.editKeyboardShortcuts.split(/\s*,\s*/);
            for (var i=0; i<tokens.length; i++) {
                var pair = tokens[i].split(" ");
                if (shortcut==pair[0] || shortcut.replace("meta+", "ctrl+")==pair[0]) {
                    if (yellow.system.coreDebugMode) console.log("yellow.edit.processShortcut shortcut:"+shortcut);
                    e.stopPropagation();
                    e.preventDefault();
                    this.processToolbar(pair[1]);
                }
            }
        }
    },
    
    // Process settings
    processSettings: function(arguments) {
        var action = arguments!="none" ? arguments : "account";
        if (action!=this.paneAction && action!="settings") this.processAction(action);
    },
    
    // Process submit
    processSubmit: function(arguments) {
        var settings = { "action":"none", "csrftoken":this.getCookie("csrftoken") };
        var tokens = arguments.split("/");
        for (var i=0; i<tokens.length; i++) {
            var pair = tokens[i].split(/[:=]/);
            if (!pair[0] || !pair[1]) continue;
            settings[pair[0]] = pair[1];
        }
        if (settings["action"]=="create" || settings["action"]=="edit" || settings["action"]=="delete") {
            settings.rawdatasource = yellow.page.rawDataSource;
            settings.rawdataedit = document.getElementById(this.paneId+"-text").value;
            settings.rawdataendofline = yellow.page.rawDataEndOfLine;
        }
        if (settings["action"]!="none") yellow.toolbox.submitForm(settings);
    },
    
    // Process help
    processHelp: function() {
        this.hidePane(this.paneId);
        window.open(this.getText("YellowHelpUrl"), "_self");
    },
    
    // Process close
    processClose: function() {
        this.hidePane(this.paneId);
        if (yellow.page.action=="login") {
            var url = yellow.system.coreServerScheme+"://"+
                yellow.system.coreServerAddress+
                yellow.system.coreServerBase+
                yellow.page.location;
            window.open(url, "_self");
        }
    },
    
    // Create popup
    createPopup: function(popupId) {
        if (yellow.system.coreDebugMode) console.log("yellow.edit.createPopup id:"+popupId);
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
                "<li><a href=\"#\" id=\"yellow-popup-format-notice\" data-action=\"toolbar\" data-status=\"notice\">"+this.getText("ToolbarNotice")+"</a></li>"+
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
                "<li><a href=\"#\" id=\"yellow-popup-list-tl\" data-action=\"toolbar\" data-status=\"tl\">"+this.getText("ToolbarTl")+"</a></li>"+
                "</ul>";
                break;
            case "yellow-popup-emojiawesome":
                var rawDataEmojis = "";
                if (yellow.system.emojiawesomeToolbarButtons && yellow.system.emojiawesomeToolbarButtons!="none") {
                    var tokens = yellow.system.emojiawesomeToolbarButtons.split(" ");
                    for (var i=0; i<tokens.length; i++) {
                        var token = tokens[i].replace(/[\:]/g,"");
                        var className = token.replace("+1", "plus1").replace("-1", "minus1").replace(/_/g, "-");
                        rawDataEmojis += "<li><a href=\"#\" id=\"yellow-popup-list-"+yellow.toolbox.encodeHtml(token)+"\" data-action=\"toolbar\" data-status=\"text\" data-arguments=\":"+yellow.toolbox.encodeHtml(token)+":\"><i class=\"ea ea-"+yellow.toolbox.encodeHtml(className)+"\"></i></a></li>";
                    }
                }
                elementDiv.innerHTML = "<ul class=\"yellow-dropdown yellow-dropdown-menu\">"+rawDataEmojis+"</ul>";
                break;
            case "yellow-popup-fontawesome":
                var rawDataIcons = "";
                if (yellow.system.fontawesomeToolbarButtons && yellow.system.fontawesomeToolbarButtons!="none") {
                    var tokens = yellow.system.fontawesomeToolbarButtons.split(" ");
                    for (var i=0; i<tokens.length; i++) {
                        var token = tokens[i].replace(/[\:]/g,"");
                        rawDataIcons += "<li><a href=\"#\" id=\"yellow-popup-list-"+yellow.toolbox.encodeHtml(token)+"\" data-action=\"toolbar\" data-status=\"text\" data-arguments=\":"+yellow.toolbox.encodeHtml(token)+":\"><i class=\"fa "+yellow.toolbox.encodeHtml(token)+"\"></i></a></li>";
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
            if (yellow.system.coreDebugMode) console.log("yellow.edit.showPopup id:"+popupId);
            yellow.toolbox.setVisible(element, true);
            this.popupId = popupId;
            this.updateToolbar(status, "yellow-toolbar-selected");
            var elementParent = document.getElementById(this.paneId+"-toolbar-"+status);
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
            if (yellow.system.coreDebugMode) console.log("yellow.edit.hidePopup id:"+popupId);
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
            dispatchEvent(new Event("DOMContentLoaded"));
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
        element.setAttribute("accept", yellow.system.editUploadExtensions);
        element.setAttribute("multiple", "multiple");
        yellow.toolbox.addEvent(element, "change", yellow.onDrop);
        element.click();
    },
    
    // Upload file
    uploadFile: function(elementText, file) {
        if (this.isUserAccess("upload", yellow.page.location)) {
            var extension = (file.name.lastIndexOf(".")!=-1 ? file.name.substring(file.name.lastIndexOf("."), file.name.length) : "").toLowerCase();
            var extensions = yellow.system.editUploadExtensions.split(/\s*,\s*/);
            if (file.size<=yellow.system.coreFileSizeMax && extensions.indexOf(extension)!=-1) {
                var text = "["+this.getText("UploadProgress")+"]\u200b";
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
            } else {
                var textError = extensions.indexOf(extension)!=-1 ? "file too big!" : "file format not supported!";
                var textNew = "[Can't upload file '"+file.name+"', "+textError+"]";
                yellow.editor.setMarkdown(elementText, textNew, "insert");
            }
        } else {
            var textNew = "[Can't upload file '"+file.name+"', access is restricted!]";
            yellow.editor.setMarkdown(elementText, textNew, "insert");
        }
    },
    
    // Upload done
    uploadFileDone: function(elementText, responseText) {
        var result = JSON.parse(responseText);
        if (result) {
            var textOld = "["+this.getText("UploadProgress")+"]\u200b";
            var textNew;
            if (result.location.substring(0, yellow.system.coreImageLocation.length)==yellow.system.coreImageLocation) {
                textNew = "[image "+result.location.substring(yellow.system.coreImageLocation.length)+"]";
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
            var textOld = "["+this.getText("UploadProgress")+"]\u200b";
            var textNew = "["+result.error+"]";
            yellow.editor.replace(elementText, textOld, textNew);
        }
    },

    // Bind actions to links
    bindActions: function(element) {
        var elements = element.getElementsByTagName("a");
        for (var i=0, l=elements.length; i<l; i++) {
            if (elements[i].getAttribute("href") && elements[i].getAttribute("href").substring(0, 13)=="#data-action-") {
                elements[i].setAttribute("data-action", elements[i].getAttribute("href").substring(13));
            }
            if (elements[i].getAttribute("data-action")) elements[i].onclick = yellow.onClickAction;
            if (elements[i].getAttribute("data-action")=="toolbar") elements[i].onmousedown = function(e) { e.preventDefault(); };
        }
    },
    
    // Return pane action
    getPaneAction: function(paneId) {
        var panePrefix = "yellow-pane-";
        var paneAction = paneId.substring(panePrefix.length);
        if (paneAction=="edit") {
            if (document.getElementById("yellow-pane-edit-text").value.length==0) paneAction = "delete";
            if (yellow.page.statusCode==434 || yellow.page.statusCode==435) paneAction = "create";
        }
        return paneAction;
    },
    
    // Return raw data for pane action
    getRawDataPaneAction: function(paneAction, text, important) {
        var rawDataAction = "";
        if (this.isUserAccess(paneAction) || important) {
            if (!text) text = this.getText(paneAction);
            rawDataAction = "<a href=\"#\" id=\"yellow-pane-"+paneAction+"-bar\" data-action=\""+paneAction+"\" aria-expanded=\"false\">"+yellow.toolbox.encodeHtml(text)+"</a>";
        }
        return rawDataAction;
    },
    
    // Return raw data for settings actions
    getRawDataSettingsActions: function(paneAction) {
        var rawDataActions = "";
        if (yellow.system.editSettingsActions && yellow.system.editSettingsActions!="none") {
            var tokens = yellow.system.editSettingsActions.split(/\s*,\s*/);
            for (var i=0; i<tokens.length; i++) {
                var token = tokens[i];
                rawDataActions += "<a href=\"#\""+(token==paneAction ? "class=\"active\"": "")+" data-action=\"settings\" data-arguments=\""+yellow.toolbox.encodeHtml(token)+"\">"+this.getText(token+"Title")+"</a><br />";
            }
        }
        return rawDataActions;
    },
    
    // Return raw data for languages
    getRawDataLanguages: function(paneId) {
        var rawDataLanguages = "";
        if (yellow.system.coreLanguages && Object.keys(yellow.system.coreLanguages).length>1) {
            for (var language in yellow.system.coreLanguages) {
                var checked = language==this.getRequest("language") ? " checked=\"checked\"" : "";
                rawDataLanguages += "<label for=\""+paneId+"-"+language+"\"><input type=\"radio\" name=\"language\" id=\""+paneId+"-"+language+"\" value=\""+language+"\""+checked+"> "+yellow.toolbox.encodeHtml(yellow.system.coreLanguages[language])+"</label><br />";
            }
        }
        return rawDataLanguages;
    },
    
    // Return raw data for buttons
    getRawDataButtons: function(paneId) {
        var rawDataButtons = "";
        if (yellow.system.editToolbarButtons && yellow.system.editToolbarButtons!="none") {
            var tokens = yellow.system.editToolbarButtons.split(/\s*,\s*/);
            for (var i=0; i<tokens.length; i++) {
                var token = tokens[i];
                if (token!="separator") {
                    var shortcut = this.getShortcut(token);
                    var rawDataShortcut = shortcut ? "&nbsp;&nbsp;"+yellow.toolbox.encodeHtml(shortcut) : "";
                    var rawDataExpandable = this.isExpandable(token) ? " aria-expanded=\"false\"" : "";
                    rawDataButtons += "<li><a href=\"#\" id=\""+paneId+"-toolbar-"+yellow.toolbox.encodeHtml(token)+"\" class=\"yellow-toolbar-btn-icon yellow-toolbar-tooltip\" data-action=\"toolbar\" data-status=\""+yellow.toolbox.encodeHtml(token)+"\" aria-label=\""+this.getText("Toolbar", "", token)+rawDataShortcut+"\""+rawDataExpandable+"><i class=\"yellow-icon yellow-icon-"+yellow.toolbox.encodeHtml(token)+"\"></i></a></li>";
                } else {
                    rawDataButtons += "<li><a href=\"#\" class=\"yellow-toolbar-btn-separator\"></a></li>";
                }
            }
        }
        return rawDataButtons;
    },
    
    // Return request data
    getRequest: function(key, prefix) {
        if (!prefix) prefix = "request";
        key = prefix + yellow.toolbox.toUpperFirst(key);
        return (key in yellow.page) ? yellow.page[key] : "";
    },
    
    // Return shortcut setting
    getShortcut: function(key) {
        var shortcut = "";
        var tokens = yellow.system.editKeyboardShortcuts.split(/\s*,\s*/);
        for (var i=0; i<tokens.length; i++) {
            var pair = tokens[i].split(" ");
            if (key==pair[1]) {
                shortcut = pair[0];
                break;
            }
        }
        var labels = yellow.language.editKeyboardLabels.split(/\s*,\s*/);
        if (navigator.platform.indexOf("Mac")==-1) {
            shortcut = shortcut.toUpperCase().replace("CTRL+", labels[0]).replace("ALT+", labels[1]).replace("SHIFT+", labels[2]);
        } else {
            shortcut = shortcut.toUpperCase().replace("CTRL+ALT+", "ALT+CTRL+").replace("CTRL+SHIFT+", "SHIFT+CTRL+");
            shortcut = shortcut.replace("CTRL+", labels[3]).replace("ALT+", labels[4]).replace("SHIFT+", labels[5]);
        }
        return shortcut;
    },

    // Return text setting
    getText: function(key, prefix, postfix) {
        if (!prefix) prefix = "edit";
        if (!postfix) postfix = "";
        key = prefix + yellow.toolbox.toUpperFirst(key) + yellow.toolbox.toUpperFirst(postfix);
        return (key in yellow.language) ? yellow.language[key] : "["+key+"]";
    },

    // Return browser cookie
    getCookie: function(key) {
        return yellow.toolbox.getCookie(key);
    },
    
    // Check if user with access
    isUserAccess: function(action, location) {
        var tokens = yellow.user.access.split(/\s*,\s*/);
        return tokens.indexOf(action)!=-1 && (!location || location.substring(0, yellow.user.home.length)==yellow.user.home);
    },

    // Check if element is expandable
    isExpandable: function(name) {
        return (name=="format" || name=="heading" || name=="list" || name=="emojiawesome" || name=="fontawesome");
    },
    
    // Check if extension exists
    isExtension: function(name) {
        return name in yellow.system.coreExtensions;
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
        if (yellow.system.coreDebugMode) console.log("yellow.editor.setMarkdown type:"+information.type);
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
            var matches = lines[i].match(/^(\s*[\#\*\-\!\>\s]+)?(\s+\[.\]|\s*\d+\.)?[ \t]+/);
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
    setMetaData: function(element, key, toggle) {
        var information = this.getMetaDataInformation(element, key);
        if (information.bottom!=0) {
            var value = "";
            if (key=="status") {
                var tokens = yellow.system.editStatusValues.split(/\s*,\s*/);
                var index = tokens.indexOf(information.value);
                value = tokens[index+1<tokens.length ? index+1 : index];
            }
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
            if (yellow.system.coreDebugMode) console.log("yellow.editor.setMetaData key:"+key);
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
    
    // Return browser cookie
    getCookie: function(key) {
        var matches = document.cookie.match("(^|; )"+key+"=([^;]+)");
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
    submitForm: function(arguments) {
        var elementForm = document.createElement("form");
        elementForm.setAttribute("method", "post");
        for (var key in arguments) {
            if (!arguments.hasOwnProperty(key)) continue;
            var elementInput = document.createElement("input");
            elementInput.setAttribute("type", "hidden");
            elementInput.setAttribute("name", key);
            elementInput.setAttribute("value", arguments[key]);
            elementForm.appendChild(elementInput);
        }
        document.body.appendChild(elementForm);
        elementForm.submit();
    }
};

yellow.edit.intervalId = setInterval("yellow.onLoad(new Event('DOMContentLoading'))", 1);
window.addEventListener("DOMContentLoaded", yellow.onLoad, false);
