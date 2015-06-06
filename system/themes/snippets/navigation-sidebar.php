<?php $page = $yellow->page->getParentTop(false) ?>
<?php $pages = $page ? $page->getChildren(!$page->isVisible()): $yellow->pages->clean() ?>
<?php $yellow->page->setLastModified($pages->getModified()) ?>
<div class="navigation-sidebar">
<ul>
<?php foreach($pages as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation() ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
</ul>
</div>
