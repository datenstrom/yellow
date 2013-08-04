<?php
// Copyright (c) 2013 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Plain text parser core plugin
class Yellow_Plaintext
{
	const Version = "0.1.1";
	var $text;				//plain text
	var $textHtml;			//generated text (HTML format)

	// Parse text, dummy transformation
	function parse($text)
	{
		$this->text = $text;
		return $textHtml;
	}
}

$yellow->registerPlugin("plaintext", "Yellow_Plaintext", Yellow_Plaintext::Version);
?>