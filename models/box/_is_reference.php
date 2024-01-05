<?php
/** @var OFFLINE\Boxes\Models\Box $model */
if ($model->reference || $model->referenced_by?->count()): ?>
    <div class="callout callout-info no-icon">
        <div class="header d-flex align-items-center" style="padding: 10px 10px 5px">
            <div style="width: 40px">
                <svg style="width: 24px; margin-top: -8px;" viewBox="0 0 24 24" fill="currentColor"
                     xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M16.333 7.53a.543.543 0 0 0-.548 0L9.54 11.173l6.52 3.803 6.518-3.803zm6.788 4.583-6.519 3.803v6.52l6.25-3.646a.543.543 0 0 0 .27-.469zm-7.605 10.322v-6.519l-6.52-3.803v6.208a.543.543 0 0 0 .27.47l6.25 3.645z"
                        style="stroke-width:0.724328"
                    />
                    <path
                        d="M8.585 3.134a.543.543 0 0 0-.547 0L1.792 6.777l6.52 3.803 6.518-3.803Zm-.817 14.905V11.52L1.25 7.717v6.208c0 .193.103.372.27.47l6.25 3.645z"
                        style="opacity:0.599407;stroke-width:0.724328"
                    />
                </svg>
            </div>
            <h3 style="margin-bottom: 8px; font-size: 12px; margin-left: -5px;">
                <?= e(trans('offline.boxes::lang.reference_hint_title')); ?>
            </h3>
        </div>
        <div class="content" style="padding: 0 10px 10px 45px; font-size: 12px; margin-top: -4px;">
            <?php if ($model->reference): ?>
                <p style="padding-bottom: .75em">
                    <?= e(trans('offline.boxes::lang.reference_hint_reference')); ?>
                    <a href="?boxes_page=<?= e($model->reference->holder->id); ?>&boxes_box=<?= e($model->reference->id); ?>">
                        <?= e($model->reference->holder->name); ?>
                        &rarr; <?= e($model->reference->getPartial()->config->name); ?>
                    </a>
                </p>
            <?php endif; ?>
            <p>
                <?= e(trans('offline.boxes::lang.reference_hint_text')); ?>
            </p>

            <?php if ($model->referenced_by->count()): ?>
                <p style="padding-top: .75em">
                    <?= e(trans('offline.boxes::lang.reference_hint_referenced_by')); ?>
                </p>
                <ul style="padding-top: .75em; list-style: disc; padding-left: .75em;">
                    <?php foreach ($model->referenced_by as $box): ?>
                        <li>
                            <a href="?boxes_page=<?= e($box->holder->id); ?>&boxes_box=<?= e($box->references_box_id); ?>">
                                <?= e($box->holder->name); ?>
                                &rarr; <?= e($box->reference->getPartial()->config->name); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
