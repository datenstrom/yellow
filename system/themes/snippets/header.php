<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $yellow->page->getHtml("language") ?>">
<head>
<meta charset="utf-8" />
<meta name="description" content="<?php echo $yellow->page->getHtml("description") ?>" />
<meta name="keywords" content="<?php echo $yellow->page->getHtml("keywords") ?>" />
<meta name="author" content="<?php echo $yellow->page->getHtml("author") ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php if($yellow->page->isExisting("robots")): ?>
<meta name="robots" content="<?php echo $yellow->page->getHtml("robots") ?>" />
<?php endif ?>
<title><?php echo $yellow->page->getHtml("titleHeader") ?></title>
<?php echo $yellow->page->getExtra("header") ?>
</head>
<body>
<?php $yellow->page->set("pageClass", "page") ?>
<?php $yellow->page->set("pageClass", $yellow->page->get("pageClass")." ".$yellow->page->get("template")) ?>
<?php if($yellow->page->get("navigation")=="navigation-sidebar") $yellow->page->setPage("sidebar", $yellow->page); ?>
<?php if($page = $yellow->pages->find($yellow->lookup->getDirectoryLocation($yellow->page->location).$yellow->page->get("sidebar"))) $yellow->page->setPage("sidebar", $page) ?>
<?php if($yellow->page->isPage("sidebar")) $yellow->page->set("pageClass", $yellow->page->get("pageClass")." with-sidebar") ?>
<div class="<?php echo $yellow->page->getHtml("pageClass") ?>">
<div class="header">
<div class="sitename">
<h1><a href="<?php echo $yellow->page->base."/" ?>"><i class="sitename-logo"></i><?php echo $yellow->page->getHtml("sitename") ?></a></h1>
<?php if($yellow->page->isExisting("tagline")): ?><h2><?php echo $yellow->page->getHtml("tagline") ?></h2><?php endif ?>
</div>
<div class="sitename-banner"></div>
<?php $yellow->snippet($yellow->page->get("navigation")) ?>
</div>
