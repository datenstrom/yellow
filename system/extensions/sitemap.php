<?php
// Sitemap extension, https://github.com/annaesvensson/yellow-sitemap

class YellowSitemap {
    const VERSION = "0.8.13";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("sitemapLocation", "/sitemap/");
        $this->yellow->system->setDefault("sitemapFileXml", "sitemap.xml");
        $this->yellow->system->setDefault("sitemapPaginationLimit", "30");
    }

    // Handle page layout
    public function onParsePageLayout($page, $name) {
        if ($name=="sitemap") {
            $pages = $this->yellow->content->index(false, false);
            if ($this->isRequestXml($page)) {
                $this->yellow->page->setLastModified($pages->getModified());
                $this->yellow->page->setHeader("Content-Type", "text/xml; charset=utf-8");
                $output = "<?xml version=\"1.0\" encoding=\"utf-8\"\077>\r\n";
                $output .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\r\n";
                foreach ($pages as $pageSitemap) {
                    $output .= "<url><loc>".$pageSitemap->getUrl()."</loc></url>\r\n";
                }
                $output .= "</urlset>\r\n";
                $this->yellow->page->setOutput($output);
            } else {
                $pages->sort("title");
                $this->yellow->page->setPages("sitemap", $pages);
                $this->yellow->page->setLastModified($pages->getModified());
            }
        }
    }
    
    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $locationSitemap = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("sitemapLocation");
            $locationSitemap .= $this->yellow->lookup->normaliseArguments("page:".$this->yellow->system->get("sitemapFileXml"), false);
            $output = "<link rel=\"sitemap\" type=\"text/xml\" href=\"$locationSitemap\" />\n";
        }
        return $output;
    }
    
    // Check if XML requested
    public function isRequestXml($page) {
        return $page->getRequest("page")==$this->yellow->system->get("sitemapFileXml");
    }
}
