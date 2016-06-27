<?php $pages = $yellow->pages->top() ?>
<?php $pagesMultiLanguage = $yellow->pages->multi($yellow->page->location, false, true) ?>
<?php $yellow->page->setLastModified($pages->getModified()) ?>
<?php $yellow->page->setLastModified($pagesMultiLanguage->getModified()) ?>
<div class="navigation">
<ul>
<?php foreach($pages as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation(true) ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
<?php foreach($pagesMultiLanguage as $page): ?>
<li><a href="<?php echo $page->getLocation(true).$yellow->toolbox->getLocationArgs() ?>"><?php echo $yellow->text->getTextHtml("languageDescription", $page->get("language")) ?></a></li>
<?php endforeach ?>
</ul>
</div>
<div class="navigation-banner"></div>
