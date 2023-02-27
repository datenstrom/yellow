<?php
// Draft extension, https://github.com/annaesvensson/yellow-draft

class YellowDraft {
    const VERSION = "0.8.17";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle page meta data
    public function onParseMetaData($page) {
        if ($page->get("status")=="draft") $page->visible = false;
    }
    
    // Handle page layout
    public function onParsePageLayout($page, $name) {
        if ($this->yellow->page->get("status")=="draft" && $this->yellow->getRequestHandler()=="core") {
            $errorMessage = "";
            if ($this->yellow->extension->isExisting("edit")) {
                $errorMessage .= "<a href=\"".$this->yellow->page->get("editPageUrl")."\">";
                $errorMessage .= $this->yellow->language->getText("draftPageError")."</a>";
            }
            $this->yellow->page->error(420, $errorMessage);
        }
    }
}
