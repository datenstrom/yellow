// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main API
var yellow =
{
	version: "0.1.0",
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
	timerId: 0,			//interface timer ID
	heightOld: 0,		//height of big panes

	// Initialise web interface
	init: function()
	{
		this.intervalId = setInterval("yellow.webinterface.create()", 1);
		yellow.toolbox.addEvent(window, "resize", yellow.onResize);
		yellow.toolbox.addEvent(document, "click", yellow.onClick);
		yellow.toolbox.addEvent(document, "keydown", yellow.onKeydown);
	},
	
	// Create action bar and panes
	create: function()
	{
		var body = document.getElementsByTagName("body")[0];
		if(!body || !body.firstChild || this.created) return;
		if(yellow.debug) console.log("yellow.webinterface.create email:"+yellow.config.userEmail+" "+yellow.config.userName);		
		if(yellow.config.userEmail)
		{
			var location = yellow.config.baseLocation+yellow.config.pluginsLocation;
			var element = document.createElement("div");
			element.className = "yellow-bar";
			element.setAttribute("id", "yellow-bar");
			element.innerHTML =
				"<div class=\"yellow-barleft\">"+
				"<img src=\""+location+"core_webinterface.png\" width=\"16\" height=\"16\"> Yellow"+
				"<button class=\"yellow-barlink\" onclick=\"yellow.onShow('yellow-paneedit');\">"+this.getText("Edit")+"</button>"+
				"<button class=\"yellow-barlink\" onclick=\"yellow.onShow('yellow-paneshow');\">"+this.getText("Show")+"</button>"+
				"</div>"+
				"<div class=\"yellow-barright\">"+
				"<button class=\"yellow-barlink\" onclick=\"yellow.onShow('yellow-paneuser');\" id=\"yellow-username\">"+this.getText("User")+"</button>"+
				"</div>";
			body.insertBefore(element, body.firstChild);
			yellow.toolbox.insertAfter(this.createPane("yellow-paneedit"), body.firstChild);
			yellow.toolbox.insertAfter(this.createPane("yellow-paneshow", yellow.pages), body.firstChild);
			yellow.toolbox.insertAfter(this.createPane("yellow-paneuser"), body.firstChild);
			yellow.toolbox.setText(document.getElementById("yellow-username"), yellow.config.userName+" ↓");
			yellow.toolbox.setText(document.getElementById("yellow-edittext"), yellow.page.rawData);
		} else {
			var element = document.createElement("div");
			element.className = "yellow-login";
			element.setAttribute("id", "yellow-login");
			element.innerHTML =
				"<form method=\"post\" name=\"formlogin\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\"/>"+
				"<h1>"+this.getText("LoginText")+"</h1>"+
				"<p>"+this.getText("LoginEmail")+" <input name=\"email\" maxlength=\"64\" /></p>"+
				"<p>"+this.getText("LoginPassword")+" <input type=\"password\" name=\"password\" maxlength=\"64\" /></p>"+
				"<p><input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("LoginButton")+"\"/></p>"+
				"</form>";
			body.insertBefore(element, body.firstChild);
		}
		clearInterval(this.intervalId);
		this.created = true;
		this.resizePanes(true);
	},
	
	// Create pane
	createPane: function(id, data)
	{
		if(yellow.debug) console.log("yellow.webinterface.createPane id:"+id);
		var outDiv = document.createElement("div");
		if(id == "yellow-paneedit")
		{
			outDiv.innerHTML =
				"<p>Editing page...</p>"+
				"<form method=\"post\" name=\"formeditor\">"+
				"<input type=\"hidden\" name=\"action\" value=\"edit\"/>"+
				"<textarea id=\"yellow-edittext\" name=\"rawdata\"></textarea>"+
				"<div id=\"yellow-editbuttons\">"+
				"<input class=\"yellow-btn\" type=\"submit\" value=\""+this.getText("SaveButton")+"\"/>"+
				"</div>"+
				"</form>";
		} else if(id == "yellow-paneshow") {
			outDiv.innerHTML = "<p>Showing files...</p>";
			for(var n in data)
			{
				var outUl = document.createElement("ul");
				var outLi = document.createElement("li");
				var outA = document.createElement("a");
				outA.setAttribute("href", data[n]["location"]);
				yellow.toolbox.setText(outA, data[n]["title"]);
				outLi.appendChild(outA);
				outUl.appendChild(outLi);
				outDiv.appendChild(outUl);
			}
		} else if(id == "yellow-paneuser") {
			outDiv.innerHTML =
				"<p>"+yellow.config.userEmail+"</p>"+
				"<form method=\"post\" name=\"formlogout\">"+
				"<input type=\"hidden\" name=\"action\" value=\"logout\"/>"+
				"<p><a href=\"javascript:document.formlogout.submit();\">"+this.getText("UserLogout")+"</a></p> "+
				"</form>";
		}
		var element = document.createElement("div");
		element.className = "yellow-pane yellow-panebubble";
		element.setAttribute("id", id);
		element.style.display = "none";
		element.appendChild(outDiv);
		return element;
	},
	
	// Show pane
	showPane: function(id)
	{
		if(document.getElementById(id).style.display != "block")
		{
			this.hidePanes();
			if(yellow.debug) console.log("yellow.webinterface.showPane id:"+id);
			document.getElementById(id).style.display = "block";
			this.resizePanes(true);
		} else {
			this.hidePane(id);
		}
	},

	// Hide pane
	hidePane: function(id)
	{
		if(document.getElementById(id).style.display != "none")
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

	// Resize panes, recalculate height and width where needed
	resizePanes: function(force)
	{
		var interfaceHeight;
		if(window.innerHeight)
		{
			interfaceHeight = window.innerHeight;
		} else {
			if(window.document.documentElement && window.document.documentElement.clientHeight) 
			{ 
				interfaceHeight = window.document.documentElement.clientHeight; 
			} else {
				interfaceHeight = window.document.body.clientHeight; 
			} 
		}
		if((interfaceHeight!=this.heightOld || force) && document.getElementById("yellow-bar"))
		{
			this.heightOld = interfaceHeight;
			var elementBar = document.getElementById("yellow-bar");
			var borderRadius = 6;
			var panePadding = 5;
			var editPadding = 5;
			var interfaceTop = elementBar.offsetHeight + 1;
			interfaceHeight -= interfaceTop + borderRadius*2;
			if(yellow.debug) console.log("yellow.webinterface.resizePanes windowY:"+interfaceHeight+" actionbarY:"+document.getElementById("yellow-bar").offsetHeight+" buttonsY:"+document.getElementById("yellow-editbuttons").offsetHeight+" editorX:"+document.getElementById("yellow-paneedit").offsetWidth);

			this.setPaneHeight(document.getElementById("yellow-paneedit"), interfaceHeight, null, interfaceTop);
			this.setPaneHeight(document.getElementById("yellow-paneshow"), null, interfaceHeight, interfaceTop);
			this.setPaneHeight(document.getElementById("yellow-paneuser"), null, null, interfaceTop);
			
			var editTextHeight = interfaceHeight - panePadding*2 - editPadding*2 - 10
								- (document.getElementById("yellow-edittext").offsetTop-document.getElementById("yellow-paneedit").getElementsByTagName("p")[0].offsetTop)
								- document.getElementById("yellow-editbuttons").offsetHeight;
			document.getElementById("yellow-paneedit").style.width = Math.max(0, elementBar.offsetWidth - panePadding*2) + "px";
			document.getElementById("yellow-edittext").style.height = Math.max(0, editTextHeight) + "px";
			document.getElementById("yellow-edittext").style.width = Math.max(0, document.getElementById("yellow-paneedit").offsetWidth - 2 - panePadding*2 - editPadding*2) + "px";
			document.getElementById("yellow-paneuser").style.marginLeft = Math.max(0, elementBar.offsetWidth - document.getElementById("yellow-paneuser").offsetWidth) + "px";
		}
	},

	// Set pane height
	setPaneHeight: function(element, height, maxHeight, top)
	{
		if(maxHeight)
		{
			element.style.maxHeight = Math.max(0, maxHeight) + "px";
		} else if(height) {
			element.style.height = Math.max(0, height) + "px";
		}
		element.style.top = top + "px";
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
		while(element.firstChild!==null) element.removeChild(element.firstChild);
		element.appendChild(document.createTextNode(text));
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
	}
}

yellow.webinterface.init();