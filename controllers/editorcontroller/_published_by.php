<?php
/** @var OFFLINE\Boxes\Models\Page $record */
?>
<?= e($record->published_by_user?->login ?: $record->updated_by_user?->login ?: '-'); ?>

