<?php
// Backtotop extension, https://github.com/GiovanniSalmeri/yellow-backtotop

class YellowBacktotop {
    const VERSION = "0.8.20";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }

    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}backtotop.css\" />\n";
            $output .= "<script type=\"text/javascript\" defer=\"defer\" src=\"{$extensionLocation}backtotop.js\"></script>\n";
        }
        if ($name=="footer") {
            $output = "<div><a href=\"#\" id=\"backtotop\">".$this->yellow->language->getTextHtml("backtotopLabel")."</a></div>\n";
        }
        return $output;
    }
}
