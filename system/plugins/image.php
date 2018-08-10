<?php
// Image plugin, https://github.com/datenstrom/yellow-plugins/tree/master/image
// Copyright (c) 2013-2017 Datenstrom, https://datenstrom.se
// This file may be used and distributed under the terms of the public license.

class YellowImage {
    const VERSION = "0.7.3";
    public $yellow;             //access to API
    public $graphicsLibrary;    //graphics library support? (boolean)

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->config->setDefault("imageThumbnailLocation", "/media/thumbnails/");
        $this->yellow->config->setDefault("imageThumbnailDir", "media/thumbnails/");
        $this->yellow->config->setDefault("imageThumbnailJpgQuality", 80);
        $this->yellow->config->setDefault("imageAlt", "Image");
        $this->graphicsLibrary = $this->isGraphicsLibrary();
    }

    // Handle page content parsing of custom block
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
                list($src, $width, $height) = $this->getImageInfo($this->yellow->config->get("imageDir").$name, $width, $height);
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
    
    // Handle command
    public function onCommand($args) {
        list($command) = $args;
        switch ($command) {
            case "clean":   $statusCode = $this->cleanCommand($args); break;
            default:        $statusCode = 0;
        }
        return $statusCode;
    }

    // Clean thumbnails
    public function cleanCommand($args) {
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
    public function getImageInfo($fileName, $widthOutput, $heightOutput) {
        $fileNameShort = substru($fileName, strlenu($this->yellow->config->get("imageDir")));
        list($widthInput, $heightInput, $type) = $this->yellow->toolbox->detectImageInfo($fileName);
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
                if (!$this->saveImage($image, $fileNameOutput, $type) ||
                    !$this->yellow->toolbox->modifyFile($fileNameOutput, $this->yellow->toolbox->getFileModified($fileName))) {
                    $this->yellow->page->error(500, "Can't write file '$fileNameOutput'!");
                }
            }
            $src = $this->yellow->config->get("serverBase").$this->yellow->config->get("imageThumbnailLocation").$fileNameThumb;
            list($width, $height) = $this->yellow->toolbox->detectImageInfo($fileNameOutput);
        }
        return array($src, $width, $height);
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
    public function saveImage($image, $fileName, $type) {
        $ok = false;
        switch ($type) {
            case "gif": $ok = @imagegif($image, $fileName); break;
            case "jpg": $ok = @imagejpeg($image, $fileName, $this->yellow->config->get("imageThumbnailJpgQuality")); break;
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

$yellow->plugins->register("image", "YellowImage", YellowImage::VERSION);
