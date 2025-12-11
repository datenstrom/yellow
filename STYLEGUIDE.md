# Datenstrom Yellow style guide

First download a copy of the PHP standards and do not read them.
Burn them, it's a great symbolic gesture. 

Here's how to format code:

* Use consistent indentation with 4 spaces, no tabs.
* Classe names should use PascalCase, e.g. `YellowCore`, `YellowExampleFeature`.
* Method/function names should use use camelCase, e.g. `getRequestInformation`, `onLoad`.
* Property/variable names should use camelCase, e.g. `$yellow`, `$statusCode`, `$fileName`.
* HTML/CSS classes and ids should use kebab-case, e.g. `yellow-toolbar`, `company-logo`.
* One space is used after keywords such as `if`, `switch`, `case`, `for`, `while`, `return`,  
  e.g. `switch ($statusCode)`, `return $statusCode`.
* One space is used around parentheses and compound logical operations,  
  e.g. `if ($name=="example" && ($type=="block" || $type=="inline"))`.
* Start each source file with link to a website, that contains license and contact information,  
  e.g. `// Core extension, https://github.com/annaesvensson/yellow-core`.
* Use a single-line comment to describe classes, methods and properties,  
  e.g. `// Return request information`.
* Opening braces `{` are on the same line, closing braces `}` are placed on their own line.
* Don't use code comments inside methods and functions, if unsure refactor code.
* Keep methods relatively small, sweet and focused on one thing, if unsure do less.

Here's how to format documentation:

* Use Markdown for documentation
* Add a screenshot when possible
* Include many examples

A few more tips for developers. It's best to have a look at the code of some extensions in your `system/workers` folder. Make yourself familiar with our coding style as well, for example with the file `system/workers/core.php`. Then you can dive into any extension and find a well-known structure in which you can quickly find your way around. The style is not that important, but that we all use the same one.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
