<?php
// Meta extension, https://github.com/annaesvensson/yellow-meta

class YellowMeta {
    const VERSION = "0.8.17";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("metaDefaultImage", "favicon");
    }
    
    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header" && !$page->isError()) {
            list($imageUrl, $imageAlt) = $this->getImageInformation($page);
            $locale = $this->yellow->language->getText("languageLocale", $page->get("language"));
            $output .= "<meta property=\"og:url\" content=\"".htmlspecialchars($page->getUrl().$this->yellow->toolbox->getLocationArguments())."\" />\n";
            $output .= "<meta property=\"og:locale\" content=\"".htmlspecialchars($locale)."\" />\n";
            $output .= "<meta property=\"og:type\" content=\"website\" />\n";
            $output .= "<meta property=\"og:title\" content=\"".$page->getHtml("title")."\" />\n";
            $output .= "<meta property=\"og:site_name\" content=\"".$page->getHtml("sitename")."\" />\n";
            $output .= "<meta property=\"og:description\" content=\"".$page->getHtml("description")."\" />\n";
            $output .= "<meta property=\"og:image\" content=\"".htmlspecialchars($imageUrl)."\" />\n";
            $output .= "<meta property=\"og:image:alt\" content=\"".htmlspecialchars($imageAlt)."\" />\n";
        }
        return $output;
    }
    
    // Handle page output data
    public function onParsePageOutput($page, $text) {
        $output = null;
        if ($text && preg_match("/^(.*?)<html(.*?)>(.*)$/s", $text, $matches)) {
            $output = $matches[1]."<html".$matches[2]." prefix=\"og: http://ogp.me/ns#\">".$matches[3];
        }
        return $output;
    }
    
    // Return image information for page
    public function getImageInformation($page) {
        if ($page->isExisting("image")) {
            $name = $page->get("image");
            $alt = $page->isExisting("imageAlt") ? $page->get("imageAlt") : $page->get("title");
        } elseif (preg_match("/\[image(\s.*?)\]/", $page->getContentRaw(), $matches)) {
            list($name, $alt) = $this->yellow->toolbox->getTextArguments(trim($matches[1]));
            if (is_string_empty($alt)) $alt = $page->get("title");
        } else {
            $name = $this->yellow->system->get("metaDefaultImage");
            $alt = $page->isExisting("imageAlt") ? $page->get("imageAlt") : $page->get("title");
        }
        if (!preg_match("/^\w+:/", $name)) {
            $location = $name!="favicon" ? $this->yellow->system->get("coreImageLocation").$name :
                $this->yellow->system->get("coreThemeLocation").$this->yellow->lookup->normaliseName($page->get("theme")).".png";
            $url = $this->yellow->lookup->normaliseUrl(
                $this->yellow->system->get("coreServerScheme"),
                $this->yellow->system->get("coreServerAddress"),
                $this->yellow->system->get("coreServerBase"), $location);
        } else {
            $url = $this->yellow->lookup->normaliseUrl("", "", "", $name);
        }
        return array($url, $alt);
    }
}
