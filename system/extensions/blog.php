<?php
// Blog extension, https://github.com/annaesvensson/yellow-blog

class YellowBlog {
    const VERSION = "0.8.23";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("blogStartLocation", "auto");
        $this->yellow->system->setDefault("blogNewLocation", "@title");
        $this->yellow->system->setDefault("blogEntriesMax", "5");
        $this->yellow->system->setDefault("blogPaginationLimit", "5");
    }
    
    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;
        if (substru($name, 0, 4)=="blog" && ($type=="block" || $type=="inline")) {
            switch($name) {
                case "blogauthors": $output = $this->getShorcutBlogauthors($page, $name, $text); break;
                case "blogpages":   $output = $this->getShorcutBlogpages($page, $name, $text); break;
                case "blogchanges": $output = $this->getShorcutBlogchanges($page, $name, $text); break;
                case "blogrelated": $output = $this->getShorcutBlogrelated($page, $name, $text); break;
                case "blogtags":    $output = $this->getShorcutBlogtags($page, $name, $text); break;
                case "blogyears":   $output = $this->getShorcutBlogyears($page, $name, $text); break;
                case "blogmonths":  $output = $this->getShorcutBlogmonths($page, $name, $text); break;
            }
        }
        return $output;
    }
        
    // Return blogauthors shortcut
    public function getShorcutBlogauthors($page, $name, $text) {
        $output = null;
        list($startLocation, $entriesMax) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        if (is_string_empty($entriesMax)) $entriesMax = $this->yellow->system->get("blogEntriesMax");
        $blogStart = $this->yellow->content->find($startLocation);
        $pages = $this->getBlogPages($startLocation);
        $page->setLastModified($pages->getModified());
        $authors = $this->getMeta($pages, "author");
        if (!is_array_empty($authors)) {
            $authors = $this->yellow->lookup->normaliseArray($authors);
            if ($entriesMax!=0 && count($authors)>$entriesMax) {
                uasort($authors, "strnatcasecmp");
                $authors = array_slice($authors, -$entriesMax, $entriesMax, true);
            }
            uksort($authors, "strnatcasecmp");
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($authors as $key=>$value) {
                $output .= "<li><a href=\"".$blogStart->getLocation(true).$this->yellow->lookup->normaliseArguments("author:$key")."\">";
                $output .= htmlspecialchars($key)."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Blogauthors '$startLocation' does not exist!");
        }
        return $output;
    }

    // Return blogpages shortcut
    public function getShorcutBlogpages($page, $name, $text) {
        $output = null;
        list($startLocation, $entriesMax, $filterTag) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        if (is_string_empty($entriesMax)) $entriesMax = $this->yellow->system->get("blogEntriesMax");
        $pages = $this->getBlogPages($startLocation);
        if (!is_string_empty($filterTag)) $pages->filter("tag", $filterTag);
        $pages->sort("title");
        $page->setLastModified($pages->getModified());
        if (!is_array_empty($pages)) {
            if ($entriesMax!=0) $pages->limit($entriesMax);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($pages as $pageBlog) {
                $output .= "<li><a".($pageBlog->isExisting("tag") ? " class=\"".$this->getClass($pageBlog)."\"" : "");
                $output .=" href=\"".$pageBlog->getLocation(true)."\">".$pageBlog->getHtml("title")."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Blogpages '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Return blogchanges shortcut
    public function getShorcutBlogchanges($page, $name, $text) {
        $output = null;
        list($startLocation, $entriesMax, $filterTag) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        if (is_string_empty($entriesMax)) $entriesMax = $this->yellow->system->get("blogEntriesMax");
        $pages = $this->getBlogPages($startLocation);
        if (!is_string_empty($filterTag)) $pages->filter("tag", $filterTag);
        $pages->sort("published", false);
        $page->setLastModified($pages->getModified());
        if (!is_array_empty($pages)) {
            if ($entriesMax!=0) $pages->limit($entriesMax);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($pages as $pageBlog) {
                $output .= "<li><a".($pageBlog->isExisting("tag") ? " class=\"".$this->getClass($pageBlog)."\"" : "");
                $output .=" href=\"".$pageBlog->getLocation(true)."\">".$pageBlog->getHtml("title")."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Blogchanges '$startLocation' does not exist!");
        }
        return $output;
    }
        
    // Return blogrelated shortcut
    public function getShorcutBlogrelated($page, $name, $text) {
        $output = null;
        list($startLocation, $entriesMax) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        if (is_string_empty($entriesMax)) $entriesMax = $this->yellow->system->get("blogEntriesMax");
        $pages = $this->getBlogPages($startLocation);
        $pages->similar($page->getPage("main"));
        $page->setLastModified($pages->getModified());
        if (!is_array_empty($pages)) {
            if ($entriesMax!=0) $pages->limit($entriesMax);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($pages as $pageBlog) {
                $output .= "<li><a".($pageBlog->isExisting("tag") ? " class=\"".$this->getClass($pageBlog)."\"" : "");
                $output .= " href=\"".$pageBlog->getLocation(true)."\">".$pageBlog->getHtml("title")."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Blogrelated '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Return blogtags shortcut
    public function getShorcutBlogtags($page, $name, $text) {
        $output = null;
        list($startLocation, $entriesMax) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        if (is_string_empty($entriesMax)) $entriesMax = $this->yellow->system->get("blogEntriesMax");
        $blogStart = $this->yellow->content->find($startLocation);
        $pages = $this->getBlogPages($startLocation);
        $page->setLastModified($pages->getModified());
        $tags = $this->getMeta($pages, "tag");
        if (!is_array_empty($tags)) {
            $tags = $this->yellow->lookup->normaliseArray($tags);
            if ($entriesMax!=0 && count($tags)>$entriesMax) {
                uasort($tags, "strnatcasecmp");
                $tags = array_slice($tags, -$entriesMax, $entriesMax, true);
            }
            uksort($tags, "strnatcasecmp");
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($tags as $key=>$value) {
                $output .= "<li><a href=\"".$blogStart->getLocation(true).$this->yellow->lookup->normaliseArguments("tag:$key")."\">";
                $output .= htmlspecialchars($key)."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Blogtags '$startLocation' does not exist!");
        }
        return $output;
    }

    // Return blogyears shortcut
    public function getShorcutBlogyears($page, $name, $text) {
        $output = null;
        list($startLocation, $entriesMax) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        if (is_string_empty($entriesMax)) $entriesMax = $this->yellow->system->get("blogEntriesMax");
        $blogStart = $this->yellow->content->find($startLocation);
        $pages = $this->getBlogPages($startLocation);
        $page->setLastModified($pages->getModified());
        $years = $this->getYears($pages, "published");
        if (!is_array_empty($years)) {
            if ($entriesMax!=0) $years = array_slice($years, -$entriesMax, $entriesMax, true);
            uksort($years, "strnatcasecmp");
            $years = array_reverse($years, true);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($years as $key=>$value) {
                $output .= "<li><a href=\"".$blogStart->getLocation(true).$this->yellow->lookup->normaliseArguments("published:$key")."\">";
                $output .= htmlspecialchars($key)."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Blogyears '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Return blogmonths shortcut
    public function getShorcutBlogmonths($page, $name, $text) {
        $output = null;
        list($startLocation, $entriesMax) = $this->yellow->toolbox->getTextArguments($text);
        if (is_string_empty($startLocation)) $startLocation = $this->yellow->system->get("blogStartLocation");
        if (is_string_empty($entriesMax)) $entriesMax = $this->yellow->system->get("blogEntriesMax");
        $blogStart = $this->yellow->content->find($startLocation);
        $pages = $this->getBlogPages($startLocation);
        $page->setLastModified($pages->getModified());
        $months = $this->getMonths($pages, "published");
        if (!is_array_empty($months)) {
            if ($entriesMax!=0) $months = array_slice($months, -$entriesMax, $entriesMax, true);
            uksort($months, "strnatcasecmp");
            $months = array_reverse($months, true);
            $output = "<div class=\"".htmlspecialchars($name)."\">\n";
            $output .= "<ul>\n";
            foreach ($months as $key=>$value) {
                $output .= "<li><a href=\"".$blogStart->getLocation(true).$this->yellow->lookup->normaliseArguments("published:$key")."\">";
                $output .= htmlspecialchars($this->yellow->language->normaliseDate($key))."</a></li>\n";
            }
            $output .= "</ul>\n";
            $output .= "</div>\n";
        } else {
            $page->error(500, "Blogmonths '$startLocation' does not exist!");
        }
        return $output;
    }
    
    // Handle page layout
    public function onParsePageLayout($page, $name) {
        if ($name=="blog-start") {
            $pages = $this->getBlogPages($page->location);
            $pagesFilter = array();
            if ($page->isRequest("tag")) {
                $pages->filter("tag", $page->getRequest("tag"));
                array_push($pagesFilter, $pages->getFilter());
            }
            if ($page->isRequest("author")) {
                $pages->filter("author", $page->getRequest("author"));
                array_push($pagesFilter, $pages->getFilter());
            }
            if ($page->isRequest("published")) {
                $pages->filter("published", $page->getRequest("published"), false);
                array_push($pagesFilter, $this->yellow->language->normaliseDate($pages->getFilter()));
            }
            $pages->sort("published", false);
            if (!is_array_empty($pagesFilter)) {
                $text = implode(" ", $pagesFilter);
                $page->set("titleHeader", $text." - ".$page->get("sitename"));
                $page->set("titleContent", $page->get("title").": ".$text);
                $page->set("title", $page->get("title").": ".$text);
                $page->set("blogWithFilter", true);
            }
            $page->setPages("blog", $pages);
            $page->setLastModified($pages->getModified());
            $page->setHeader("Cache-Control", "max-age=60");
        }
        if ($name=="blog") {
            $blogStartLocation = $this->yellow->system->get("blogStartLocation");
            if ($blogStartLocation!="auto") {
                $blogStart = $this->yellow->content->find($blogStartLocation);
            } else {
                $blogStart = $page->getParent();
            }
            $page->setPage("blogStart", $blogStart);
        }
    }
    
    // Handle content file editing
    public function onEditContentFile($page, $action, $email) {
        if ($page->get("layout")=="blog") $page->set("editNewLocation", $this->yellow->system->get("blogNewLocation"));
    }

    // Return blog pages
    public function getBlogPages($location) {
        $pages = $this->yellow->content->clean();
        $blogStart = $this->yellow->content->find($location);
        if ($blogStart && $blogStart->get("layout")=="blog-start") {
            if ($this->yellow->system->get("blogStartLocation")!="auto") {
                $pages = $this->yellow->content->index();
            } else {
                $pages = $blogStart->getChildren();
            }
            $pages->filter("layout", "blog");
        }
        return $pages;
    }
    
    // Return class for page
    public function getClass($page) {
        $class = "";
        if ($page->isExisting("tag")) {
            foreach (preg_split("/\s*,\s*/", $page->get("tag")) as $tag) {
                $class .= " tag-".$this->yellow->lookup->normaliseArguments($tag, false);
            }
        }
        return trim($class);
    }
    
    // Return meta data from page collection
    public function getMeta($pages, $key) {
        $data = array();
        foreach ($pages as $page) {
            if ($page->isExisting($key)) {
                foreach (preg_split("/\s*,\s*/", $page->get($key)) as $entry) {
                    if (!isset($data[$entry])) $data[$entry] = 0;
                    ++$data[$entry];
                }
            }
        }
        return $data;
    }
    
    // Return years from page collection
    public function getYears($pages, $key) {
        $data = array();
        foreach ($pages as $page) {
            if (preg_match("/^(\d+)\-/", $page->get($key), $matches)) {
                if (!isset($data[$matches[1]])) $data[$matches[1]] = 0;
                ++$data[$matches[1]];
            }
        }
        return $data;
    }
    
    // Return months from page collection
    public function getMonths($pages, $key) {
        $data = array();
        foreach ($pages as $page) {
            if (preg_match("/^(\d+\-\d+)\-/", $page->get($key), $matches)) {
                if (!isset($data[$matches[1]])) $data[$matches[1]] = 0;
                ++$data[$matches[1]];
            }
        }
        return $data;
    }
}
