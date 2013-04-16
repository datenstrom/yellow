// Copyright (c) 2013 Datenstrom, http://www.datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Yellow main API
var yellow =
{
	version: "0.0.0",	//Hello web interface!
	onClick: function(e) { yellow.webinterface.hidePanesOnClick(e); },
	onShow: function(id) { yellow.webinterface.showPane(id); },
	onReset: function(id) { yellow.webinterface.resetPane(id); },
	onResize: function() { yellow.webinterface.resizePanes(); },
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
		window.onresize = yellow.onResize;
		document.onclick = yellow.onClick;
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
			element.className = "yellowbar";
			element.setAttribute("id", "yellowbar");
			element.innerHTML =
				"<div class=\"yellowbarleft\">"+
				"<img src=\""+location+"core_webinterface.png\" width=\"16\" height=\"16\"> Yellow"+
				"<button class=\"yellowbarlink\" onclick=\"yellow.onShow('yellowpaneedit');\">"+this.getText("Edit")+"</button>"+
				"<button class=\"yellowbarlink\" onclick=\"yellow.onShow('yellowpaneshow');\">"+this.getText("Show")+"</button>"+
				"</div>"+
				"<div class=\"yellowbarright\">"+
				"<button class=\"yellowbarlink\" onclick=\"yellow.onShow('yellowpaneuser');\" id=\"yellowusername\">"+this.getText("User")+"</button>"+
				"</div>";
			body.insertBefore(element, body.firstChild);
			yellow.toolbox.insertAfter(this.createPane("yellowpaneedit"), body.firstChild);
			yellow.toolbox.insertAfter(this.createPane("yellowpaneshow", yellow.pages), body.firstChild);
			yellow.toolbox.insertAfter(this.createPane("yellowpaneuser"), body.firstChild);
			yellow.toolbox.setText(document.getElementById("yellowusername"), yellow.config.userName+" ↓");
			yellow.toolbox.setText(document.getElementById("yellowedittext"), yellow.page.rawData);
		} else {
			var element = document.createElement("div");
			element.className = "yellowlogin yellowbubble";
			element.setAttribute("id", "yellowlogin");
			element.innerHTML =
				"<form method=\"post\" name=\"formlogin\">"+
				"<input type=\"hidden\" name=\"action\" value=\"login\"/>"+
				"<h1>"+this.getText("LoginText")+"</h1>"+
				"<p>"+this.getText("LoginEmail")+" <input name=\"email\" maxlength=\"64\" /></p>"+
				"<p>"+this.getText("LoginPassword")+" <input type=\"password\" name=\"password\" maxlength=\"64\" /></p>"+
				"<p><input type=\"submit\" value=\""+this.getText("LoginButton")+"\"/></p>"+
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
		if(id == "yellowpaneedit")
		{
			outDiv.innerHTML =
				"<p>Editing page...</p>"+
				"<form method=\"post\" name=\"formeditor\">"+
				"<input type=\"hidden\" name=\"action\" value=\"edit\"/>"+
				"<textarea id=\"yellowedittext\" name=\"rawdata\"></textarea>"+
				"<div id=\"yelloweditbuttons\">"+
				"<input type=\"submit\" value=\""+this.getText("SaveButton")+"\"/>"+
				"<input type=\"button\" value=\""+this.getText("CancelButton")+"\" onclick=\"yellow.onReset('yellowpaneedit');\"/>"+
				"</div>"+
				"</form>";
		} else if(id == "yellowpaneshow") {
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
		} else if(id == "yellowpaneuser") {
			outDiv.innerHTML =
				"<p>"+yellow.config.userEmail+"</p>"+
				"<form method=\"post\" name=\"formlogout\">"+
				"<input type=\"hidden\" name=\"action\" value=\"logout\"/>"+
				"<p><a href=\"javascript:document.formlogout.submit();\">"+this.getText("UserLogout")+"</a></p> "+
				"</form>";
		}
		var element = document.createElement("div");
		element.className = "yellowpane yellowbubble";
		element.setAttribute("id", id);
		element.appendChild(outDiv);
		return element;
	},
	
	// Reset pane
	resetPane: function(id)
	{
		if(id == "yellowpaneedit")
		{
			document.formeditor.reset();
			yellow.toolbox.setText(document.getElementById("yellowedittext"), yellow.page.rawData);
			this.hidePane(id);
		}
	},
	
	// Show pane
	showPane: function(id)
	{
		if(document.getElementById(id).style.display == "block")
		{
			this.hidePanes();
		} else {
			this.hidePanes();
			if(yellow.debug) console.log("yellow.webinterface.showPane id:"+id);
			document.getElementById(id).style.display = "block";
			this.resizePanes(true);
		}
	},

	// Hide pane
	hidePane: function(id)
	{
		if(document.getElementById(id)) document.getElementById(id).style.display = "none";
	},

	// Hide all panes
	hidePanes: function()
	{
		for(var element=document.getElementById("yellowbar"); element; element=element.nextSibling)
		{
			if(element.className && element.className.indexOf("yellowpane")>=0)
			{
				this.hidePane(element.getAttribute("id"));
			}
		}
	},

	// Hide all panes on mouse click
	hidePanesOnClick: function(e)
	{
		var element = yellow.toolbox.getElementForEvent(e);
		while(element = element.parentNode)
		{
			if(element.className)
			{
				if(element.className.indexOf("yellowpane")>=0 || element.className.indexOf("yellowbar")>=0) return;
			}
		}
		this.hidePanes();
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
		if((interfaceHeight!=this.heightOld || force) && document.getElementById("yellowbar"))
		{
			this.heightOld = interfaceHeight;
			var elementBar = document.getElementById("yellowbar");
			var borderRadius = 6;
			var panePadding = 5;
			var editPadding = 5;
			var interfaceTop = elementBar.offsetHeight + 1;
			interfaceHeight -= interfaceTop + borderRadius*2;
			if(yellow.debug) console.log("yellow.webinterface.resizePanes windowY:"+interfaceHeight+" actionbarY:"+document.getElementById("yellowbar").offsetHeight+" buttonsY:"+document.getElementById("yelloweditbuttons").offsetHeight+" editorX:"+document.getElementById("yellowpaneedit").offsetWidth);

			this.setPaneHeight(document.getElementById("yellowpaneedit"), interfaceHeight, null, interfaceTop);
			this.setPaneHeight(document.getElementById("yellowpaneshow"), null, interfaceHeight, interfaceTop);
			this.setPaneHeight(document.getElementById("yellowpaneuser"), null, null, interfaceTop);
			
			var editTextHeight = interfaceHeight - panePadding*2 - editPadding*2 - 10
								- (document.getElementById("yellowedittext").offsetTop-document.getElementById("yellowpaneedit").getElementsByTagName("p")[0].offsetTop)
								- document.getElementById("yelloweditbuttons").offsetHeight;
			document.getElementById("yellowpaneedit").style.width = Math.max(0, elementBar.offsetWidth - panePadding*2) + "px";
			document.getElementById("yellowedittext").style.height = Math.max(0, editTextHeight) + "px";
			document.getElementById("yellowedittext").style.width = Math.max(0, document.getElementById("yellowpaneedit").offsetWidth - 2 - panePadding*2 - editPadding*2) + "px";
			document.getElementById("yellowpaneuser").style.marginLeft = Math.max(0, elementBar.offsetWidth - document.getElementById("yellowpaneuser").offsetWidth) + "px";
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
	
	// Return element for event
	getElementForEvent: function(e)
	{
		e = e ? e : window.event;
		return e.target ? e.target : e.srcElement;
	}
}

yellow.webinterface.init();