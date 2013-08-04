<?php $yellow->snippet("header") ?>
<?php $yellow->snippet("navigation") ?>
<?php $yellow->snippet("content", $yellow->page->getTitle(), $yellow->page->getContent()) ?>
<?php $yellow->snippet("footer") ?>