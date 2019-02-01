<?php if ($yellow->page->isPage("sidebar")): ?>
<div class="sidebar" role="complementary">
<?php $page = $yellow->page->getPage("sidebar") ?>
<?php $page->setPage("main", $yellow->page) ?>
<?php echo $page->getContent() ?>
</div>
<?php elseif ($yellow->page->isPage("navigation-sidebar")): ?>
<div class="sidebar" role="complementary">
<div class="navigation-sidebar">
<?php $page = $yellow->page->getPage("navigation-sidebar") ?>
<?php $pages = $page->getChildren(!$page->isVisible()) ?>
<?php $yellow->page->setLastModified($pages->getModified()) ?>
<p><?php echo $page->getHtml("titleNavigation") ?></p>
<ul>
<?php foreach ($pages as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation(true) ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
</ul>
</div>
</div>
<?php endif ?>
