<?php
// Image plugin, https://github.com/datenstrom/yellow-plugins/tree/master/image
// Copyright (c) 2013-2018 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowImage {
    const VERSION = "0.7.7";
    public $yellow;             //access to API
    public $graphicsLibrary;    //graphics library support? (boolean)

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->config->setDefault("imageAlt", "Image");
        $this->yellow->config->setDefault("imageUploadWidthMax", "1280");
        $this->yellow->config->setDefault("imageUploadHeightMax", "1280");
        $this->yellow->config->setDefault("imageUploadJpgQuality", "80");
        $this->yellow->config->setDefault("imageThumbnailLocation", "/media/thumbnails/");
        $this->yellow->config->setDefault("imageThumbnailDir", "media/thumbnails/");
        $this->yellow->config->setDefault("imageThumbnailJpgQuality", "80");
        $this->graphicsLibrary = $this->isGraphicsLibrary();
    }

    // Handle page content of custom block
    public function onParseContentBlock($page, $name, $text, $shortcut) {
        $output = null;
        if ($name=="image" && $shortcut) {
            if (!$this->graphicsLibrary) {
                $this->yellow->page->error(500, "Plugin 'image' requires GD library with gif/jpg/png support!");
                return $output;
            }
            list($name, $alt, $style, $width, $height) = $this->yellow->toolbox->getTextArgs($text);
            if (!preg_match("/^\w+:/", $name)) {
                if (empty($alt)) $alt = $this->yellow->config->get("imageAlt");
                if (empty($width)) $width = "100%";
                if (empty($height)) $height = $width;
                list($src, $width, $height) = $this->getImageInformation($this->yellow->config->get("imageDir").$name, $width, $height);
            } else {
                if (empty($alt)) $alt = $this->yellow->config->get("imageAlt");
                $src = $this->yellow->lookup->normaliseUrl("", "", "", $name);
                $width = $height = 0;
            }
            $output = "<img src=\"".htmlspecialchars($src)."\"";
            if ($width && $height) $output .= " width=\"".htmlspecialchars($width)."\" height=\"".htmlspecialchars($height)."\"";
            if (!empty($alt)) $output .= " alt=\"".htmlspecialchars($alt)."\" title=\"".htmlspecialchars($alt)."\"";
            if (!empty($style)) $output .= " class=\"".htmlspecialchars($style)."\"";
            $output .= " />";
        }
        return $output;
    }
    
    // Handle media file changes
    public function onEditMediaFile($file, $action) {
        if ($action=="upload" && $this->graphicsLibrary) {
            $fileName = $file->fileName;
            $fileType = $this->yellow->toolbox->getFileType($file->get("fileNameShort"));
            list($widthInput, $heightInput, $type) = $this->yellow->toolbox->detectImageInformation($fileName, $fileType);
            $widthMax = $this->yellow->config->get("imageUploadWidthMax");
            $heightMax = $this->yellow->config->get("imageUploadHeightMax");
            if (($widthInput>$widthMax || $heightInput>$heightMax) && ($type=="gif" || $type=="jpg" || $type=="png")) {
                list($widthOutput, $heightOutput) = $this->getImageDimensionsFit($widthInput, $heightInput, $widthMax, $heightMax);
                $image = $this->loadImage($fileName, $type);
                $image = $this->resizeImage($image, $widthInput, $heightInput, $widthOutput, $heightOutput);
                if (!$this->saveImage($image, $fileName, $type, $this->yellow->config->get("imageUploadJpgQuality"))) {
                    $file->error(500, "Can't write file '$fileName'!");
                }
            }
            if ($this->yellow->config->get("safeMode") && $fileType=="svg") {
                $output = $this->sanitiseXmlData($this->yellow->toolbox->readFile($fileName));
                if (empty($output) || !$this->yellow->toolbox->createFile($fileName, $output)) {
                     $file->error(500, "Can't write file '$fileName'!");
                }
            }
        }
    }
    
    // Handle command
    public function onCommand($args) {
        list($command) = $args;
        switch ($command) {
            case "clean":   $statusCode = $this->processCommandClean($args); break;
            default:        $statusCode = 0;
        }
        return $statusCode;
    }

    // Process command to clean thumbnails
    public function processCommandClean($args) {
        $statusCode = 0;
        list($command, $path) = $args;
        if ($path=="all") {
            $path = $this->yellow->config->get("imageThumbnailDir");
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/.*/", false, false) as $entry) {
                if (!$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
            }
            if ($statusCode==500) echo "ERROR cleaning thumbnails: Can't delete files in directory '$path'!\n";
        }
        return $statusCode;
    }

    // Return image info, create thumbnail on demand
    public function getImageInformation($fileName, $widthOutput, $heightOutput) {
        $fileNameShort = substru($fileName, strlenu($this->yellow->config->get("imageDir")));
        list($widthInput, $heightInput, $type) = $this->yellow->toolbox->detectImageInformation($fileName);
        $widthOutput = $this->convertValueAndUnit($widthOutput, $widthInput);
        $heightOutput = $this->convertValueAndUnit($heightOutput, $heightInput);
        if (($widthInput==$widthOutput && $heightInput==$heightOutput) || $type=="svg") {
            $src = $this->yellow->config->get("serverBase").$this->yellow->config->get("imageLocation").$fileNameShort;
            $width = $widthOutput;
            $height = $heightOutput;
        } else {
            $fileNameThumb = ltrim(str_replace(array("/", "\\", "."), "-", dirname($fileNameShort)."/".pathinfo($fileName, PATHINFO_FILENAME)), "-");
            $fileNameThumb .= "-".$widthOutput."x".$heightOutput;
            $fileNameThumb .= ".".pathinfo($fileName, PATHINFO_EXTENSION);
            $fileNameOutput = $this->yellow->config->get("imageThumbnailDir").$fileNameThumb;
            if ($this->isFileNotUpdated($fileName, $fileNameOutput)) {
                $image = $this->loadImage($fileName, $type);
                $image = $this->resizeImage($image, $widthInput, $heightInput, $widthOutput, $heightOutput);
                if (!$this->saveImage($image, $fileNameOutput, $type, $this->yellow->config->get("imageThumbnailJpgQuality")) ||
                    !$this->yellow->toolbox->modifyFile($fileNameOutput, $this->yellow->toolbox->getFileModified($fileName))) {
                    $this->yellow->page->error(500, "Can't write file '$fileNameOutput'!");
                }
            }
            $src = $this->yellow->config->get("serverBase").$this->yellow->config->get("imageThumbnailLocation").$fileNameThumb;
            list($width, $height) = $this->yellow->toolbox->detectImageInformation($fileNameOutput);
        }
        return array($src, $width, $height);
    }
    
    // Return image dimensions that fit, scale proportional
    public function getImageDimensionsFit($widthInput, $heightInput, $widthMax, $heightMax) {
        $widthOutput = $widthMax;
        $heightOutput = $widthMax * ($heightInput / $widthInput);
        if ($heightOutput>$heightMax) {
            $widthOutput = $widthOutput * ($heightMax / $heightOutput);
            $heightOutput = $heightOutput * ($heightMax / $heightOutput);
        }
        return array(intval($widthOutput), intval($heightOutput));
    }

    // Load image from file
    public function loadImage($fileName, $type) {
        $image = false;
        switch ($type) {
            case "gif": $image = @imagecreatefromgif($fileName); break;
            case "jpg": $image = @imagecreatefromjpeg($fileName); break;
            case "png": $image = @imagecreatefrompng($fileName); break;
        }
        return $image;
    }
    
    // Save image to file
    public function saveImage($image, $fileName, $type, $quality) {
        $ok = false;
        switch ($type) {
            case "gif": $ok = @imagegif($image, $fileName); break;
            case "jpg": $ok = @imagejpeg($image, $fileName, $quality); break;
            case "png": $ok = @imagepng($image, $fileName); break;
        }
        return $ok;
    }

    // Create image from scratch
    public function createImage($width, $height) {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return $image;
    }

    // Resize image
    public function resizeImage($image, $widthInput, $heightInput, $widthOutput, $heightOutput) {
        $widthFit = $widthInput * ($heightOutput / $heightInput);
        $heightFit = $heightInput * ($widthOutput / $widthInput);
        $widthDiff = abs($widthOutput - $widthFit);
        $heightDiff = abs($heightOutput - $heightFit);
        $imageOutput = $this->createImage($widthOutput, $heightOutput);
        if ($heightFit>$heightOutput) {
            imagecopyresampled($imageOutput, $image, 0, $heightDiff/-2, 0, 0, $widthOutput, $heightFit, $widthInput, $heightInput);
        } else {
            imagecopyresampled($imageOutput, $image, $widthDiff/-2, 0, 0, 0, $widthFit, $heightOutput, $widthInput, $heightInput);
        }
        return $imageOutput;
    }
    
    // Return value according to unit
    public function convertValueAndUnit($text, $valueBase) {
        $value = $unit = "";
        if (preg_match("/([\d\.]+)(\S*)/", $text, $matches)) {
            $value = $matches[1];
            $unit = $matches[2];
            if ($unit=="%") $value = $valueBase * $value / 100;
        }
        return intval($value);
    }
    
    // Return sanitised XML data
    public function sanitiseXmlData($rawData) {
        $output = "";
        $elementsHtml = array(
            "a", "abbr", "acronym", "address", "area", "article", "aside", "audio", "b", "bdi", "bdo", "big", "blink", "blockquote", "body", "br", "button", "canvas", "caption", "center", "cite", "code", "col", "colgroup", "content", "data", "datalist", "dd", "decorator", "del", "details", "dfn", "dir", "div", "dl", "dt", "element", "em", "fieldset", "figcaption", "figure", "font", "footer", "form", "h1", "h2", "h3", "h4", "h5", "h6", "head", "header", "hgroup", "hr", "html", "i", "image", "img", "input", "ins", "kbd", "label", "legend", "li", "main", "map", "mark", "marquee", "menu", "menuitem", "meter", "nav", "nobr", "ol", "optgroup", "option", "output", "p", "pre", "progress", "q", "rp", "rt", "ruby", "s", "samp", "section", "select", "shadow", "small", "source", "spacer", "span", "strike", "strong", "style", "sub", "summary", "sup", "table", "tbody", "td", "template", "textarea", "tfoot", "th", "thead", "time", "tr", "track", "tt", "u", "ul", "var", "video", "wbr");
        $elementsSvg = array(
            "svg", "altglyph", "altglyphdef", "altglyphitem", "animatecolor", "animatemotion", "animatetransform", "circle", "clippath", "defs", "desc", "ellipse", "feblend", "fecolormatrix", "fecomponenttransfer", "fecomposite", "feconvolvematrix", "fediffuselighting", "fedisplacementmap", "fedistantlight", "feflood", "fefunca", "fefuncb", "fefuncg", "fefuncr", "fegaussianblur", "femerge", "femergenode", "femorphology", "feoffset", "fepointlight", "fespecularlighting", "fespotlight", "fetile", "feturbulence", "filter", "font", "g", "glyph", "glyphref", "hkern", "image", "line", "lineargradient", "marker", "mask", "metadata", "mpath", "path", "pattern", "polygon", "polyline", "radialgradient", "rect", "stop", "switch", "symbol", "text", "textpath", "title", "tref", "tspan", "use", "view", "vkern");
        $attributesHtml = array(
            "accept", "action", "align", "alt", "autocomplete", "background", "bgcolor", "border", "cellpadding", "cellspacing", "checked", "cite", "class", "clear", "color", "cols", "colspan", "coords", "crossorigin", "datetime", "default", "dir", "disabled", "download", "enctype", "face", "for", "headers", "height", "hidden", "high", "href", "hreflang", "id", "integrity", "ismap", "label", "lang", "list", "loop", "low", "max", "maxlength", "media", "method", "min", "multiple", "name", "noshade", "novalidate", "nowrap", "open", "optimum", "pattern", "placeholder", "poster", "preload", "pubdate", "radiogroup", "readonly", "rel", "required", "rev", "reversed", "role", "rows", "rowspan", "spellcheck", "scope", "selected", "shape", "size", "sizes", "span", "srclang", "start", "src", "srcset", "step", "style", "summary", "tabindex", "title", "type", "usemap", "valign", "value", "width", "xmlns");
        $attributesSvg = array(
            "accent-height", "accumulate", "additivive", "alignment-baseline", "ascent", "attributename", "attributetype", "azimuth", "basefrequency", "baseline-shift", "begin", "bias", "by", "class", "clip", "clip-path", "clip-rule", "color", "color-interpolation", "color-interpolation-filters", "color-profile", "color-rendering", "cx", "cy", "d", "dx", "dy", "diffuseconstant", "direction", "display", "divisor", "dur", "edgemode", "elevation", "end", "fill", "fill-opacity", "fill-rule", "filter", "flood-color", "flood-opacity", "font-family", "font-size", "font-size-adjust", "font-stretch", "font-style", "font-variant", "font-weight", "fx", "fy", "g1", "g2", "glyph-name", "glyphref", "gradientunits", "gradienttransform", "height", "href", "id", "image-rendering", "in", "in2", "k", "k1", "k2", "k3", "k4", "kerning", "keypoints", "keysplines", "keytimes", "lang", "lengthadjust", "letter-spacing", "kernelmatrix", "kernelunitlength", "lighting-color", "local", "marker-end", "marker-mid", "marker-start", "markerheight", "markerunits", "markerwidth", "maskcontentunits", "maskunits", "max", "mask", "media", "method", "mode", "min", "name", "numoctaves", "offset", "operator", "opacity", "order", "orient", "orientation", "origin", "overflow", "paint-order", "path", "pathlength", "patterncontentunits", "patterntransform", "patternunits", "points", "preservealpha", "preserveaspectratio", "r", "rx", "ry", "radius", "refx", "refy", "repeatcount", "repeatdur", "restart", "result", "rotate", "scale", "seed", "shape-rendering", "specularconstant", "specularexponent", "spreadmethod", "stddeviation", "stitchtiles", "stop-color", "stop-opacity", "stroke-dasharray", "stroke-dashoffset", "stroke-linecap", "stroke-linejoin", "stroke-miterlimit", "stroke-opacity", "stroke", "stroke-width", "style", "surfacescale", "tabindex", "targetx", "targety", "transform", "text-anchor", "text-decoration", "text-rendering", "textlength", "type", "u1", "u2", "unicode", "values", "viewbox", "visibility", "vert-adv-y", "vert-origin-x", "vert-origin-y", "width", "word-spacing", "wrap", "writing-mode", "xchannelselector", "ychannelselector", "x", "x1", "x2", "xmlns", "y", "y1", "y2", "z", "zoomandpan");
        $attributesXml = array(
            "xlink:href", "xml:id", "xml:space");
        if (!empty($rawData)) {
            $entityLoader = libxml_disable_entity_loader(true);
            $internalErrors = libxml_use_internal_errors(true);
            $document = new DOMDocument();
            $document->recover = true;
            if ($document->loadXML($rawData)) {
                $elementsSafe = array_merge($elementsHtml, $elementsSvg);
                $attributesSafe = array_merge($attributesHtml, $attributesSvg, $attributesXml);
                $elements = $document->getElementsByTagName("*");
                for ($i=$elements->length-1; $i>=0; --$i) {
                    $element = $elements->item($i);
                    if (!in_array(strtolower($element->tagName), $elementsSafe)) {
                        $element->parentNode->removeChild($element);
                        continue;
                    }
                    for ($j=$element->attributes->length-1; $j>=0; --$j) {
                        $attribute = $element->attributes->item($j);
                        if (!in_array(strtolower($attribute->name), $attributesSafe) && !preg_match("/^(aria|data)-/i", $attribute->name)) {
                            $element->removeAttribute($attribute->name);
                        }
                    }
                    $href = $element->getAttribute("href");
                    if (preg_match("/^\w+:/", $href) && !preg_match("/^(http|https|ftp|mailto):/", $href)) {
                        $element->setAttribute("href", "error-xss-filter");
                    }
                    $href = $element->getAttribute("xlink:href");
                    if (preg_match("/^\w+:/", $href) && !preg_match("/^(http|https|ftp|mailto):/", $href)) {
                        $element->setAttribute("xlink:href", "error-xss-filter");
                    }
                }
                $output = $document->saveXML();
                if (!preg_match("/^<\?xml /", $rawData) && preg_match("/^<\?xml (.*?)>\s*(.*)$/s", $output, $matches)) $output = $matches[2];
            }
            libxml_disable_entity_loader($entityLoader);
            libxml_use_internal_errors($internalErrors);
        }
        return $output;
    }

    // Check if file needs to be updated
    public function isFileNotUpdated($fileNameInput, $fileNameOutput) {
        return $this->yellow->toolbox->getFileModified($fileNameInput)!=$this->yellow->toolbox->getFileModified($fileNameOutput);
    }

    // Check graphics library support
    public function isGraphicsLibrary() {
        return extension_loaded("gd") && function_exists("gd_info") &&
            ((imagetypes()&(IMG_GIF|IMG_JPG|IMG_PNG))==(IMG_GIF|IMG_JPG|IMG_PNG));
    }
}
