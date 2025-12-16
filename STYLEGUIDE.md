# Datenstrom Yellow style guide

You should use the following guidelines for your code:

* Use consistent indentation with 4 spaces, no tabs.
* Use double quotes for strings, not single quotes, e.g. `"Coffee is good for you"`.
* Classe names should use PascalCase, e.g. `YellowCore`, `YellowEdit`, `YellowFika`.
* Method/function names should use camelCase, e.g. `getRequestInformation`, `onLoad`.
* Property/variable names should use camelCase, e.g. `$yellow`, `$statusCode`, `$fileName`.
* HTML/CSS related names should use kebab-case, e.g. `yellow`, `edit-toolbar`, `fika-logo`.
* Opening braces `{` are on the same line, closing braces `}` are placed on their own line.
* One space is used after keywords such as `if`, `switch`, `case`, `for`, `while`, `return`,  
  e.g. `switch ($statusCode)`, `for ($i=0; $i<$length; ++$i)`, `return $statusCode`.
* One space is used around parentheses and compound logical operations,  
  e.g. `if ($name=="example" && ($type=="block" || $type=="inline"))`.
* Start each source file with link to a website, that contains license and contact information,  
  e.g. `// Core extension, https://github.com/annaesvensson/yellow-core`.
* Use a single-line comment to describe classes, methods and properties,  
  e.g. `// Return request information`.
* Download a copy of the PHP standards and burn them, it's a great symbolic gesture.
* Keep methods relatively small, sweet and focused on one thing, if unsure do less.
* Don't have code comments inside methods and functions.

You should use the following guidelines for your documentation:

* Use Markdown for text formatting, no tabs.
* Titles may contain a version number, e.g. `API for developers`, `Core 0.9.1`.
* Descriptions should be one line max, e.g. `Core functionality of your website`.
* Help files should use a `[toc]` shortcut to make a table of contents.
* Readme files should start with a section to explain how to install an extension.
* Readme files should use the following order for headings, all are optional:  
  `How to...`  
  `Examples`  
  `Settings`  
  `Acknowledgements`  
  `Developer`, `Designer`, `Translator`
* Settings should be described in the following style:  
  `CoreServerUrl` = URL of the website  
  `CoreTimezone` = timezone of the website  
  `CoreDebugMode` = enable debug mode, 0 to 3  
* Files should be described in the following style:  
  `system/extensions/yellow-system.ini` = file with system settings  
  `system/extensions/yellow-language.ini` = file with language settings  
  `system/extensions/yellow-user.ini` = file with user settings  
* Use HTML to add a language switcher suitable for GitHub and Codeberg,  
  e.g. `<p align="right"><a href="README.md">English</a> &nbsp; <a href="README-sv.md">Svenska</a></p>`.
* Use HTML to add a screenshot suitable for GitHub and Codeberg,  
  e.g. `<p align="center"><img src="SCREENSHOT.png" alt="Screenshot"></p>`.
* Use HTML at the beginning of a line to add additional link targets,  
  e.g. `<a id="settings-page"></a>`, `<a id="settings-files"></a>`.
* Give multiple examples for users to copy/paste, if unsure add more examples.
* Review the entire documentation from the perspective of the user.
* Don't use the words "easy, flexible, user-friendly".
  
You should use the following guidelines for your extension:

* Extension names should be a singular word, no spaces, e.g. `Core`, `Edit`, `Fika`.
* Version numbers should begin with the release number, e.g. `0.9.1`, `0.9.2`, `0.9.3`.
* Descriptions should be one line max, e.g. `Core functionality of your website`.
* File names should use kebab-case, the extension name is used as prefix,  
  e.g. `fika.php`, `fika.css`, `fika.js`, `fika-library.min.js`, `fika-stack.svg`.
* Repository names should use kebap-case, e.g. `yellow-core`, `yellow-edit`, `yellow-fika`.
* Repository documentation files should be in Markdown format, e.g. `README.md` 
* Repository screenshots/previews should be in PNG image format, e.g. `SCREENSHOT.png`.
* Repositories should have a flat folder structure, file actions are stored in file `extension.ini`.
* Check the spelling, British English is the reference language, other languages are optional.
* Keep extensions relatively small, sweet and focused on one thing, if unsure do less.
* Don't have more than one extension per repository.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
