// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main API
var yellow =
{
	version: "0.1.1",
	onClick: function(e) { yellow.webinterface.hidePanesOnClick(yellow.toolbox.getEventElement(e)); },
	onKeydown: function(e) { yellow.webinterface.hidePanesOnKeydown(yellow.toolbox.getEventKeycode(e)); },
	onResize: function() { yellow.webinterface.resizePanes(); },
	onShow: function(id) { yellow.webinterface.showPane(id); },
	webinterface:{}, page:{}, pages:{}, toolbox:{}, config:{}, text:{}
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
				yellow.toolbox.insertAfter(this.createPane("yellow-paneedit"), body.firstChild);
				yellow.toolbox.insertAfter(this.createPane("yellow-paneshow"), body.firstChild);
				yellow.toolbox.insertAfter(this.createPane("yellow-paneuser"), body.firstChild);
				yellow.toolbox.setText(document.getElementById("yellow-username"), yellow.config.userName);
				yellow.toolbox.setText(document.getElementById("yellow-edittext"), yellow.page.rawData);
			} else {
				yellow.toolbox.insertBefore(this.createBar("yellow-bar", true), body.firstChild);
				yellow.toolbox.insertAfter(this.createPane("yellow-panelogin", true), body.firstChild);
				this.showPane("yellow-panelogin");
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
			var location = yellow.config.baseLocation+yellow.config.pluginLocation;			
			elementBar.innerHTML =
				"<div class=\"yellow-barleft\">"+
				"<a href=\"http://datenstrom.se/yellow/\" target=\"_blank\"><img src=\""+location+"core_webinterface.png\" width=\"16\" height=\"16\"> Yellow</a>"+
				"<a href=\"#\" onclick=\"yellow.onShow('yellow-paneedit'); return false;\">"+this.getText("Edit")+"</a>"+
				"<a href=\"#\" onclick=\"yellow.onShow('yellow-paneshow'); return false;\">"+this.getText("Show")+"</a>"+
				"</div>"+
				"<div class=\"yellow-barright\">"+
				"<a href=\"#\" onclick=\"yellow.onShow('yellow-paneuser'); return false;\" id=\"yellow-username\">"+this.getText("User")+"</a>"+
				"</div>";
		}
		return elementBar;
	},
	
	// Create pane
	createPane: function(id, simple)
	{
		if(yellow.debug) console.log("yellow.webinterface.createPane id:"+id);
		var elementPane = document.createElement("div");
		elementPane.className = simple ? "yellow-pane" : "yellow-pane yellow-panebubble";
		elementPane.setAttribute("id", id);
		elementPane.style.display = "none";
		var elementDiv = document.createElement("div");
		elementDiv.setAttribute("id", id+"content");
		if(id == "yellow-panelogin")
		{
			elementDiv.innerHTML =
				"<h1>"+this.getText("LoginText")+"</h1>"+
				"<form method=\"post\" name=\"formlogin\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\"/>"+
				"<p><label for=\"email\">"+this.getText("LoginEmail")+"</label> <input name=\"email\" id=\"email\" maxlength=\"64\" /></p>"+
				"<p><label for=\"password\">"+this.getText("LoginPassword")+"</label> <input type=\"password\" name=\"password\" id=\"password\" maxlength=\"64\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\"/></p>"+
				"</form>";
		} else if(id == "yellow-paneedit") {
			elementDiv.innerHTML =
				"<p>Editing page...</p>"+
				"<form method=\"post\" name=\"formeditor\">"+
				"<input type=\"hidden\" name=\"action\" value=\"edit\"/>"+
				"<textarea id=\"yellow-edittext\" name=\"rawdata\"></textarea>"+
				"<div id=\"yellow-editinfo\"/></div>"+
				"<div id=\"yellow-editbuttons\">"+
				"<input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("SaveButton")+"\"/>"+
				"</div>"+
				"</form>";
		} else if(id == "yellow-paneshow") {
			elementDiv.innerHTML = "<p>Showing files...</p>";
			var elementUl = document.createElement("ul");
			for(var n in yellow.pages)
			{
				var elementLi = document.createElement("li");
				var elementA = document.createElement("a");
				elementA.setAttribute("href", yellow.pages[n]["location"]);
				yellow.toolbox.setText(elementA, yellow.pages[n]["title"]);
				elementLi.appendChild(elementA);
				elementUl.appendChild(elementLi);
			}
			elementDiv.appendChild(elementUl);
		} else if(id == "yellow-paneuser") {
			elementDiv.innerHTML =
				"<p>"+yellow.config.userEmail+"</p>"+
				"<form method=\"post\" name=\"formlogout\">"+
				"<input type=\"hidden\" name=\"action\" value=\"logout\"/>"+
				"<p><a href=\"javascript:document.formlogout.submit();\">"+this.getText("UserLogout")+"</a></p> "+
				"</form>";
		}

		elementPane.appendChild(elementDiv);
		return elementPane;
	},
	
	// Show or hide pane
	showPane: function(id)
	{
		if(!yellow.toolbox.isVisible(document.getElementById(id)))
		{
			this.hidePanes();
			if(yellow.debug) console.log("yellow.webinterface.showPane id:"+id);
			document.getElementById(id).style.display = "block";
			this.resizePanes();
		} else {
			this.hidePane(id);
		}
	},

	// Hide pane
	hidePane: function(id)
	{
		if(yellow.toolbox.isVisible(document.getElementById(id)))
		{
			if(yellow.debug) console.log("yellow.webinterface.hidePane id:"+id);
			document.getElementById(id).style.display = "none";
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
			if(yellow.toolbox.isVisible(document.getElementById("yellow-panelogin")))
			{
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-panelogin"), paneWidth);
			}
			if(yellow.toolbox.isVisible(document.getElementById("yellow-paneedit")))
			{
				yellow.toolbox.setOuterTop(document.getElementById("yellow-paneedit"), paneTop);
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-paneedit"), paneHeight);
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-paneedit"), paneWidth);
				yellow.toolbox.setOuterWidth(document.getElementById("yellow-edittext"), yellow.toolbox.getWidth(document.getElementById("yellow-paneedit")));
				var height1 = yellow.toolbox.getHeight(document.getElementById("yellow-paneedit"));
				var height2 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-paneeditcontent"));
				var height3 = yellow.toolbox.getOuterHeight(document.getElementById("yellow-edittext"));
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-edittext"), height1 - height2 + height3);
			}
			if(yellow.toolbox.isVisible(document.getElementById("yellow-paneshow")))
			{
				yellow.toolbox.setOuterTop(document.getElementById("yellow-paneshow"), paneTop);
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-paneshow"), paneHeight, true);
			}
			if(yellow.toolbox.isVisible(document.getElementById("yellow-paneuser")))
			{
				yellow.toolbox.setOuterTop(document.getElementById("yellow-paneuser"), paneTop);
				yellow.toolbox.setOuterHeight(document.getElementById("yellow-paneuser"), paneHeight, true);
				yellow.toolbox.setOuterLeft(document.getElementById("yellow-paneuser"), paneWidth - yellow.toolbox.getOuterWidth(document.getElementById("yellow-paneuser")), true);
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
		height -=this.getBoxSize(element).height;
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
		width = element.offsetWidth;
		if(includeMargin) width += this.getMarginSize(element).width;
		return width;
	},

	getOuterHeight: function(element, includeMargin)
	{
		height = element.offsetHeight;
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
	}
}

yellow.webinterface.init();