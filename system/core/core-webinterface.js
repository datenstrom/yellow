// Copyright (c) 2013-2014 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main API
var yellow =
{
	version: "0.2.4",
	onClick: function(e) { yellow.webinterface.hidePanesOnClick(yellow.toolbox.getEventElement(e)); },
	onKeydown: function(e) { yellow.webinterface.hidePanesOnKeydown(yellow.toolbox.getEventKeycode(e)); },
	onResize: function() { yellow.webinterface.resizePanes(); },
	onShow: function(id) { yellow.webinterface.showPane(id); },
	onLogout: function() { yellow.toolbox.submitForm({"action":"logout"}); },
	webinterface:{}, page:{}, toolbox:{}, config:{}, text:{}
}

// Yellow web interface
yellow.webinterface =
{
	created: false,		//interface created? (boolean)
	intervalId: 0,		//interface timer interval ID

	// Initialise web interface
	init: function()
	{
		this.intervalId = setInterval("yellow.webinterface.create()", 1);
		yellow.toolbox.addEvent(window, "resize", yellow.onResize);
		yellow.toolbox.addEvent(document, "click", yellow.onClick);
		yellow.toolbox.addEvent(document, "keydown", yellow.onKeydown);
	},
	
	// Create web interface
	create: function()
	{
		var body = document.getElementsByTagName("body")[0];
		if(body && body.firstChild && !this.created)
		{
			this.created = true;
			if(yellow.debug) console.log("yellow.webinterface.create email:"+yellow.config.userEmail+" "+yellow.config.userName);
			if(yellow.config.userEmail)
			{
				yellow.toolbox.insertBefore(this.createBar("yellow-bar"), body.firstChild);
				yellow.toolbox.insertAfter(this.createPane("yellow-pane-edit"), body.firstChild);
				yellow.toolbox.insertAfter(this.createPane("yellow-pane-user"), body.firstChild);
				yellow.toolbox.setText(document.getElementById("yellow-edit-text"), yellow.page.rawData);
			} else {
				yellow.toolbox.insertBefore(this.createBar("yellow-bar", true), body.firstChild);
				yellow.toolbox.insertAfter(this.createPane("yellow-pane-login", true), body.firstChild);
				this.showPane("yellow-pane-login");
			}
			clearInterval(this.intervalId);
		}
	},
	
	// Create bar
	createBar: function(id, simple)
	{
		if(yellow.debug) console.log("yellow.webinterface.createBar id:"+id);
		var elementBar = document.createElement("div");
		elementBar.className = "yellow-bar yellow";
		elementBar.setAttribute("id", id);
		if(!simple)
		{
			var location = yellow.config.serverBase+yellow.config.pluginLocation;			
			elementBar.innerHTML =
				"<div class=\"yellow-bar-left\">"+
				"<a href=\"https://github.com/markseu/yellowcms-extensions/blob/master/documentation/README.md\" target=\"_blank\"><i class=\"yellow-icon\"></i> Yellow</a>"+
				"<a href=\"#\" onclick=\"yellow.onShow('yellow-pane-edit'); return false;\">"+this.getText("Edit")+"</a>"+
				"</div>"+
				"<div class=\"yellow-bar-right\">"+
				"<a href=\"#\" onclick=\"yellow.onShow('yellow-pane-user'); return false;\" id=\"yellow-username\">"+yellow.config.userName+" &#9662;</a>"+
				"</div>";
		}
		return elementBar;
	},
	
	// Create pane
	createPane: function(id, simple)
	{
		if(yellow.debug) console.log("yellow.webinterface.createPane id:"+id);
		var elementPane = document.createElement("div");
		elementPane.className = simple ? "yellow-pane" : "yellow-pane yellow-pane-bubble";
		elementPane.setAttribute("id", id);
		elementPane.style.display = "none";
		var elementDiv = document.createElement("div");
		elementDiv.setAttribute("id", id+"-content");
		if(id == "yellow-pane-login")
		{
			elementDiv.innerHTML =
				"<h1>"+this.getText("LoginText")+"</h1>"+
				"<form method=\"post\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\" />"+
				"<p><label for=\"email\">"+this.getText("LoginEmail")+"</label> <input name=\"email\" id=\"email\" maxlength=\"64\" /></p>"+
				"<p><label for=\"password\">"+this.getText("LoginPassword")+"</label> <input type=\"password\" name=\"password\" id=\"password\" maxlength=\"64\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\" /></p>"+
				"</form>";
		} else if(id == "yellow-pane-edit") {
			elementDiv.innerHTML =
				"<form method=\"post\">"+
				"<input type=\"hidden\" name=\"action\" value=\"edit\" />"+
				"<textarea id=\"yellow-edit-text\" name=\"rawdata\"></textarea>"+
				"<div id=\"yellow-edit-info\" /></div>"+
				"<div id=\"yellow-edit-buttons\">"+
				"<input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("SaveButton")+"\" />"+
				"</div>"+
				"</form>";
		} else if(id == "yellow-pane-user") {
			elementDiv.innerHTML =
				"<p>"+yellow.config.userEmail+"</p>"+
				"<p><a href=\"#\" onclick=\"yellow.onLogout(); return false;\">"+this.getText("UserLogout")+"</a></p>";
		}

		elementPane.appendChild(elementDiv);
		return elementPane;
	},
	
	// Show or hide pane
	showPane: function(id)
	{
		var element = document.getElementById(id);
		if(!yellow.toolbox.isVisible(element))
		{
			this.hidePanes();
			if(yellow.debug) console.log("yellow.webinterface.showPane id:"+id);
			element.style.display = "block";
			yellow.toolbox.addClass(document.body, "yellow-body-modal-open");
			this.resizePanes();
		} else {
			this.hidePane(id);
		}
	},

	// Hide pane
	hidePane: function(id)
	{
		var element = document.getElementById(id);
		if(yellow.toolbox.isVisible(element))
		{
			if(yellow.debug) console.log("yellow.webinterface.hidePane id:"+id);
			element.style.display = "none";
			yellow.toolbox.removeClass(document.body, "yellow-body-modal-open");
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
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-edit-text"), yellow.toolbox.getWidth(document.getElementById("yellow-pane-edit")));
				var height1 = yellow.toolbox.getHeight(document.getElementById("yellow-pane-edit"));
				var height2 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-pane-edit-content"));
				var height3 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-edit-text"));
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-edit-text"), height1 - height2 + height3);
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
	
	// Return text string
	getText: function(key)
	{
		return ("webinterface"+key in yellow.text) ? yellow.text["webinterface"+key] : "[webinterface"+key+"]";
	}
}

// Yellow toolbox with helpers
yellow.toolbox =
{
	// Set element text
	setText: function(element, text)
	{
		while(element.firstChild !== null) element.removeChild(element.firstChild);
		element.appendChild(document.createTextNode(text));
	},
	
	// Insert element before element
	insertBefore: function(newElement, referenceElement)
	{
		referenceElement.parentNode.insertBefore(newElement, referenceElement);
	},

	// Insert element after element
	insertAfter: function(newElement, referenceElement)
	{
		referenceElement.parentNode.insertBefore(newElement, referenceElement.nextSibling);
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
	
	// Submit form with post method
	submitForm: function(params)
	{
		var elementForm = document.createElement("form");
		elementForm.setAttribute("method", "post");
		for(var key in params)
		{
			if(!params.hasOwnProperty(key)) continue;
			var elementInput = document.createElement("input");
			elementInput.setAttribute("type", "hidden");
			elementInput.setAttribute("name", key);
			elementInput.setAttribute("value", params[key]);
			elementForm.appendChild(elementInput);
		}
		document.body.appendChild(elementForm);
		elementForm.submit();
	}
}

yellow.webinterface.init();