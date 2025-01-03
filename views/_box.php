<?php
/** @var OFFLINE\Boxes\Classes\Partial\RenderContext $context */

/** @var string $output */

use OFFLINE\Boxes\Classes\Partial\PartialOptions;

$box = $context->box;

$renderScaffolding = $context->isEditor || $context->renderScaffolding;

if ($renderScaffolding):
    ?>
<div
    class="
        oc-box
        oc-box--<?= e($box->partial); ?>
        <?= $context->loop['first'] ? 'oc-box--first' : ''; ?>
        <?= $context->loop['last'] ? 'oc-box--last' : ''; ?>
        <?= !$box->is_enabled ? 'oc-box--disabled' : ''; ?>
        <?= e($box->spacingClasses); ?>
        <?= e(count($context->scaffoldingClasses) ? implode(' ', $context->scaffoldingClasses) : ''); ?>
    "
    data-box="<?= e((string)$box->id); ?>"
    <?php if ($context->referenceFor): ?>
    data-box-reference="<?= e($context->referenceFor->id); ?>"
    <?php endif; ?>
    <?php
        if ($context->isEditor): ?>
        data-box-name="<?= e($context->partial->config->name); ?>"
        data-box-partial="<?= e($context->partial->config->handle); ?>"
        <?php if ($context->partial->config->children): ?>
            data-box-supports-children
            data-box-partial-contexts="<?= e(implode(OFFLINE\Boxes\Classes\Partial\PartialConfig::PARTIAL_CONTEXT_SEPARATOR, $context->partial->config->children)); ?>"
        <?php endif; ?>
        <?php
            if (is_array($box->locked) && count($box->locked) > 0):
                if (!array_diff(PartialOptions::LOCKABLE, $box->locked)): ?>
                data-box-fully-locked
            <?php endif; ?>
            data-box-locked="<?= e(implode(',', $box->locked)); ?>"
        <?php endif; ?>
    <?php endif; ?>
>
<?php endif; ?>

<?= $output; ?>

    <?php
    if ($context->isEditor && $context->partial->config->children): ?>
        <div class="oc-boxes-wrapper">
            <a
                href="#"
                data-parent-id="<?= e((string)$box->id); ?>"
                class="oc-boxes-add-box oc-boxes-add-box--child"
            >
                <?= e(trans('offline.boxes::lang.add_box_child', ['parent' => $box->getPartial()->config->name])); ?>
            </a>
        </div>
    <?php
    endif;

if ($renderScaffolding): ?>
</div>
<?php endif; ?>
