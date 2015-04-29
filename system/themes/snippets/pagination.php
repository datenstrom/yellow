<?php list($name, $pages) = $yellow->getSnippetArgs() ?>
<?php if($pages->isPagination()): ?>
<div class="pagination">
<?php if($pages->getPaginationPrevious()): ?>
<a class="previous" href="<?php echo $pages->getPaginationPrevious() ?>"><?php echo $yellow->text->getHtml("paginationPrevious") ?></a>
<?php endif ?>
<?php if($pages->getPaginationNext()): ?>
<a class="next" href="<?php echo $pages->getPaginationNext() ?>"><?php echo $yellow->text->getHtml("paginationNext") ?></a>
<?php endif ?>
</div>
<?php endif ?>
