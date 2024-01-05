<?php
/** @var OFFLINE\Boxes\Models\Page $revision */
/** @var string $previewUrl */
?>
<div class="flex flex-col user-select-none w-full">
    <div class="px-4 flex-0 flex items-center justify-center pt-4 px-4">
        <h2 class="text-oc-body-inverse font-bold text-lg">
            <?= e(trans('offline.boxes::lang.revision')); ?>
        </h2>
    </div>
    <table class="table mt-4">
        <tr>
            <th class="px-4" style="width: 50%">
                <?= e(trans('offline.boxes::lang.name')); ?>
            </th>
            <td class="px-4">
                <?= e($revision->name); ?>
            </td>
        </tr>
        <tr>
            <th class="px-4">
                <?= e(trans('offline.boxes::lang.published_state')); ?>
            </th>
            <td class="px-4">
                <?= e(trans('offline.boxes::lang.revision_states.' . $revision->published_state)); ?>
            </td>
        </tr>
        <tr>
            <th class="px-4">
                <?= e(trans('offline.boxes::lang.published_at')); ?>
            </th>
            <td class="px-4">
                <?= e($revision->published_at); ?>
            </td>
        </tr>
        <tr>
            <th class="px-4">
                <?= e(trans('offline.boxes::lang.published_by')); ?>
            </th>
            <td class="px-4">
                <?= e($revision->published_by_user?->login ?? '-'); ?>
            </td>
        </tr>
        <tr>
            <td class="px-4 py-3">
                <a
                    href="javascript:;"
                    class="oc-icon-life-buoy hover:no-underline"
                    data-request="onRestoreRevision"
                    data-request-data="id: '<?= e($revision->id); ?>'"
                    data-request-confirm="<?= e(trans('offline.boxes::lang.restore_revision_confirm')); ?>"
                    data-request-success="$('.control-popup').data('oc.popup').hide()"
                >
                    <?= e(trans('offline.boxes::lang.restore_revision')); ?>
                </a>
            </td>
            <td class="px-4 py-3">
                <?php if ($previewUrl): ?>
                    <a href="<?= e($previewUrl); ?>" class="oc-icon-eye hover:no-underline" style="margin-right: 2rem"
                       target="_blank">
                        <?= e(trans('offline.boxes::lang.view_revision')); ?>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <div class="flex-0 p-4 mt-4 flex justify-end space-x-4">
        <button data-dismiss="popup" class="btn btn-primary">
            <?= e(trans('backend::lang.relation.close')); ?>
        </button>
    </div>
</div>
