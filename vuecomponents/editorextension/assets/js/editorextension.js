Vue.component('offline-boxes-editor-extension', {
    extends: oc.Modules.import('offline.boxes.editor.extension.documentcomponent.base'),
    template: '#offline_boxes_vuecomponents_editorextension',
    data: function () {
        const EditorModelDefinition = oc.Modules.import('backend.vuecomponents.monacoeditor.modeldefinition');
        const defMarkup = new EditorModelDefinition(
            'twig',
            this.trans('cms::lang.page.editor_markup'),
            {},
            'markup',
            'backend-icon-background monaco-document html'
        );

        const defConfig = new EditorModelDefinition(
            'yaml',
            'YAML',
            {},
            'config',
            'backend-icon-background monaco-document html'
        );

        return {
            documentData: {},
            documentTitleProperty: 'name',
            codeEditorModelDefinitions: [defMarkup, defConfig],
            defMarkup: defMarkup,
            defConfig: defConfig
        };
    },
    computed: {
        toolbarElements: function computeToolbarElements() {
            return [
                {
                    type: 'button',
                    icon: 'octo-icon-save',
                    label: this.trans('backend::lang.form.save'),
                    hotkey: 'ctrl+s, cmd+s',
                    tooltip: this.trans('backend::lang.form.save'),
                    tooltipHotkey: '⌃S, ⌘S',
                    command: 'save'
                },
                {
                    type: 'separator'
                },
                {
                    type: 'button',
                    icon: 'octo-icon-delete',
                    disabled: this.isNewDocument,
                    command: 'delete',
                    hotkey: 'shift+option+d',
                    tooltip: this.trans('backend::lang.form.delete'),
                    tooltipHotkey: '⇧⌥D'
                },
                {
                    type: 'button',
                    icon: this.documentHeaderCollapsed ? 'octo-icon-angle-down' : 'octo-icon-angle-up',
                    command: 'document:toggleToolbar',
                    fixedRight: true,
                    tooltip: this.trans('editor::lang.common.toggle_document_header')
                }
            ];
        }
    },
    methods: {
        getRootProperties: function () {
            return ['fileName', 'markup', 'config'];
        },

        getMainUiDocumentProperties: function getMainUiDocumentProperties() {
            return ['fileName', 'markup', 'config'];
        },

        documentSaved: function documentSaved(data) {
            this.documentData.name = data.metadata.name

            this.lastSavedDocumentData = $.oc.vueUtils.getCleanObject(data.document);
        },

        documentLoaded: function documentLoaded(data) {
            if (this.$refs.editor) {
                this.$refs.editor.updateValue(this.defMarkup, this.documentData.markup);
                this.$refs.editor.updateValue(this.defConfig, this.documentData.config);
            }
        },

        documentCreatedOrLoaded: function documentCreatedOrLoaded() {
            this.defMarkup.setHolderObject(this.documentData);
            this.defConfig.setHolderObject(this.documentData);
        }
    },
});
