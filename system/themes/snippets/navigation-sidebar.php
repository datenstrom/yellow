<?php list($name, $pages, $sidebar) = $yellow->getSnippetArgs() ?>
<?php if(!$pages) $pages = $yellow->pages->top() ?>
<?php $yellow->page->setLastModified($pages->getModified()) ?>
<?php if(!$sidebar): ?>
<div class="navigation">
<ul>
<?php foreach($pages as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation(true) ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
</ul>
</div>
<div class="navigation-banner"></div>
<?php else: ?>
<div class="navigation-sidebar">
<ul>
<?php foreach($pages as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation(true) ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
</ul>
</div>
<?php endif ?>