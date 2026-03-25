oc.Modules.register('editor.extension.offline.boxes.documentcontroller.boxes', function() {
    'use strict';

    const DocumentControllerBase = oc.Modules.import('editor.extension.documentcontroller.base');
    const EditorCommand = oc.Modules.import('editor.command');

    class DocumentControllerBoxes extends DocumentControllerBase {
        get documentType() {
            return 'boxes';
        }

        initListeners() {
            // Proxy the create-box-in-section command to the create-document@boxes command with a custom payload.
            this.on(this.editorNamespace + ':create-box-in-section', function (command) {
                const params = command.parameter

                const [section, path] = params.split('||')

                this.emit(
                    new EditorCommand(this.editorNamespace + ':create-document@boxes'),
                    { section: section, path: path }
                )
            });
        }

        /**
         * Inject the section into the document config, if available.
         * @param command
         * @param payload
         * @param documentData
         */
        onBeforeDocumentCreated(command, payload, documentData) {
            if (payload.section) {
                documentData.document.config = documentData.document.config.replace('section: Common', 'section: ' + payload.section)
            }
            if (payload.path) {
                documentData.document.fileName  = payload.path + '/' + documentData.document.fileName
            }
            documentData.document.fileName = documentData.document.fileName.replace('{time}', this.now)
            documentData.document.config = documentData.document.config.replace('{time}', this.now)
        }

        get now () {
           const time = new Date();

           return String(time.getHours()).padStart(2, '0') + String(time.getMinutes()).padStart(2, '0') + String(time.getSeconds()).padStart(2, '0');
        }

        get vueEditorComponentName() {
            return 'offline-boxes-editor-extension';
        }
    }

    return DocumentControllerBoxes;
});
