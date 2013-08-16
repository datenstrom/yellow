<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $yellow->page->getHtml("language") ?>">
<head>
<meta charset="utf-8" />
<meta name="description" content="<?php echo $yellow->page->getHtml("description") ?>" />
<meta name="keywords" content="<?php echo $yellow->page->getHtml("keywords") ?>" />
<meta name="author" content="<?php echo $yellow->page->getHtml("author") ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $yellow->page->getHtml("sitename")." - ".$yellow->page->getHtml("title") ?></title>
<link rel="shortcut icon" href="<?php echo $yellow->config->get("serverBase").$yellow->config->get("imageLocation")."default_icon.png" ?>" />
<link rel="stylesheet" type="text/css" media="all" href="<?php echo $yellow->config->get("serverBase").$yellow->config->get("styleLocation").$yellow->page->get("style").".css" ?>" />
<?php echo $yellow->getHeaderExtra() ?>
</head>
<body>
<div class="page">
<div class="header"><h1><a href="<?php echo $yellow->config->get("serverBase")."/" ?>"><?php echo $yellow->page->getHtml("sitename") ?></a></h1></div>
<div class="header-banner"></div>
