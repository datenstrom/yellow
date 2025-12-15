# Datenstrom Yellow style guide

First download a copy of the PHP standards and burn them, it's a great symbolic gesture. 

## How to format code

* Use consistent indentation with 4 spaces, no tabs.
* Use double quotes for strings, no single quotes, e.g. `"Coffee is good for you"`.
* Classe names should use PascalCase, e.g. `YellowCore`, `YellowEdit`, `YellowFika`.
* Method/function names should use camelCase, e.g. `getRequestInformation`, `onLoad`.
* Property/variable names should use camelCase, e.g. `$yellow`, `$statusCode`, `$fileName`.
* HTML/CSS related names should use kebab-case, e.g. `yellow-toolbar`, `fika-logo`.
* Opening braces `{` are on the same line, closing braces `}` are placed on their own line.
* One space is used after keywords such as `if`, `switch`, `case`, `for`, `while`, `return`,  
  e.g. `switch ($statusCode)`, `for ($i=0; $i<$length; ++$i)`, `return $statusCode`.
* One space is used around parentheses and compound logical operations,  
  e.g. `if ($name=="example" && ($type=="block" || $type=="inline"))`.
* Start each source file with link to a website, that contains license and contact information,  
  e.g. `// Core extension, https://github.com/annaesvensson/yellow-core`.
* Use a single-line comment to describe classes, methods and properties,  
  e.g. `// Return request information`.
* Keep methods relatively small, sweet and focused on one thing, if unsure do less.
* Don't have code comments inside methods and functions, if unsure refactor code.

## How to format extensions

* Extension names should be singular without space, e.g. `Core`, `Edit`, `Fika`.
* Version numbers should begin with the release number, e.g. `0.9.1`, `0.9.2`, `0.9.3`.
* Descriptions should be one line, e.g. `Core functionality of your website`.
* File names should use use kebab-case, the extension name is used as prefix,  
  e.g. `fika.php`, `fika.css`, `fika.js`, `fika-library.min.js`, `fika-stack.svg`.
* Repository names should use kebap-case, e.g. `yellow-core`, `yellow-edit`, `yellow-fika`.
* Repository documentation should be in `English`, other languages are optional.
* Repository screenshots/thumbnails should be in PNG image format, e.g. `SCREENSHOT.png`.
* Repositories should have a flat folder structure, file actions are stored in file `extension.ini`.
* Keep extensions relatively small, sweet and focused on one thing, if unsure do less.
* Don't have more than one extension per repository, unless it's a complete website.

## How to format documentation

* Use Markdown for text formatting.
* Titles should be one line and may contain the version number, e.g. `Core 0.9.1`.
* Descriptions should be one line, e.g. `Core functionality of your website`.
* Headings should be in the following order, applies mostly to extensions:  
  `How to install an extension`  
  `How to...`  
  `Examples`  
  `Settings`  
  `Acknowledgements`  
  `Developer`, `Designer`, `Translator`
* Settings and other key-value pairs should be presented in the following style:  
  `Author` = name of the author  
  `Email` = email of the author  
  `Tag` = page tag(s) for categorisation, comma separated  
* Link targets can be added with HTML, e.g. `<a id="settings-coffee"></a>`.
* Give multiple examples for users to copy/paste, if unsure add more examples.
* Don't use the words "easy, fast, flexible, user-friendly", anyone can claim that.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
