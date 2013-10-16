<?php list($name, $pages) = $yellow->getSnippetArgs() ?>
<?php if($pages->isPagination()): ?>
<div class="pagination">
<?php if($pages->getLocationPrevious()): ?>
<a class="previous" href="<?php echo $pages->getLocationPrevious() ?>"><?php echo $yellow->text->getHtml("paginationPrevious") ?></a>
<?php endif ?>
<?php if($pages->getLocationNext()): ?>
<a class="next" href="<?php echo $pages->getLocationNext() ?>"><?php echo $yellow->text->getHtml("paginationNext") ?></a>
<?php endif ?>
</div>
<?php endif ?>
