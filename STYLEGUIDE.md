# Datenstrom Yellow style guide

First download a copy of the PHP standards and do not read them.
Burn them, it's a great symbolic gesture. 

Here's what you should check to apply our coding standards: 

* **Classes:** Use **PascalCase** (e.g., `YellowExample`).
* **Properties/Variables:** Use **camelCase** (e.g., `$yellow`, `$statusCode`, `$outputLines`).
* **Methods/Functions:** Use **camelCase** (e.g., `onLoad`, `onParseContentElement`).
* **Brace Placement:** The opening brace (`{`) for classes, methods, and control structures is on the same line. Closing braces (`}`) are placed on their own line.
* **Indentation:** **4 spaces** are used for each indentation.
* **Spacing:**
    * One space is used after control structures (`switch`, `if`, `while`, etc.) and before the opening parenthesis (e.g., `if ($name=="example" && ($type=="block" || $type=="inline"))`).
    * Operators are typically surrounded by spaces (e.g., `$statusCode = $returnStatus != 0 ? 500 : 200;`).
* **Comments:**
    * Write the name and repository URL as single-line comment after the opening `<?php` tag in your `extension.php` file (e.g. `// Example extension, https://github.com/username/yellow-example`).
    * Use single-line C-style comments (`//`) to describe the class purpose, extension source, property roles, and method handlers (e.g., `// access to API`, `// Handle initialisation`).
    * **In-line Comments:** Comments are placed above the code block they describe or next to property declarations. Don't use code comments inside methods and functions.
* Use the **long opening tag `<?php`** in your extension files, avoid the final closing tag `?>`. 
* More to come...

It's best to have a look at the code of some extensions in your `system/workers` folder. Make yourself familiar with our coding and documentation standards. Then you can dive into any extension and find a well-known structure in which you can quickly find your way around. Make sure you have completed the [self-review checklist](https://github.com/annaesvensson/yellow-publish/blob/main/self-review-checklist.md) before announcing a new extension.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
