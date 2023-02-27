<?php
// Random extension, https://github.com/schulle4u/yellow-extensions-schulle4u/tree/main/random

class YellowRandom {
    const VERSION = "0.8.8";
    public $yellow;            //access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("randomLocation", "/blog/");
        $this->yellow->system->setDefault("randomPagesMax", "5");
        $this->yellow->system->setDefault("randomMode", "list");
    }

    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        if ($name=="random" && ($type=="block" || $type=="inline")) {
            list($location, $pagesMax, $mode) = $this->yellow->toolbox->getTextArguments($text);
            if (is_string_empty($location)) $location = $this->yellow->system->get("randomLocation");
            if (is_string_empty($pagesMax)) $pagesMax = $this->yellow->system->get("randomPagesMax");
            if (is_string_empty($mode)) $mode = $this->yellow->system->get("randomMode");
            $this->yellow->page->setHeader("Cache-Control", "no-store, no-cache, must-revalidate");
            $parent = $this->yellow->content->find($location);
            $pages = $parent ? $parent->getChildren(false) : $this->yellow->content->clean();
            if (!is_array_empty($pages)) {
                $output .= "<div class=\"".$name."\">\n";
                if ($mode == "list") $output .= "<ul>\n";
                foreach ($pages->shuffle()->limit($pagesMax) as $page) {
                    if ($mode == "full") {
                        $output .= "<h2>".$page->getHtml("title")."</h2>\n";
                        $output .= $page->getContent();
                    }
                    if ($mode == "list") {
                        $output .= "<li><a href=\"".$page->getLocation(true)."\">".$page->getHtml("title")."</a></li>\n";
                    }
                    if ($mode == "teaser") {
                        $output .= "<h2><a href=\"".$page->getLocation(true)."\">".$page->getHtml("title")."</a></h2>\n";
                        $output .= $this->yellow->toolbox->createTextDescription($page->getContent(), 0, false, "<!--more-->", " <a href=\"".$page->getLocation(true)."\">".$this->yellow->language->getTextHtml("blogMore")."</a>");
                    }
                }
                if ($mode == "list") $output .= "</ul>\n";
                $output .= "</div>\n";
            } else {
                $page->error(500, "Random location '$location' does not exist!");
            }
        }
        return $output;
    }
}
