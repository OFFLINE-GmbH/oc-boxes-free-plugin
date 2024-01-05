<?php
/** @var $handler */
/** @var Backend\Widgets\Form $widget */
?>

<?= Form::open(['id' => 'boxes-page-form', 'data-change-monitor' => 1]); ?>

<?= $widget->render(); ?>

<div class="form-buttons">
    <div class="loading-indicator-container">
        <div class="form-buttons__primary">
            <?php if ($this->isFullMode() && $this->hasFeature('revisions')): ?>
                <button
                    type="submit"
                    class="btn btn-default oc-icon-save"
                    data-request="<?= e($handler); ?>"
                    data-request-success="window.document.dispatchEvent(new CustomEvent('boxes.handler.success', { detail: { handler: '<?= e($handler); ?>', response: data } }))"
                    data-hotkey="ctrl+s, cmd+s"
                    data-tooltip-text=" (Ctrl/Cmd+S)"
                    data-disposable
                >
                    <?= e(trans('offline.boxes::lang.save_draft')); ?>
                </button>

                <button
                    id="boxes-btn-publish"
                    type="button"
                    class="btn btn-primary btn-icon oc-icon-rocket"
                    data-request="<?= e($handler); ?>"
                    data-request-success="window.document.dispatchEvent(new CustomEvent('boxes.handler.success', { detail: { handler: 'onPreparePublish', response: data } }))"
                    data-request-data="publish: true"
                    data-hotkey="ctrl+p, cmd+p"
                    data-tooltip-text="<?= e(trans('offline.boxes::lang.publish')); ?> (Ctrl/Cmd+P)"
                    data-disposable
                >
                </button>

            <?php else: ?>

                <button
                    type="submit"
                    class="btn btn-default oc-icon-save"
                    data-request="<?= e($handler); ?>"
                    data-request-success="window.document.dispatchEvent(new CustomEvent('boxes.handler.success', { detail: { handler: '<?= e($handler); ?>', response: data } }))"
                    data-hotkey="ctrl+s, cmd+s"
                    data-tooltip-text=" (Ctrl/Cmd+S)"
                    data-disposable
                >
                    <?= e(trans('offline.boxes::lang.save')); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= Form::close(); ?>
