oc.Modules.register('offline.boxes.editor.extension.documentcomponent.base', function() {
    'use strict';

    const EditorDocumentComponentBase = {
        extends: oc.Modules.import('editor.extension.documentcomponent.base'),

        methods: {
            getSaveDocumentData: function getSaveDocumentData(inspectorDocumentData) {
                const documentData = inspectorDocumentData ? inspectorDocumentData : this.documentData;

                const data = $.oc.vueUtils.getCleanObject(documentData);
                const result = {};

                // Copy root properties
                //
                Object.keys(data).forEach((property) => {
                        result[property] = data[property];
                });

                return result;
            },


            onParentTabSelected: function onParentTabSelected() {
                if (this.$refs.editor) {
                    this.$nextTick(() => this.$refs.editor.layout());
                }
            },

            onToolbarCommand: function onToolbarCommand(command, isHotkey) {
                this.handleBasicDocumentCommands(command, isHotkey);
            }
        }
    };

    return EditorDocumentComponentBase;
});
