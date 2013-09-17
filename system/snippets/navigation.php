<div class="navigation">
<ul>
<?php foreach($yellow->pages->top() as $page): ?>
<li><a<?php echo $page->isActive() ? " class=\"active\"" : "" ?> href="<?php echo $page->getLocation() ?>"><?php echo $page->getHtml("titleNavigation") ?></a></li>
<?php endforeach ?>
</ul>
</div>
