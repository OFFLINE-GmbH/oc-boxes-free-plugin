<backend-component-document
    :header-collapsed="documentHeaderCollapsed"
    :full-screen="documentFullScreen"
    :loading="initializing"
    :processing="processing"
    :error-loading-document="errorLoadingDocument"
    error-loading-document-header="<?= e(trans('offline.boxes::lang.error_loading_box')); ?>"
    container-css-class="fill-container"
>
    <template v-slot:header>
        <backend-component-document-header
            title-property="fileName"
            ref="documentHeader"
            :data="documentData"
            :disabled="processing"
        ></backend-component-document-header>
    </template>

    <template v-slot:toolbar>
        <backend-component-document-toolbar
            :elements="toolbarElements"
            @command="onToolbarCommand"
            :disabled="processing"
        ></backend-component-document-toolbar>
    </template>

    <template v-slot:content>
        <div class="flex-layout-column fill-container">
            <div class="flex-layout-item stretch editor-panel relative">
                <backend-component-monacoeditor
                    ref="editor"
                    container-css-class="fill-container"
                    :model-definitions="codeEditorModelDefinitions"
                    :glyph-margin="true"
                >
                </backend-component-monacoeditor>
            </div>
        </div>
    </template>
</backend-component-document>
