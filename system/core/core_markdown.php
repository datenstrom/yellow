<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Markdown parser core plugin
class Yellow_Markdown
{
	const Version = "0.1.3";
	var $markdown;			//markdown parser
	var $html;				//generated HTML
	
	// Initialise plugin
	function initPlugin($yellow)
	{
		$this->markdown = new Yellow_MarkdownExtraParser($yellow);
	}
	
	// Parse text
	function parse($text)
	{
		return $this->html = $this->markdown->transform($text);
	}
}

require("markdown.php");
class Yellow_MarkdownExtraParser extends MarkdownExtra_Parser
{
	var $yellow;	//access to API

	function __construct($yellow)
	{
		$this->yellow = $yellow;
		parent::__construct();
	}
	
	// Transform text
	function transform($text)
	{
		$baseLocation = $this->yellow->config->get("baseLocation");
		$text = preg_replace("/@baseLocation/i", $baseLocation, $text);
		return parent::transform($text);
	}
	
	// Handle images
	function _doImages_inline_callback($matches)
	{
		$path = $matches[3]=="" ? $matches[4] : $matches[3];
		$src = $this->yellow->config->get("baseLocation").$this->yellow->config->get("imageLocation").$path;
		list($width, $height) = $this->yellow->toolbox->detectImageDimensions($this->yellow->config->get("imageDir").$path);
		$alt = $matches[2];
		$title = $matches[7];
		$attr  = $this->doExtraAttributes("img", $dummy =& $matches[8]);
		
		$result = "<img src=\"".$this->encodeAttribute($src)."\"";
		if($width && $height) $result .= " width=\"$width\" height=\"$height\"";
		if(!empty($alt)) $result .= " alt=\"".$this->encodeAttribute($alt)."\"";
		if(!empty($title)) $result .= " title=\"".$this->encodeAttribute($title)."\"";
		$result .= $attr;
		$result .= $this->empty_element_suffix;
		
		return $this->hashPart($result);
	}
}

$yellow->registerPlugin("markdown", "Yellow_Markdown", Yellow_Markdown::Version);
?>