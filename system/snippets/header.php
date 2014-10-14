<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $yellow->page->getHtml("language") ?>">
<head>
<meta charset="utf-8" />
<meta name="description" content="<?php echo $yellow->page->getHtml("description") ?>" />
<meta name="keywords" content="<?php echo $yellow->page->getHtml("keywords") ?>" />
<meta name="author" content="<?php echo $yellow->page->getHtml("author") ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo $yellow->page->getHtml("titleHeader") ?></title>
<link rel="shortcut icon" href="<?php echo $yellow->config->get("serverBase").$yellow->config->get("imageLocation")."icon.png" ?>" />
<link rel="stylesheet" type="text/css" media="all" href="<?php echo $yellow->config->get("serverBase").$yellow->config->get("themeLocation").$yellow->page->get("theme").".css" ?>" />
<?php echo $yellow->page->getHeaderExtra() ?>
</head>
<body>
<div class="page">
