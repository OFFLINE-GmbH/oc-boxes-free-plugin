<?php
/** @var OFFLINE\Boxes\Models\Page $record */
$class = match ($record->published_state) {
    OFFLINE\Boxes\Classes\PublishedState::DRAFT => 'text-info',
    OFFLINE\Boxes\Classes\PublishedState::PUBLISHED => 'text-success',
    default => 'text-muted',
};
?>
<span
    class="oc-icon-circle <?= $class; ?> whitespace-nowrap"
    title="<?= e(trans('offline.boxes::lang.revision_states.' . $record->published_state)); ?>"
>
    <?= e($record->version); ?>
</span>

