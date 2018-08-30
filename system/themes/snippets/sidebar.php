<?php if($yellow->page->isPage("sidebar")): ?>
<?php if($yellow->page->get("navigation")=="navigation-sidebar" && $yellow->page->getPage("sidebar")==$yellow->page): ?>
<?php $page = $yellow->page->getParentTop(true) ?>
<?php $pages = $page->getChildren(!$page->isVisible()) ?>
<?php $yellow->page->setLastModified($pages->getModified()) ?>
<div class="sidebar" role="complementary">
<div class="navigation-sidebar">
<p><?php echo $page->getHtml("titleNavigation") ?></p>
<ul>
<?php foreach($pages as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation(true) ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
</ul>
</div>
</div>
<?php else: ?>
<?php $page = $yellow->page->getPage("sidebar") ?>
<?php $page->setPage("main", $yellow->page) ?>
<?php $yellow->page->setLastModified($page->getModified()) ?>
<div class="sidebar" role="complementary">
<?php echo $page->getContent() ?>
</div>
<?php endif ?>
<?php endif ?>
