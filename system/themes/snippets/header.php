<!DOCTYPE html><html lang="<?php echo $yellow->page->getHtml("language") ?>">
<head>
<title><?php echo $yellow->page->getHtml("titleHeader") ?></title>
<meta charset="utf-8" />
<meta name="description" content="<?php echo $yellow->page->getHtml("description") ?>" />
<meta name="keywords" content="<?php echo $yellow->page->getHtml("keywords") ?>" />
<meta name="author" content="<?php echo $yellow->page->getHtml("author") ?>" />
<meta name="generator" content="Datenstrom Yellow" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php echo $yellow->page->getExtra("header") ?>
</head>
<body>
<?php $yellow->page->set("pageClass", "page") ?>
<?php $yellow->page->set("pageClass", $yellow->page->get("pageClass")." template-".$yellow->page->get("template")) ?>
<?php if($yellow->page->get("navigation")=="navigation-sidebar") $yellow->page->setPage("sidebar", $yellow->page); ?>
<?php if($page = $yellow->pages->find($yellow->lookup->getDirectoryLocation($yellow->page->location).$yellow->page->get("sidebar"))) $yellow->page->setPage("sidebar", $page) ?>
<?php if($yellow->page->isPage("sidebar")) $yellow->page->set("pageClass", $yellow->page->get("pageClass")." with-sidebar") ?>
<div class="<?php echo $yellow->page->getHtml("pageClass") ?>">
<div class="header" role="banner">
<div class="sitename">
<h1><a href="<?php echo $yellow->page->base."/" ?>"><i class="sitename-logo"></i><?php echo $yellow->page->getHtml("sitename") ?></a></h1>
<?php if($yellow->page->isExisting("tagline")): ?><h2><?php echo $yellow->page->getHtml("tagline") ?></h2><?php endif ?>
</div>
<div class="sitename-banner"></div>
<?php $yellow->snippet($yellow->page->get("navigation")) ?>
</div>
