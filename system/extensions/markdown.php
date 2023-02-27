<?php
// Markdown extension, https://github.com/annaesvensson/yellow-markdown

class YellowMarkdown {
    const VERSION = "0.8.24";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }
    
    // Handle page content in raw format
    public function onParseContentRaw($page, $text) {
        $markdown = new YellowMarkdownParser($this->yellow, $page);
        $text = $markdown->transform($text);
        $text = $this->yellow->lookup->normaliseData($text, "html");
        return $text;
    }
}

// PHP Markdown Lib
// Copyright (c) 2004-2018 Michel Fortin
// <https://michelf.ca/>
// All rights reserved.
//
// Original Markdown
// Copyright (c) 2004-2006 John Gruber
// <https://daringfireball.net/>
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions are
// met:
//
// * Redistributions of source code must retain the above copyright notice,
//   this list of conditions and the following disclaimer.
//
// * Redistributions in binary form must reproduce the above copyright
//   notice, this list of conditions and the following disclaimer in the
//   documentation and/or other materials provided with the distribution.
//
// * Neither the name "Markdown" nor the names of its contributors may
//   be used to endorse or promote products derived from this software
//   without specific prior written permission.
//
// This software is provided by the copyright holders and contributors "as
// is" and any express or implied warranties, including, but not limited
// to, the implied warranties of merchantability and fitness for a
// particular purpose are disclaimed. In no event shall the copyright owner
// or contributors be liable for any direct, indirect, incidental, special,
// exemplary, or consequential damages (including, but not limited to,
// procurement of substitute goods or services; loss of use, data, or
// profits; or business interruption) however caused and on any theory of
// liability, whether in contract, strict liability, or tort (including
// negligence or otherwise) arising in any way out of the use of this
// software, even if advised of the possibility of such damage.

class MarkdownParser {
	/**
	 * Define the package version
	 * @var string
	 */
	const MARKDOWNLIB_VERSION = "1.9.0";

	/**
	 * Simple function interface - Initialize the parser and return the result
	 * of its transform method. This will work fine for derived classes too.
	 *
	 * @api
	 *
	 * @param  string $text
	 * @return string
	 */
	public static function defaultTransform($text) {
		// Take parser class on which this function was called.
		$parser_class = \get_called_class();

		// Try to take parser from the static parser list
		static $parser_list;
		$parser =& $parser_list[$parser_class];

		// Create the parser it not already set
		if (!$parser) {
			$parser = new $parser_class;
		}

		// Transform text using parser.
		return $parser->transform($text);
	}

	/**
	 * Configuration variables
	 */

	/**
	 * Change to ">" for HTML output.
	 * @var string
	 */
	public $empty_element_suffix = " />";

	/**
	 * The width of indentation of the output markup
	 * @var int
	 */
	public $tab_width = 4;

	/**
	 * Change to `true` to disallow markup or entities.
	 * @var boolean
	 */
	public $no_markup   = false;
	public $no_entities = false;


	/**
	 * Change to `true` to enable line breaks on \n without two trailling spaces
	 * @var boolean
	 */
	public $hard_wrap = false;

	/**
	 * Predefined URLs and titles for reference links and images.
	 * @var array
	 */
	public $predef_urls   = array();
	public $predef_titles = array();

	/**
	 * Optional filter function for URLs
	 * @var callable|null
	 */
	public $url_filter_func = null;

	/**
	 * Optional header id="" generation callback function.
	 * @var callable|null
	 */
	public $header_id_func = null;

	/**
	 * Optional function for converting code block content to HTML
	 * @var callable|null
	 */
	public $code_block_content_func = null;

	/**
	 * Optional function for converting code span content to HTML.
	 * @var callable|null
	 */
	public $code_span_content_func = null;

	/**
	 * Class attribute to toggle "enhanced ordered list" behaviour
	 * setting this to true will allow ordered lists to start from the index
	 * number that is defined first.
	 *
	 * For example:
	 * 2. List item two
	 * 3. List item three
	 *
	 * Becomes:
	 * <ol start="2">
	 * <li>List item two</li>
	 * <li>List item three</li>
	 * </ol>
	 *
	 * @var bool
	 */
	public $enhanced_ordered_list = false;

	/**
	 * Parser implementation
	 */

	/**
	 * Regex to match balanced [brackets].
	 * Needed to insert a maximum bracked depth while converting to PHP.
	 * @var int
	 */
	protected $nested_brackets_depth = 6;
	protected $nested_brackets_re;

	protected $nested_url_parenthesis_depth = 4;
	protected $nested_url_parenthesis_re;

	/**
	 * Table of hash values for escaped characters:
	 * @var string
	 */
	protected $escape_chars = '\`*_{}[]()>#+-.!';
	protected $escape_chars_re;

	/**
	 * Constructor function. Initialize appropriate member variables.
	 * @return void
	 */
	public function __construct() {
		$this->_initDetab();
		$this->prepareItalicsAndBold();

		$this->nested_brackets_re =
			str_repeat('(?>[^\[\]]+|\[', $this->nested_brackets_depth).
			str_repeat('\])*', $this->nested_brackets_depth);

		$this->nested_url_parenthesis_re =
			str_repeat('(?>[^()\s]+|\(', $this->nested_url_parenthesis_depth).
			str_repeat('(?>\)))*', $this->nested_url_parenthesis_depth);

		$this->escape_chars_re = '['.preg_quote($this->escape_chars).']';

		// Sort document, block, and span gamut in ascendent priority order.
		asort($this->document_gamut);
		asort($this->block_gamut);
		asort($this->span_gamut);
	}


	/**
	 * Internal hashes used during transformation.
	 * @var array
	 */
	protected $urls        = array();
	protected $titles      = array();
	protected $html_hashes = array();

	/**
	 * Status flag to avoid invalid nesting.
	 * @var boolean
	 */
	protected $in_anchor = false;

	/**
	 * Status flag to avoid invalid nesting.
	 * @var boolean
	 */
	protected $in_emphasis_processing = false;

	/**
	 * Called before the transformation process starts to setup parser states.
	 * @return void
	 */
	protected function setup() {
		// Clear global hashes.
		$this->urls        = $this->predef_urls;
		$this->titles      = $this->predef_titles;
		$this->html_hashes = array();
		$this->in_anchor   = false;
		$this->in_emphasis_processing = false;
	}

	/**
	 * Called after the transformation process to clear any variable which may
	 * be taking up memory unnecessarly.
	 * @return void
	 */
	protected function teardown() {
		$this->urls        = array();
		$this->titles      = array();
		$this->html_hashes = array();
	}

	/**
	 * Main function. Performs some preprocessing on the input text and pass
	 * it through the document gamut.
	 *
	 * @api
	 *
	 * @param  string $text
	 * @return string
	 */
	public function transform($text) {
		$this->setup();

		# Remove UTF-8 BOM and marker character in input, if present.
		$text = preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);

		# Standardize line endings:
		#   DOS to Unix and Mac to Unix
		$text = preg_replace('{\r\n?}', "\n", $text);

		# Make sure $text ends with a couple of newlines:
		$text .= "\n\n";

		# Convert all tabs to spaces.
		$text = $this->detab($text);

		# Turn block-level HTML blocks into hash entries
		$text = $this->hashHTMLBlocks($text);

		# Strip any lines consisting only of spaces and tabs.
		# This makes subsequent regexen easier to write, because we can
		# match consecutive blank lines with /\n+/ instead of something
		# contorted like /[ ]*\n+/ .
		$text = preg_replace('/^[ ]+$/m', '', $text);

		# Run document gamut methods.
		foreach ($this->document_gamut as $method => $priority) {
			$text = $this->$method($text);
		}

		$this->teardown();

		return $text . "\n";
	}

	/**
	 * Define the document gamut
	 * @var array
	 */
	protected $document_gamut = array(
		// Strip link definitions, store in hashes.
		"stripLinkDefinitions" => 20,
		"runBasicBlockGamut"   => 30,
	);

	/**
	 * Strips link definitions from text, stores the URLs and titles in
	 * hash references
	 * @param  string $text
	 * @return string
	 */
	protected function stripLinkDefinitions($text) {

		$less_than_tab = $this->tab_width - 1;

		// Link defs are in the form: ^[id]: url "optional title"
		$text = preg_replace_callback('{
							^[ ]{0,'.$less_than_tab.'}\[(.+)\][ ]?:	# id = $1
							  [ ]*
							  \n?				# maybe *one* newline
							  [ ]*
							(?:
							  <(.+?)>			# url = $2
							|
							  (\S+?)			# url = $3
							)
							  [ ]*
							  \n?				# maybe one newline
							  [ ]*
							(?:
								(?<=\s)			# lookbehind for whitespace
								["(]
								(.*?)			# title = $4
								[")]
								[ ]*
							)?	# title is optional
							(?:\n+|\Z)
			}xm',
			array($this, '_stripLinkDefinitions_callback'),
			$text
		);
		return $text;
	}

	/**
	 * The callback to strip link definitions
	 * @param  array $matches
	 * @return string
	 */
	protected function _stripLinkDefinitions_callback($matches) {
		$link_id = strtolower($matches[1]);
		$url = $matches[2] == '' ? $matches[3] : $matches[2];
		$this->urls[$link_id] = $url;
		$this->titles[$link_id] =& $matches[4];
		return ''; // String that will replace the block
	}

	/**
	 * Hashify HTML blocks
	 * @param  string $text
	 * @return string
	 */
	protected function hashHTMLBlocks($text) {
		if ($this->no_markup) {
			return $text;
		}

		$less_than_tab = $this->tab_width - 1;

		/**
		 * Hashify HTML blocks:
		 *
		 * We only want to do this for block-level HTML tags, such as headers,
		 * lists, and tables. That's because we still want to wrap <p>s around
		 * "paragraphs" that are wrapped in non-block-level tags, such as
		 * anchors, phrase emphasis, and spans. The list of tags we're looking
		 * for is hard-coded:
		 *
		 * *  List "a" is made of tags which can be both inline or block-level.
		 *    These will be treated block-level when the start tag is alone on
		 *    its line, otherwise they're not matched here and will be taken as
		 *    inline later.
		 * *  List "b" is made of tags which are always block-level;
		 */
		$block_tags_a_re = 'ins|del';
		$block_tags_b_re = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|'.
						   'script|noscript|style|form|fieldset|iframe|math|svg|'.
						   'article|section|nav|aside|hgroup|header|footer|'.
						   'figure';

		// Regular expression for the content of a block tag.
		$nested_tags_level = 4;
		$attr = '
			(?>				# optional tag attributes
			  \s			# starts with whitespace
			  (?>
				[^>"/]+		# text outside quotes
			  |
				/+(?!>)		# slash not followed by ">"
			  |
				"[^"]*"		# text inside double quotes (tolerate ">")
			  |
				\'[^\']*\'	# text inside single quotes (tolerate ">")
			  )*
			)?
			';
		$content =
			str_repeat('
				(?>
				  [^<]+			# content without tag
				|
				  <\2			# nested opening tag
					'.$attr.'	# attributes
					(?>
					  />
					|
					  >', $nested_tags_level).	// end of opening tag
					  '.*?'.					// last level nested tag content
			str_repeat('
					  </\2\s*>	# closing nested tag
					)
				  |
					<(?!/\2\s*>	# other tags with a different name
				  )
				)*',
				$nested_tags_level);
		$content2 = str_replace('\2', '\3', $content);

		/**
		 * First, look for nested blocks, e.g.:
		 * 	<div>
		 * 		<div>
		 * 		tags for inner block must be indented.
		 * 		</div>
		 * 	</div>
		 *
		 * The outermost tags must start at the left margin for this to match,
		 * and the inner nested divs must be indented.
		 * We need to do this before the next, more liberal match, because the
		 * next match will start at the first `<div>` and stop at the
		 * first `</div>`.
		 */
		$text = preg_replace_callback('{(?>
			(?>
				(?<=\n)			# Starting on its own line
				|				# or
				\A\n?			# the at beginning of the doc
			)
			(						# save in $1

			  # Match from `\n<tag>` to `</tag>\n`, handling nested tags
			  # in between.

						[ ]{0,'.$less_than_tab.'}
						<('.$block_tags_b_re.')# start tag = $2
						'.$attr.'>			# attributes followed by > and \n
						'.$content.'		# content, support nesting
						</\2>				# the matching end tag
						[ ]*				# trailing spaces/tabs
						(?=\n+|\Z)	# followed by a newline or end of document

			| # Special version for tags of group a.

						[ ]{0,'.$less_than_tab.'}
						<('.$block_tags_a_re.')# start tag = $3
						'.$attr.'>[ ]*\n	# attributes followed by >
						'.$content2.'		# content, support nesting
						</\3>				# the matching end tag
						[ ]*				# trailing spaces/tabs
						(?=\n+|\Z)	# followed by a newline or end of document

			| # Special case just for <hr />. It was easier to make a special
			  # case than to make the other regex more complicated.

						[ ]{0,'.$less_than_tab.'}
						<(hr)				# start tag = $2
						'.$attr.'			# attributes
						/?>					# the matching end tag
						[ ]*
						(?=\n{2,}|\Z)		# followed by a blank line or end of document

			| # Special case for standalone HTML comments:

					[ ]{0,'.$less_than_tab.'}
					(?s:
						<!-- .*? -->
					)
					[ ]*
					(?=\n{2,}|\Z)		# followed by a blank line or end of document

			| # PHP and ASP-style processor instructions (<? and <%)

					[ ]{0,'.$less_than_tab.'}
					(?s:
						<([?%])			# $2
						.*?
						\2>
					)
					[ ]*
					(?=\n{2,}|\Z)		# followed by a blank line or end of document

			)
			)}Sxmi',
			array($this, '_hashHTMLBlocks_callback'),
			$text
		);

		return $text;
	}

	/**
	 * The callback for hashing HTML blocks
	 * @param  string $matches
	 * @return string
	 */
	protected function _hashHTMLBlocks_callback($matches) {
		$text = $matches[1];
		$key  = $this->hashBlock($text);
		return "\n\n$key\n\n";
	}

	/**
	 * Called whenever a tag must be hashed when a function insert an atomic
	 * element in the text stream. Passing $text to through this function gives
	 * a unique text-token which will be reverted back when calling unhash.
	 *
	 * The $boundary argument specify what character should be used to surround
	 * the token. By convension, "B" is used for block elements that needs not
	 * to be wrapped into paragraph tags at the end, ":" is used for elements
	 * that are word separators and "X" is used in the general case.
	 *
	 * @param  string $text
	 * @param  string $boundary
	 * @return string
	 */
	protected function hashPart($text, $boundary = 'X') {
		// Swap back any tag hash found in $text so we do not have to `unhash`
		// multiple times at the end.
		$text = $this->unhash($text);

		// Then hash the block.
		static $i = 0;
		$key = "$boundary\x1A" . ++$i . $boundary;
		$this->html_hashes[$key] = $text;
		return $key; // String that will replace the tag.
	}

	/**
	 * Shortcut function for hashPart with block-level boundaries.
	 * @param  string $text
	 * @return string
	 */
	protected function hashBlock($text) {
		return $this->hashPart($text, 'B');
	}

	/**
	 * Define the block gamut - these are all the transformations that form
	 * block-level tags like paragraphs, headers, and list items.
	 * @var array
	 */
	protected $block_gamut = array(
		"doHeaders"         => 10,
		"doHorizontalRules" => 20,
		"doLists"           => 40,
		"doCodeBlocks"      => 50,
		"doBlockQuotes"     => 60,
	);

	/**
	 * Run block gamut tranformations.
	 *
	 * We need to escape raw HTML in Markdown source before doing anything
	 * else. This need to be done for each block, and not only at the
	 * begining in the Markdown function since hashed blocks can be part of
	 * list items and could have been indented. Indented blocks would have
	 * been seen as a code block in a previous pass of hashHTMLBlocks.
	 *
	 * @param  string $text
	 * @return string
	 */
	protected function runBlockGamut($text) {
		$text = $this->hashHTMLBlocks($text);
		return $this->runBasicBlockGamut($text);
	}

	/**
	 * Run block gamut tranformations, without hashing HTML blocks. This is
	 * useful when HTML blocks are known to be already hashed, like in the first
	 * whole-document pass.
	 *
	 * @param  string $text
	 * @return string
	 */
	protected function runBasicBlockGamut($text) {

		foreach ($this->block_gamut as $method => $priority) {
			$text = $this->$method($text);
		}

		// Finally form paragraph and restore hashed blocks.
		$text = $this->formParagraphs($text);

		return $text;
	}

	/**
	 * Convert horizontal rules
	 * @param  string $text
	 * @return string
	 */
	protected function doHorizontalRules($text) {
		return preg_replace(
			'{
				^[ ]{0,3}	# Leading space
				([-*_])		# $1: First marker
				(?>			# Repeated marker group
					[ ]{0,2}	# Zero, one, or two spaces.
					\1			# Marker character
				){2,}		# Group repeated at least twice
				[ ]*		# Tailing spaces
				$			# End of line.
			}mx',
			"\n".$this->hashBlock("<hr$this->empty_element_suffix")."\n",
			$text
		);
	}

	/**
	 * These are all the transformations that occur *within* block-level
	 * tags like paragraphs, headers, and list items.
	 * @var array
	 */
	protected $span_gamut = array(
		// Process character escapes, code spans, and inline HTML
		// in one shot.
		"parseSpan"           => -30,
		// Process anchor and image tags. Images must come first,
		// because ![foo][f] looks like an anchor.
		"doImages"            =>  10,
		"doAnchors"           =>  20,
		// Make links out of things like `<https://example.com/>`
		// Must come after doAnchors, because you can use < and >
		// delimiters in inline links like [this](<url>).
		"doAutoLinks"         =>  30,
		"encodeAmpsAndAngles" =>  40,
		"doItalicsAndBold"    =>  50,
		"doHardBreaks"        =>  60,
	);

	/**
	 * Run span gamut transformations
	 * @param  string $text
	 * @return string
	 */
	protected function runSpanGamut($text) {
		foreach ($this->span_gamut as $method => $priority) {
			$text = $this->$method($text);
		}

		return $text;
	}

	/**
	 * Do hard breaks
	 * @param  string $text
	 * @return string
	 */
	protected function doHardBreaks($text) {
		if ($this->hard_wrap) {
			return preg_replace_callback('/ *\n/',
				array($this, '_doHardBreaks_callback'), $text);
		} else {
			return preg_replace_callback('/ {2,}\n/',
				array($this, '_doHardBreaks_callback'), $text);
		}
	}

	/**
	 * Trigger part hashing for the hard break (callback method)
	 * @param  array $matches
	 * @return string
	 */
	protected function _doHardBreaks_callback($matches) {
		return $this->hashPart("<br$this->empty_element_suffix\n");
	}

	/**
	 * Turn Markdown link shortcuts into XHTML <a> tags.
	 * @param  string $text
	 * @return string
	 */
	protected function doAnchors($text) {
		if ($this->in_anchor) {
			return $text;
		}
		$this->in_anchor = true;

		// First, handle reference-style links: [link text] [id]
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				('.$this->nested_brackets_re.')	# link text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]
			)
			}xs',
			array($this, '_doAnchors_reference_callback'), $text);

		// Next, inline-style links: [link text](url "optional title")
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  \[
				('.$this->nested_brackets_re.')	# link text = $2
			  \]
			  \(			# literal paren
				[ \n]*
				(?:
					<(.+?)>	# href = $3
				|
					('.$this->nested_url_parenthesis_re.')	# href = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# Title = $7
				  \6		# matching quote
				  [ \n]*	# ignore any spaces/tabs between closing quote and )
				)?			# title is optional
			  \)
			)
			}xs',
			array($this, '_doAnchors_inline_callback'), $text);

		// Last, handle reference-style shortcuts: [link text]
		// These must come last in case you've also got [link text][1]
		// or [link text](/foo)
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				([^\[\]]+)		# link text = $2; can\'t contain [ or ]
			  \]
			)
			}xs',
			array($this, '_doAnchors_reference_callback'), $text);

		$this->in_anchor = false;
		return $text;
	}

	/**
	 * Callback method to parse referenced anchors
	 * @param  string $matches
	 * @return string
	 */
	protected function _doAnchors_reference_callback($matches) {
		$whole_match =  $matches[1];
		$link_text   =  $matches[2];
		$link_id     =& $matches[3];

		if ($link_id == "") {
			// for shortcut links like [this][] or [this].
			$link_id = $link_text;
		}

		// lower-case and turn embedded newlines into spaces
		$link_id = strtolower($link_id);
		$link_id = preg_replace('{[ ]?\n}', ' ', $link_id);

		if (isset($this->urls[$link_id])) {
			$url = $this->urls[$link_id];
			$url = $this->encodeURLAttribute($url);

			$result = "<a href=\"$url\"";
			if ( isset( $this->titles[$link_id] ) ) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}

			$link_text = $this->runSpanGamut($link_text);
			$result .= ">$link_text</a>";
			$result = $this->hashPart($result);
		} else {
			$result = $whole_match;
		}
		return $result;
	}

	/**
	 * Callback method to parse inline anchors
	 * @param  string $matches
	 * @return string
	 */
	protected function _doAnchors_inline_callback($matches) {
		$link_text		=  $this->runSpanGamut($matches[2]);
		$url			=  $matches[3] === '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		// If the URL was of the form <s p a c e s> it got caught by the HTML
		// tag parser and hashed. Need to reverse the process before using
		// the URL.
		$unhashed = $this->unhash($url);
		if ($unhashed !== $url)
			$url = preg_replace('/^<(.*)>$/', '\1', $unhashed);

		$url = $this->encodeURLAttribute($url);

		$result = "<a href=\"$url\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\"";
		}

		$link_text = $this->runSpanGamut($link_text);
		$result .= ">$link_text</a>";

		return $this->hashPart($result);
	}

	/**
	 * Turn Markdown image shortcuts into <img> tags.
	 * @param  string $text
	 * @return string
	 */
	protected function doImages($text) {
		// First, handle reference-style labeled images: ![alt text][id]
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nested_brackets_re.')		# alt text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]

			)
			}xs',
			array($this, '_doImages_reference_callback'), $text);

		// Next, handle inline images:  ![alt text](url "optional title")
		// Don't forget: encode * and _
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nested_brackets_re.')		# alt text = $2
			  \]
			  \s?			# One optional whitespace character
			  \(			# literal paren
				[ \n]*
				(?:
					<(\S*)>	# src url = $3
				|
					('.$this->nested_url_parenthesis_re.')	# src url = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# title = $7
				  \6		# matching quote
				  [ \n]*
				)?			# title is optional
			  \)
			)
			}xs',
			array($this, '_doImages_inline_callback'), $text);

		return $text;
	}

	/**
	 * Callback to parse references image tags
	 * @param  array $matches
	 * @return string
	 */
	protected function _doImages_reference_callback($matches) {
		$whole_match = $matches[1];
		$alt_text    = $matches[2];
		$link_id     = strtolower($matches[3]);

		if ($link_id == "") {
			$link_id = strtolower($alt_text); // for shortcut links like ![this][].
		}

		$alt_text = $this->encodeAttribute($alt_text);
		if (isset($this->urls[$link_id])) {
			$url = $this->encodeURLAttribute($this->urls[$link_id]);
			$result = "<img src=\"$url\" alt=\"$alt_text\"";
			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			$result .= $this->empty_element_suffix;
			$result = $this->hashPart($result);
		} else {
			// If there's no such link ID, leave intact:
			$result = $whole_match;
		}

		return $result;
	}

	/**
	 * Callback to parse inline image tags
	 * @param  array $matches
	 * @return string
	 */
	protected function _doImages_inline_callback($matches) {
		$whole_match	= $matches[1];
		$alt_text		= $matches[2];
		$url			= $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		$alt_text = $this->encodeAttribute($alt_text);
		$url = $this->encodeURLAttribute($url);
		$result = "<img src=\"$url\" alt=\"$alt_text\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\""; // $title already quoted
		}
		$result .= $this->empty_element_suffix;

		return $this->hashPart($result);
	}

	/**
	 * Parse Markdown heading elements to HTML
	 * @param  string $text
	 * @return string
	 */
	protected function doHeaders($text) {
		/**
		 * Setext-style headers:
		 *	  Header 1
		 *	  ========
		 *
		 *	  Header 2
		 *	  --------
		 */
		$text = preg_replace_callback('{ ^(.+?)[ ]*\n(=+|-+)[ ]*\n+ }mx',
			array($this, '_doHeaders_callback_setext'), $text);

		/**
		 * atx-style headers:
		 *   # Header 1
		 *   ## Header 2
		 *   ## Header 2 with closing hashes ##
		 *   ...
		 *   ###### Header 6
		 */
		$text = preg_replace_callback('{
				^(\#{1,6})	# $1 = string of #\'s
				[ ]*
				(.+?)		# $2 = Header text
				[ ]*
				\#*			# optional closing #\'s (not counted)
				\n+
			}xm',
			array($this, '_doHeaders_callback_atx'), $text);

		return $text;
	}

	/**
	 * Setext header parsing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doHeaders_callback_setext($matches) {
		// Terrible hack to check we haven't found an empty list item.
		if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1])) {
			return $matches[0];
		}

		$level = $matches[2][0] == '=' ? 1 : 2;

		// ID attribute generation
		$idAtt = $this->_generateIdFromHeaderValue($matches[1]);

		$block = "<h$level$idAtt>".$this->runSpanGamut($matches[1])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	/**
	 * ATX header parsing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doHeaders_callback_atx($matches) {
		// ID attribute generation
		$idAtt = $this->_generateIdFromHeaderValue($matches[2]);

		$level = strlen($matches[1]);
		$block = "<h$level$idAtt>".$this->runSpanGamut($matches[2])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	/**
	 * If a header_id_func property is set, we can use it to automatically
	 * generate an id attribute.
	 *
	 * This method returns a string in the form id="foo", or an empty string
	 * otherwise.
	 * @param  string $headerValue
	 * @return string
	 */
	protected function _generateIdFromHeaderValue($headerValue) {
		if (!is_callable($this->header_id_func)) {
			return "";
		}

		$idValue = call_user_func($this->header_id_func, $headerValue);
		if (!$idValue) {
			return "";
		}

		return ' id="' . $this->encodeAttribute($idValue) . '"';
	}

	/**
	 * Form HTML ordered (numbered) and unordered (bulleted) lists.
	 * @param  string $text
	 * @return string
	 */
	protected function doLists($text) {
		$less_than_tab = $this->tab_width - 1;

		// Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re  = '[*+-]';
		$marker_ol_re  = '\d+[\.]';

		$markers_relist = array(
			$marker_ul_re => $marker_ol_re,
			$marker_ol_re => $marker_ul_re,
			);

		foreach ($markers_relist as $marker_re => $other_marker_re) {
			// Re-usable pattern to match any entirel ul or ol list:
			$whole_list_re = '
				(								# $1 = whole list
				  (								# $2
					([ ]{0,'.$less_than_tab.'})	# $3 = number of spaces
					('.$marker_re.')			# $4 = first list item marker
					[ ]+
				  )
				  (?s:.+?)
				  (								# $5
					  \z
					|
					  \n{2,}
					  (?=\S)
					  (?!						# Negative lookahead for another list item marker
						[ ]*
						'.$marker_re.'[ ]+
					  )
					|
					  (?=						# Lookahead for another kind of list
					    \n
						\3						# Must have the same indentation
						'.$other_marker_re.'[ ]+
					  )
				  )
				)
			'; // mx

			// We use a different prefix before nested lists than top-level lists.
			//See extended comment in _ProcessListItems().

			if ($this->list_level) {
				$text = preg_replace_callback('{
						^
						'.$whole_list_re.'
					}mx',
					array($this, '_doLists_callback'), $text);
			} else {
				$text = preg_replace_callback('{
						(?:(?<=\n)\n|\A\n?) # Must eat the newline
						'.$whole_list_re.'
					}mx',
					array($this, '_doLists_callback'), $text);
			}
		}

		return $text;
	}

	/**
	 * List parsing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doLists_callback($matches) {
		// Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re  = '[*+-]';
		$marker_ol_re  = '\d+[\.]';
		$marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";
		$marker_ol_start_re = '[0-9]+';

		$list = $matches[1];
		$list_type = preg_match("/$marker_ul_re/", $matches[4]) ? "ul" : "ol";

		$marker_any_re = ( $list_type == "ul" ? $marker_ul_re : $marker_ol_re );

		$list .= "\n";
		$result = $this->processListItems($list, $marker_any_re);

		$ol_start = 1;
		if ($this->enhanced_ordered_list) {
			// Get the start number for ordered list.
			if ($list_type == 'ol') {
				$ol_start_array = array();
				$ol_start_check = preg_match("/$marker_ol_start_re/", $matches[4], $ol_start_array);
				if ($ol_start_check){
					$ol_start = $ol_start_array[0];
				}
			}
		}

		if ($ol_start > 1 && $list_type == 'ol'){
			$result = $this->hashBlock("<$list_type start=\"$ol_start\">\n" . $result . "</$list_type>");
		} else {
			$result = $this->hashBlock("<$list_type>\n" . $result . "</$list_type>");
		}
		return "\n". $result ."\n\n";
	}

	/**
	 * Nesting tracker for list levels
	 * @var integer
	 */
	protected $list_level = 0;

	/**
	 * Process the contents of a single ordered or unordered list, splitting it
	 * into individual list items.
	 * @param  string $list_str
	 * @param  string $marker_any_re
	 * @return string
	 */
	protected function processListItems($list_str, $marker_any_re) {
		/**
		 * The $this->list_level global keeps track of when we're inside a list.
		 * Each time we enter a list, we increment it; when we leave a list,
		 * we decrement. If it's zero, we're not in a list anymore.
		 *
		 * We do this because when we're not inside a list, we want to treat
		 * something like this:
		 *
		 *		I recommend upgrading to version
		 *		8. Oops, now this line is treated
		 *		as a sub-list.
		 *
		 * As a single paragraph, despite the fact that the second line starts
		 * with a digit-period-space sequence.
		 *
		 * Whereas when we're inside a list (or sub-list), that line will be
		 * treated as the start of a sub-list. What a kludge, huh? This is
		 * an aspect of Markdown's syntax that's hard to parse perfectly
		 * without resorting to mind-reading. Perhaps the solution is to
		 * change the syntax rules such that sub-lists must start with a
		 * starting cardinal number; e.g. "1." or "a.".
		 */
		$this->list_level++;

		// Trim trailing blank lines:
		$list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);

		$list_str = preg_replace_callback('{
			(\n)?							# leading line = $1
			(^[ ]*)							# leading whitespace = $2
			('.$marker_any_re.'				# list marker and space = $3
				(?:[ ]+|(?=\n))	# space only required if item is not empty
			)
			((?s:.*?))						# list item text   = $4
			(?:(\n+(?=\n))|\n)				# tailing blank line = $5
			(?= \n* (\z | \2 ('.$marker_any_re.') (?:[ ]+|(?=\n))))
			}xm',
			array($this, '_processListItems_callback'), $list_str);

		$this->list_level--;
		return $list_str;
	}

	/**
	 * List item parsing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _processListItems_callback($matches) {
		$item = $matches[4];
		$leading_line =& $matches[1];
		$leading_space =& $matches[2];
		$marker_space = $matches[3];
		$tailing_blank_line =& $matches[5];

		if ($leading_line || $tailing_blank_line ||
			preg_match('/\n{2,}/', $item))
		{
			// Replace marker with the appropriate whitespace indentation
			$item = $leading_space . str_repeat(' ', strlen($marker_space)) . $item;
			$item = $this->runBlockGamut($this->outdent($item)."\n");
		} else {
			// Recursion for sub-lists:
			$item = $this->doLists($this->outdent($item));
			$item = $this->formParagraphs($item, false);
		}

		return "<li>" . $item . "</li>\n";
	}

	/**
	 * Process Markdown `<pre><code>` blocks.
	 * @param  string $text
	 * @return string
	 */
	protected function doCodeBlocks($text) {
		$text = preg_replace_callback('{
				(?:\n\n|\A\n?)
				(	            # $1 = the code block -- one or more lines, starting with a space/tab
				  (?>
					[ ]{'.$this->tab_width.'}  # Lines must start with a tab or a tab-width of spaces
					.*\n+
				  )+
				)
				((?=^[ ]{0,'.$this->tab_width.'}\S)|\Z)	# Lookahead for non-space at line-start, or end of doc
			}xm',
			array($this, '_doCodeBlocks_callback'), $text);

		return $text;
	}

	/**
	 * Code block parsing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doCodeBlocks_callback($matches) {
		$codeblock = $matches[1];

		$codeblock = $this->outdent($codeblock);
		if (is_callable($this->code_block_content_func)) {
			$codeblock = call_user_func($this->code_block_content_func, $codeblock, "");
		} else {
			$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);
		}

		# trim leading newlines and trailing newlines
		$codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

		$codeblock = "<pre><code>$codeblock\n</code></pre>";
		return "\n\n" . $this->hashBlock($codeblock) . "\n\n";
	}

	/**
	 * Create a code span markup for $code. Called from handleSpanToken.
	 * @param  string $code
	 * @return string
	 */
	protected function makeCodeSpan($code) {
		if (is_callable($this->code_span_content_func)) {
			$code = call_user_func($this->code_span_content_func, $code);
		} else {
			$code = htmlspecialchars(trim($code), ENT_NOQUOTES);
		}
		return $this->hashPart("<code>$code</code>");
	}

	/**
	 * Define the emphasis operators with their regex matches
	 * @var array
	 */
	protected $em_relist = array(
		''  => '(?:(?<!\*)\*(?!\*)|(?<!_)_(?!_))(?![\.,:;]?\s)',
		'*' => '(?<![\s*])\*(?!\*)',
		'_' => '(?<![\s_])_(?!_)',
	);

	/**
	 * Define the strong operators with their regex matches
	 * @var array
	 */
	protected $strong_relist = array(
		''   => '(?:(?<!\*)\*\*(?!\*)|(?<!_)__(?!_))(?![\.,:;]?\s)',
		'**' => '(?<![\s*])\*\*(?!\*)',
		'__' => '(?<![\s_])__(?!_)',
	);

	/**
	 * Define the emphasis + strong operators with their regex matches
	 * @var array
	 */
	protected $em_strong_relist = array(
		''    => '(?:(?<!\*)\*\*\*(?!\*)|(?<!_)___(?!_))(?![\.,:;]?\s)',
		'***' => '(?<![\s*])\*\*\*(?!\*)',
		'___' => '(?<![\s_])___(?!_)',
	);

	/**
	 * Container for prepared regular expressions
	 * @var array
	 */
	protected $em_strong_prepared_relist;

	/**
	 * Prepare regular expressions for searching emphasis tokens in any
	 * context.
	 * @return void
	 */
	protected function prepareItalicsAndBold() {
		foreach ($this->em_relist as $em => $em_re) {
			foreach ($this->strong_relist as $strong => $strong_re) {
				// Construct list of allowed token expressions.
				$token_relist = array();
				if (isset($this->em_strong_relist["$em$strong"])) {
					$token_relist[] = $this->em_strong_relist["$em$strong"];
				}
				$token_relist[] = $em_re;
				$token_relist[] = $strong_re;

				// Construct master expression from list.
				$token_re = '{(' . implode('|', $token_relist) . ')}';
				$this->em_strong_prepared_relist["$em$strong"] = $token_re;
			}
		}
	}

	/**
	 * Convert Markdown italics (emphasis) and bold (strong) to HTML
	 * @param  string $text
	 * @return string
	 */
	protected function doItalicsAndBold($text) {
		if ($this->in_emphasis_processing) {
			return $text; // avoid reentrency
		}
		$this->in_emphasis_processing = true;

		$token_stack = array('');
		$text_stack = array('');
		$em = '';
		$strong = '';
		$tree_char_em = false;

		while (1) {
			// Get prepared regular expression for seraching emphasis tokens
			// in current context.
			$token_re = $this->em_strong_prepared_relist["$em$strong"];

			// Each loop iteration search for the next emphasis token.
			// Each token is then passed to handleSpanToken.
			$parts = preg_split($token_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);
			$text_stack[0] .= $parts[0];
			$token =& $parts[1];
			$text =& $parts[2];

			if (empty($token)) {
				// Reached end of text span: empty stack without emitting.
				// any more emphasis.
				while ($token_stack[0]) {
					$text_stack[1] .= array_shift($token_stack);
					$text_stack[0] .= array_shift($text_stack);
				}
				break;
			}

			$token_len = strlen($token);
			if ($tree_char_em) {
				// Reached closing marker while inside a three-char emphasis.
				if ($token_len == 3) {
					// Three-char closing marker, close em and strong.
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong><em>$span</em></strong>";
					$text_stack[0] .= $this->hashPart($span);
					$em = '';
					$strong = '';
				} else {
					// Other closing marker: close one em or strong and
					// change current token state to match the other
					$token_stack[0] = str_repeat($token[0], 3-$token_len);
					$tag = $token_len == 2 ? "strong" : "em";
					$span = $text_stack[0];
					$span = $this->runSpanGamut($span);
					$span = "<$tag>$span</$tag>";
					$text_stack[0] = $this->hashPart($span);
					$$tag = ''; // $$tag stands for $em or $strong
				}
				$tree_char_em = false;
			} else if ($token_len == 3) {
				if ($em) {
					// Reached closing marker for both em and strong.
					// Closing strong marker:
					for ($i = 0; $i < 2; ++$i) {
						$shifted_token = array_shift($token_stack);
						$tag = strlen($shifted_token) == 2 ? "strong" : "em";
						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<$tag>$span</$tag>";
						$text_stack[0] .= $this->hashPart($span);
						$$tag = ''; // $$tag stands for $em or $strong
					}
				} else {
					// Reached opening three-char emphasis marker. Push on token
					// stack; will be handled by the special condition above.
					$em = $token[0];
					$strong = "$em$em";
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$tree_char_em = true;
				}
			} else if ($token_len == 2) {
				if ($strong) {
					// Unwind any dangling emphasis marker:
					if (strlen($token_stack[0]) == 1) {
						$text_stack[1] .= array_shift($token_stack);
						$text_stack[0] .= array_shift($text_stack);
						$em = '';
					}
					// Closing strong marker:
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong>$span</strong>";
					$text_stack[0] .= $this->hashPart($span);
					$strong = '';
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$strong = $token;
				}
			} else {
				// Here $token_len == 1
				if ($em) {
					if (strlen($token_stack[0]) == 1) {
						// Closing emphasis marker:
						array_shift($token_stack);
						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<em>$span</em>";
						$text_stack[0] .= $this->hashPart($span);
						$em = '';
					} else {
						$text_stack[0] .= $token;
					}
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$em = $token;
				}
			}
		}
		$this->in_emphasis_processing = false;
		return $text_stack[0];
	}

	/**
	 * Parse Markdown blockquotes to HTML
	 * @param  string $text
	 * @return string
	 */
	protected function doBlockQuotes($text) {
		$text = preg_replace_callback('/
			  (								# Wrap whole match in $1
				(?>
				  ^[ ]*>[ ]?			# ">" at the start of a line
					.+\n					# rest of the first line
				  (.+\n)*					# subsequent consecutive lines
				  \n*						# blanks
				)+
			  )
			/xm',
			array($this, '_doBlockQuotes_callback'), $text);

		return $text;
	}

	/**
	 * Blockquote parsing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doBlockQuotes_callback($matches) {
		$bq = $matches[1];
		// trim one level of quoting - trim whitespace-only lines
		$bq = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bq);
		$bq = $this->runBlockGamut($bq); // recurse

		$bq = preg_replace('/^/m', "  ", $bq);
		// These leading spaces cause problem with <pre> content,
		// so we need to fix that:
		$bq = preg_replace_callback('{(\s*<pre>.+?</pre>)}sx',
			array($this, '_doBlockQuotes_callback2'), $bq);

		return "\n" . $this->hashBlock("<blockquote>\n$bq\n</blockquote>") . "\n\n";
	}

	/**
	 * Blockquote parsing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doBlockQuotes_callback2($matches) {
		$pre = $matches[1];
		$pre = preg_replace('/^  /m', '', $pre);
		return $pre;
	}

	/**
	 * Parse paragraphs
	 *
	 * @param  string $text String to process in paragraphs
	 * @param  boolean $wrap_in_p Whether paragraphs should be wrapped in <p> tags
	 * @return string
	 */
	protected function formParagraphs($text, $wrap_in_p = true) {
		// Strip leading and trailing lines:
		$text = preg_replace('/\A\n+|\n+\z/', '', $text);

		$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

		// Wrap <p> tags and unhashify HTML blocks
		foreach ($grafs as $key => $value) {
			if (!preg_match('/^B\x1A[0-9]+B$/', $value)) {
				// Is a paragraph.
				$value = $this->runSpanGamut($value);
				if ($wrap_in_p) {
					$value = preg_replace('/^([ ]*)/', "<p>", $value);
					$value .= "</p>";
				}
				$grafs[$key] = $this->unhash($value);
			} else {
				// Is a block.
				// Modify elements of @grafs in-place...
				$graf = $value;
				$block = $this->html_hashes[$graf];
				$graf = $block;
//				if (preg_match('{
//					\A
//					(							# $1 = <div> tag
//					  <div  \s+
//					  [^>]*
//					  \b
//					  markdown\s*=\s*  ([\'"])	#	$2 = attr quote char
//					  1
//					  \2
//					  [^>]*
//					  >
//					)
//					(							# $3 = contents
//					.*
//					)
//					(</div>)					# $4 = closing tag
//					\z
//					}xs', $block, $matches))
//				{
//					list(, $div_open, , $div_content, $div_close) = $matches;
//
//					// We can't call Markdown(), because that resets the hash;
//					// that initialization code should be pulled into its own sub, though.
//					$div_content = $this->hashHTMLBlocks($div_content);
//
//					// Run document gamut methods on the content.
//					foreach ($this->document_gamut as $method => $priority) {
//						$div_content = $this->$method($div_content);
//					}
//
//					$div_open = preg_replace(
//						'{\smarkdown\s*=\s*([\'"]).+?\1}', '', $div_open);
//
//					$graf = $div_open . "\n" . $div_content . "\n" . $div_close;
//				}
				$grafs[$key] = $graf;
			}
		}

		return implode("\n\n", $grafs);
	}

	/**
	 * Encode text for a double-quoted HTML attribute. This function
	 * is *not* suitable for attributes enclosed in single quotes.
	 * @param  string $text
	 * @return string
	 */
	protected function encodeAttribute($text) {
		$text = $this->encodeAmpsAndAngles($text);
		$text = str_replace('"', '&quot;', $text);
		return $text;
	}

	/**
	 * Encode text for a double-quoted HTML attribute containing a URL,
	 * applying the URL filter if set. Also generates the textual
	 * representation for the URL (removing mailto: or tel:) storing it in $text.
	 * This function is *not* suitable for attributes enclosed in single quotes.
	 *
	 * @param  string $url
	 * @param  string $text Passed by reference
	 * @return string        URL
	 */
	protected function encodeURLAttribute($url, &$text = null) {
		if (is_callable($this->url_filter_func)) {
			$url = call_user_func($this->url_filter_func, $url);
		}

		if (preg_match('{^mailto:}i', $url)) {
			$url = $this->encodeEntityObfuscatedAttribute($url, $text, 7);
		} else if (preg_match('{^tel:}i', $url)) {
			$url = $this->encodeAttribute($url);
			$text = substr($url, 4);
		} else {
			$url = $this->encodeAttribute($url);
			$text = $url;
		}

		return $url;
	}

	/**
	 * Smart processing for ampersands and angle brackets that need to
	 * be encoded. Valid character entities are left alone unless the
	 * no-entities mode is set.
	 * @param  string $text
	 * @return string
	 */
	protected function encodeAmpsAndAngles($text) {
		if ($this->no_entities) {
			$text = str_replace('&', '&amp;', $text);
		} else {
			// Ampersand-encoding based entirely on Nat Irons's Amputator
			// MT plugin: <http://bumppo.net/projects/amputator/>
			$text = preg_replace('/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/',
								'&amp;', $text);
		}
		// Encode remaining <'s
		$text = str_replace('<', '&lt;', $text);

		return $text;
	}

	/**
	 * Parse Markdown automatic links to anchor HTML tags
	 * @param  string $text
	 * @return string
	 */
	protected function doAutoLinks($text) {
		$text = preg_replace_callback('{<((https?|ftp|dict|tel):[^\'">\s]+)>}i',
			array($this, '_doAutoLinks_url_callback'), $text);

		// Email addresses: <address@domain.foo>
		$text = preg_replace_callback('{
			<
			(?:mailto:)?
			(
				(?:
					[-!#$%&\'*+/=?^_`.{|}~\w\x80-\xFF]+
				|
					".*?"
				)
				\@
				(?:
					[-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
				|
					\[[\d.a-fA-F:]+\]	# IPv4 & IPv6
				)
			)
			>
			}xi',
			array($this, '_doAutoLinks_email_callback'), $text);

		return $text;
	}

	/**
	 * Parse URL callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doAutoLinks_url_callback($matches) {
		$url = $this->encodeURLAttribute($matches[1], $text);
		$link = "<a href=\"$url\">$text</a>";
		return $this->hashPart($link);
	}

	/**
	 * Parse email address callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _doAutoLinks_email_callback($matches) {
		$addr = $matches[1];
		$url = $this->encodeURLAttribute("mailto:$addr", $text);
		$link = "<a href=\"$url\">$text</a>";
		return $this->hashPart($link);
	}

	/**
	 * Input: some text to obfuscate, e.g. "mailto:foo@example.com"
	 *
	 * Output: the same text but with most characters encoded as either a
	 *         decimal or hex entity, in the hopes of foiling most address
	 *         harvesting spam bots. E.g.:
	 *
	 *        &#109;&#x61;&#105;&#x6c;&#116;&#x6f;&#58;&#x66;o&#111;
	 *        &#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;&#101;&#46;&#x63;&#111;
	 *        &#x6d;
	 *
	 * Note: the additional output $tail is assigned the same value as the
	 * ouput, minus the number of characters specified by $head_length.
	 *
	 * Based by a filter by Matthew Wickline, posted to BBEdit-Talk.
	 * With some optimizations by Milian Wolff. Forced encoding of HTML
	 * attribute special characters by Allan Odgaard.
	 *
	 * @param  string  $text
	 * @param  string  $tail Passed by reference
	 * @param  integer $head_length
	 * @return string
	 */
	protected function encodeEntityObfuscatedAttribute($text, &$tail = null, $head_length = 0) {
		if ($text == "") {
			return $tail = "";
		}

		$chars = preg_split('/(?<!^)(?!$)/', $text);
		$seed = (int)abs(crc32($text) / strlen($text)); // Deterministic seed.

		foreach ($chars as $key => $char) {
			$ord = ord($char);
			// Ignore non-ascii chars.
			if ($ord < 128) {
				$r = ($seed * (1 + $key)) % 100; // Pseudo-random function.
				// roughly 10% raw, 45% hex, 45% dec
				// '@' *must* be encoded. I insist.
				// '"' and '>' have to be encoded inside the attribute
				if ($r > 90 && strpos('@"&>', $char) === false) {
					/* do nothing */
				} else if ($r < 45) {
					$chars[$key] = '&#x'.dechex($ord).';';
				} else {
					$chars[$key] = '&#'.$ord.';';
				}
			}
		}

		$text = implode('', $chars);
		$tail = $head_length ? implode('', array_slice($chars, $head_length)) : $text;

		return $text;
	}

	/**
	 * Take the string $str and parse it into tokens, hashing embeded HTML,
	 * escaped characters and handling code spans.
	 * @param  string $str
	 * @return string
	 */
	protected function parseSpan($str) {
		$output = '';

		$span_re = '{
				(
					\\\\'.$this->escape_chars_re.'
				|
					(?<![`\\\\])
					`+						# code span marker
			'.( $this->no_markup ? '' : '
				|
					<!--    .*?     -->		# comment
				|
					<\?.*?\?> | <%.*?%>		# processing instruction
				|
					<[!$]?[-a-zA-Z0-9:_]+	# regular tags
					(?>
						\s
						(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*
					)?
					>
				|
					<[-a-zA-Z0-9:_]+\s*/> # xml-style empty tag
				|
					</[-a-zA-Z0-9:_]+\s*> # closing tag
			').'
				)
				}xs';

		while (1) {
			// Each loop iteration seach for either the next tag, the next
			// openning code span marker, or the next escaped character.
			// Each token is then passed to handleSpanToken.
			$parts = preg_split($span_re, $str, 2, PREG_SPLIT_DELIM_CAPTURE);

			// Create token from text preceding tag.
			if ($parts[0] != "") {
				$output .= $parts[0];
			}

			// Check if we reach the end.
			if (isset($parts[1])) {
				$output .= $this->handleSpanToken($parts[1], $parts[2]);
				$str = $parts[2];
			} else {
				break;
			}
		}

		return $output;
	}

	/**
	 * Handle $token provided by parseSpan by determining its nature and
	 * returning the corresponding value that should replace it.
	 * @param  string $token
	 * @param  string $str Passed by reference
	 * @return string
	 */
	protected function handleSpanToken($token, &$str) {
		switch ($token[0]) {
			case "\\":
				return $this->hashPart("&#". ord($token[1]). ";");
			case "`":
				// Search for end marker in remaining text.
				if (preg_match('/^(.*?[^`])'.preg_quote($token).'(?!`)(.*)$/sm',
					$str, $matches))
				{
					$str = $matches[2];
					$codespan = $this->makeCodeSpan($matches[1]);
					return $this->hashPart($codespan);
				}
				return $token; // Return as text since no ending marker found.
			default:
				return $this->hashPart($token);
		}
	}

	/**
	 * Remove one level of line-leading tabs or spaces
	 * @param  string $text
	 * @return string
	 */
	protected function outdent($text) {
		return preg_replace('/^(\t|[ ]{1,' . $this->tab_width . '})/m', '', $text);
	}


	/**
	 * String length function for detab. `_initDetab` will create a function to
	 * handle UTF-8 if the default function does not exist.
	 * @var string
	 */
	protected $utf8_strlen = 'mb_strlen';

	/**
	 * Replace tabs with the appropriate amount of spaces.
	 *
	 * For each line we separate the line in blocks delemited by tab characters.
	 * Then we reconstruct every line by adding the  appropriate number of space
	 * between each blocks.
	 *
	 * @param  string $text
	 * @return string
	 */
	protected function detab($text) {
		$text = preg_replace_callback('/^.*\t.*$/m',
			array($this, '_detab_callback'), $text);

		return $text;
	}

	/**
	 * Replace tabs callback
	 * @param  string $matches
	 * @return string
	 */
	protected function _detab_callback($matches) {
		$line = $matches[0];
		$strlen = $this->utf8_strlen; // strlen function for UTF-8.

		// Split in blocks.
		$blocks = explode("\t", $line);
		// Add each blocks to the line.
		$line = $blocks[0];
		unset($blocks[0]); // Do not add first block twice.
		foreach ($blocks as $block) {
			// Calculate amount of space, insert spaces, insert block.
			$amount = $this->tab_width -
				$strlen($line, 'UTF-8') % $this->tab_width;
			$line .= str_repeat(" ", $amount) . $block;
		}
		return $line;
	}

	/**
	 * Check for the availability of the function in the `utf8_strlen` property
	 * (initially `mb_strlen`). If the function is not available, create a
	 * function that will loosely count the number of UTF-8 characters with a
	 * regular expression.
	 * @return void
	 */
	protected function _initDetab() {

		if (function_exists($this->utf8_strlen)) {
			return;
		}

		$this->utf8_strlen = function($text) {
			return preg_match_all('/[\x00-\xBF]|[\xC0-\xFF][\x80-\xBF]*/', $text, $m);
		};
	}

	/**
	 * Swap back in all the tags hashed by _HashHTMLBlocks.
	 * @param  string $text
	 * @return string
	 */
	protected function unhash($text) {
		return preg_replace_callback('/(.)\x1A[0-9]+\1/',
			array($this, '_unhash_callback'), $text);
	}

	/**
	 * Unhashing callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _unhash_callback($matches) {
		return $this->html_hashes[$matches[0]];
	}
}

class MarkdownExtraParser extends MarkdownParser {
	/**
	 * Configuration variables
	 */

	/**
	 * Prefix for footnote ids.
	 * @var string
	 */
	public $fn_id_prefix = "";

	/**
	 * Optional title attribute for footnote links.
	 * @var string
	 */
	public $fn_link_title = "";

	/**
	 * Optional class attribute for footnote links and backlinks.
	 * @var string
	 */
	public $fn_link_class     = "footnote-ref";
	public $fn_backlink_class = "footnote-backref";

	/**
	 * Content to be displayed within footnote backlinks. The default is '↩';
	 * the U+FE0E on the end is a Unicode variant selector used to prevent iOS
	 * from displaying the arrow character as an emoji.
	 * Optionally use '^^' and '%%' to refer to the footnote number and
	 * reference number respectively. {@see parseFootnotePlaceholders()}
	 * @var string
	 */
	public $fn_backlink_html = '&#8617;&#xFE0E;';

	/**
	 * Optional title and aria-label attributes for footnote backlinks for
	 * added accessibility (to ensure backlink uniqueness).
	 * Use '^^' and '%%' to refer to the footnote number and reference number
	 * respectively. {@see parseFootnotePlaceholders()}
	 * @var string
	 */
	public $fn_backlink_title = "";
	public $fn_backlink_label = "";

	/**
	 * Class name for table cell alignment (%% replaced left/center/right)
	 * For instance: 'go-%%' becomes 'go-left' or 'go-right' or 'go-center'
	 * If empty, the align attribute is used instead of a class name.
	 * @var string
	 */
	public $table_align_class_tmpl = '';

	/**
	 * Optional class prefix for fenced code block.
	 * @var string
	 */
	public $code_class_prefix = "";

	/**
	 * Class attribute for code blocks goes on the `code` tag;
	 * setting this to true will put attributes on the `pre` tag instead.
	 * @var boolean
	 */
	public $code_attr_on_pre = false;

	/**
	 * Predefined abbreviations.
	 * @var array
	 */
	public $predef_abbr = array();

	/**
	 * Only convert atx-style headers if there's a space between the header and #
	 * @var boolean
	 */
	public $hashtag_protection = false;

	/**
	 * Determines whether footnotes should be appended to the end of the document.
	 * If true, footnote html can be retrieved from $this->footnotes_assembled.
	 * @var boolean
	 */
	public $omit_footnotes = false;


	/**
	 * After parsing, the HTML for the list of footnotes appears here.
	 * This is available only if $omit_footnotes == true.
	 *
	 * Note: when placing the content of `footnotes_assembled` on the page,
	 * consider adding the attribute `role="doc-endnotes"` to the `div` or
	 * `section` that will enclose the list of footnotes so they are
	 * reachable to accessibility tools the same way they would be with the
	 * default HTML output.
	 * @var null|string
	 */
	public $footnotes_assembled = null;

	/**
	 * Parser implementation
	 */

	/**
	 * Constructor function. Initialize the parser object.
	 * @return void
	 */
	public function __construct() {
		// Add extra escapable characters before parent constructor
		// initialize the table.
		$this->escape_chars .= ':|';

		// Insert extra document, block, and span transformations.
		// Parent constructor will do the sorting.
		$this->document_gamut += array(
			"doFencedCodeBlocks" => 5,
			"stripFootnotes"     => 15,
			"stripAbbreviations" => 25,
			"appendFootnotes"    => 50,
		);
		$this->block_gamut += array(
			"doFencedCodeBlocks" => 5,
			"doTables"           => 15,
			"doDefLists"         => 45,
		);
		$this->span_gamut += array(
			"doFootnotes"        => 5,
			"doAbbreviations"    => 70,
		);

		$this->enhanced_ordered_list = true;
		parent::__construct();
	}


	/**
	 * Extra variables used during extra transformations.
	 * @var array
	 */
	protected $footnotes = array();
	protected $footnotes_ordered = array();
	protected $footnotes_ref_count = array();
	protected $footnotes_numbers = array();
	protected $abbr_desciptions = array();
	/** @var string */
	protected $abbr_word_re = '';

	/**
	 * Give the current footnote number.
	 * @var integer
	 */
	protected $footnote_counter = 1;

    /**
     * Ref attribute for links
     * @var array
     */
	protected $ref_attr = array();

	/**
	 * Setting up Extra-specific variables.
	 */
	protected function setup() {
		parent::setup();

		$this->footnotes = array();
		$this->footnotes_ordered = array();
		$this->footnotes_ref_count = array();
		$this->footnotes_numbers = array();
		$this->abbr_desciptions = array();
		$this->abbr_word_re = '';
		$this->footnote_counter = 1;
		$this->footnotes_assembled = null;

		foreach ($this->predef_abbr as $abbr_word => $abbr_desc) {
			if ($this->abbr_word_re)
				$this->abbr_word_re .= '|';
			$this->abbr_word_re .= preg_quote($abbr_word);
			$this->abbr_desciptions[$abbr_word] = trim($abbr_desc);
		}
	}

	/**
	 * Clearing Extra-specific variables.
	 */
	protected function teardown() {
		$this->footnotes = array();
		$this->footnotes_ordered = array();
		$this->footnotes_ref_count = array();
		$this->footnotes_numbers = array();
		$this->abbr_desciptions = array();
		$this->abbr_word_re = '';

		if ( ! $this->omit_footnotes )
			$this->footnotes_assembled = null;

		parent::teardown();
	}


	/**
	 * Extra attribute parser
	 */

	/**
	 * Expression to use to catch attributes (includes the braces)
	 * @var string
	 */
	protected $id_class_attr_catch_re = '\{((?>[ ]*[#.a-z][-_:a-zA-Z0-9=]+){1,})[ ]*\}';

	/**
	 * Expression to use when parsing in a context when no capture is desired
	 * @var string
	 */
	protected $id_class_attr_nocatch_re = '\{(?>[ ]*[#.a-z][-_:a-zA-Z0-9=]+){1,}[ ]*\}';

	/**
	 * Parse attributes caught by the $this->id_class_attr_catch_re expression
	 * and return the HTML-formatted list of attributes.
	 *
	 * Currently supported attributes are .class and #id.
	 *
	 * In addition, this method also supports supplying a default Id value,
	 * which will be used to populate the id attribute in case it was not
	 * overridden.
	 * @param  string $tag_name
	 * @param  string $attr
	 * @param  mixed  $defaultIdValue
	 * @param  array  $classes
	 * @return string
	 */
	protected function doExtraAttributes($tag_name, $attr, $defaultIdValue = null, $classes = array()) {
		if (empty($attr) && !$defaultIdValue && empty($classes)) {
			return "";
		}

		// Split on components
		preg_match_all('/[#.a-z][-_:a-zA-Z0-9=]+/', $attr, $matches);
		$elements = $matches[0];

		// Handle classes and IDs (only first ID taken into account)
		$attributes = array();
		$id = false;
		foreach ($elements as $element) {
			if ($element[0] === '.') {
				$classes[] = substr($element, 1);
			} else if ($element[0] === '#') {
				if ($id === false) $id = substr($element, 1);
			} else if (strpos($element, '=') > 0) {
				$parts = explode('=', $element, 2);
				$attributes[] = $parts[0] . '="' . $parts[1] . '"';
			}
		}

		if ($id === false || $id === '') {
			$id = $defaultIdValue;
		}

		// Compose attributes as string
		$attr_str = "";
		if (!empty($id)) {
			$attr_str .= ' id="'.$this->encodeAttribute($id) .'"';
		}
		if (!empty($classes)) {
			$attr_str .= ' class="'. implode(" ", $classes) . '"';
		}
		if (!$this->no_markup && !empty($attributes)) {
			$attr_str .= ' '.implode(" ", $attributes);
		}
		return $attr_str;
	}

	/**
	 * Strips link definitions from text, stores the URLs and titles in
	 * hash references.
	 * @param  string $text
	 * @return string
	 */
	protected function stripLinkDefinitions($text) {
		$less_than_tab = $this->tab_width - 1;

		// Link defs are in the form: ^[id]: url "optional title"
		$text = preg_replace_callback('{
							^[ ]{0,'.$less_than_tab.'}\[(.+)\][ ]?:	# id = $1
							  [ ]*
							  \n?				# maybe *one* newline
							  [ ]*
							(?:
							  <(.+?)>			# url = $2
							|
							  (\S+?)			# url = $3
							)
							  [ ]*
							  \n?				# maybe one newline
							  [ ]*
							(?:
								(?<=\s)			# lookbehind for whitespace
								["(]
								(.*?)			# title = $4
								[")]
								[ ]*
							)?	# title is optional
					(?:[ ]* '.$this->id_class_attr_catch_re.' )?  # $5 = extra id & class attr
							(?:\n+|\Z)
			}xm',
			array($this, '_stripLinkDefinitions_callback'),
			$text);
		return $text;
	}

	/**
	 * Strip link definition callback
	 * @param  array $matches
	 * @return string
	 */
	protected function _stripLinkDefinitions_callback($matches) {
		$link_id = strtolower($matches[1]);
		$url = $matches[2] == '' ? $matches[3] : $matches[2];
		$this->urls[$link_id] = $url;
		$this->titles[$link_id] =& $matches[4];
		$this->ref_attr[$link_id] = $this->doExtraAttributes("", $dummy =& $matches[5]);
		return ''; // String that will replace the block
	}


	/**
	 * HTML block parser
	 */

	/**
	 * Tags that are always treated as block tags
	 * @var string
	 */
	protected $block_tags_re = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|form|fieldset|iframe|hr|legend|article|section|nav|aside|hgroup|header|footer|figcaption|figure';

	/**
	 * Tags treated as block tags only if the opening tag is alone on its line
	 * @var string
	 */
	protected $context_block_tags_re = 'script|noscript|style|ins|del|iframe|object|source|track|param|math|svg|canvas|audio|video';

	/**
	 * Tags where markdown="1" default to span mode:
	 * @var string
	 */
	protected $contain_span_tags_re = 'p|h[1-6]|li|dd|dt|td|th|legend|address';

	/**
	 * Tags which must not have their contents modified, no matter where
	 * they appear
	 * @var string
	 */
	protected $clean_tags_re = 'script|style|math|svg';

	/**
	 * Tags that do not need to be closed.
	 * @var string
	 */
	protected $auto_close_tags_re = 'hr|img|param|source|track';

	/**
	 * Hashify HTML Blocks and "clean tags".
	 *
	 * We only want to do this for block-level HTML tags, such as headers,
	 * lists, and tables. That's because we still want to wrap <p>s around
	 * "paragraphs" that are wrapped in non-block-level tags, such as anchors,
	 * phrase emphasis, and spans. The list of tags we're looking for is
	 * hard-coded.
	 *
	 * This works by calling _HashHTMLBlocks_InMarkdown, which then calls
	 * _HashHTMLBlocks_InHTML when it encounter block tags. When the markdown="1"
	 * attribute is found within a tag, _HashHTMLBlocks_InHTML calls back
	 *  _HashHTMLBlocks_InMarkdown to handle the Markdown syntax within the tag.
	 * These two functions are calling each other. It's recursive!
	 * @param  string $text
	 * @return string
	 */
	protected function hashHTMLBlocks($text) {
		if ($this->no_markup) {
			return $text;
		}

		// Call the HTML-in-Markdown hasher.
		list($text, ) = $this->_hashHTMLBlocks_inMarkdown($text);

		return $text;
	}

	/**
	 * Parse markdown text, calling _HashHTMLBlocks_InHTML for block tags.
	 *
	 * *   $indent is the number of space to be ignored when checking for code
	 *     blocks. This is important because if we don't take the indent into
	 *     account, something like this (which looks right) won't work as expected:
	 *
	 *     <div>
	 *         <div markdown="1">
	 *         Hello World.  <-- Is this a Markdown code block or text?
	 *         </div>  <-- Is this a Markdown code block or a real tag?
	 *     <div>
	 *
	 *     If you don't like this, just don't indent the tag on which
	 *     you apply the markdown="1" attribute.
	 *
	 * *   If $enclosing_tag_re is not empty, stops at the first unmatched closing
	 *     tag with that name. Nested tags supported.
	 *
	 * *   If $span is true, text inside must treated as span. So any double
	 *     newline will be replaced by a single newline so that it does not create
	 *     paragraphs.
	 *
	 * Returns an array of that form: ( processed text , remaining text )
	 *
	 * @param  string  $text
	 * @param  integer $indent
	 * @param  string  $enclosing_tag_re
	 * @param  boolean $span
	 * @return array
	 */
	protected function _hashHTMLBlocks_inMarkdown($text, $indent = 0,
										$enclosing_tag_re = '', $span = false)
	{

		if ($text === '') return array('', '');

		// Regex to check for the presense of newlines around a block tag.
		$newline_before_re = '/(?:^\n?|\n\n)*$/';
		$newline_after_re =
			'{
				^						# Start of text following the tag.
				(?>[ ]*<!--.*?-->)?		# Optional comment.
				[ ]*\n					# Must be followed by newline.
			}xs';

		// Regex to match any tag.
		$block_tag_re =
			'{
				(					# $2: Capture whole tag.
					</?					# Any opening or closing tag.
						(?>				# Tag name.
							' . $this->block_tags_re . '			|
							' . $this->context_block_tags_re . '	|
							' . $this->clean_tags_re . '        	|
							(?!\s)'.$enclosing_tag_re . '
						)
						(?:
							(?=[\s"\'/a-zA-Z0-9])	# Allowed characters after tag name.
							(?>
								".*?"		|	# Double quotes (can contain `>`)
								\'.*?\'   	|	# Single quotes (can contain `>`)
								.+?				# Anything but quotes and `>`.
							)*?
						)?
					>					# End of tag.
				|
					<!--    .*?     -->	# HTML Comment
				|
					<\?.*?\?> | <%.*?%>	# Processing instruction
				|
					<!\[CDATA\[.*?\]\]>	# CData Block
				' . ( !$span ? ' # If not in span.
				|
					# Indented code block
					(?: ^[ ]*\n | ^ | \n[ ]*\n )
					[ ]{' . ($indent + 4) . '}[^\n]* \n
					(?>
						(?: [ ]{' . ($indent + 4) . '}[^\n]* | [ ]* ) \n
					)*
				|
					# Fenced code block marker
					(?<= ^ | \n )
					[ ]{0,' . ($indent + 3) . '}(?:~{3,}|`{3,})
					[ ]*
					(?: \.?[-_:a-zA-Z0-9]+ )? # standalone class name
					[ ]*
					(?: ' . $this->id_class_attr_nocatch_re . ' )? # extra attributes
					[ ]*
					(?= \n )
				' : '' ) . ' # End (if not is span).
				|
					# Code span marker
					# Note, this regex needs to go after backtick fenced
					# code blocks but it should also be kept outside of the
					# "if not in span" condition adding backticks to the parser
					`+
				)
			}xs';


		$depth = 0;		// Current depth inside the tag tree.
		$parsed = "";	// Parsed text that will be returned.

		// Loop through every tag until we find the closing tag of the parent
		// or loop until reaching the end of text if no parent tag specified.
		do {
			// Split the text using the first $tag_match pattern found.
			// Text before  pattern will be first in the array, text after
			// pattern will be at the end, and between will be any catches made
			// by the pattern.
			$parts = preg_split($block_tag_re, $text, 2,
								PREG_SPLIT_DELIM_CAPTURE);

			// If in Markdown span mode, add a empty-string span-level hash
			// after each newline to prevent triggering any block element.
			if ($span) {
				$void = $this->hashPart("", ':');
				$newline = "\n$void";
				$parts[0] = $void . str_replace("\n", $newline, $parts[0]) . $void;
			}

			$parsed .= $parts[0]; // Text before current tag.

			// If end of $text has been reached. Stop loop.
			if (count($parts) < 3) {
				$text = "";
				break;
			}

			$tag  = $parts[1]; // Tag to handle.
			$text = $parts[2]; // Remaining text after current tag.

			// Check for: Fenced code block marker.
			// Note: need to recheck the whole tag to disambiguate backtick
			// fences from code spans
			if (preg_match('{^\n?([ ]{0,' . ($indent + 3) . '})(~{3,}|`{3,})[ ]*(?:\.?[-_:a-zA-Z0-9]+)?[ ]*(?:' . $this->id_class_attr_nocatch_re . ')?[ ]*\n?$}', $tag, $capture)) {
				// Fenced code block marker: find matching end marker.
				$fence_indent = strlen($capture[1]); // use captured indent in re
				$fence_re = $capture[2]; // use captured fence in re
				if (preg_match('{^(?>.*\n)*?[ ]{' . ($fence_indent) . '}' . $fence_re . '[ ]*(?:\n|$)}', $text,
					$matches))
				{
					// End marker found: pass text unchanged until marker.
					$parsed .= $tag . $matches[0];
					$text = substr($text, strlen($matches[0]));
				}
				else {
					// No end marker: just skip it.
					$parsed .= $tag;
				}
			}
			// Check for: Indented code block.
			else if ($tag[0] === "\n" || $tag[0] === " ") {
				// Indented code block: pass it unchanged, will be handled
				// later.
				$parsed .= $tag;
			}
			// Check for: Code span marker
			// Note: need to check this after backtick fenced code blocks
			else if ($tag[0] === "`") {
				// Find corresponding end marker.
				$tag_re = preg_quote($tag);
				if (preg_match('{^(?>.+?|\n(?!\n))*?(?<!`)' . $tag_re . '(?!`)}',
					$text, $matches))
				{
					// End marker found: pass text unchanged until marker.
					$parsed .= $tag . $matches[0];
					$text = substr($text, strlen($matches[0]));
				}
				else {
					// Unmatched marker: just skip it.
					$parsed .= $tag;
				}
			}
			// Check for: Opening Block level tag or
			//            Opening Context Block tag (like ins and del)
			//               used as a block tag (tag is alone on it's line).
			else if (preg_match('{^<(?:' . $this->block_tags_re . ')\b}', $tag) ||
				(	preg_match('{^<(?:' . $this->context_block_tags_re . ')\b}', $tag) &&
					preg_match($newline_before_re, $parsed) &&
					preg_match($newline_after_re, $text)	)
				)
			{
				// Need to parse tag and following text using the HTML parser.
				list($block_text, $text) =
					$this->_hashHTMLBlocks_inHTML($tag . $text, "hashBlock", true);

				// Make sure it stays outside of any paragraph by adding newlines.
				$parsed .= "\n\n$block_text\n\n";
			}
			// Check for: Clean tag (like script, math)
			//            HTML Comments, processing instructions.
			else if (preg_match('{^<(?:' . $this->clean_tags_re . ')\b}', $tag) ||
				$tag[1] === '!' || $tag[1] === '?')
			{
				// Need to parse tag and following text using the HTML parser.
				// (don't check for markdown attribute)
				list($block_text, $text) =
					$this->_hashHTMLBlocks_inHTML($tag . $text, "hashClean", false);

				$parsed .= $block_text;
			}
			// Check for: Tag with same name as enclosing tag.
			else if ($enclosing_tag_re !== '' &&
				// Same name as enclosing tag.
				preg_match('{^</?(?:' . $enclosing_tag_re . ')\b}', $tag))
			{
				// Increase/decrease nested tag count.
				if ($tag[1] === '/') {
					$depth--;
				} else if ($tag[strlen($tag)-2] !== '/') {
					$depth++;
				}

				if ($depth < 0) {
					// Going out of parent element. Clean up and break so we
					// return to the calling function.
					$text = $tag . $text;
					break;
				}

				$parsed .= $tag;
			}
			else {
				$parsed .= $tag;
			}
		} while ($depth >= 0);

		return array($parsed, $text);
	}

	/**
	 * Parse HTML, calling _HashHTMLBlocks_InMarkdown for block tags.
	 *
	 * *   Calls $hash_method to convert any blocks.
	 * *   Stops when the first opening tag closes.
	 * *   $md_attr indicate if the use of the `markdown="1"` attribute is allowed.
	 *     (it is not inside clean tags)
	 *
	 * Returns an array of that form: ( processed text , remaining text )
	 * @param  string $text
	 * @param  string $hash_method
	 * @param  bool $md_attr Handle `markdown="1"` attribute
	 * @return array
	 */
	protected function _hashHTMLBlocks_inHTML($text, $hash_method, $md_attr) {
		if ($text === '') return array('', '');

		// Regex to match `markdown` attribute inside of a tag.
		$markdown_attr_re = '
			{
				\s*			# Eat whitespace before the `markdown` attribute
				markdown
				\s*=\s*
				(?>
					(["\'])		# $1: quote delimiter
					(.*?)		# $2: attribute value
					\1			# matching delimiter
				|
					([^\s>]*)	# $3: unquoted attribute value
				)
				()				# $4: make $3 always defined (avoid warnings)
			}xs';

		// Regex to match any tag.
		$tag_re = '{
				(					# $2: Capture whole tag.
					</?					# Any opening or closing tag.
						[\w:$]+			# Tag name.
						(?:
							(?=[\s"\'/a-zA-Z0-9])	# Allowed characters after tag name.
							(?>
								".*?"		|	# Double quotes (can contain `>`)
								\'.*?\'   	|	# Single quotes (can contain `>`)
								.+?				# Anything but quotes and `>`.
							)*?
						)?
					>					# End of tag.
				|
					<!--    .*?     -->	# HTML Comment
				|
					<\?.*?\?> | <%.*?%>	# Processing instruction
				|
					<!\[CDATA\[.*?\]\]>	# CData Block
				)
			}xs';

		$original_text = $text;		// Save original text in case of faliure.

		$depth		= 0;	// Current depth inside the tag tree.
		$block_text	= "";	// Temporary text holder for current text.
		$parsed		= "";	// Parsed text that will be returned.
		$base_tag_name_re = '';

		// Get the name of the starting tag.
		// (This pattern makes $base_tag_name_re safe without quoting.)
		if (preg_match('/^<([\w:$]*)\b/', $text, $matches))
			$base_tag_name_re = $matches[1];

		// Loop through every tag until we find the corresponding closing tag.
		do {
			// Split the text using the first $tag_match pattern found.
			// Text before  pattern will be first in the array, text after
			// pattern will be at the end, and between will be any catches made
			// by the pattern.
			$parts = preg_split($tag_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);

			if (count($parts) < 3) {
				// End of $text reached with unbalenced tag(s).
				// In that case, we return original text unchanged and pass the
				// first character as filtered to prevent an infinite loop in the
				// parent function.
				return array($original_text[0], substr($original_text, 1));
			}

			$block_text .= $parts[0]; // Text before current tag.
			$tag         = $parts[1]; // Tag to handle.
			$text        = $parts[2]; // Remaining text after current tag.

			// Check for: Auto-close tag (like <hr/>)
			//			 Comments and Processing Instructions.
			if (preg_match('{^</?(?:' . $this->auto_close_tags_re . ')\b}', $tag) ||
				$tag[1] === '!' || $tag[1] === '?')
			{
				// Just add the tag to the block as if it was text.
				$block_text .= $tag;
			}
			else {
				// Increase/decrease nested tag count. Only do so if
				// the tag's name match base tag's.
				if (preg_match('{^</?' . $base_tag_name_re . '\b}', $tag)) {
					if ($tag[1] === '/') {
						$depth--;
					} else if ($tag[strlen($tag)-2] !== '/') {
						$depth++;
					}
				}

				// Check for `markdown="1"` attribute and handle it.
				if ($md_attr &&
					preg_match($markdown_attr_re, $tag, $attr_m) &&
					preg_match('/^1|block|span$/', $attr_m[2] . $attr_m[3]))
				{
					// Remove `markdown` attribute from opening tag.
					$tag = preg_replace($markdown_attr_re, '', $tag);

					// Check if text inside this tag must be parsed in span mode.
					$mode = $attr_m[2] . $attr_m[3];
					$span_mode = $mode === 'span' || ($mode !== 'block' &&
						preg_match('{^<(?:' . $this->contain_span_tags_re . ')\b}', $tag));

					// Calculate indent before tag.
					if (preg_match('/(?:^|\n)( *?)(?! ).*?$/', $block_text, $matches)) {
						$strlen = $this->utf8_strlen;
						$indent = $strlen($matches[1], 'UTF-8');
					} else {
						$indent = 0;
					}

					// End preceding block with this tag.
					$block_text .= $tag;
					$parsed .= $this->$hash_method($block_text);

					// Get enclosing tag name for the ParseMarkdown function.
					// (This pattern makes $tag_name_re safe without quoting.)
					preg_match('/^<([\w:$]*)\b/', $tag, $matches);
					$tag_name_re = $matches[1];

					// Parse the content using the HTML-in-Markdown parser.
					list ($block_text, $text)
						= $this->_hashHTMLBlocks_inMarkdown($text, $indent,
							$tag_name_re, $span_mode);

					// Outdent markdown text.
					if ($indent > 0) {
						$block_text = preg_replace("/^[ ]{1,$indent}/m", "",
													$block_text);
					}

					// Append tag content to parsed text.
					if (!$span_mode) {
						$parsed .= "\n\n$block_text\n\n";
					} else {
						$parsed .= (string) $block_text;
					}

					// Start over with a new block.
					$block_text = "";
				}
				else $block_text .= $tag;
			}

		} while ($depth > 0);

		// Hash last block text that wasn't processed inside the loop.
		$parsed .= $this->$hash_method($block_text);

		return array($parsed, $text);
	}

	/**
	 * Called whenever a tag must be hashed when a function inserts a "clean" tag
	 * in $text, it passes through this function and is automaticaly escaped,
	 * blocking invalid nested overlap.
	 * @param  string $text
	 * @return string
	 */
	protected function hashClean($text) {
		return $this->hashPart($text, 'C');
	}

	/**
	 * Turn Markdown link shortcuts into XHTML <a> tags.
	 * @param  string $text
	 * @return string
	 */
	protected function doAnchors($text) {
		if ($this->in_anchor) {
			return $text;
		}
		$this->in_anchor = true;

		// First, handle reference-style links: [link text] [id]
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				(' . $this->nested_brackets_re . ')	# link text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]
			)
			}xs',
			array($this, '_doAnchors_reference_callback'), $text);

		// Next, inline-style links: [link text](url "optional title")
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  \[
				(' . $this->nested_brackets_re . ')	# link text = $2
			  \]
			  \(			# literal paren
				[ \n]*
				(?:
					<(.+?)>	# href = $3
				|
					(' . $this->nested_url_parenthesis_re . ')	# href = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# Title = $7
				  \6		# matching quote
				  [ \n]*	# ignore any spaces/tabs between closing quote and )
				)?			# title is optional
			  \)
			  (?:[ ]? ' . $this->id_class_attr_catch_re . ' )?	 # $8 = id/class attributes
			)
			}xs',
			array($this, '_doAnchors_inline_callback'), $text);

		// Last, handle reference-style shortcuts: [link text]
		// These must come last in case you've also got [link text][1]
		// or [link text](/foo)
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				([^\[\]]+)		# link text = $2; can\'t contain [ or ]
			  \]
			)
			}xs',
			array($this, '_doAnchors_reference_callback'), $text);

		$this->in_anchor = false;
		return $text;
	}

	/**
	 * Callback for reference anchors
	 * @param  array $matches
	 * @return string
	 */
	protected function _doAnchors_reference_callback($matches) {
		$whole_match =  $matches[1];
		$link_text   =  $matches[2];
		$link_id     =& $matches[3];

		if ($link_id == "") {
			// for shortcut links like [this][] or [this].
			$link_id = $link_text;
		}

		// lower-case and turn embedded newlines into spaces
		$link_id = strtolower($link_id);
		$link_id = preg_replace('{[ ]?\n}', ' ', $link_id);

		if (isset($this->urls[$link_id])) {
			$url = $this->urls[$link_id];
			$url = $this->encodeURLAttribute($url);

			$result = "<a href=\"$url\"";
			if ( isset( $this->titles[$link_id] ) ) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			if (isset($this->ref_attr[$link_id]))
				$result .= $this->ref_attr[$link_id];

			$link_text = $this->runSpanGamut($link_text);
			$result .= ">$link_text</a>";
			$result = $this->hashPart($result);
		}
		else {
			$result = $whole_match;
		}
		return $result;
	}

	/**
	 * Callback for inline anchors
	 * @param  array $matches
	 * @return string
	 */
	protected function _doAnchors_inline_callback($matches) {
		$link_text		=  $this->runSpanGamut($matches[2]);
		$url			=  $matches[3] === '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];
		$attr  = $this->doExtraAttributes("a", $dummy =& $matches[8]);

		// if the URL was of the form <s p a c e s> it got caught by the HTML
		// tag parser and hashed. Need to reverse the process before using the URL.
		$unhashed = $this->unhash($url);
		if ($unhashed !== $url)
			$url = preg_replace('/^<(.*)>$/', '\1', $unhashed);

		$url = $this->encodeURLAttribute($url);

		$result = "<a href=\"$url\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\"";
		}
		$result .= $attr;

		$link_text = $this->runSpanGamut($link_text);
		$result .= ">$link_text</a>";

		return $this->hashPart($result);
	}

	/**
	 * Turn Markdown image shortcuts into <img> tags.
	 * @param  string $text
	 * @return string
	 */
	protected function doImages($text) {
		// First, handle reference-style labeled images: ![alt text][id]
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				(' . $this->nested_brackets_re . ')		# alt text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]

			)
			}xs',
			array($this, '_doImages_reference_callback'), $text);

		// Next, handle inline images:  ![alt text](url "optional title")
		// Don't forget: encode * and _
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				(' . $this->nested_brackets_re . ')		# alt text = $2
			  \]
			  \s?			# One optional whitespace character
			  \(			# literal paren
				[ \n]*
				(?:
					<(\S*)>	# src url = $3
				|
					(' . $this->nested_url_parenthesis_re . ')	# src url = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# title = $7
				  \6		# matching quote
				  [ \n]*
				)?			# title is optional
			  \)
			  (?:[ ]? ' . $this->id_class_attr_catch_re . ' )?	 # $8 = id/class attributes
			)
			}xs',
			array($this, '_doImages_inline_callback'), $text);

		return $text;
	}

	/**
	 * Callback for referenced images
	 * @param  array $matches
	 * @return string
	 */
	protected function _doImages_reference_callback($matches) {
		$whole_match = $matches[1];
		$alt_text    = $matches[2];
		$link_id     = strtolower($matches[3]);

		if ($link_id === "") {
			$link_id = strtolower($alt_text); // for shortcut links like ![this][].
		}

		$alt_text = $this->encodeAttribute($alt_text);
		if (isset($this->urls[$link_id])) {
			$url = $this->encodeURLAttribute($this->urls[$link_id]);
			$result = "<img src=\"$url\" alt=\"$alt_text\"";
			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			if (isset($this->ref_attr[$link_id])) {
				$result .= $this->ref_attr[$link_id];
			}
			$result .= $this->empty_element_suffix;
			$result = $this->hashPart($result);
		}
		else {
			// If there's no such link ID, leave intact:
			$result = $whole_match;
		}

		return $result;
	}

	/**
	 * Callback for inline images
	 * @param  array $matches
	 * @return string
	 */
	protected function _doImages_inline_callback($matches) {
		$alt_text		= $matches[2];
		$url			= $matches[3] === '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];
		$attr  = $this->doExtraAttributes("img", $dummy =& $matches[8]);

		$alt_text = $this->encodeAttribute($alt_text);
		$url = $this->encodeURLAttribute($url);
		$result = "<img src=\"$url\" alt=\"$alt_text\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\""; // $title already quoted
		}
		$result .= $attr;
		$result .= $this->empty_element_suffix;

		return $this->hashPart($result);
	}

	/**
	 * Process markdown headers. Redefined to add ID and class attribute support.
	 * @param  string $text
	 * @return string
	 */
	protected function doHeaders($text) {
		// Setext-style headers:
		//  Header 1  {#header1}
		//	  ========
		//
		//	  Header 2  {#header2 .class1 .class2}
		//	  --------
		//
		$text = preg_replace_callback(
			'{
				(^.+?)								# $1: Header text
				(?:[ ]+ ' . $this->id_class_attr_catch_re . ' )?	 # $3 = id/class attributes
				[ ]*\n(=+|-+)[ ]*\n+				# $3: Header footer
			}mx',
			array($this, '_doHeaders_callback_setext'), $text);

		// atx-style headers:
		//	# Header 1        {#header1}
		//	## Header 2       {#header2}
		//	## Header 2 with closing hashes ##  {#header3.class1.class2}
		//	...
		//	###### Header 6   {.class2}
		//
		$text = preg_replace_callback('{
				^(\#{1,6})	# $1 = string of #\'s
				[ ]'.($this->hashtag_protection ? '+' : '*').'
				(.+?)		# $2 = Header text
				[ ]*
				\#*			# optional closing #\'s (not counted)
				(?:[ ]+ ' . $this->id_class_attr_catch_re . ' )?	 # $3 = id/class attributes
				[ ]*
				\n+
			}xm',
			array($this, '_doHeaders_callback_atx'), $text);

		return $text;
	}

	/**
	 * Callback for setext headers
	 * @param  array $matches
	 * @return string
	 */
	protected function _doHeaders_callback_setext($matches) {
		if ($matches[3] === '-' && preg_match('{^- }', $matches[1])) {
			return $matches[0];
		}

		$level = $matches[3][0] === '=' ? 1 : 2;

		$defaultId = is_callable($this->header_id_func) ? call_user_func($this->header_id_func, $matches[1]) : null;

		$attr  = $this->doExtraAttributes("h$level", $dummy =& $matches[2], $defaultId);
		$block = "<h$level$attr>" . $this->runSpanGamut($matches[1]) . "</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	/**
	 * Callback for atx headers
	 * @param  array $matches
	 * @return string
	 */
	protected function _doHeaders_callback_atx($matches) {
		$level = strlen($matches[1]);

		$defaultId = is_callable($this->header_id_func) ? call_user_func($this->header_id_func, $matches[2]) : null;
		$attr  = $this->doExtraAttributes("h$level", $dummy =& $matches[3], $defaultId);
		$block = "<h$level$attr>" . $this->runSpanGamut($matches[2]) . "</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}

	/**
	 * Form HTML tables.
	 * @param  string $text
	 * @return string
	 */
	protected function doTables($text) {
		$less_than_tab = $this->tab_width - 1;
		// Find tables with leading pipe.
		//
		//	| Header 1 | Header 2
		//	| -------- | --------
		//	| Cell 1   | Cell 2
		//	| Cell 3   | Cell 4
		$text = preg_replace_callback('
			{
				^							# Start of a line
				[ ]{0,' . $less_than_tab . '}	# Allowed whitespace.
				[|]							# Optional leading pipe (present)
				(.+) \n						# $1: Header row (at least one pipe)

				[ ]{0,' . $less_than_tab . '}	# Allowed whitespace.
				[|] ([ ]*[-:]+[-| :]*) \n	# $2: Header underline

				(							# $3: Cells
					(?>
						[ ]*				# Allowed whitespace.
						[|] .* \n			# Row content.
					)*
				)
				(?=\n|\Z)					# Stop at final double newline.
			}xm',
			array($this, '_doTable_leadingPipe_callback'), $text);

		// Find tables without leading pipe.
		//
		//	Header 1 | Header 2
		//	-------- | --------
		//	Cell 1   | Cell 2
		//	Cell 3   | Cell 4
		$text = preg_replace_callback('
			{
				^							# Start of a line
				[ ]{0,' . $less_than_tab . '}	# Allowed whitespace.
				(\S.*[|].*) \n				# $1: Header row (at least one pipe)

				[ ]{0,' . $less_than_tab . '}	# Allowed whitespace.
				([-:]+[ ]*[|][-| :]*) \n	# $2: Header underline

				(							# $3: Cells
					(?>
						.* [|] .* \n		# Row content
					)*
				)
				(?=\n|\Z)					# Stop at final double newline.
			}xm',
			array($this, '_DoTable_callback'), $text);

		return $text;
	}

	/**
	 * Callback for removing the leading pipe for each row
	 * @param  array $matches
	 * @return string
	 */
	protected function _doTable_leadingPipe_callback($matches) {
		$head		= $matches[1];
		$underline	= $matches[2];
		$content	= $matches[3];

		$content	= preg_replace('/^ *[|]/m', '', $content);

		return $this->_doTable_callback(array($matches[0], $head, $underline, $content));
	}

	/**
	 * Make the align attribute in a table
	 * @param  string $alignname
	 * @return string
	 */
	protected function _doTable_makeAlignAttr($alignname) {
		if (empty($this->table_align_class_tmpl)) {
			return " align=\"$alignname\"";
		}

		$classname = str_replace('%%', $alignname, $this->table_align_class_tmpl);
		return " class=\"$classname\"";
	}

	/**
	 * Calback for processing tables
	 * @param  array $matches
	 * @return string
	 */
	protected function _doTable_callback($matches) {
		$head		= $matches[1];
		$underline	= $matches[2];
		$content	= $matches[3];

		// Remove any tailing pipes for each line.
		$head		= preg_replace('/[|] *$/m', '', $head);
		$underline	= preg_replace('/[|] *$/m', '', $underline);
		$content	= preg_replace('/[|] *$/m', '', $content);

		// Reading alignement from header underline.
		$separators	= preg_split('/ *[|] */', $underline);
		foreach ($separators as $n => $s) {
			if (preg_match('/^ *-+: *$/', $s))
				$attr[$n] = $this->_doTable_makeAlignAttr('right');
			else if (preg_match('/^ *:-+: *$/', $s))
				$attr[$n] = $this->_doTable_makeAlignAttr('center');
			else if (preg_match('/^ *:-+ *$/', $s))
				$attr[$n] = $this->_doTable_makeAlignAttr('left');
			else
				$attr[$n] = '';
		}

		// Parsing span elements, including code spans, character escapes,
		// and inline HTML tags, so that pipes inside those gets ignored.
		$head		= $this->parseSpan($head);
		$headers	= preg_split('/ *[|] */', $head);
		$col_count	= count($headers);
		$attr       = array_pad($attr, $col_count, '');

		// Write column headers.
		$text = "<table>\n";
		$text .= "<thead>\n";
		$text .= "<tr>\n";
		foreach ($headers as $n => $header) {
			$text .= "  <th$attr[$n]>" . $this->runSpanGamut(trim($header)) . "</th>\n";
		}
		$text .= "</tr>\n";
		$text .= "</thead>\n";

		// Split content by row.
		$rows = explode("\n", trim($content, "\n"));

		$text .= "<tbody>\n";
		foreach ($rows as $row) {
			// Parsing span elements, including code spans, character escapes,
			// and inline HTML tags, so that pipes inside those gets ignored.
			$row = $this->parseSpan($row);

			// Split row by cell.
			$row_cells = preg_split('/ *[|] */', $row, $col_count);
			$row_cells = array_pad($row_cells, $col_count, '');

			$text .= "<tr>\n";
			foreach ($row_cells as $n => $cell) {
				$text .= "  <td$attr[$n]>" . $this->runSpanGamut(trim($cell)) . "</td>\n";
			}
			$text .= "</tr>\n";
		}
		$text .= "</tbody>\n";
		$text .= "</table>";

		return $this->hashBlock($text) . "\n";
	}

	/**
	 * Form HTML definition lists.
	 * @param  string $text
	 * @return string
	 */
	protected function doDefLists($text) {
		$less_than_tab = $this->tab_width - 1;

		// Re-usable pattern to match any entire dl list:
		$whole_list_re = '(?>
			(								# $1 = whole list
			  (								# $2
				[ ]{0,' . $less_than_tab . '}
				((?>.*\S.*\n)+)				# $3 = defined term
				\n?
				[ ]{0,' . $less_than_tab . '}:[ ]+ # colon starting definition
			  )
			  (?s:.+?)
			  (								# $4
				  \z
				|
				  \n{2,}
				  (?=\S)
				  (?!						# Negative lookahead for another term
					[ ]{0,' . $less_than_tab . '}
					(?: \S.*\n )+?			# defined term
					\n?
					[ ]{0,' . $less_than_tab . '}:[ ]+ # colon starting definition
				  )
				  (?!						# Negative lookahead for another definition
					[ ]{0,' . $less_than_tab . '}:[ ]+ # colon starting definition
				  )
			  )
			)
		)'; // mx

		$text = preg_replace_callback('{
				(?>\A\n?|(?<=\n\n))
				' . $whole_list_re . '
			}mx',
			array($this, '_doDefLists_callback'), $text);

		return $text;
	}

	/**
	 * Callback for processing definition lists
	 * @param  array $matches
	 * @return string
	 */
	protected function _doDefLists_callback($matches) {
		// Re-usable patterns to match list item bullets and number markers:
		$list = $matches[1];

		// Turn double returns into triple returns, so that we can make a
		// paragraph for the last item in a list, if necessary:
		$result = trim($this->processDefListItems($list));
		$result = "<dl>\n" . $result . "\n</dl>";
		return $this->hashBlock($result) . "\n\n";
	}

	/**
	 * Process the contents of a single definition list, splitting it
	 * into individual term and definition list items.
	 * @param  string $list_str
	 * @return string
	 */
	protected function processDefListItems($list_str) {

		$less_than_tab = $this->tab_width - 1;

		// Trim trailing blank lines:
		$list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);

		// Process definition terms.
		$list_str = preg_replace_callback('{
			(?>\A\n?|\n\n+)						# leading line
			(									# definition terms = $1
				[ ]{0,' . $less_than_tab . '}	# leading whitespace
				(?!\:[ ]|[ ])					# negative lookahead for a definition
												#   mark (colon) or more whitespace.
				(?> \S.* \n)+?					# actual term (not whitespace).
			)
			(?=\n?[ ]{0,3}:[ ])					# lookahead for following line feed
												#   with a definition mark.
			}xm',
			array($this, '_processDefListItems_callback_dt'), $list_str);

		// Process actual definitions.
		$list_str = preg_replace_callback('{
			\n(\n+)?							# leading line = $1
			(									# marker space = $2
				[ ]{0,' . $less_than_tab . '}	# whitespace before colon
				\:[ ]+							# definition mark (colon)
			)
			((?s:.+?))							# definition text = $3
			(?= \n+ 							# stop at next definition mark,
				(?:								# next term or end of text
					[ ]{0,' . $less_than_tab . '} \:[ ]	|
					<dt> | \z
				)
			)
			}xm',
			array($this, '_processDefListItems_callback_dd'), $list_str);

		return $list_str;
	}

	/**
	 * Callback for <dt> elements in definition lists
	 * @param  array $matches
	 * @return string
	 */
	protected function _processDefListItems_callback_dt($matches) {
		$terms = explode("\n", trim($matches[1]));
		$text = '';
		foreach ($terms as $term) {
			$term = $this->runSpanGamut(trim($term));
			$text .= "\n<dt>" . $term . "</dt>";
		}
		return $text . "\n";
	}

	/**
	 * Callback for <dd> elements in definition lists
	 * @param  array $matches
	 * @return string
	 */
	protected function _processDefListItems_callback_dd($matches) {
		$leading_line	= $matches[1];
		$marker_space	= $matches[2];
		$def			= $matches[3];

		if ($leading_line || preg_match('/\n{2,}/', $def)) {
			// Replace marker with the appropriate whitespace indentation
			$def = str_repeat(' ', strlen($marker_space)) . $def;
			$def = $this->runBlockGamut($this->outdent($def . "\n\n"));
			$def = "\n". $def ."\n";
		}
		else {
			$def = rtrim($def);
			$def = $this->runSpanGamut($this->outdent($def));
		}

		return "\n<dd>" . $def . "</dd>\n";
	}

	/**
	 * Adding the fenced code block syntax to regular Markdown:
	 *
	 * ~~~
	 * Code block
	 * ~~~
	 *
	 * @param  string $text
	 * @return string
	 */
	protected function doFencedCodeBlocks($text) {

		$text = preg_replace_callback('{
				(?:\n|\A)
				# 1: Opening marker
				(
					(?:~{3,}|`{3,}) # 3 or more tildes/backticks.
				)
				[ ]*
				(?:
					\.?([-_:a-zA-Z0-9]+) # 2: standalone class name
				)?
				[ ]*
				(?:
					' . $this->id_class_attr_catch_re . ' # 3: Extra attributes
				)?
				[ ]* \n # Whitespace and newline following marker.

				# 4: Content
				(
					(?>
						(?!\1 [ ]* \n)	# Not a closing marker.
						.*\n+
					)+
				)

				# Closing marker.
				\1 [ ]* (?= \n )
			}xm',
			array($this, '_doFencedCodeBlocks_callback'), $text);

		return $text;
	}

	/**
	 * Callback to process fenced code blocks
	 * @param  array $matches
	 * @return string
	 */
	protected function _doFencedCodeBlocks_callback($matches) {
		$classname =& $matches[2];
		$attrs     =& $matches[3];
		$codeblock = $matches[4];

		if ($this->code_block_content_func) {
			$codeblock = call_user_func($this->code_block_content_func, $codeblock, $classname);
		} else {
			$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);
		}

		$codeblock = preg_replace_callback('/^\n+/',
			array($this, '_doFencedCodeBlocks_newlines'), $codeblock);

		$classes = array();
		if ($classname !== "") {
			if ($classname[0] === '.') {
				$classname = substr($classname, 1);
			}
			$classes[] = $this->code_class_prefix . $classname;
		}
		$attr_str = $this->doExtraAttributes($this->code_attr_on_pre ? "pre" : "code", $attrs, null, $classes);
		$pre_attr_str  = $this->code_attr_on_pre ? $attr_str : '';
		$code_attr_str = $this->code_attr_on_pre ? '' : $attr_str;
		$codeblock  = "<pre$pre_attr_str><code$code_attr_str>$codeblock</code></pre>";

		return "\n\n".$this->hashBlock($codeblock)."\n\n";
	}

	/**
	 * Replace new lines in fenced code blocks
	 * @param  array $matches
	 * @return string
	 */
	protected function _doFencedCodeBlocks_newlines($matches) {
		return str_repeat("<br$this->empty_element_suffix",
			strlen($matches[0]));
	}

	/**
	 * Redefining emphasis markers so that emphasis by underscore does not
	 * work in the middle of a word.
	 * @var array
	 */
	protected $em_relist = array(
		''  => '(?:(?<!\*)\*(?!\*)|(?<![a-zA-Z0-9_])_(?!_))(?![\.,:;]?\s)',
		'*' => '(?<![\s*])\*(?!\*)',
		'_' => '(?<![\s_])_(?![a-zA-Z0-9_])',
	);
	protected $strong_relist = array(
		''   => '(?:(?<!\*)\*\*(?!\*)|(?<![a-zA-Z0-9_])__(?!_))(?![\.,:;]?\s)',
		'**' => '(?<![\s*])\*\*(?!\*)',
		'__' => '(?<![\s_])__(?![a-zA-Z0-9_])',
	);
	protected $em_strong_relist = array(
		''    => '(?:(?<!\*)\*\*\*(?!\*)|(?<![a-zA-Z0-9_])___(?!_))(?![\.,:;]?\s)',
		'***' => '(?<![\s*])\*\*\*(?!\*)',
		'___' => '(?<![\s_])___(?![a-zA-Z0-9_])',
	);

	/**
	 * Parse text into paragraphs
	 * @param  string $text String to process in paragraphs
	 * @param  boolean $wrap_in_p Whether paragraphs should be wrapped in <p> tags
	 * @return string       HTML output
	 */
	protected function formParagraphs($text, $wrap_in_p = true) {
		// Strip leading and trailing lines:
		$text = preg_replace('/\A\n+|\n+\z/', '', $text);

		$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

		// Wrap <p> tags and unhashify HTML blocks
		foreach ($grafs as $key => $value) {
			$value = trim($this->runSpanGamut($value));

			// Check if this should be enclosed in a paragraph.
			// Clean tag hashes & block tag hashes are left alone.
			$is_p = $wrap_in_p && !preg_match('/^B\x1A[0-9]+B|^C\x1A[0-9]+C$/', $value);

			if ($is_p) {
				$value = "<p>$value</p>";
			}
			$grafs[$key] = $value;
		}

		// Join grafs in one text, then unhash HTML tags.
		$text = implode("\n\n", $grafs);

		// Finish by removing any tag hashes still present in $text.
		$text = $this->unhash($text);

		return $text;
	}


	/**
	 * Footnotes - Strips link definitions from text, stores the URLs and
	 * titles in hash references.
	 * @param  string $text
	 * @return string
	 */
	protected function stripFootnotes($text) {
		$less_than_tab = $this->tab_width - 1;

		// Link defs are in the form: [^id]: url "optional title"
		$text = preg_replace_callback('{
			^[ ]{0,' . $less_than_tab . '}\[\^(.+?)\][ ]?:	# note_id = $1
			  [ ]*
			  \n?					# maybe *one* newline
			(						# text = $2 (no blank lines allowed)
				(?:
					.+				# actual text
				|
					\n				# newlines but
					(?!\[.+?\][ ]?:\s)# negative lookahead for footnote or link definition marker.
					(?!\n+[ ]{0,3}\S)# ensure line is not blank and followed
									# by non-indented content
				)*
			)
			}xm',
			array($this, '_stripFootnotes_callback'),
			$text);
		return $text;
	}

	/**
	 * Callback for stripping footnotes
	 * @param  array $matches
	 * @return string
	 */
	protected function _stripFootnotes_callback($matches) {
		$note_id = $this->fn_id_prefix . $matches[1];
		$this->footnotes[$note_id] = $this->outdent($matches[2]);
		return ''; // String that will replace the block
	}

	/**
	 * Replace footnote references in $text [^id] with a special text-token
	 * which will be replaced by the actual footnote marker in appendFootnotes.
	 * @param  string $text
	 * @return string
	 */
	protected function doFootnotes($text) {
		if (!$this->in_anchor) {
			$text = preg_replace('{\[\^(.+?)\]}', "F\x1Afn:\\1\x1A:", $text);
		}
		return $text;
	}

	/**
	 * Append footnote list to text
	 * @param  string $text
	 * @return string
	 */
	protected function appendFootnotes($text) {
		$text = preg_replace_callback('{F\x1Afn:(.*?)\x1A:}',
			array($this, '_appendFootnotes_callback'), $text);

		if ( ! empty( $this->footnotes_ordered ) ) {
			$this->_doFootnotes();
			if ( ! $this->omit_footnotes ) {
				$text .= "\n\n";
				$text .= "<div class=\"footnotes\" role=\"doc-endnotes\">\n";
				$text .= "<hr" . $this->empty_element_suffix . "\n";
				$text .= $this->footnotes_assembled;
				$text .= "</div>";
			}
		}
		return $text;
	}


	/**
	 * Generates the HTML for footnotes.  Called by appendFootnotes, even if
	 * footnotes are not being appended.
	 * @return void
	 */
	protected function _doFootnotes() {
		$attr = array();
		if ($this->fn_backlink_class !== "") {
			$class = $this->fn_backlink_class;
			$class = $this->encodeAttribute($class);
			$attr['class'] = " class=\"$class\"";
		}
		$attr['role'] = " role=\"doc-backlink\"";
		$num = 0;

		$text = "<ol>\n\n";
		while (!empty($this->footnotes_ordered)) {
			$footnote = reset($this->footnotes_ordered);
			$note_id = key($this->footnotes_ordered);
			unset($this->footnotes_ordered[$note_id]);
			$ref_count = $this->footnotes_ref_count[$note_id];
			unset($this->footnotes_ref_count[$note_id]);
			unset($this->footnotes[$note_id]);

			$footnote .= "\n"; // Need to append newline before parsing.
			$footnote = $this->runBlockGamut("$footnote\n");
			$footnote = preg_replace_callback('{F\x1Afn:(.*?)\x1A:}',
				array($this, '_appendFootnotes_callback'), $footnote);

			$num++;
			$note_id = $this->encodeAttribute($note_id);

			// Prepare backlink, multiple backlinks if multiple references
			// Do not create empty backlinks if the html is blank
			$backlink = "";
			if (!empty($this->fn_backlink_html)) {
				for ($ref_num = 1; $ref_num <= $ref_count; ++$ref_num) {
					if (!empty($this->fn_backlink_title)) {
						$attr['title'] = ' title="' . $this->encodeAttribute($this->fn_backlink_title) . '"';
					}
					if (!empty($this->fn_backlink_label)) {
						$attr['label'] = ' aria-label="' . $this->encodeAttribute($this->fn_backlink_label) . '"';
					}
					$parsed_attr = $this->parseFootnotePlaceholders(
						implode('', $attr),
						$num,
						$ref_num
					);
					$backlink_text = $this->parseFootnotePlaceholders(
						$this->fn_backlink_html,
						$num,
						$ref_num
					);
					$ref_count_mark = $ref_num > 1 ? $ref_num : '';
					$backlink .= " <a href=\"#fnref$ref_count_mark:$note_id\"$parsed_attr>$backlink_text</a>";
				}
				$backlink = trim($backlink);
			}

			// Add backlink to last paragraph; create new paragraph if needed.
			if (!empty($backlink)) {
				if (preg_match('{</p>$}', $footnote)) {
					$footnote = substr($footnote, 0, -4) . "&#160;$backlink</p>";
				} else {
					$footnote .= "\n\n<p>$backlink</p>";
				}
			}

			$text .= "<li id=\"fn:$note_id\" role=\"doc-endnote\">\n";
			$text .= $footnote . "\n";
			$text .= "</li>\n\n";
		}
		$text .= "</ol>\n";

		$this->footnotes_assembled = $text;
	}

	/**
	 * Callback for appending footnotes
	 * @param  array $matches
	 * @return string
	 */
	protected function _appendFootnotes_callback($matches) {
		$node_id = $this->fn_id_prefix . $matches[1];

		// Create footnote marker only if it has a corresponding footnote *and*
		// the footnote hasn't been used by another marker.
		if (isset($this->footnotes[$node_id])) {
			$num =& $this->footnotes_numbers[$node_id];
			if (!isset($num)) {
				// Transfer footnote content to the ordered list and give it its
				// number
				$this->footnotes_ordered[$node_id] = $this->footnotes[$node_id];
				$this->footnotes_ref_count[$node_id] = 1;
				$num = $this->footnote_counter++;
				$ref_count_mark = '';
			} else {
				$ref_count_mark = $this->footnotes_ref_count[$node_id] += 1;
			}

			$attr = "";
			if ($this->fn_link_class !== "") {
				$class = $this->fn_link_class;
				$class = $this->encodeAttribute($class);
				$attr .= " class=\"$class\"";
			}
			if ($this->fn_link_title !== "") {
				$title = $this->fn_link_title;
				$title = $this->encodeAttribute($title);
				$attr .= " title=\"$title\"";
			}
			$attr .= " role=\"doc-noteref\"";

			$attr = str_replace("%%", $num, $attr);
			$node_id = $this->encodeAttribute($node_id);

			return
				"<sup id=\"fnref$ref_count_mark:$node_id\">".
				"<a href=\"#fn:$node_id\"$attr>$num</a>".
				"</sup>";
		}

		return "[^" . $matches[1] . "]";
	}

	/**
	 * Build footnote label by evaluating any placeholders.
	 * - ^^  footnote number
	 * - %%  footnote reference number (Nth reference to footnote number)
	 * @param  string $label
	 * @param  int    $footnote_number
	 * @param  int    $reference_number
	 * @return string
	 */
	protected function parseFootnotePlaceholders($label, $footnote_number, $reference_number) {
		return str_replace(
			array('^^', '%%'),
			array($footnote_number, $reference_number),
			$label
		);
	}


	/**
	 * Abbreviations - strips abbreviations from text, stores titles in hash
	 * references.
	 * @param  string $text
	 * @return string
	 */
	protected function stripAbbreviations($text) {
		$less_than_tab = $this->tab_width - 1;

		// Link defs are in the form: [id]*: url "optional title"
		$text = preg_replace_callback('{
			^[ ]{0,' . $less_than_tab . '}\*\[(.+?)\][ ]?:	# abbr_id = $1
			(.*)					# text = $2 (no blank lines allowed)
			}xm',
			array($this, '_stripAbbreviations_callback'),
			$text);
		return $text;
	}

	/**
	 * Callback for stripping abbreviations
	 * @param  array $matches
	 * @return string
	 */
	protected function _stripAbbreviations_callback($matches) {
		$abbr_word = $matches[1];
		$abbr_desc = $matches[2];
		if ($this->abbr_word_re) {
			$this->abbr_word_re .= '|';
		}
		$this->abbr_word_re .= preg_quote($abbr_word);
		$this->abbr_desciptions[$abbr_word] = trim($abbr_desc);
		return ''; // String that will replace the block
	}

	/**
	 * Find defined abbreviations in text and wrap them in <abbr> elements.
	 * @param  string $text
	 * @return string
	 */
	protected function doAbbreviations($text) {
		if ($this->abbr_word_re) {
			// cannot use the /x modifier because abbr_word_re may
			// contain significant spaces:
			$text = preg_replace_callback('{' .
				'(?<![\w\x1A])' .
				'(?:' . $this->abbr_word_re . ')' .
				'(?![\w\x1A])' .
				'}',
				array($this, '_doAbbreviations_callback'), $text);
		}
		return $text;
	}

	/**
	 * Callback for processing abbreviations
	 * @param  array $matches
	 * @return string
	 */
	protected function _doAbbreviations_callback($matches) {
		$abbr = $matches[0];
		if (isset($this->abbr_desciptions[$abbr])) {
			$desc = $this->abbr_desciptions[$abbr];
			if (empty($desc)) {
				return $this->hashPart("<abbr>$abbr</abbr>");
			}
			$desc = $this->encodeAttribute($desc);
			return $this->hashPart("<abbr title=\"$desc\">$abbr</abbr>");
		}
		return $matches[0];
	}
}

// Markdown parser, Copyright Datenstrom, License GPLv2

class YellowMarkdownParser extends MarkdownExtraParser {
    public $yellow;             // access to API
    public $page;               // access to page
    public $idAttributes;       // id attributes
    public $noticeLevel;        // recursive level

    public function __construct($yellow, $page) {
        $this->yellow = $yellow;
        $this->page = $page;
        $this->idAttributes = array();
        $this->noticeLevel = 0;
        $this->url_filter_func = function($url) use ($yellow, $page) {
            return $yellow->lookup->normaliseLocation($url, $page->location);
        };
        $this->span_gamut += array("doStrikethrough" => 55);
        $this->block_gamut += array("doNoticeBlocks" => 65);
        $this->escape_chars .= "~";
        parent::__construct();
    }

    // Handle striketrough
    public function doStrikethrough($text) {
        $parts = preg_split("/(?<![~])(~~)(?![~])/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($parts)>3) {
            $text = "";
            $open = false;
            foreach ($parts as $part) {
                if ($part=="~~") {
                    $text .= $open ? "</del>" : "<del>";
                    $open = !$open;
                } else {
                    $text .= $part;
                }
            }
            if ($open) $text .= "</del>";
        }
        return $text;
    }

    // Handle links
    public function doAutoLinks($text) {
        $text = preg_replace_callback("/<(\w+:[^\'\">\s]+)>/", array($this, "_doAutoLinks_url_callback"), $text);
        $text = preg_replace_callback("/<([\w\+\-\.]+@[\w\-\.]+)>/", array($this, "_doAutoLinks_email_callback"), $text);
        $text = preg_replace_callback("/^\s*\[(\w+)([^\]]*)\]\s*$/", array($this, "_doAutoLinks_shortcutBlock_callback"), $text);
        $text = preg_replace_callback("/\[(\w+)(.*?)\]/", array($this, "_doAutoLinks_shortcutInline_callback"), $text);
        $text = preg_replace_callback("/\[\-\-(.*?)\-\-\]/", array($this, "_doAutoLinks_shortcutComment_callback"), $text);
        $text = preg_replace_callback("/\:([\w\+\-\_]+)\:/", array($this, "_doAutoLinks_shortcutSymbol_callback"), $text);
        $text = preg_replace_callback("/((http|https|ftp):\/\/\S+[^\'\"\,\.\;\:\*\~\s]+)/", array($this, "_doAutoLinks_url_callback"), $text);
        $text = preg_replace_callback("/([\w\+\-\.]+@[\w\-\.]+\.[\w]{2,4})/", array($this, "_doAutoLinks_email_callback"), $text);
        return $text;
    }
    
    // Handle shortcuts, block style
    public function _doAutoLinks_shortcutBlock_callback($matches) {
        $output = $this->page->parseContentShortcut($matches[1], trim($matches[2]), "block");
        return is_null($output) ? $matches[0] : $this->hashBlock($output);
    }
    
    // Handle shortcuts, inline style
    public function _doAutoLinks_shortcutInline_callback($matches) {
        $output = $this->page->parseContentShortcut($matches[1], trim($matches[2]), "inline");
        return is_null($output) ? $matches[0] : $this->hashPart($output);
    }
    
    // Handle shortcuts, comment style
    public function _doAutoLinks_shortcutComment_callback($matches) {
        $output = "<!--".htmlspecialchars($matches[1], ENT_NOQUOTES)."-->";
        return $this->hashBlock($output);
    }
    
    // Handle shortcuts, symbol style
    public function _doAutoLinks_shortcutSymbol_callback($matches) {
        $output = $this->page->parseContentShortcut("", $matches[1], "symbol");
        return is_null($output) ? $matches[0] : $this->hashPart($output);
    }
    
    // Handle fenced code blocks
    public function _doFencedCodeBlocks_callback($matches) {
        $text = $matches[4];
        $name = is_string_empty($matches[2]) ? "" : trim("$matches[2] $matches[3]");
        $output = $this->page->parseContentShortcut($name, $text, "code");
        if (is_null($output)) {
            $attr = $this->doExtraAttributes("pre", ".$matches[2] $matches[3]");
            $output = "<pre$attr><code>".htmlspecialchars($text, ENT_NOQUOTES)."</code></pre>";
        }
        return "\n\n".$this->hashBlock($output)."\n\n";
    }
    
    // Handle headers, text style
    public function _doHeaders_callback_setext($matches) {
        if ($matches[3]=="-" && preg_match('{^- }', $matches[1])) return $matches[0];
        $text = $matches[1];
        $level = $matches[3][0]=="=" ? 1 : 2;
        $attr = $this->doExtraAttributes("h$level", $dummy =& $matches[2]);
        if (is_string_empty($attr) && $level>=2) $attr = $this->getIdAttribute($text);
        $output = "<h$level$attr>".$this->runSpanGamut($text)."</h$level>";
        return "\n".$this->hashBlock($output)."\n\n";
    }
    
    // Handle headers, atx style
    public function _doHeaders_callback_atx($matches) {
        $text = $matches[2];
        $level = strlen($matches[1]);
        $attr = $this->doExtraAttributes("h$level", $dummy =& $matches[3]);
        if (is_string_empty($attr) && $level>=2) $attr = $this->getIdAttribute($text);
        $output = "<h$level$attr>".$this->runSpanGamut($text)."</h$level>";
        return "\n".$this->hashBlock($output)."\n\n";
    }
    
    // Handle inline links
    public function _doAnchors_inline_callback($matches) {
        $url = $matches[3]=="" ? $matches[4] : $matches[3];
        $text = $matches[2];
        $title = isset($matches[7]) ? $matches[7] : "";
        $attr = $this->doExtraAttributes("a", $dummy =& $matches[8]);
        $output = "<a href=\"".$this->encodeURLAttribute($url)."\"";
        if (!is_string_empty($title)) $output .= " title=\"".$this->encodeAttribute($title)."\"";
        $output .= $attr;
        $output .= ">".$this->runSpanGamut($text)."</a>";
        return $this->hashPart($output);
    }
    
    // Handle inline images
    public function _doImages_inline_callback($matches) {
        $src = $matches[3]=="" ? $matches[4] : $matches[3];
        if (!preg_match("/^\w+:/", $src)) {
            $src = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreImageLocation").$src;
        }
        $alt = $matches[2];
        $title = isset($matches[7]) ? $matches[7] : $matches[2];
        $attr = $this->doExtraAttributes("img", $dummy =& $matches[8]);
        $output = "<img src=\"".$this->encodeURLAttribute($src)."\"";
        if (!is_string_empty($alt)) $output .= " alt=\"".$this->encodeAttribute($alt)."\"";
        if (!is_string_empty($title)) $output .= " title=\"".$this->encodeAttribute($title)."\"";
        $output .= $attr;
        $output .= $this->empty_element_suffix;
        return $this->hashPart($output);
    }
    
    // Handle lists, task list
    public function _processListItems_callback($matches) {
        $attr = "";
        $item = $matches[4];
        $leadingLine = $matches[1];
        $tailingLine = $matches[5];
        if ($leadingLine || $tailingLine || preg_match('/\n{2,}/', $item))
        {
            $item = $matches[2].str_repeat(' ', strlen($matches[3])).$item;
            $item = $this->runBlockGamut($this->outdent($item)."\n");
        } else {
            $item = $this->doLists($this->outdent($item));
            $item = $this->formParagraphs($item, false);
            $token = substr($item, 0, 4);
            if ($token=="[ ] " || $token=="[x] ") {
                $attr = " class=\"task-list-item\"";
                $item = ($token=='[ ] ' ? "<input type=\"checkbox\" disabled=\"disabled\" /> " :
                    "<input type=\"checkbox\" disabled=\"disabled\" checked=\"checked\" /> ").substr($item, 4);
            }
        }
        return "<li$attr>".$item."</li>\n";
    }
    
    // Handle blockquotes, CommonMark compatible
    public function doBlockQuotes($text) {
        return preg_replace_callback("/((?>^[ ]*>[ ]?.+\n(.+\n)*)+)/m", array($this, "_doBlockQuotes_callback"), $text);
    }
    
    // Handle notice blocks
    public function doNoticeBlocks($text) {
        return preg_replace_callback("/((?>^[ ]*!(?!\[)[ ]?.+\n(.+\n)*)+)/m", array($this, "_doNoticeBlocks_callback"), $text);
    }
    
    // Handle notice blocks over multiple lines
    public function _doNoticeBlocks_callback($matches) {
        $lines = $matches[1];
        $attr = "";
        $text = preg_replace("/^[ ]*![ ]?/m", "", $lines);
        if (preg_match("/^[ ]*".$this->id_class_attr_catch_re."[ ]*\n([\S\s]*)$/m", $text, $matches)) {
            $attr = $this->doExtraAttributes("div", $dummy =& $matches[1]);
            $text = $matches[2];
        } elseif ($this->noticeLevel==0) {
            $level = strspn(str_replace(array("![", " "), "", $lines), "!");
            $attr = " class=\"notice$level\"";
        }
        if (!is_string_empty($text)) {
            ++$this->noticeLevel;
            $output = "<div$attr>\n".$this->runBlockGamut($text)."\n</div>";
            --$this->noticeLevel;
        } else {
            $output = "<div$attr></div>";
        }
        return "\n".$this->hashBlock($output)."\n\n";
    }
    
    // Return unique id attribute
    public function getIdAttribute($text) {
        $attr = "";
        $text = $this->yellow->lookup->normaliseName($text, true, false, true);
        $text = trim(preg_replace("/-+/", "-", $text), "-");
        if (!isset($this->idAttributes[$text])) {
            $this->idAttributes[$text] = $text;
            $attr = " id=\"$text\"";
        }
        return $attr;
    }
}
