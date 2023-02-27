<?php
// Clarity extension, https://github.com/zenblom/yellow-clarity

class YellowClarity {
    const VERSION = "0.8.11";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("ClarityAlwaysSidebar", "true");
    }
    
    // Handle update
    public function onUpdate($action) {
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        if ($action=="install") {
            $this->yellow->system->save($fileName, array("theme" => "clarity"));
        } elseif ($action=="uninstall" && $this->yellow->system->get("theme")=="clarity") {
            $theme = reset(array_diff($this->yellow->system->getValues("theme"), array("clarity")));
            $this->yellow->system->save($fileName, array("theme" => $theme));
        }
    }
}
