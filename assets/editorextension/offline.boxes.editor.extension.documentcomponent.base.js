import { DocumentComponentBase } from '/modules/editor/assets/js/editor.extension.documentcomponent.base.js';

export const BoxesDocumentComponentBase = {
    extends: DocumentComponentBase,

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
