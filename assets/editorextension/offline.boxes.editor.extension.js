import { ExtensionBase } from '/modules/editor/assets/js/editor.extension.base.js';
import { DocumentControllerBoxes } from './offline.boxes.editor.extension.documentcontroller.boxes.js';

class BoxesEditorExtension extends ExtensionBase {
    listDocumentControllerClasses() {
        return [
            DocumentControllerBoxes,
        ];
    }
}

// Register with the editor extension registry
oc.editorExtensions = oc.editorExtensions || {};
oc.editorExtensions['offline.boxes'] = BoxesEditorExtension;

export { BoxesEditorExtension };
