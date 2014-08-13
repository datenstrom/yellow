// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main API
var yellow =
{
	version: "0.3.4",
	action: function(text) { yellow.webinterface.action(text); },
	onClick: function(e) { yellow.webinterface.hidePanesOnClick(yellow.toolbox.getEventElement(e)); },
	onKeydown: function(e) { yellow.webinterface.hidePanesOnKeydown(yellow.toolbox.getEventKeycode(e)); },
	onResize: function() { yellow.webinterface.resizePanes(); },
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
				this.showPane("yellow-pane-login");
			}
			clearInterval(this.intervalId);
		}
	},
	
	// Execute action
	action: function(text)
	{
		switch(text)
		{
			case "edit":	this.togglePane("yellow-pane-edit", "edit"); break;
			case "new":		this.togglePane("yellow-pane-edit", "new"); break;
			case "user":	this.togglePane("yellow-pane-user"); break;
			case "send":	this.sendPane(this.paneId, this.paneType); break;
			case "logout":	yellow.toolbox.submitForm({"action":"logout"}); break;
		}
	},
	
	// Create bar
	createBar: function(id, normal, elementReference)
	{
		if(yellow.debug) console.log("yellow.webinterface.createBar id:"+id);
		var elementBar = document.createElement("div");
		elementBar.className = "yellow-bar yellow";
		elementBar.setAttribute("id", id);
		if(normal)
		{
			var location = yellow.config.serverBase+yellow.config.pluginLocation;			
			elementBar.innerHTML =
				"<div class=\"yellow-bar-left\">"+
				"<a href=\"#\" onclick=\"yellow.action('edit'); return false;\">"+this.getText("Edit")+"</a>"+
				"</div>"+
				"<div class=\"yellow-bar-right\">"+
				"<a href=\"#\" onclick=\"yellow.action('new'); return false;\">"+this.getText("New")+"</a>"+
				"<a href=\"#\" onclick=\"yellow.action('user'); return false;\">"+yellow.config.userName+" &#9662;</a>"+
				"</div>";
		}
		yellow.toolbox.insertBefore(elementBar, elementReference);
	},
	
	// Create pane
	createPane: function(paneId, normal, elementReference)
	{
		if(yellow.debug) console.log("yellow.webinterface.createPane id:"+paneId);
		var elementPane = document.createElement("div");
		elementPane.className = normal ? "yellow-pane yellow-pane-bubble" : "yellow-pane";
		elementPane.setAttribute("id", paneId);
		elementPane.style.display = "none";
		var elementDiv = document.createElement("div");
		elementDiv.setAttribute("id", paneId+"-content");
		if(paneId == "yellow-pane-login")
		{
			elementDiv.innerHTML =
				"<h1>"+this.getText("LoginText")+"</h1>"+
				"<form method=\"post\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\" />"+
				"<p><label for=\"email\">"+this.getText("LoginEmail")+"</label> <input name=\"email\" id=\"email\" maxlength=\"64\" /></p>"+
				"<p><label for=\"password\">"+this.getText("LoginPassword")+"</label> <input type=\"password\" name=\"password\" id=\"password\" maxlength=\"64\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\" /></p>"+
				"</form>";
		} else if(paneId == "yellow-pane-edit") {
			elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<textarea id=\"yellow-pane-edit-page\" name=\"rawdataedit\"></textarea>"+
				"<div id=\"yellow-pane-edit-buttons\">"+
				"<input id=\"yellow-pane-edit-send\" class=\"yellow-btn\" type=\"button\" onclick=\"yellow.action('send'); return false;\" value=\""+this.getText("EditButton")+"\" />"+
				"</div>"+
				"</form>";
		} else if(paneId == "yellow-pane-user") {
			elementDiv.innerHTML =
				"<p>"+yellow.config.userEmail+"</p>"+
				"<p><a href=\""+this.getText("UserHelpUrl")+"\">"+this.getText("UserHelp")+"</a></p>" +
				"<p><a href=\"#\" onclick=\"yellow.action('logout'); return false;\">"+this.getText("UserLogout")+"</a></p>";
		}

		elementPane.appendChild(elementDiv);
		yellow.toolbox.insertAfter(elementPane, elementReference);
	},

	// Update pane
	updatePane: function(paneId, paneType, init)
	{
		if(yellow.debug) console.log("yellow.webinterface.updatePane id:"+paneId);
		if(paneId == "yellow-pane-edit")
		{
			if(init)
			{
				var string = paneType=="new" ? yellow.page.rawDataNew : yellow.page.rawDataEdit;
				document.getElementById("yellow-pane-edit-page").value = string;
			}
			var key, className;
			switch(this.getPaneAction(paneId, paneType))
			{
				case "create":	key = "CreateButton"; className = "yellow-btn yellow-btn-green"; break;
				case "edit":	key = "EditButton"; className = "yellow-btn"; break;
				case "delete":	key = "DeleteButton"; className = "yellow-btn yellow-btn-red"; break;
				default:		key = "CancelButton"; className = "yellow-btn";
			}
			document.getElementById("yellow-pane-edit-send").value = this.getText(key);
			document.getElementById("yellow-pane-edit-send").className = className;
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
	togglePane: function(paneId, paneType)
	{
		if(this.paneId!=paneId || this.paneType!=paneType)
		{
			this.hidePane(this.paneId);
			this.showPane(paneId, paneType);
		} else {
			this.hidePane(paneId);
		}
	},
	
	// Show pane
	showPane: function(paneId, paneType)
	{
		var element = document.getElementById(paneId);
		if(!yellow.toolbox.isVisible(element))
		{
			if(yellow.debug) console.log("yellow.webinterface.showPane id:"+paneId);
			element.style.display = "block";
			yellow.toolbox.addClass(document.body, "yellow-body-modal-open");
			this.resizePanes();
			this.updatePane(paneId, paneType, true);
			this.paneId = paneId;
			this.paneType = paneType;
		}
	},

	// Hide pane
	hidePane: function(paneId)
	{
		var element = document.getElementById(paneId);
		if(yellow.toolbox.isVisible(element))
		{
			if(yellow.debug) console.log("yellow.webinterface.hidePane id:"+paneId);
			element.style.display = "none";
			yellow.toolbox.removeClass(document.body, "yellow-body-modal-open");
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
		while(element = element.parentNode)
		{
			if(element.className)
			{
				if(element.className.indexOf("yellow-pane")>=0 || element.className.indexOf("yellow-bar")>=0) return;
			}
		}
		this.hidePanes();
	},
	
	// Hide all panes on ESC key
	hidePanesOnKeydown: function(keycode)
	{
		if(keycode == 27) this.hidePanes();
	},

	// Resize panes, recalculate width and height where needed
	resizePanes: function()
	{
		if(document.getElementById("yellow-bar"))
		{
			var elementBar = document.getElementById("yellow-bar");
			var paneTop = yellow.toolbox.getOuterTop(elementBar) + yellow.toolbox.getOuterHeight(elementBar);
			var paneWidth = yellow.toolbox.getOuterWidth(elementBar, true);
			var paneHeight = yellow.toolbox.getWindowHeight() - paneTop - yellow.toolbox.getOuterHeight(elementBar);
			if(yellow.toolbox.isVisible(document.getElementById("yellow-pane-login")))
			{
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-login"), paneWidth);
			}
			if(yellow.toolbox.isVisible(document.getElementById("yellow-pane-edit")))
			{
				yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-edit"), paneTop);
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit"), paneHeight);
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit"), paneWidth);
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-pane-edit-page"), yellow.toolbox.getWidth(document.getElementById("yellow-pane-edit")));
				var height1 = yellow.toolbox.getHeight(document.getElementById("yellow-pane-edit"));
				var height2 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-content"));
				var height3 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-page"));
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-edit-page"), height1 - height2 + height3);
			}
			if(yellow.toolbox.isVisible(document.getElementById("yellow-pane-user")))
			{
				yellow.toolbox.setOuterTop(document.getElementById("yellow-pane-user"), paneTop);
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-pane-user"), paneHeight, true);
				yellow.toolbox.setOuterLeft(document.getElementById("yellow-pane-user"), paneWidth - yellow.toolbox.getOuterWidth(document.getElementById("yellow-pane-user")), true);
			}
			if(yellow.debug) console.log("yellow.webinterface.resizePanes bar:"+elementBar.offsetWidth+"/"+elementBar.offsetHeight);
		}
	},
	
	// Return pane action
	getPaneAction: function(paneId, paneType)
	{
		var action = "";
		if(paneId == "yellow-pane-edit")
		{
			if(yellow.page.userPermission)
			{
				var string = document.getElementById("yellow-pane-edit-page").value;
				if(yellow.page.statusCode==424 || paneType=="new")
				{
					action = string ? "create" : "";
				} else {
					action = string ? "edit" : "delete";
				}
			}
		}
		return action;
	},
	
	// Return text string
	getText: function(key)
	{
		return ("webinterface"+key in yellow.text) ? yellow.text["webinterface"+key] : "[webinterface"+key+"]";
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

	// Add event handler
	addEvent: function(element, type, handler)
	{
		if(element.addEventListener) element.addEventListener(type, handler, false);
		else element.attachEvent('on'+type, handler);
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
	
	// Check if element exists and is visible
	isVisible: function(element)
	{
		return element && element.style.display != "none";
	},
	
	// Encode newline characters
	encodeNewline: function(string)
	{
		return string
			.replace(/[\\]/g, "\\\\")
			.replace(/[\r]/g, "\\r")
			.replace(/[\n]/g, "\\n");
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