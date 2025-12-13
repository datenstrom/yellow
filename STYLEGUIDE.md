# Datenstrom Yellow style guide

First download a copy of the PHP standards and do not read them.
Burn them, it's a great symbolic gesture. 

Here's how to format code:

* Use consistent indentation with 4 spaces, no tabs.
* Use double quotes for strings, no single quotes, e.g. `"Coffee is good for you"`.
* Classe names should use PascalCase, e.g. `YellowCore`, `YellowFika`, `YellowImage`.
* Method/function names should use camelCase, e.g. `getRequestInformation`, `onLoad`.
* Property/variable names should use camelCase, e.g. `$yellow`, `$statusCode`, `$fileName`.
* HTML/CSS related names should use kebab-case, e.g. `yellow-toolbar`, `company-logo`.
* Opening braces `{` are on the same line, closing braces `}` are placed on their own line.
* One space is used after keywords such as `if`, `switch`, `case`, `for`, `while`, `return`,  
  e.g. `switch ($statusCode)`, `for ($i=0; $i<$length; ++$i)`, `return $statusCode`.
* One space is used around parentheses and compound logical operations,  
  e.g. `if ($name=="example" && ($type=="block" || $type=="inline"))`.
* Start each source file with link to a website, that contains license and contact information,  
  e.g. `// Core extension, https://github.com/annaesvensson/yellow-core`.
* Use a single-line comment to describe classes, methods and properties,  
  e.g. `// Return request information`.
* Don't use code comments inside methods and functions, if unsure refactor code.
* Keep methods relatively small, sweet and focused on one thing, if unsure do less.

Here's how to format repositories:

* Repository names should be singular, e.g. `yellow-core`, `yellow-fika`, `yellow-image`.
* Version numbers should begin with the release number, e.g. `0.9.1`, `0.9.2`, `0.9.3`.
* Copy the PHP file into the repository, use a flat folder structure,  e.g. `fika.php`.
* Copy optional supporting files, file names should use the same prefix everywhere,  
  e.g. `fika.html`, `fika.css`, `fika.js`, `fika-library.min.js`, `fika-logo.png`.
* Edit installation information and file actions in file `extension.ini`.
* Provide user documentation in `English`, other languages are optional.
* Don't put more than one extension into a repository, unless it's a complete website.
* Keep extensions relatively small, sweet and focused on one thing, if unsure do less.

Here's how to format documentation:

* Use Markdown for documentation
* The title should include name and version, e.g. `Core 0.9.1`.
* The description should be one line, e.g. `Core functionality of your website`.
* Images should be in PNG format, e.g. `SCREENSHOT.png`
* Headings should be arranged in the following order:  
  `How to install an extension`  
  `How to...`  
  `Examples` - optional  
  `Settings` - optional  
  `Acknowledgements` - optional  
  `Developer`, `Designer`, `Translator`
* Give multiple examples for users to copy/paste, if unsure add more examples.
* Don't use the words "easy, fast, flexible, user-friendly", anyone can claim that.

A few more tips for developers. It's best to have a look at the code of some extensions in your `system/workers` folder. Make yourself familiar with our coding style as well, for example with file `system/workers/core.php`. Then you can dive into any extension and find a well-known structure in which you can quickly find your way around. The style is not that important, but that we all use the same one.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
