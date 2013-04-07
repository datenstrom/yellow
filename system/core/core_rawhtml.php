<?php
// Copyright (c) 2013 Datenstrom, http://www.datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Raw HTML parser core plugin
class Yellow_RawHtml
{
	var $html;      //generated HTML

    // Parse text, dummy transformation
	function parse($text)
	{
		return $this->html = $text;
	}
}

$yellow->registerPlugin("rawhtml", "Yellow_RawHtml", "0.1.0");
?>