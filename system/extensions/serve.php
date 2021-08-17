<?php
// Serve extension, https://github.com/datenstrom/yellow-extensions/tree/master/source/serve

class YellowServe {
    const VERSION = "0.8.15";
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
        return "serve [directory url]\n";
    }
    
    // Process command to start built-in web server
    public function processCommandServe($command, $text) {
        list($path, $url) = $this->yellow->toolbox->getTextArguments($text);
        if (empty($url)) $url = "http://localhost:8000";
        list($scheme, $address, $base) = $this->yellow->lookup->getUrlInformation($url);
        if ($scheme=="http" && !empty($address)) {
            if (!preg_match("/\:\d+$/", $address)) $address .= ":8000";
            echo "Starting built-in web server on $scheme://$address/\n";
            echo "Press Ctrl+C to quit...\n";
            if (empty($path) || $path=="dynamic") {
                exec("php -S $address yellow.php 2>&1", $outputLines, $returnStatus);
            } else {
                exec("php -S $address -t $path 2>&1", $outputLines, $returnStatus);
            }
            $statusCode = $returnStatus!=0 ? 500 : 200;
            if ($statusCode!=200) {
                $output = !empty($outputLines) ? end($outputLines) : "Please check arguments!";
                if (preg_match("/^\[(.*?)\]\s*(.*)$/", $output, $matches)) $output = $matches[2];
                echo "ERROR starting web server: $output\n";
            }
        } else {
            $statusCode = 400;
            echo "Yellow $command: Invalid arguments\n";
        }
        return $statusCode;
    }
}
