// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow API
var yellow =
{
	version: "0.6.6",
	action: function(text) { yellow.webinterface.action(text); },
	onClick: function(e) { yellow.webinterface.hidePanesOnClick(yellow.toolbox.getEventElement(e)); },
	onKeydown: function(e) { yellow.webinterface.hidePanesOnKeydown(yellow.toolbox.getEventKeycode(e)); },
	onResize: function() { yellow.webinterface.resizePane(yellow.webinterface.paneId); },
	onUpdate: function() { yellow.webinterface.updatePane(yellow.webinterface.paneId, yellow.webinterface.paneType); },
	webinterface:{}, toolbox:{}, page:{}, config:{}, text:{}
}

// Yellow web interface
yellow.webinterface =
{
	loaded: false,		//web interface loaded? (boolean)
	intervalId: 0,		//timer interval ID
	paneId: 0,			//visible pane ID
	paneType: 0,		//visible pane type

	// Initialise web interface
	init: function()
	{
		this.intervalId = setInterval("yellow.webinterface.load()", 1);
		yellow.toolbox.addEvent(document, "click", yellow.onClick);
		yellow.toolbox.addEvent(document, "keydown", yellow.onKeydown);
		yellow.toolbox.addEvent(window, "resize", yellow.onResize);
	},
	
	// Load web interface
	load: function()
	{
		var body = document.getElementsByTagName("body")[0];
		if(body && body.firstChild && !this.loaded)
		{
			this.loaded = true;
			if(yellow.config.webinterfaceLocation)
			{
				if(yellow.debug) console.log("yellow.webinterface.load email:"+yellow.config.userEmail+" "+yellow.config.userName);
				if(yellow.config.userEmail)
				{
					this.createBar("yellow-bar", true, body.firstChild);
					this.createPane("yellow-pane-edit", true, body.firstChild);
					this.createPane("yellow-pane-user", true, body.firstChild);
					yellow.toolbox.addEvent(document.getElementById("yellow-pane-edit-page"), "keyup", yellow.onUpdate);
					yellow.toolbox.addEvent(document.getElementById("yellow-pane-edit-page"), "change", yellow.onUpdate);
				} else {
					this.createBar("yellow-bar", false, body.firstChild);
					this.createPane("yellow-pane-login", false, body.firstChild);
					this.createPane("yellow-pane-signup", false, body.firstChild);
					this.createPane("yellow-pane-recover", false, body.firstChild);
					yellow.action(yellow.page.loginAction);
				}
			}
			clearInterval(this.intervalId);
		}
	},
	
	// Execute action
	action: function(text)
	{
		switch(text)
		{
			case "login":		this.togglePane("yellow-pane-login"); break;
			case "logout":		yellow.toolbox.submitForm({"action":"logout"}); break;
			case "signup":		this.togglePane("yellow-pane-signup", "signup"); break;
			case "confirm":		this.togglePane("yellow-pane-signup", "confirm"); break;
			case "approve":		this.togglePane("yellow-pane-signup", "approve"); break;
			case "recover":		this.togglePane("yellow-pane-recover", "recover"); break;
			case "create":		this.togglePane("yellow-pane-edit", "create", true); break;
			case "edit":		this.togglePane("yellow-pane-edit", "edit", true); break;
			case "delete":		this.togglePane("yellow-pane-edit", "delete", true); break;
			case "user":		this.togglePane("yellow-pane-user"); break;
			case "send":		this.sendPane(this.paneId, this.paneType); break;
			case "close":		this.hidePane(this.paneId); break;
		}
	},
	
	// Create bar
	createBar: function(id, normal, elementReference)
	{
		if(yellow.debug) console.log("yellow.webinterface.createBar id:"+id);
		var elementBar = document.createElement("div");
		elementBar.className = "yellow-bar";
		elementBar.setAttribute("id", id);
		if(normal)
		{
			elementBar.innerHTML =
				"<div class=\"yellow-bar-left\">"+
				"<a href=\"#\" onclick=\"yellow.action('edit'); return false;\" id=\"yellow-pane-edit-link\">"+this.getText("Edit")+"</a>"+
				"</div>"+
				"<div class=\"yellow-bar-right\">"+
				"<a href=\"#\" onclick=\"yellow.action('create'); return false;\" id=\"yellow-pane-create-link\">"+this.getText("Create")+"</a>"+
				"<a href=\"#\" onclick=\"yellow.action('delete'); return false;\" id=\"yellow-pane-delete-link\">"+this.getText("Delete")+"</a>"+
				"<a href=\"#\" onclick=\"yellow.action('user'); return false;\" id=\"yellow-pane-user-link\">"+yellow.config.userName+"</a>"+
				"</div>";
		}
		yellow.toolbox.insertBefore(elementBar, elementReference);
	},
	
	// Create pane
	createPane: function(paneId, bubble, elementReference)
	{
		if(yellow.debug) console.log("yellow.webinterface.createPane id:"+paneId);
		var elementPane = document.createElement("div");
		elementPane.className = "yellow-pane";
		elementPane.setAttribute("id", paneId);
		elementPane.style.display = "none";
		if(bubble)
		{
			var elementArrow = document.createElement("span");
			elementArrow.className = "yellow-arrow";
			elementArrow.setAttribute("id", paneId+"-arrow");
			elementPane.appendChild(elementArrow);
		}
		var elementDiv = document.createElement("div");
		elementDiv.setAttribute("id", paneId+"-content");
		if(paneId == "yellow-pane-login")
		{
			elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-cancel\">x</a>"+
				"<h1>"+this.getText("LoginTitle")+"</h1>"+
				"<div id=\"yellow-pane-login-fields\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\" />"+
				"<p><label for=\"email\">"+this.getText("LoginEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"email\" maxlength=\"64\" value=\""+yellow.config.loginEmail+"\" /></p>"+
				"<p><label for=\"password\">"+this.getText("LoginPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"password\" maxlength=\"64\" value=\""+yellow.config.loginPassword+"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-login-buttons\">"+
				"<p><a href=\"#\" onclick=\"yellow.action('recover'); return false;\">"+this.getText("LoginRecover")+"</a><p>"+
				"<p><a href=\"#\" onclick=\"yellow.action('signup'); return false;\">"+this.getText("LoginSignup")+"</a><p>"+
				"</div>"+
				"</form>";
		} else if(paneId == "yellow-pane-signup") {
			elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-cancel\">x</a>"+
				"<h1>"+this.getText("SignupTitle")+"</h1>"+
				"<div id=\"yellow-pane-signup-status\" class=\""+yellow.page.loginStatus+"\">"+this.getText("SignupStatus", "", yellow.page.loginStatus)+"</div>"+
				"<div id=\"yellow-pane-signup-fields\">"+
				"<input type=\"hidden\" name=\"action\" value=\"signup\" />"+
				"<p><label for=\"name\">"+this.getText("SignupName")+"</label><br /><input class=\"yellow-form-control\" name=\"name\" id=\"name\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("Name"))+"\" /></p>"+
				"<p><label for=\"email\">"+this.getText("SignupEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("Email"))+"\" /></p>"+
				"<p><label for=\"password\">"+this.getText("SignupPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"password\" maxlength=\"64\" value=\"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("SignupButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-signup-buttons\">"+
				"<p><input class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('close'); return false;\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"</form>";
		} else if(paneId == "yellow-pane-recover") {
			elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<a href=\"#\" onclick=\"yellow.action('close'); return false;\" class=\"yellow-cancel\">x</a>"+
				"<h1>"+this.getText("RecoverTitle")+"</h1>"+
				"<div id=\"yellow-pane-recover-status\" class=\""+yellow.page.loginStatus+"\">"+this.getText("RecoverStatus", "", yellow.page.loginStatus)+"</div>"+
				"<div id=\"yellow-pane-recover-fields-first\">"+
				"<input type=\"hidden\" name=\"action\" value=\"recover\" />"+
				"<p><label for=\"email\">"+this.getText("RecoverEmail")+"</label><br /><input class=\"yellow-form-control\" name=\"email\" id=\"email\" maxlength=\"64\" value=\""+yellow.toolbox.encodeHtml(this.getRequest("Email"))+"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("RecoverButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-recover-fields-second\">"+
				"<p><label for=\"password\">"+this.getText("RecoverPassword")+"</label><br /><input class=\"yellow-form-control\" type=\"password\" name=\"password\" id=\"password\" maxlength=\"64\" value=\"\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("RecoverButton")+"\" /></p>"+
				"</div>"+
				"<div id=\"yellow-pane-recover-buttons\">"+
				"<p><input class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('close'); return false;\" value=\""+this.getText("OkButton")+"\" /></p>"+
				"</div>"+
				"</form>";
		} else if(paneId == "yellow-pane-edit") {
			elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<h1 id=\"yellow-pane-edit-title\">"+this.getText("Edit")+"</h1>"+
				"<textarea id=\"yellow-pane-edit-page\" class=\"yellow-form-control\" name=\"rawdataedit\"></textarea>"+
				"<div id=\"yellow-pane-edit-buttons\">"+
				"<input id=\"yellow-pane-edit-send\" class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('send'); return false;\" value=\""+this.getText("EditButton")+"\" />"+
				"<input id=\"yellow-pane-edit-close\" class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('close'); return false;\" value=\""+this.getText("CancelButton")+"\" />"+
				"<a href=\""+this.getText("MarkdownHelpUrl", "yellow")+"\" target=\"_blank\" id=\"yellow-pane-edit-help\">"+this.getText("MarkdownHelp")+"</a>" +
				"</div>"+
				"</form>";
		} else if(paneId == "yellow-pane-user") {
			elementDiv.innerHTML =
				"<p>"+yellow.config.userEmail+"</p>"+
				"<p><a href=\""+this.getText("UserHelpUrl", "yellow")+"\" onclick=\"yellow.action('user'); return true;\">"+this.getText("UserHelp")+"</a></p>" +
				"<p><a href=\"#\" onclick=\"yellow.action('logout'); return false;\">"+this.getText("UserLogout")+"</a></p>";
		}
		elementPane.appendChild(elementDiv);
		yellow.toolbox.insertAfter(elementPane, elementReference);
	},

	// Update pane
	updatePane: function(paneId, paneType, init)
	{
		if(yellow.debug) console.log("yellow.webinterface.updatePane id:"+paneId);
		if(paneId == "yellow-pane-login")
		{
			if(!yellow.config.loginExtra)
			{
				document.getElementById("yellow-pane-login-buttons").style.display = "none";
			}
		} else if(paneId == "yellow-pane-signup") {
			switch(paneType)
			{
				case "signup":	text = this.getText("SignupStatus", "", yellow.page.loginStatus); break;
				case "confirm":	text = this.getText("ConfirmStatus", "", yellow.page.loginStatus); break;
				case "approve":	text = this.getText("ApproveStatus", "", yellow.page.loginStatus); break;
			}
			document.getElementById("yellow-pane-signup-status").innerHTML = yellow.toolbox.encodeHtml(text);
			if(yellow.page.loginStatus=="next" || yellow.page.loginStatus=="done" || yellow.page.loginStatus=="expire")
			{
				document.getElementById("yellow-pane-signup-fields").style.display = "none";
			} else {
				document.getElementById("yellow-pane-signup-buttons").style.display = "none";
			}
		} else if(paneId == "yellow-pane-recover") {
			if(yellow.page.loginStatus=="next" || yellow.page.loginStatus=="done" || yellow.page.loginStatus=="expire")
			{
				document.getElementById("yellow-pane-recover-fields-first").style.display = "none";
				document.getElementById("yellow-pane-recover-fields-second").style.display = "none";
			} else {
				document.getElementById("yellow-pane-recover-buttons").style.display = "none";
				if(this.getRequest("Id"))
				{
					document.getElementById("yellow-pane-recover-fields-first").style.display = "none";
				} else {
					document.getElementById("yellow-pane-recover-fields-second").style.display = "none";
				}
			}
		} else if(paneId == "yellow-pane-edit") {
			if(init)
			{
				var title = yellow.page.title;
				var string = yellow.page.rawDataEdit;
				switch(paneType)
				{
					case "create":	title = this.getText("CreateTitle"); string = yellow.page.rawDataNew; break;
					case "delete":	title = this.getText("DeleteTitle"); break;
				}
				document.getElementById("yellow-pane-edit-title").innerHTML = yellow.toolbox.encodeHtml(title);
				document.getElementById("yellow-pane-edit-page").value = string;
				yellow.toolbox.setCursorPosition(document.getElementById("yellow-pane-edit-page"), 0);
			}
			var action = this.getPaneAction(paneId, paneType)
			if(action)
			{
				var key, className;
				switch(action)
				{
					case "create":	key = "CreateButton"; className = "yellow-btn yellow-btn-create"; break;
					case "edit":	key = "EditButton"; className = "yellow-btn yellow-btn-edit"; break;
					case "delete":	key = "DeleteButton"; className = "yellow-btn yellow-btn-delete"; break;
				}
				document.getElementById("yellow-pane-edit-send").value = this.getText(key);
				document.getElementById("yellow-pane-edit-send").className = className;
			} else {
				document.getElementById("yellow-pane-edit-send").style.display = "none";
			}
		}
	},
	
	// Send pane
	sendPane: function(paneId, paneType)
	{
		if(yellow.debug) console.log("yellow.webinterface.sendPane id:"+paneId);
		if(paneId == "yellow-pane-edit")
		{
			var action = this.getPaneAction(paneId, paneType);
			if(action)
			{
				var params = {};
				params.action = action;
				params.rawdatasource = yellow.page.rawDataSource;
				params.rawdataedit = document.getElementById("yellow-pane-edit-page").value;
				yellow.toolbox.submitForm(params, true);
			} else {
				this.hidePane(paneId);
			}
		}
	},
	
	// Show or hide pane
	togglePane: function(paneId, paneType, modal)
	{
		if(this.paneId!=paneId || this.paneType!=paneType)
		{
			this.hidePane(this.paneId);
			this.showPane(paneId, paneType, modal);
		} else {
			this.hidePane(paneId);
		}
	},
	
	// Show pane
	showPane: function(paneId, paneType, modal)
	{
		var element = document.getElementById(paneId);
		if(!yellow.toolbox.isVisible(element))
		{
			if(yellow.debug) console.log("yellow.webinterface.showPane id:"+paneId);
			element.style.display = "block";
			if(modal)
			{
				yellow.toolbox.addClass(document.body, "yellow-body-modal-open");
				yellow.toolbox.addValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
			}
			this.paneId = paneId;
			this.paneType = paneType;
			this.resizePane(paneId);
			this.updatePane(paneId, paneType, true);
		}
	},

	// Hide pane
	hidePane: function(paneId)
	{
		var element = document.getElementById(paneId);
		if(yellow.toolbox.isVisible(element))
		{
			if(yellow.debug) console.log("yellow.webinterface.hidePane id:"+paneId);
			yellow.toolbox.removeClass(document.body, "yellow-body-modal-open");
			yellow.toolbox.removeValue("meta[name=viewport]", "content", ", maximum-scale=1, user-scalable=0");
			element.style.display = "none";
			this.paneId = 0;
			this.paneType = 0;
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
		if(keycode == 27) this.hidePanes();
	},

	// Resize panes where needed
	resizePane: function(paneId)
	{
		if(document.getElementById("yellow-bar"))
		{
			var elementBar = document.getElementById("yellow-bar");
			var paneTop = yellow.toolbox.getOuterTop(elementBar) + yellow.toolbox.getOuterHeight(elementBar);
			var paneWidth = yellow.toolbox.getOuterWidth(elementBar, true);
			var paneHeight = yellow.toolbox.getWindowHeight() - paneTop - yellow.toolbox.getOuterHeight(elementBar);
			switch(paneId)
			{
				case "yellow-pane-login":
				case "yellow-pane-signup":
				case "yellow-pane-recover":
					yellow.toolbox.setOuterTop(document.getElementById(paneId), paneTop);
					yellow.toolbox.setOuterWidth(document.getElementById(paneId), paneWidth);
					break;
				case "yellow-pane-edit":
					yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-edit"), paneTop);
					yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit"), paneHeight);
					yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit"), paneWidth);
					yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit-page"), yellow.toolbox.getWidth(document.getElementById("yellow-pane-edit")));
					var height1 = yellow.toolbox.getHeight(document.getElementById("yellow-pane-edit"));
					var height2 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-content"));
					var height3 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-page"));
					yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit-page"), height1 - height2 + height3);
					var elementLink = document.getElementById("yellow-pane-"+this.paneType+"-link");
					var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
					position -= yellow.toolbox.getOuterLeft(document.getElementById("yellow-pane-edit")) + 1;
					yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-edit-arrow"), position);
					break;
				case "yellow-pane-user":
					yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-user"), paneTop);
					yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-user"), paneHeight, true);
					yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-user"), paneWidth - yellow.toolbox.getOuterWidth(document.getElementById("yellow-pane-user")), true);
					var elementLink = document.getElementById("yellow-pane-user-link");
					var position = yellow.toolbox.getOuterLeft(elementLink) + yellow.toolbox.getOuterWidth(elementLink)/2;
					position -= yellow.toolbox.getOuterLeft(document.getElementById("yellow-pane-user"));
					yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-user-arrow"), position);
					break;
			}
			if(yellow.debug) console.log("yellow.webinterface.resizePane bar:"+elementBar.offsetWidth+"/"+elementBar.offsetHeight);
		}
	},
	
	// Return pane action
	getPaneAction: function(paneId, paneType)
	{
		var action = "";
		if(paneId == "yellow-pane-edit")
		{
			if(!yellow.page.userRestrictions)
			{
				var string = document.getElementById("yellow-pane-edit-page").value;
				switch(paneType)
				{
					case "create":	action = "create"; break;
					case "edit":	action = string ? "edit" : "delete"; break;
					case "delete":	action = "delete"; break;
				}
				if(yellow.page.statusCode==424 && paneType!="delete") action = "create";
			}
		}
		return action;
	},

	// Return request string
	getRequest: function(key, prefix)
	{
		if(!prefix) prefix = "request";
		key = prefix + key;
		return (key in yellow.page) ? yellow.page[key] : "";
	},

	// Return text string
	getText: function(key, prefix, postfix)
	{
		if(!prefix) prefix = "webinterface";
		if(!postfix) postfix = ""
		key = prefix + key + postfix.charAt(0).toUpperCase() + postfix.slice(1);
		return (key in yellow.text) ? yellow.text[key] : "["+key+"]";
	}
}

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
		else element.attachEvent('on'+type, handler);
	},
	
	// Remove event handler
	removeEvent: function(element, type, handler)
	{
		if(element.removeEventListener) element.removeEventListener(type, handler, false);
		else element.detachEvent('on'+type, handler);
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
		return e.keyCode
	},
	
	// Set element width/height in pixel, including padding and border
	setOuterWidth: function(element, width, maxWidth)
	{
		width -= this.getBoxSize(element).width;
		if(maxWidth)
		{
			element.style.maxWidth = Math.max(0, width) + "px";
		} else {
			element.style.width = Math.max(0, width) + "px";
		}
	},
	
	setOuterHeight: function(element, height, maxHeight)
	{
		height -= this.getBoxSize(element).height;
		if(maxHeight)
		{
			element.style.maxHeight = Math.max(0, height) + "px";
		} else {
			element.style.height = Math.max(0, height) + "px";
		}
	},
	
	// Return element width/height in pixel, including padding and border
	getOuterWidth: function(element, includeMargin)
	{
		var width = element.offsetWidth;
		if(includeMargin) width += this.getMarginSize(element).width;
		return width;
	},

	getOuterHeight: function(element, includeMargin)
	{
		var height = element.offsetHeight;
		if(includeMargin) height += this.getMarginSize(element).height;
		return height;
	},
	
	// Return element width/height in pixel
	getWidth: function(element)
	{
		return element.offsetWidth - this.getBoxSize(element).width;
	},
	
	getHeight: function(element)
	{
		return element.offsetHeight - this.getBoxSize(element).height;
	},
	
	// Set element top/left position in pixel
	setOuterTop: function(element, top, marginTop)
	{
		if(marginTop)
		{
			element.style.marginTop = Math.max(0, top) + "px";
		} else {
			element.style.top = Math.max(0, top) + "px";
		}
	},
	
	setOuterLeft: function(element, left, marginLeft)
	{
		if(marginLeft)
		{
			element.style.marginLeft = Math.max(0, left) + "px";
		} else {
			element.style.left = Math.max(0, left) + "px";
		}
	},
	
	// Return element top/left position in pixel
	getOuterTop: function(element)
	{
		var top = element.getBoundingClientRect().top;
		return top + (window.pageYOffset || document.documentElement.scrollTop);
	},
	
	getOuterLeft: function(element)
	{
		var left = element.getBoundingClientRect().left;
		return left + (window.pageXOffset || document.documentElement.scrollLeft);
	},
	
	// Return window width/height in pixel
	getWindowWidth: function()
	{
		return window.innerWidth || document.documentElement.clientWidth;
	},
	
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
			range.move('character', pos);
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
			rangeDuplicate.setEndPoint('EndToEnd', range);
			pos = rangeDuplicate.text.length - range.text.length;
		}
		return pos;
	},
	
	// Check if element exists and is visible
	isVisible: function(element)
	{
		return element && element.style.display != "none";
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
}

yellow.webinterface.init();