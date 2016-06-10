<?php list($name, $pages, $level) = $yellow->getSnippetArgs() ?>
<?php if(!$pages) $pages = $yellow->pages->top() ?>
<?php $yellow->page->setLastModified($pages->getModified()) ?>
<?php if(!$level): ?>
<div class="navigation-tree">
<?php endif ?>
<ul>
<?php foreach($pages as $page): ?>
<?php $children = $page->getChildren() ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation(true) ?>"><?php echo $page->getHtml("titleNavigation") ?></a><?php if($children->count()) { echo "\n"; $yellow->snippet($name, $children, $level+1); } ?></li>
<?php endforeach ?>
</ul>
<?php if(!$level): ?>
</div>
<div class="navigation-banner"></div>
<?php endif ?>
