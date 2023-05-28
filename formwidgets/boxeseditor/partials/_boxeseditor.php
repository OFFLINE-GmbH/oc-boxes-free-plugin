<?php if ($this->previewMode): ?>

    <div class="form-control">
        Preview not supported
    </div>

<?php else: ?>

    <div
        class="oc-boxes-editor-root"
        id="<?= $this->getId(); ?>"
    ></div>

    <script id="oc-boxes-state-<?= $this->getId(); ?>">
        <?php /** @var array $state */ ?>
        window.__OC_BOXES_STATE__ = window.__OC_BOXES_STATE__ || {}
        window.__OC_BOXES_STATE__['<?= $this->getId(); ?>'] = <?= json_encode($state); ?>

        function DOMReady (callback) {
            return document.readyState === 'interactive' || document.readyState === 'complete' ? callback() : document.addEventListener('DOMContentLoaded', callback)
        }

        DOMReady(function () {
            let retryCount = 0;

            const init = () => {
                retryCount++

                if (retryCount > 10) {
                    return
                }

                if (!window.__OC_BOXES__) {
                    setTimeout(init, 200)
                    return
                }

                // Dispatch the boxes:init event. This will be picked up by the BoxesEditor component.
                window.dispatchEvent(new CustomEvent('boxes:init', {
                    detail: document.querySelector('#<?= $this->getId(); ?>')
                }))
            }

            init()
        })
    </script>
<?php endif; ?>
