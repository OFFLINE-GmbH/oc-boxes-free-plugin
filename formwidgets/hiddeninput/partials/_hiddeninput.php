<?php if (!$this->previewMode): ?>

    <input
        type="hidden"
        id="<?= $this->getId('input'); ?>"
        name="<?= $name; ?>"
        value="<?= $value ?? ''; ?>"
        class="form-control"
        autocomplete="off" />

<?php endif; ?>
