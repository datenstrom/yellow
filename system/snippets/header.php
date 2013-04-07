<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $yellow->page->getHtml("language") ?>">
<head>
<meta charset="utf-8" />
<meta name="description" content="<?php echo $yellow->page->getHtml("description") ?>" />
<meta name="keywords" content="<?php echo $yellow->page->getHtml("keywords") ?>" />
<meta name="author" content="<?php echo $yellow->page->getHtml("author") ?>" />
<title><?php echo $yellow->config->getHtml("sitename")." - ".$yellow->page->getHtml("title") ?></title>
<link rel="shortcut icon" href="<?php echo $yellow->config->get("baseLocation").$yellow->config->get("imagesLocation")."default_icon.png" ?>" />
<link href="<?php echo $yellow->config->get("baseLocation").$yellow->config->get("stylesLocation")."default_style.css" ?>" rel="styleSheet" media="all" type="text/css" />
<link href="<?php echo $yellow->config->get("baseLocation").$yellow->config->get("stylesLocation")."print_style.css" ?>" rel="styleSheet" media="print" type="text/css" />
<?php echo $yellow->getHeaderExtra() ?>
</head>
<body>
<div class="header"><a href="<?php echo $yellow->config->get("baseLocation")."/" ?>"><?php echo $yellow->config->getHtml("sitename") ?></a></div>
