<?php
// Redirect extension, https://github.com/schulle4u/yellow-extensions-schulle4u/tree/main/redirect

class YellowRedirect {
    const VERSION = "0.8.4";
    public $yellow;         //access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("redirectTime", "5");
    }
    
    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        if ($name=="redirect" && ($type=="inline")) {
            $redirectTime = $page->getHtml("redirectTime");
            if (is_string_empty($redirectTime)) $redirectTime = $this->yellow->system->get("redirectTime");
            $output = "<span id=\"countdown\">".$redirectTime."</span>";
        }
        return $output;
    }
    
    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name == "header" && $page->getHtml("redirectLocation") && $this->yellow->getRequestHandler()=="core") {
            $redirectTime = $page->getHtml("redirectTime");
            if (is_string_empty($redirectTime)) $redirectTime = $this->yellow->system->get("redirectTime");
            $redirectLocation = $page->getHtml("redirectLocation");
            if (!preg_match("/^\w+:/", $redirectLocation)) {
                $redirectLocation = $this->yellow->system->get("coreServerBase").$redirectLocation;
            } else {
                $redirectLocation = $this->yellow->lookup->normaliseUrl("", "", "", $redirectLocation);
            }
            $output = "<meta http-equiv=\"refresh\" content=\"".$redirectTime."; URL=".$redirectLocation."\">\n";
        }
        if ($name == "footer" && $page->getHtml("redirectLocation")) {
            $output = "<script type=\"text/javascript\">\n";
            $output .= "var seconds=document.getElementById(\"countdown\").textContent;var countdown=setInterval(function(){seconds--;document.getElementById(\"countdown\").textContent=seconds;if(seconds<=0)clearInterval(countdown);},1000);\n";
            $output .= "</script>\n";
        }
        return $output;
    }
}
