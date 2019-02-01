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
<?php if ($page = $yellow->pages->shared($yellow->page->location, false, $yellow->page->get("header"))) $yellow->page->setPage("header", $page) ?>
<?php if ($page = $yellow->pages->shared($yellow->page->location, false, $yellow->page->get("footer"))) $yellow->page->setPage("footer", $page) ?>
<?php if ($page = $yellow->pages->shared($yellow->page->location, false, $yellow->page->get("sidebar"))) $yellow->page->setPage("sidebar", $page) ?>
<?php if ($yellow->page->get("navigation")=="navigation-sidebar") $yellow->page->setPage("navigation-sidebar", $yellow->page->getParentTop(true)) ?>
<?php $yellow->page->set("pageClass", "page template-".$yellow->page->get("template")) ?>
<?php if (!$yellow->page->isError() && ($yellow->page->isPage("sidebar") || $yellow->page->isPage("navigation-sidebar"))) $yellow->page->set("pageClass", $yellow->page->get("pageClass")." with-sidebar") ?>
<div class="<?php echo $yellow->page->getHtml("pageClass") ?>">
<div class="header" role="banner">
<div class="sitename">
<h1><a href="<?php echo $yellow->page->getBase(true)."/" ?>"><i class="sitename-logo"></i><?php echo $yellow->page->getHtml("sitename") ?></a></h1>
<?php if ($yellow->page->isPage("header")) echo $yellow->page->getPage("header")->getContent() ?>
</div>
<div class="sitename-banner"></div>
<?php $yellow->snippet($yellow->page->get("navigation")) ?>
</div>
