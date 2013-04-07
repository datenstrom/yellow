<?php $yellow->snippet("header") ?>
<?php $yellow->snippet("navigation") ?>
<div class="content">
<h1><?php echo $yellow->page->getTitle() ?></h1>
<?php echo $yellow->page->getContent() ?>
</div>
<?php $yellow->snippet("footer") ?>
