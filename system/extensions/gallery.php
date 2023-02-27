<?php
// Gallery extension, https://github.com/annaesvensson/yellow-gallery

class YellowGallery {
    const VERSION = "0.8.18";
    public $yellow;         // access to API

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("gallerySorting", "name");
        $this->yellow->system->setDefault("galleryStyle", "zoom");
    }
    
    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        if ($name=="gallery" && ($type=="block" || $type=="inline")) {
            list($pattern, $sorting, $style, $size) = $this->yellow->toolbox->getTextArguments($text);
            if (is_string_empty($sorting)) $sorting = $this->yellow->system->get("gallerySorting");
            if (is_string_empty($style)) $style = $this->yellow->system->get("galleryStyle");
            if (is_string_empty($size)) $size = "100%";
            if (is_string_empty($pattern)) {
                $pattern = "unknown";
                $files = $this->yellow->media->clean();
            } else {
                $images = $this->yellow->system->get("coreImageLocation");
                $files = $this->yellow->media->index()->match("#$images$pattern#");
                if ($sorting=="modified") $files->sort("modified", false);
                elseif ($sorting=="size") $files->sort("size", false);
            }
            if ($this->yellow->extension->isExisting("image")) {
                if (!is_array_empty($files)) {
                    $page->setLastModified($files->getModified());
                    $output = "<div class=\"".($style!="simple" ? "photoswipe" : "gallery")."\" data-fullscreenel=\"false\" data-shareel=\"false\" data-history=\"false\"";
                    if (substru($size, -1, 1)!="%") $output .= " data-thumbsquare=\"true\"";
                    $output .= ">\n";
                    foreach ($files as $file) {
                        list($src, $width, $height) = $this->yellow->extension->get("image")->getImageInformation($file->fileName, $size, $size);
                        list($widthInput, $heightInput) = $this->yellow->toolbox->detectImageInformation($file->fileName);
                        if (!$widthInput || !$heightInput) $widthInput = $heightInput = "500";
                        $caption = $this->yellow->language->isText($file->fileName) ? $this->yellow->language->getText($file->fileName) : "";
                        $alt = is_string_empty($caption) ? basename($file->getLocation(true)) : $caption;
                        $output .= "<a href=\"".$file->getLocation(true)."\"";
                        $output .= " data-size=\"".htmlspecialchars("{$widthInput}x{$heightInput}")."\"";
                        $output .= " data-caption=\"".htmlspecialchars($caption)."\"";
                        $output .= ">";
                        $output .= "<img src=\"".htmlspecialchars($src)."\"";
                        if ($width && $height) $output .= " width=\"".htmlspecialchars($width)."\" height=\"".htmlspecialchars($height)."\"";
                        $output .= " alt=\"".htmlspecialchars($alt)."\" title=\"".htmlspecialchars($alt)."\" />";
                        $output .= "</a> \n";
                    }
                    $output .= "</div>";
                } else {
                    $page->error(500, "Gallery '$pattern' does not exist!");
                }
            } else {
                $page->error(500, "Gallery requires 'image' extension!");
            }
        }
        return $output;
    }

    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}gallery.css\" />\n";
            $output .= "<script type=\"text/javascript\" defer=\"defer\" src=\"{$extensionLocation}gallery-photoswipe.min.js\"></script>\n";
            $output .= "<script type=\"text/javascript\" defer=\"defer\" src=\"{$extensionLocation}gallery.js\"></script>\n";
        }
        return $output;
    }
}
