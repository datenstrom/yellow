<?php
// Serve extension, https://github.com/annaesvensson/yellow-serve

class YellowServe {
    const VERSION = "0.8.24";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle command
    public function onCommand($command, $text) {
        switch ($command) {
            case "serve":   $statusCode = $this->processCommandServe($command, $text); break;
            default:        $statusCode = 0;
        }
        return $statusCode;
    }
    
    // Handle command help
    public function onCommandHelp() {
        return "serve [url]";
    }
    
    // Process command to start web server
    public function processCommandServe($command, $text) {
        list($url) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($url)) $url = "http://localhost:8000/";
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($url);
        if ($scheme=="http" && !is_string_empty($address) && is_string_empty($base)) {
            if (!preg_match("/\:\d+$/", $address)) $address .= ":8000";
            if ($this->checkServerSettings("$scheme://$address/")) {
                echo "Starting web server. Open a web browser and go to $scheme://$address/\n";
                echo "Press Ctrl+C to quit...\n";
                exec(PHP_BINARY." -S $address yellow.php 2>&1", $outputLines, $returnStatus);
                $statusCode = $returnStatus!=0 ? 500 : 200;
                if ($statusCode!=200) {
                    $output = !is_array_empty($outputLines) ? end($outputLines) : "Please check arguments!";
                    if (preg_match("/^\[(.*?)\]\s*(.*)$/", $output, $matches)) $output = $matches[2];
                    echo "ERROR starting web server: $output\n";
                }
            } else {
                $statusCode = 400;
                $fileName = $this->yellow->system->get("coreExtensionDirectory").$this->yellow->system->get("coreSystemFile");
                echo "ERROR starting web server: Please configure `CoreServerUrl: auto` in file '$fileName'!\n";
            }
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
    
    // Check server settings
    public function checkServerSettings($url) {
        return $this->yellow->system->get("coreServerUrl")=="auto" ||
            $this->yellow->system->get("coreServerUrl")==$url;
    }
}
