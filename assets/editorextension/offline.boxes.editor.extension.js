oc.Modules.register('editor.extension.offline.boxes.main', function() {
    'use strict';

    const ExtensionBase = oc.Modules.import('editor.extension.base');

    class BoxesEditorExtension extends ExtensionBase {
        listDocumentControllerClasses() {
            return [
                oc.Modules.import('editor.extension.offline.boxes.documentcontroller.boxes'),
            ];
        }
    }

    return BoxesEditorExtension;
});
