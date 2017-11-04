// Edit plugin, https://github.com/datenstrom/yellow-plugins/tree/master/edit
// Copyright (c) 2013-2017 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

var yellow =
{
	action: function(action, status, args) { yellow.edit.action(action, status, args); },
	onLoad: function() { yellow.edit.load(); },
	onClickAction: function(e) { yellow.edit.clickAction(e); },
	onClick: function(e) { yellow.edit.click(e); },
	onKeydown: function(e) { yellow.edit.keydown(e); },
	onUpdate: function() { yellow.edit.updatePane(yellow.edit.paneId, yellow.edit.paneAction, yellow.edit.paneStatus); },
	onResize: function() { yellow.edit.resizePane(yellow.edit.paneId, yellow.edit.paneAction, yellow.edit.paneStatus); }
};

yellow.edit =
{
	paneId: 0,			//visible pane ID
	paneActionOld: 0,	//previous pane action
	paneAction: 0,		//current pane action
	paneStatus: 0,		//current pane status
	intervalId: 0,		//timer interval ID

	// Handle initialisation
	load: function()
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
	
	// Handle action
	action: function(action, status, args)
	{
		status = status ? status : "none";
		args = args ? args : "none";
		switch(action)
		{
			case "login":		this.showPane("yellow-pane-login", action, status); break;
			case "logout":		this.sendPane("yellow-pane-logout", action); break;
			case "signup":		this.showPane("yellow-pane-signup", action, status); break;
			case "confirm":		this.showPane("yellow-pane-signup", action, status); break;
			case "approve":		this.showPane("yellow-pane-signup", action, status); break;
			case "reactivate":	this.showPane("yellow-pane-settings", action, status); break;
			case "recover":		this.showPane("yellow-pane-recover", action, status); break;
			case "settings":	this.showPane("yellow-pane-settings", action, status); break;
			case "reconfirm":	this.showPane("yellow-pane-settings", action, status); break;
			case "change":		this.showPane("yellow-pane-settings", action, status); break;
			case "version":		this.showPane("yellow-pane-version", action, status); break;
			case "update":		this.sendPane("yellow-pane-update", action, status, args); break;
			case "create":		this.showPane("yellow-pane-edit", action, status, true); break;
			case "edit":		this.showPane("yellow-pane-edit", action, status, true); break;
			case "delete":		this.showPane("yellow-pane-edit", action, status, true); break;
			case "user":		this.showPane("yellow-pane-user", action, status); break;
			case "help":		this.hidePane(this.paneId); location.href = this.getText("UserHelpUrl", "yellow"); break;
			case "send":		this.sendPane(this.paneId, this.paneAction); break;
			case "close":		this.hidePane(this.paneId); break;
		}
	},
	
	// Handle action clicked
	clickAction: function(e)
	{
		e.stopPropagation();
		e.preventDefault();
		var element = e.target;
		for(; element; element=element.parentNode)
		{
			if(element.tagName=="A") break;
		}
		this.action(element.getAttribute("data-action"), element.getAttribute("data-status"), element.getAttribute("data-args"));
	},
	
	// Handle mouse clicked
	click: function(e)
	{
		if(this.paneId && !document.getElementById(this.paneId).contains(e.target)) this.hidePane(this.paneId);
	},
	
	// Handle keyboard
	keydown: function(e)
	{
		if(this.paneId && e.keyCode==27) this.hidePane(this.paneId);
	},
	
	// Create bar
	createBar: function(barId)
	{
		if(yellow.config.debug) console.log("yellow.edit.createBar id:"+barId);
		var elementBar = document.createElement("div");
		elementBar.className = "yellow-bar";
		elementBar.setAttribute("id", barId);
		if(barId=="yellow-bar")
		{
			yellow.toolbox.addEvent(document, "click", yellow.onClick);
			yellow.toolbox.addEvent(document, "keydown", yellow.onKeydown);
			yellow.toolbox.addEvent(window, "resize", yellow.onResize);
		}
		var elementDiv = document.createElement("div");
		elementDiv.setAttribute("id", barId+"-content");
		if(yellow.config.userName)
		{
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
	createPane: function(paneId, paneAction, paneStatus)
	{
		if(yellow.config.debug) console.log("yellow.edit.createPane id:"+paneId);
		var elementPane = document.createElement("div");
		elementPane.className = "yellow-pane";
		elementPane.setAttribute("id", paneId);
		elementPane.style.display = "none";
		if(paneId=="yellow-pane-edit")
		{
			yellow.toolbox.addEvent(elementPane, "input", yellow.onUpdate);
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
				"<a href=\"#\" class=\"yellow-close\" data-action=\"close\">x</a>"+
				"<h1>"+this.getText("LoginTitle")+"</h1>"+
				"<div id=\"yellow-pane-login-fields\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\" />"+
				"<p><label for=\"yellow-pane-login-email\">"+this.getText("LoginEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-login-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.config.editLoginEmail)+"\" /></p>"+
				"<p><label for=\"yellow-pane-login-password\">"+this.getText("LoginPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-login-password\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(yellow.config.editLoginPassword)+"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-login-buttons\">"+
				"<p><a href=\"#\" id=\"yellow-pane-login-recover\" data-action=\"recover\">"+this.getText("LoginRecover")+"</a><p>"+
				"<p><a href=\"#\" id=\"yellow-pane-login-signup\" data-action=\"signup\">"+this.getText("LoginSignup")+"</a><p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-signup":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" class=\"yellow-close\" data-action=\"close\">x</a>"+
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
				"<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-recover":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" class=\"yellow-close\" data-action=\"close\">x</a>"+
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
				"<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-settings":
				var rawDataLanguages = "";
				if(yellow.config.serverLanguages && Object.keys(yellow.config.serverLanguages).length>1)
				{
					rawDataLanguages += "<p>";
					for(var language in yellow.config.serverLanguages)
					{
						var checked = language==this.getRequest("language") ? " checked=\"checked\"" : "";
						rawDataLanguages += "<label for=\"yellow-pane-settings-"+language+"\"><input type=\"radio\" name=\"language\" id=\"yellow-pane-settings-"+language+"\" value=\""+language+"\""+checked+"> "+yellow.toolbox.encodeHtml(yellow.config.serverLanguages[language])+"</label><br />";
					}
					rawDataLanguages += "</p>";
				}
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" class=\"yellow-close\" data-action=\"close\">x</a>"+
				"<h1 id=\"yellow-pane-settings-title\">"+this.getText("SettingsTitle")+"</h1>"+
				"<div id=\"yellow-pane-settings-status\" class=\""+paneStatus+"\">"+this.getText(paneAction+"Status", "", paneStatus)+"</div>"+
				"<div id=\"yellow-pane-settings-fields\">"+
				"<input type=\"hidden\" name=\"action\" value=\"settings\" />"+
				"<p><label for=\"yellow-pane-settings-name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"yellow-pane-settings-name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("name"))+"\" /></p>"+
				"<p><label for=\"yellow-pane-settings-email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"yellow-pane-settings-email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("email"))+"\" /></p>"+
				"<p><label for=\"yellow-pane-settings-password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"yellow-pane-settings-password\" maxlength=\"64\" value=\"\" /></p>"+rawDataLanguages+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-settings-buttons\">"+
				"<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-version":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" class=\"yellow-close\" data-action=\"close\">x</a>"+
				"<h1 id=\"yellow-pane-version-title\">"+yellow.toolbox.encodeHtml(yellow.config.serverVersion)+"</h1>"+
				"<div id=\"yellow-pane-version-status\" class=\""+paneStatus+"\">"+this.getText("VersionStatus", "", paneStatus)+"</div>"+
				"<div id=\"yellow-pane-version-fields\">"+yellow.page.rawDataOutput+"</div>"+
				"<div id=\"yellow-pane-version-buttons\">"+
				"<p><a href=\"#\" class=\"yellow-btn\" data-action=\"close\">"+this.getText("OkButton")+"</a></p>"+
				"</div>"+
				"</form>";
				break;
			case "yellow-pane-edit":
				elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" class=\"yellow-close\" data-action=\"close\">x</a>"+
				"<h1 id=\"yellow-pane-edit-title\">"+this.getText("Edit")+"</h1>"+
				"<textarea id=\"yellow-pane-edit-page\" class=\"yellow-form-control\" name=\"rawdataedit\"></textarea>"+
				"<div id=\"yellow-pane-edit-buttons\">"+
				"<a href=\"#\" id=\"yellow-pane-edit-send\" class=\"yellow-btn\" data-action=\"send\">"+this.getText("EditButton")+"</a>"+
				"<a href=\""+this.getText("MarkdownHelpUrl", "yellow")+"\" target=\"_blank\" id=\"yellow-pane-edit-help\">"+this.getText("MarkdownHelp")+"</a>" +
				"</div>"+
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
	updatePane: function(paneId, paneAction, paneStatus, init)
	{
		if(yellow.config.debug) console.log("yellow.edit.updatePane id:"+paneId);
		var showFields = paneStatus!="next" && paneStatus!="done" && paneStatus!="expired";
		switch(paneId)
		{
			case "yellow-pane-login":
				if(yellow.config.editLoginRestrictions)
				{
					yellow.toolbox.setVisible(document.getElementById("yellow-pane-login-signup"), false);
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
					document.getElementById("yellow-pane-settings-status").innerHTML = "<a href=\"#\" data-action=\"version\">"+this.getText("VersionTitle")+"</a>";
					document.getElementById("yellow-pane-settings-name").value = yellow.config.userName;
					document.getElementById("yellow-pane-settings-email").value = yellow.config.userEmail;
					document.getElementById("yellow-pane-settings-"+yellow.config.userLanguage).checked = true;
				}
				break;
			case "yellow-pane-version":
				if(paneStatus=="none" && yellow.config.userUpdate)
				{
					document.getElementById("yellow-pane-version-status").innerHTML = this.getText("VersionStatusCheck");
					document.getElementById("yellow-pane-version-fields").innerHTML = "";
					setTimeout("yellow.action('send');", 500);
				}
				if(paneStatus=="updates" && yellow.config.userWebmaster)
				{
					document.getElementById("yellow-pane-version-status").innerHTML = "<a href=\"#\" data-action=\"update\">"+this.getText("VersionUpdateNormal")+"</a>";
				}
				break;
			case "yellow-pane-edit":
				if(init)
				{
					var title;
					var string = yellow.page.rawDataEdit;
					switch(paneAction)
					{
						case "create":	title = this.getText("CreateTitle"); string = yellow.page.rawDataNew; break;
						case "edit":	title = yellow.page.title ? yellow.page.title : this.getText("Edit"); break;
						case "delete":	title = this.getText("DeleteTitle"); break;
					}
					document.getElementById("yellow-pane-edit-title").innerHTML = yellow.toolbox.encodeHtml(title);
					document.getElementById("yellow-pane-edit-page").value = string;
					yellow.toolbox.setCursorPosition(document.getElementById("yellow-pane-edit-page"), 0);
				}
				var key, className, readOnly;
				switch(this.getAction(paneId, paneAction))
				{
					case "create":	key = "CreateButton"; className = "yellow-btn yellow-btn-create"; readOnly = false; break;
					case "edit":	key = "EditButton"; className = "yellow-btn yellow-btn-edit"; readOnly = false; break;
					case "delete":	key = "DeleteButton"; className = "yellow-btn yellow-btn-delete"; readOnly = false; break;
					case "":		key = "CancelButton";  className = "yellow-btn yellow-btn-cancel"; readOnly = true; break;
				}
				document.getElementById("yellow-pane-edit-send").innerHTML = this.getText(key);
				document.getElementById("yellow-pane-edit-send").className = className;
				document.getElementById("yellow-pane-edit-page").readOnly = readOnly;
				break;
		}
		this.bindActions(document.getElementById(paneId));
	},

	// Resize pane
	resizePane: function(paneId, paneAction, paneStatus)
	{
		var elementBar = document.getElementById("yellow-bar-content");
		var paneLeft = yellow.toolbox.getOuterLeft(elementBar);
		var paneTop = yellow.toolbox.getOuterTop(elementBar) + yellow.toolbox.getOuterHeight(elementBar) + 10;
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
			if(!document.getElementById(paneId)) this.createPane(paneId, paneAction, paneStatus);
			var element = document.getElementById(paneId);
			if(!yellow.toolbox.isVisible(element))
			{
				if(yellow.config.debug) console.log("yellow.edit.showPane id:"+paneId);
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
				this.updatePane(paneId, paneAction, paneStatus, this.paneActionOld!=this.paneAction);
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
			yellow.toolbox.removeClass(document.body, "yellow-body-modal-open");
			yellow.toolbox.removeValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
			yellow.toolbox.setVisible(element, false);
			this.paneId = 0;
			this.paneActionOld = this.paneAction;
			this.paneAction = 0;
			this.paneStatus = 0;
		}
	},

	// Send pane
	sendPane: function(paneId, paneAction, paneStatus, paneArgs)
	{
		if(yellow.config.debug) console.log("yellow.edit.sendPane id:"+paneId);
		if(paneId=="yellow-pane-edit")
		{
			paneAction = this.getAction(paneId, paneAction);
			if(paneAction)
			{
				var args = {};
				args.action = paneAction;
				args.rawdatasource = yellow.page.rawDataSource;
				args.rawdataedit = document.getElementById("yellow-pane-edit-page").value;
				yellow.toolbox.submitForm(args, true);
			} else {
				this.hidePane(paneId);
			}
		} else {
			var args = {"action":paneAction};
			if(paneArgs)
			{
				var tokens = paneArgs.split('/');
				for(var i=0; i<tokens.length; i++)
				{
					var pair = tokens[i].split(/[:=]/);
					if(!pair[0] || !pair[1]) continue;
					args[pair[0]] = pair[1];
				}
			}
			yellow.toolbox.submitForm(args);
		}
	},
	
	// Bind actions to links
	bindActions: function(element)
	{
		var elements = element.getElementsByTagName("a");
		for(var i=0, l=elements.length; i<l; i++)
		{
			if(elements[i].getAttribute("data-action")) elements[i].onclick = yellow.onClickAction;
		}
	},
	
	// Return action
	getAction: function(paneId, paneAction)
	{
		if(paneId=="yellow-pane-edit")
		{
			switch(paneAction)
			{
				case "create":	action = "create"; break;
				case "edit":	action = document.getElementById("yellow-pane-edit-page").value ? "edit" : "delete"; break;
				case "delete":	action = "delete"; break;
			}
			if(yellow.page.statusCode==424 && paneAction!="delete") action = "create";
			if(yellow.config.userRestrictions) action = "";
		}
		return action;
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
		if(!prefix) prefix = "edit";
		if(!postfix) postfix = "";
		key = prefix + key.charAt(0).toUpperCase() + key.slice(1) + postfix.charAt(0).toUpperCase() + postfix.slice(1);
		return (key in yellow.text) ? yellow.text[key] : "["+key+"]";
	}
};

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
		element.addEventListener(type, handler, false);
	},
	
	// Remove event handler
	removeEvent: function(element, type, handler)
	{
		element.removeEventListener(type, handler, false);
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
		return left + window.pageXOffset;
	},
	
	// Return element top position in pixel
	getOuterTop: function(element)
	{
		var top = element.getBoundingClientRect().top;
		return top + window.pageYOffset;
	},
	
	// Return window width in pixel
	getWindowWidth: function()
	{
		return window.innerWidth;
	},
	
	// Return window height in pixel
	getWindowHeight: function()
	{
		return window.innerHeight;
	},
	
	// Return element CSS property
	getStyle: function(element, property)
	{
		return window.getComputedStyle(element).getPropertyValue(property);
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
		element.focus();
		element.setSelectionRange(pos, pos);
	},

	// Get input cursor position
	getCursorPosition: function(element)
	{
		return element.selectionStart;
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
	submitForm: function(args, encodeNewline)
	{
		var elementForm = document.createElement("form");
		elementForm.setAttribute("method", "post");
		for(var key in args)
		{
			if(!args.hasOwnProperty(key)) continue;
			var value = encodeNewline ? this.encodeNewline(args[key]) : args[key];
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

yellow.edit.intervalId = setInterval("yellow.onLoad()", 1);
