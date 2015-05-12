<?php if($yellow->page->isExisting("sidebar")): ?>
<div class="content sidebar">
<?php $location = $yellow->lookup->getDirectoryLocation($yellow->page->location).$yellow->page->get("sidebar"); ?>
<?php if($page = $yellow->pages->find($location)): ?>
<?php $yellow->page->setLastModified($page->getModified()) ?>
<?php echo $page->getContent() ?>
<?php else: ?>
<?php $page = $yellow->page->getParentTop(false) ?>
<?php $pages = $page ? $page->getChildren(): $yellow->pages->clean() ?>
<?php $yellow->page->setLastModified($pages->getModified()) ?>
<div class="navigationside">
<ul>
<?php foreach($pages as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation() ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
</ul>
</div>
<?php endif ?>
</div>
<?php endif ?>
