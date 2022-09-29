<?php
// Stockholm extension, https://github.com/annaesvensson/yellow-stockholm

class YellowStockholm {
    const VERSION = "0.8.13";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle update
    public function onUpdate($action) {
        $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
        if ($action=="install") {
            $this->yellow->system->save($fileName, array("theme" => "stockholm"));
        } elseif ($action=="uninstall" && $this->yellow->system->get("theme")=="stockholm") {
            $theme = reset(array_diff($this->yellow->system->getValues("theme"), array("stockholm")));
            $this->yellow->system->save($fileName, array("theme" => $theme));
        }
    }
}
