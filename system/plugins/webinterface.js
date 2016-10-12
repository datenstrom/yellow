// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow API
var yellow =
{
	version: "0.6.13",
	action: function(action) { yellow.webinterface.action(action, "none"); },
	onLoad: function() { yellow.webinterface.loadInterface(); },
	onClick: function(e) { yellow.webinterface.hidePanesOnClick(yellow.toolbox.getEventElement(e)); },
	onKeydown: function(e) { yellow.webinterface.hidePanesOnKeydown(yellow.toolbox.getEventKeycode(e)); },
	onUpdate: function() { yellow.webinterface.updatePane(yellow.webinterface.paneId, yellow.webinterface.paneAction, yellow.webinterface.paneStatus); },
	onResize: function() { yellow.webinterface.resizePane(yellow.webinterface.paneId, yellow.webinterface.paneAction, yellow.webinterface.paneStatus); }
};

// Yellow web interface
yellow.webinterface =
{
	paneId: 0,			//visible pane ID
	paneAction: 0,		//current pane action
	paneStatus: 0,		//current pane status
	intervalId: 0,		//timer interval ID

	// Handle action
	action: function(action, status)
	{
		if(yellow.config.debug) console.log("yellow.webinterface.action action:"+action+" status:"+status);
		switch(action)
		{
			case "login":		this.showPane("yellow-pane-login", action, status); break;
			case "logout":		this.sendPane("yellow-pane-logout", action); break;
			case "signup":		this.showPane("yellow-pane-signup", action, status); break;
			case "confirm":		this.showPane("yellow-pane-signup", action, status); break;
			case "approve":		this.showPane("yellow-pane-signup", action, status); break;
			case "recover":		this.showPane("yellow-pane-recover", action, status); break;
			case "settings":	this.showPane("yellow-pane-settings", action, status); break;
			case "reconfirm":	this.showPane("yellow-pane-settings", action, status); break;
			case "change":		this.showPane("yellow-pane-settings", action, status); break;
			case "version":		this.showPane("yellow-pane-version", action, status); break;
			case "update":		this.sendPane("yellow-pane-version", action); break;
			case "create":		this.showPane("yellow-pane-edit", action, status, true); break;
			case "edit":		this.showPane("yellow-pane-edit", action, status, true); break;
			case "delete":		this.showPane("yellow-pane-edit", action, status, true); break;
			case "user":		this.showPane("yellow-pane-user", action, status); break;
			case "send":		this.sendPane(this.paneId, this.paneAction); break;
			case "close":		this.hidePane(this.paneId); break;
			case "help":		this.hidePane(this.paneId); location.href = this.getText("UserHelpUrl", "yellow"); break;
		}
	},
	
	// Initialise interface
	loadInterface: function()
	{
		var body = document.getElementsByTagName("body")[0];
		if(body && body.firstChild && !document.getElementById("yellow-bar"))
		{
			this.createBar("yellow-bar");
			this.createPane("yellow-pane-edit", "none", "none");
			this.action(yellow.page.action, yellow.page.status);
			clearInterval(this.intervalId);
		}
	},
	
	// Create bar
	createBar: function(barId)
	{
		if(yellow.config.debug) console.log("yellow.webinterface.createBar id:"+barId);
		var elementBar = document.createElement("div");
		elementBar.className = "yellow-bar";
		elementBar.setAttribute("id", barId);
		if(barId=="yellow-bar")
		{
			yellow.toolbox.addEvent(document, "click", yellow.onClick);
			yellow.toolbox.addEvent(document, "keydown", yellow.onKeydown);
			yellow.toolbox.addEvent(window, "resize", yellow.onResize);
		}
		if(yellow.config.userName)
		{
			elementBar.innerHTML =
				"<div class=\"yellow-bar-left\">"+
				"<a href=\"#\" onclick=\"yellow.action('edit'); return false;\" id=\"yellow-pane-edit-link\">"+this.getText("Edit")+"</a>"+
				"</div>"+
				"<div class=\"yellow-bar-right\">"+
				"<a href=\"#\" onclick=\"yellow.action('create'); return false;\" id=\"yellow-pane-create-link\">"+this.getText("Create")+"</a>"+
				"<a href=\"#\" onclick=\"yellow.action('delete'); return false;\" id=\"yellow-pane-delete-link\">"+this.getText("Delete")+"</a>"+
				"<a href=\"#\" onclick=\"yellow.action('user'); return false;\" id=\"yellow-pane-user-link\">"+yellow.toolbox.encodeHtml(yellow.config.userName)+"</a>"+
				"</div>";
		}
		yellow.toolbox.insertBefore(elementBar, document.getElementsByTagName("body")[0].firstChild);
		return elementBar;
	},
	
	// Create pane
	createPane: function(paneId, paneAction, paneStatus)
	{
		if(yellow.config.debug) console.log("yellow.webinterface.createPane id:"+paneId);
		var elementPane = document.createElement("div");
		elementPane.className = "yellow-pane";
		elementPane.setAttribute("id", paneId);
		elementPane.style.display = "none";
		if(paneId=="yellow-pane-edit")
		{
			yellow.toolbox.addEvent(elementPane, "keyup", yellow.onUpdate);
			yellow.toolbox.addEvent(elementPane, "change", yellow.onUpdate);
		}
		if(paneId=="yellow-pane-edit" || paneId=="yellow-pane-user")
		{
			var elementArrow = document.createElement("span");
			elementArrow.className = "yellow-arrow";
			elementArrow.setAttribute("id", paneId+"-arrow");
			elementPane.appendChild(elementArrow);
		}
		var elementDiv = document.createElement("div");
		elementDiv.setAttribute("id", paneId+"-content");
		switch(paneId)
		{
			case "yellow-pane-login":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-close\">x</a>"+
				"<h1>"+this.getText("LoginTitle")+"</h1>"+
				"<div id=\"yellow-pane-login-fields\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\" />"+
				"<p><label for=\"yellow-pane-login-email\">"+this.getText("LoginEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-login-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.config.loginEmail)+"\" /></p>"+
				"<p><label for=\"yellow-pane-login-password\">"+this.getText("LoginPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-login-password\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.config.loginPassword)+"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-login-buttons\">"+
				"<p><a href=\"#\" onclick=\"yellow.action('recover'); return false;\">"+this.getText("LoginRecover")+"</a><p>"+
				"<p><a href=\"#\" onclick=\"yellow.action('signup'); return false;\">"+this.getText("LoginSignup")+"</a><p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-signup":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-close\">x</a>"+
				"<h1>"+this.getText("SignupTitle")+"</h1>"+
				"<div id=\"yellow-pane-signup-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</div>"+
				"<div id=\"yellow-pane-signup-fields\">"+
				"<input type=\"hidden\" name=\"action\" value=\"signup\" />"+
				"<p><label for=\"yellow-pane-signup-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-signup-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
				"<p><label for=\"yellow-pane-signup-email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-signup-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
				"<p><label for=\"yellow-pane-signup-password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-signup-password\" maxlength=\"64\" value=\"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("SignupButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-signup-buttons\">"+
				"<p><input class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('close'); return false;\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-recover":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-close\">x</a>"+
				"<h1>"+this.getText("RecoverTitle")+"</h1>"+
				"<div id=\"yellow-pane-recover-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</div>"+
				"<div id=\"yellow-pane-recover-fields-first\">"+
				"<input type=\"hidden\" name=\"action\" value=\"recover\" />"+
				"<p><label for=\"yellow-pane-recover-email\">"+this.getText("RecoverEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-recover-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-recover-fields-second\">"+
				"<p><label for=\"yellow-pane-recover-password\">"+this.getText("RecoverPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-recover-password\" maxlength=\"64\" value=\"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-recover-buttons\">"+
				"<p><input class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('close'); return false;\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-settings":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-close\">x</a>"+
				"<h1 id=\"yellow-pane-settings-title\">"+this.getText("SettingsTitle")+"</h1>"+
				"<div id=\"yellow-pane-settings-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</div>"+
				"<div id=\"yellow-pane-settings-fields\">"+
				"<input type=\"hidden\" name=\"action\" value=\"settings\" />"+
				"<p><label for=\"yellow-pane-settings-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-settings-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
				"<p><label for=\"yellow-pane-settings-email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-settings-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
				"<p><label for=\"yellow-pane-settings-password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-settings-password\" maxlength=\"64\" value=\"\" /></p>"+this.getLanguages(paneId)+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-settings-buttons\">"+
				"<p><input class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('close'); return false;\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-version":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-close\">x</a>"+
				"<h1 id=\"yellow-pane-version-title\">"+yellow.toolbox.encodeHtml(yellow.config.serverVersion)+"</h1>"+
				"<div id=\"yellow-pane-version-status\" class=\""+paneStatus+"\">"+this.getText("VersionStatus", "", paneStatus)+"</div>"+
				"<div id=\"yellow-pane-version-fields\">"+yellow.page.rawDataOutput+"</div>"+
				"<div id=\"yellow-pane-version-buttons\">"+
				"<p><input class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('close'); return false;\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-edit":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-close\">x</a>"+
				"<h1 id=\"yellow-pane-edit-title\">"+this.getText("Edit")+"</h1>"+
				"<textarea id=\"yellow-pane-edit-page\" class=\"yellow-form-control\" name=\"rawdataedit\"></textarea>"+
				"<div id=\"yellow-pane-edit-buttons\">"+
				"<input id=\"yellow-pane-edit-send\" class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('send'); return false;\" value=\""+this.getText("EditButton")+"\" />"+
				"<a href=\""+this.getText("MarkdownHelpUrl", "yellow")+"\" target=\"_blank\" id=\"yellow-pane-edit-help\">"+this.getText("MarkdownHelp")+"</a>" +
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-user":
				elementDiv.innerHTML =
				"<p>"+yellow.toolbox.encodeHtml(yellow.config.userEmail)+"</p>"+
				"<p><a href=\"#\" onclick=\"yellow.action('settings'); return false;\">"+this.getText("SettingsTitle")+"</a></p>" +
				"<p><a href=\"#\" onclick=\"yellow.action('help'); return false;\">"+this.getText("UserHelp")+"</a></p>" +
				"<p><a href=\"#\" onclick=\"yellow.action('logout'); return false;\">"+this.getText("UserLogout")+"</a></p>";
				break;
		}
		elementPane.appendChild(elementDiv);
		yellow.toolbox.insertAfter(elementPane, document.getElementsByTagName("body")[0].firstChild);
		return elementPane;
	},

	// Update pane
	updatePane: function(paneId, paneAction, paneStatus, init)
	{
		if(yellow.config.debug) console.log("yellow.webinterface.updatePane id:"+paneId);
		var showFields = paneStatus!="next" && paneStatus!="done" && paneStatus!="expired";
		switch(paneId)
		{
			case "yellow-pane-login":
				if(yellow.config.loginRestrictions)
				{
					yellow.toolbox.setVisible(document.getElementById("yellow-pane-login-buttons"), false);
				}
				break;
			case "yellow-pane-signup":
				yellow.toolbox.setVisible(document.getElementById("yellow-pane-signup-fields"), showFields);
				yellow.toolbox.setVisible(document.getElementById("yellow-pane-signup-buttons"), !showFields);
				break;
			case "yellow-pane-recover":
				yellow.toolbox.setVisible(document.getElementById("yellow-pane-recover-fields-first"), showFields);
				yellow.toolbox.setVisible(document.getElementById("yellow-pane-recover-fields-second"), showFields);
				yellow.toolbox.setVisible(document.getElementById("yellow-pane-recover-buttons"), !showFields);
				if(showFields)
				{
					if(this.getRequest("id"))
					{
						yellow.toolbox.setVisible(document.getElementById("yellow-pane-recover-fields-first"), false);
					} else {
						yellow.toolbox.setVisible(document.getElementById("yellow-pane-recover-fields-second"), false);
					}
				}
				break;
			case "yellow-pane-settings":
				yellow.toolbox.setVisible(document.getElementById("yellow-pane-settings-fields"), showFields);
				yellow.toolbox.setVisible(document.getElementById("yellow-pane-settings-buttons"), !showFields);
				if(paneStatus=="none")
				{
					document.getElementById("yellow-pane-settings-status").innerHTML = "<a href=\"#\" onclick=\"yellow.action('version'); return false;\">"+yellow.toolbox.encodeHtml(yellow.config.serverVersion)+"</a>";
					document.getElementById("yellow-pane-settings-name").value = yellow.config.userName;
					document.getElementById("yellow-pane-settings-email").value = yellow.config.userEmail;
					document.getElementById("yellow-pane-settings-"+yellow.config.userLanguage).checked = true;
				}
				break;
			case "yellow-pane-version":
				if(paneStatus=="none")
				{
					document.getElementById("yellow-pane-version-status").innerHTML = this.getText("VersionStatus", "", paneStatus);
					document.getElementById("yellow-pane-version-fields").innerHTML = "";
					setTimeout("yellow.action('send');", 100);
				}
				if(paneStatus=="updates" && yellow.config.userWebmaster)
				{
					document.getElementById("yellow-pane-version-status").innerHTML = "<a href=\"#\" onclick=\"yellow.action('update'); return false;\">"+this.getText("VersionUpdate")+"</a>";
				}
				break;
			case "yellow-pane-edit":
				if(init)
				{
					var title = yellow.page.title;
					var string = yellow.page.rawDataEdit;
					switch(paneAction)
					{
						case "create":	title = this.getText("CreateTitle"); string = yellow.page.rawDataNew; break;
						case "delete":	title = this.getText("DeleteTitle"); break;
					}
					document.getElementById("yellow-pane-edit-title").innerHTML = yellow.toolbox.encodeHtml(title);
					document.getElementById("yellow-pane-edit-page").value = string;
					yellow.toolbox.setCursorPosition(document.getElementById("yellow-pane-edit-page"), 0);
				}
				paneAction = this.getPaneAction(paneId, paneAction);
				var key, className, readOnly;
				switch(paneAction)
				{
					case "create":	key = "CreateButton"; className = "yellow-btn yellow-btn-create"; readOnly = false; break;
					case "edit":	key = "EditButton"; className = "yellow-btn yellow-btn-edit"; readOnly = false; break;
					case "delete":	key = "DeleteButton"; className = "yellow-btn yellow-btn-delete"; readOnly = false; break;
					case "":		key = "CancelButton";  className = "yellow-btn yellow-btn-cancel"; readOnly = true; break;
				}
				document.getElementById("yellow-pane-edit-send").value = this.getText(key);
				document.getElementById("yellow-pane-edit-send").className = className;
				document.getElementById("yellow-pane-edit-page").readOnly = readOnly;
				break;
		}
	},

	// Resize pane
	resizePane: function(paneId, paneAction, paneStatus)
	{
		var elementBar = document.getElementById("yellow-bar");
		var paneLeft = yellow.toolbox.getOuterLeft(elementBar);
		var paneTop = yellow.toolbox.getOuterTop(elementBar) + yellow.toolbox.getOuterHeight(elementBar);
		var paneWidth = yellow.toolbox.getOuterWidth(elementBar);
		var paneHeight = yellow.toolbox.getWindowHeight() - paneTop - yellow.toolbox.getOuterHeight(elementBar);
		switch(paneId)
		{
			case "yellow-pane-login":
			case "yellow-pane-signup":
			case "yellow-pane-recover":
			case "yellow-pane-settings":
			case "yellow-pane-version":
				yellow.toolbox.setOuterLeft(document.getElementById(paneId), paneLeft);
				yellow.toolbox.setOuterTop(document.getElementById(paneId), paneTop);
				yellow.toolbox.setOuterWidth(document.getElementById(paneId), paneWidth);
				break;
			case "yellow-pane-edit":
				yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-edit"), paneLeft);
				yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-edit"), paneTop);
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit"), paneHeight);
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit"), paneWidth);
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit-page"), yellow.toolbox.getWidth(document.getElementById("yellow-pane-edit")));
				var height1 = yellow.toolbox.getHeight(document.getElementById("yellow-pane-edit"));
				var height2 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-content"));
				var height3 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-page"));
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit-page"), height1 - height2 + height3);
				var elementLink = document.getElementById("yellow-pane-"+paneAction+"-link");
				var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
				position -= yellow.toolbox.getOuterLeft(document.getElementById("yellow-pane-edit")) + 1;
				yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-edit-arrow"), position);
				break;
			case "yellow-pane-user":
				yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-user"), paneLeft + paneWidth - yellow.toolbox.getOuterWidth(document.getElementById("yellow-pane-user")));
				yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-user"), paneTop);
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-user"), paneHeight, true);
				var elementLink = document.getElementById("yellow-pane-user-link");
				var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
				position -= yellow.toolbox.getOuterLeft(document.getElementById("yellow-pane-user"));
				yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-user-arrow"), position);
				break;
		}
	},
	
	// Show or hide pane
	showPane: function(paneId, paneAction, paneStatus, modal)
	{
		if(this.paneId!=paneId || this.paneAction!=paneAction)
		{
			this.hidePane(this.paneId);
			var element = document.getElementById(paneId);
			if(!element) element = this.createPane(paneId, paneAction, paneStatus);
			if(!yellow.toolbox.isVisible(element))
			{
				if(yellow.config.debug) console.log("yellow.webinterface.showPane id:"+paneId);
				yellow.toolbox.setVisible(element, true);
				if(modal)
				{
					yellow.toolbox.addClass(document.body, "yellow-body-modal-open");
					yellow.toolbox.addValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
				}
				this.paneId = paneId;
				this.paneAction = paneAction;
				this.paneStatus = paneStatus;
				this.resizePane(paneId, paneAction, paneStatus);
				this.updatePane(paneId, paneAction, paneStatus, true);
			}
		} else {
			this.hidePane(this.paneId);
		}
	},

	// Hide pane
	hidePane: function(paneId)
	{
		var element = document.getElementById(paneId);
		if(yellow.toolbox.isVisible(element))
		{
			if(yellow.config.debug) console.log("yellow.webinterface.hidePane id:"+paneId);
			yellow.toolbox.removeClass(document.body, "yellow-body-modal-open");
			yellow.toolbox.removeValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
			yellow.toolbox.setVisible(element, false);
			this.paneId = 0;
			this.paneAction = 0;
			this.paneStatus = 0;
		}
	},

	// Hide all panes
	hidePanes: function()
	{
		for(var element=document.getElementById("yellow-bar"); element; element=element.nextSibling)
		{
			if(element.className && element.className.indexOf("yellow-pane")>=0)
			{
				this.hidePane(element.getAttribute("id"));
			}
		}
	},

	// Hide all panes on mouse click outside
	hidePanesOnClick: function(element)
	{
		for(;element; element=element.parentNode)
		{
			if(element.className)
			{
				if(element.className.indexOf("yellow-pane")>=0 || element.className.indexOf("yellow-bar-")>=0) return;
			}
		}
		this.hidePanes();
	},
	
	// Hide all panes on ESC key
	hidePanesOnKeydown: function(keycode)
	{
		if(keycode==27) this.hidePanes();
	},
	
	// Send pane
	sendPane: function(paneId, paneAction)
	{
		if(yellow.config.debug) console.log("yellow.webinterface.sendPane id:"+paneId);
		if(paneId=="yellow-pane-edit")
		{
			paneAction = this.getPaneAction(paneId, paneAction);
			if(paneAction)
			{
				var params = {};
				params.action = paneAction;
				params.rawdatasource = yellow.page.rawDataSource;
				params.rawdataedit = document.getElementById("yellow-pane-edit-page").value;
				yellow.toolbox.submitForm(params, true);
			} else {
				this.hidePane(paneId);
			}
		} else {
			yellow.toolbox.submitForm({"action":paneAction});
		}
	},
	
	// Return pane action
	getPaneAction: function(paneId, paneAction)
	{
		if(paneId=="yellow-pane-edit")
		{
			var string = document.getElementById("yellow-pane-edit-page").value;
			var paneActionOld = paneAction;
			switch(paneAction)
			{
				case "create":	paneAction = "create"; break;
				case "edit":	paneAction = string ? "edit" : "delete"; break;
				case "delete":	paneAction = "delete"; break;
			}
			if(yellow.page.statusCode==424 && paneActionOld!="delete") paneAction = "create";
			if(yellow.config.userRestrictions) paneAction = "";
		}
		return paneAction;
	},
	
	// Return language selection
	getLanguages: function(paneId)
	{
		var languages = "";
		if(yellow.config.serverLanguages && yellow.toolbox.getLength(yellow.config.serverLanguages)>1)
		{
			languages += "<p>";
			for(var language in yellow.config.serverLanguages)
			{
				var checked = language==this.getRequest("language") ? " checked=\"checked\"" : "";
				languages += "<label for=\""+paneId+"-"+language+"\"><input type=\"radio\" name=\"language\" id=\""+paneId+"-"+language+"\" value=\""+language+"\""+checked+"> "+yellow.config.serverLanguages[language]+"</label><br />";
			}
			languages += "</p>";
		}
		return languages;
	},

	// Return request string
	getRequest: function(key, prefix)
	{
		if(!prefix) prefix = "request";
		key = prefix + key.charAt(0).toUpperCase() + key.slice(1);
		return (key in yellow.page) ? yellow.page[key] : "";
	},

	// Return text string
	getText: function(key, prefix, postfix)
	{
		if(!prefix) prefix = "webinterface";
		if(!postfix) postfix = "";
		key = prefix + key.charAt(0).toUpperCase() + key.slice(1) + postfix.charAt(0).toUpperCase() + postfix.slice(1);
		return (key in yellow.text) ? yellow.text[key] : "["+key+"]";
	}
};

// Yellow toolbox with helpers
yellow.toolbox =
{
	// Insert element before reference element
	insertBefore: function(element, elementReference)
	{
		elementReference.parentNode.insertBefore(element, elementReference);
	},

	// Insert element after reference element
	insertAfter: function(element, elementReference)
	{
		elementReference.parentNode.insertBefore(element, elementReference.nextSibling);
	},

	// Add element class
	addClass: function(element, name)
	{
		var string = element.className + " " + name;
		element.className = string.replace(/^\s+|\s+$/, "");
	},

	// Remove element class
	removeClass: function(element, name)
	{
		var string = (" " + element.className + " ").replace(" " + name + " ", " ");
		element.className = string.replace(/^\s+|\s+$/, "");
	},
	
	// Add attribute information
	addValue: function(selector, name, value)
	{
		var element = document.querySelector(selector);
		element.setAttribute(name, element.getAttribute(name) + value);
	},

	// Remove attribute information
	removeValue: function(selector, name, value)
	{
		var element = document.querySelector(selector);
		element.setAttribute(name, element.getAttribute(name).replace(value, ""));
	},
	
	// Add event handler
	addEvent: function(element, type, handler)
	{
		if(element.addEventListener) element.addEventListener(type, handler, false);
		else element.attachEvent("on"+type, handler);
	},
	
	// Remove event handler
	removeEvent: function(element, type, handler)
	{
		if(element.removeEventListener) element.removeEventListener(type, handler, false);
		else element.detachEvent("on"+type, handler);
	},
	
	// Return element of event
	getEventElement: function(e)
	{
		e = e ? e : window.event;
		return e.target ? e.target : e.srcElement;
	},
	
	// Return keycode of event
	getEventKeycode: function(e)
	{
		e = e ? e : window.event;
		return e.keyCode;
	},
	
	// Return element length
	getLength: function(element)
	{
		return Object.keys ? Object.keys(element).length : 0;
	},
	
	// Return element width in pixel
	getWidth: function(element)
	{
		return element.offsetWidth - this.getBoxSize(element).width;
	},
	
	// Return element height in pixel
	getHeight: function(element)
	{
		return element.offsetHeight - this.getBoxSize(element).height;
	},
	
	// Set element width in pixel, including padding and border
	setOuterWidth: function(element, width, setMax)
	{
		width -= this.getBoxSize(element).width;
		if(setMax)
		{
			element.style.maxWidth = Math.max(0, width) + "px";
		} else {
			element.style.width = Math.max(0, width) + "px";
		}
	},
	
	// Set element height in pixel, including padding and border
	setOuterHeight: function(element, height, setMax)
	{
		height -= this.getBoxSize(element).height;
		if(setMax)
		{
			element.style.maxHeight = Math.max(0, height) + "px";
		} else {
			element.style.height = Math.max(0, height) + "px";
		}
	},
	
	// Return element width in pixel, including padding and border
	getOuterWidth: function(element, includeMargin)
	{
		var width = element.offsetWidth;
		if(includeMargin) width += this.getMarginSize(element).width;
		return width;
	},

	// Return element height in pixel, including padding and border
	getOuterHeight: function(element, includeMargin)
	{
		var height = element.offsetHeight;
		if(includeMargin) height += this.getMarginSize(element).height;
		return height;
	},
	
	// Set element left position in pixel
	setOuterLeft: function(element, left)
	{
		element.style.left = Math.max(0, left) + "px";
	},
	
	// Set element top position in pixel
	setOuterTop: function(element, top)
	{
		element.style.top = Math.max(0, top) + "px";
	},
	
	// Return element left position in pixel
	getOuterLeft: function(element)
	{
		var left = element.getBoundingClientRect().left;
		return left + (window.pageXOffset || document.documentElement.scrollLeft);
	},
	
	// Return element top position in pixel
	getOuterTop: function(element)
	{
		var top = element.getBoundingClientRect().top;
		return top + (window.pageYOffset || document.documentElement.scrollTop);
	},
	
	// Return window width in pixel
	getWindowWidth: function()
	{
		return window.innerWidth || document.documentElement.clientWidth;
	},
	
	// Return window height in pixel
	getWindowHeight: function()
	{
		return window.innerHeight || document.documentElement.clientHeight;
	},
	
	// Return element CSS property
	getStyle: function(element, property)
	{
		var string = "";
		if(window.getComputedStyle)
		{
			string = window.getComputedStyle(element, null).getPropertyValue(property);
		} else {
			property = property.replace(/\-(\w)/g, function(match, m) { return m.toUpperCase(); });
			string = element.currentStyle[property];
		}
		return string;
	},
	
	// Return element CSS padding and border
	getBoxSize: function(element)
	{
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
	getMarginSize: function(element)
	{
		var marginLeft = parseFloat(this.getStyle(element, "margin-left")) || 0;
		var marginRight = parseFloat(this.getStyle(element, "margin-right")) || 0;
		var width = marginLeft + marginRight;
		var marginTop = parseFloat(this.getStyle(element, "margin-top")) || 0;
		var marginBottom = parseFloat(this.getStyle(element, "margin-bottom")) || 0;
		var height = marginTop + marginBottom;
		return { "width":width, "height":height };
	},
	
	// Set input cursor position
	setCursorPosition: function(element, pos)
	{
		if(element.setSelectionRange)
		{
			element.focus();
			element.setSelectionRange(pos, pos);
		} else if(element.createTextRange) {
			var range = element.createTextRange();
			range.move("character", pos);
			range.select();
		}
	},

	// Get input cursor position
	getCursorPosition: function(element)
	{
		var pos = 0;
		if(element.setSelectionRange)
		{
			pos = element.selectionStart;
		} else if(document.selection) {
			var range = document.selection.createRange();
			var rangeDuplicate = range.duplicate();
			rangeDuplicate.moveToElementText(element);
			rangeDuplicate.setEndPoint("EndToEnd", range);
			pos = rangeDuplicate.text.length - range.text.length;
		}
		return pos;
	},
	
	// Set element visibility
	setVisible: function(element, show)
	{
		element.style.display = show ? "block" : "none";
	},

	// Check if element exists and is visible
	isVisible: function(element)
	{
		return element && element.style.display!="none";
	},
	
	// Encode newline characters
	encodeNewline: function(string)
	{
		return string
			.replace(/[%]/g, "%25")
			.replace(/[\r]/g, "%0d")
			.replace(/[\n]/g, "%0a");
	},

	// Encode HTML special characters
	encodeHtml: function(string)
	{
		return string
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	},
	
	// Submit form with post method
	submitForm: function(params, encodeNewline)
	{
		var elementForm = document.createElement("form");
		elementForm.setAttribute("method", "post");
		for(var key in params)
		{
			if(!params.hasOwnProperty(key)) continue;
			var value = encodeNewline ? this.encodeNewline(params[key]) : params[key];
			var elementInput = document.createElement("input");
			elementInput.setAttribute("type", "hidden");
			elementInput.setAttribute("name", key);
			elementInput.setAttribute("value", value);
			elementForm.appendChild(elementInput);
		}
		document.body.appendChild(elementForm);
		elementForm.submit();
	}
};

yellow.webinterface.intervalId = setInterval("yellow.onLoad()", 1);
