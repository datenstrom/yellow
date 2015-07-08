<?php if($yellow->page->isExisting("sidebar")): ?>
<div class="content sidebar">
<?php $location = $yellow->lookup->getDirectoryLocation($yellow->page->location).$yellow->page->get("sidebar"); ?>
<?php if($page = $yellow->pages->find($location)): ?>
<?php $yellow->page->setPage("main", $page) ?>
<?php $yellow->page->setLastModified($page->getModified()) ?>
<?php echo $page->getContent() ?>
<?php else: ?>
<?php $yellow->snippet("navigation-sidebar") ?>
<?php endif ?>
</div>
<?php endif ?>
