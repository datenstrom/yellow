<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Markdown extra parser core plugin
class YellowMarkdownExtra
{
	const Version = "0.2.1";
	var $yellow;		//access to API
	var $textHtml;		//generated text (HTML format)
	
	// Initialise plugin
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
	}
	
	// Parse text
	function parse($text)
	{
		$markdown = new YellowMarkdownExtraParser($this->yellow);
		return $this->textHtml = $markdown->transform($text);
	}
}

require_once("markdown.php");

class YellowMarkdownExtraParser extends MarkdownExtra_Parser
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
		$text = preg_replace("/@pageRead/i", $this->yellow->page->get("pageRead"), $text);
		$text = preg_replace("/@pageEdit/i", $this->yellow->page->get("pageEdit"), $text);
		$text = preg_replace("/@pageError/i", $this->yellow->page->get("pageError"), $text);
		return parent::transform($text);
	}

	// Handle links
	function doAutoLinks($text)
	{
		$text = preg_replace_callback("/<(\w+:[^\'\">\s]+)>/", array(&$this, "_doAutoLinks_url_callback"), $text);
		$text = preg_replace_callback("/<(\w+@[\w\-\.]+)>/", array(&$this, "_doAutoLinks_email_callback"), $text);
		$text = preg_replace_callback("/\[(\w+)\s+(.*?)\]/", array(&$this, "_doAutoLinks_shortcut_callback"), $text);
		return $text;
	}
	
	// Handle shortcuts
	function _doAutoLinks_shortcut_callback($matches)
	{
		$text = preg_replace("/\s+/s", " ", $matches[2]);
		$output = $this->yellow->page->parseType($matches[1], $text, true);
		if(is_null($output)) $output = $matches[0];
		return $this->hashBlock($output);
	}
	
	// Handle fenced code blocks
	function _doFencedCodeBlocks_callback($matches)
	{
		$text = $matches[4];
		$output = $this->yellow->page->parseType($matches[2], $text, false);
		if(is_null($output))
		{
			$attr = $this->doExtraAttributes("pre", $dummy =& $matches[3]);
			$output = "<pre$attr><code>".htmlspecialchars($text, ENT_NOQUOTES)."</code></pre>";
		}
		return "\n\n".$this->hashBlock($output)."\n\n";
	}
	
	// Handle inline links
	function _doAnchors_inline_callback($matches)
	{
		$url = $matches[3]=="" ? $matches[4] : $matches[3];
		$text = $matches[2];
		$title = $matches[7];
		$attr = $this->doExtraAttributes("a", $dummy =& $matches[8]);
		$output = "<a href=\"".$this->encodeAttribute($url)."\"";
		if(!empty($title)) $output .= " title=\"".$this->encodeAttribute($title)."\"";
		$output .= $attr;
		$output .= ">".$this->runSpanGamut($text)."</a>";
		return $this->hashPart($output);
	}
	
	// Handle inline images
	function _doImages_inline_callback($matches)
	{
		$path = $matches[3]=="" ? $matches[4] : $matches[3];
		$src = $this->yellow->config->get("serverBase").$this->yellow->config->get("imageLocation").$path;
		list($width, $height) = $this->yellow->toolbox->detectImageDimensions($this->yellow->config->get("imageDir").$path);
		$alt = $matches[2];
		$title = $matches[7];
		$attr = $this->doExtraAttributes("img", $dummy =& $matches[8]);
		$output = "<img src=\"".$this->encodeAttribute($src)."\"";
		if($width && $height) $output .= " width=\"$width\" height=\"$height\"";
		if(!empty($alt)) $output .= " alt=\"".$this->encodeAttribute($alt)."\"";
		if(!empty($title)) $output .= " title=\"".$this->encodeAttribute($title)."\"";
		$output .= $attr;
		$output .= $this->empty_element_suffix;
		return $this->hashPart($output);
	}
}

$yellow->registerPlugin("markdownextra", "YellowMarkdownExtra", YellowMarkdownExtra::Version);
?>