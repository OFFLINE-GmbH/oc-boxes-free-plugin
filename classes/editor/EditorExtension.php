<?php

namespace OFFLINE\Boxes\Classes\Editor;

use Backend\VueComponents\DropdownMenu\ItemDefinition;
use Backend\VueComponents\TreeView\NodeDefinition;
use Backend\VueComponents\TreeView\SectionList;
use Editor\Classes\ExtensionBase;
use Editor\Classes\NewDocumentDescription;
use Exception;
use Lang;
use October\Rain\Parse\Yaml;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Boxes\Classes\Exceptions\PartialNotFoundException;
use OFFLINE\Boxes\Classes\Partial\PartialConfig;
use OFFLINE\Boxes\Classes\Partial\PartialReader;
use OFFLINE\Boxes\VueComponents\EditorExtension as EditorExtensionVueComponent;
use SystemException;
use ValidationException;

/**
 * EditorExtension adds Boxes objects to October Editor IDE
 */
class EditorExtension extends ExtensionBase
{
    /**
     * getNamespace returns unique extension namespace
     */
    public function getNamespace(): string
    {
        return 'OFFLINE.Boxes';
    }

    /**
     * getExtensionSortOrder affects the extension position in the Editor Navigator
     */
    public function getExtensionSortOrder()
    {
        return 10;
    }

    /**
     * Returns a list of JavaScript files required for the extension.
     * @return array Returns an associative array of JavaScript file paths and attributes.
     */
    public function listJsFiles()
    {
        return [
            '/plugins/offline/boxes/assets/editorextension/offline.boxes.editor.extension.js',
            '/plugins/offline/boxes/assets/editorextension/offline.boxes.editor.extension.documentcontroller.boxes.js',
            '/plugins/offline/boxes/assets/editorextension/offline.boxes.editor.extension.documentcomponent.base.js',
        ];
    }

    /**
     * A document is opened.
     */
    public function command_onOpenDocument()
    {
        $documentData = post('documentData');

        if (!is_array($documentData)) {
            throw new SystemException('Document data is not provided');
        }

        $key = array_get($documentData, 'key');

        if (!$key) {
            throw new SystemException('Document key is not provided');
        }

        $handle = base64_decode($key);

        return $this->buildActionReturnValue($handle);
    }

    /**
     * A document is saved.
     */
    public function command_onSaveDocument()
    {
        $documentData = post('documentData');

        if (!is_array($documentData)) {
            throw new SystemException('Document data is not provided');
        }

        $metadata = post('documentMetadata');

        if ($metadata === null) {
            throw new SystemException('Document metadata is not provided');
        }

        $markup = array_get($documentData, 'markup');

        if ($markup === null) {
            throw new SystemException('Document markup is not provided');
        }

        $fileName = array_get($documentData, 'fileName');

        if ($fileName === null) {
            throw new SystemException('Document file name is not provided');
        }

        $fileName = trim($fileName, " \n\r\t\v\0/\\");
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);

        if ($ext) {
            $fileName = str_replace('.' . $ext, '', $fileName);
        }

        $config = array_get($documentData, 'config');

        if ($config === null) {
            throw new SystemException('Document config is not provided');
        }

        $oldHandle = (string)array_get($metadata, 'handle');

        try {
            $yamlConfig = (new Yaml())->parse($config);
            $newHandle = array_get($yamlConfig, 'handle');
        } catch (Exception $e) {
            throw new ValidationException(['config' => 'Invalid YAML in the config file: ' . $e->getMessage()]);
        }

        $isNewDocument = array_get($metadata, 'isNewDocument', false);

        return $isNewDocument
            ? $this->handleNewDocument($newHandle, $fileName, $markup, $config)
            : $this->handleSaveDocument($newHandle, $oldHandle, $fileName, $markup, $config);
    }

    /**
     * A document is deleted.
     */
    public function command_onDeleteDocument()
    {
        $metadata = post('documentMetadata');

        if ($metadata === null) {
            throw new SystemException('Document metadata is not provided');
        }

        $handle = array_get($metadata, 'handle');

        if ($handle === null) {
            throw new SystemException('Document handle is not provided');
        }

        /** @var PartialReader $reader */
        $reader = PartialReader::instance();
        $partial = $reader->findByHandle($handle);

        if (!$partial) {
            throw new SystemException('Partial not found');
        }

        if (file_exists($partial->path)) {
            unlink($partial->path);
        }

        if (($config = $partial->config) && file_exists($config->path)) {
            unlink($config->path);
        }

        return [];
    }

    /**
     * Returns a list of Vue components required for the extension.
     * @return array Array of Vue component class names
     */
    public function listVueComponents()
    {
        return [
            EditorExtensionVueComponent::class,
        ];
    }

    /**
     * Initializes extension's sidebar Navigator sections.
     * @param null|mixed $documentType
     */
    public function listNavigatorSections(SectionList $sectionList, $documentType = null)
    {
        $partialSections = PartialReader::instance()->listPartialsGrouped();

        $rootNode = $sectionList->addSection('Boxes', 'OFFLINE.Boxes');
        $rootNode->setHasApiMenuItems(true);
        $rootNode->setUserDataElement('uniqueKey', 'offline.boxes:root');

        $createMenuItem = new ItemDefinition(ItemDefinition::TYPE_TEXT, Lang::get('offline.boxes::lang.create_box'), 'offline.boxes:create-document@boxes');
        $createMenuItem->setIcon('octo-icon-create');

        $createMenuItem->addItemObject(
            $rootNode->addCreateMenuItem(
                ItemDefinition::TYPE_TEXT,
                Lang::get('offline.boxes::lang.create_box'),
                'offline.boxes:create-document@boxes'
            )
        );
        $rootNode->addMenuItemObject($createMenuItem);

        foreach ($partialSections as $section => $partials) {
            // Hide read-only system partials and single file partials.
            $partials = collect($partials)
                ->filter(
                    fn ($partial) => $partial->specialCategory !== PartialConfig::SYSTEM_PARTIAL
                        && $partial->specialCategory !== PartialConfig::EXTERNAL_PARTIAL
                        && $partial->isSingleFile === false
                );

            if ($partials->isEmpty()) {
                continue;
            }

            $sectionNode = $rootNode->addNode($section, $section);
            $sectionNode->setDisplayMode(NodeDefinition::DISPLAY_MODE_TREE);
            $sectionNode->setChildKeyPrefix('boxes:');

            $basePath = '';
            $sectionKey = 'Common';

            // Guess the path and section key based on the first partial.
            if ($firstPartial = $partials->firstWhere('specialCategory', '<>', PartialConfig::EXTERNAL_PARTIAL)) {
                $basePath = pathinfo($firstPartial->path, PATHINFO_DIRNAME);
                $basePath = str_replace($this->getPartialsPath(), '', $basePath);

                $sectionKey = $firstPartial->section;
            }

            // Only add the creation actions if at least one partial of this section is within the current theme.
            if ($basePath) {
                $this->addSectionMenuItems($sectionNode, $sectionKey, $basePath);
            }

            $partials->each(function ($partial) use ($sectionNode) {
                $partialNode = $sectionNode->addNode($partial->name, base64_encode($partial->handle));
                $partialNode
                    ->setIcon('#6499F3', 'icon-cube')
                    ->setUserData([
                        'handle' => $partial->handle,
                    ]);
            });
        }
    }

    /**
     * Returns the initial data for a new document.
     */
    public function getNewDocumentsData()
    {
        $newBoxData = new NewDocumentDescription(
            Lang::get('offline.boxes::lang.create_box'),
            ['isNewDocument' => true]
        );

        $html = <<<HTML
        <div>
            {{ d(box) }}
        </div>
        HTML;

        $yaml = <<<YAML
        name: %s
        handle: new-box-{time}
        section: %s
        form:
          fields:
            text:
              label: Text
              type: text
        YAML;

        $newBoxData->setInitialDocumentData([
            'markup' => $html,
            'fileName' => 'new-box-{time}',
            'name' => Lang::get('offline.boxes::lang.new_box'),
            'config' => sprintf(
                $yaml,
                Lang::get('offline.boxes::lang.new_box'),
                Lang::get('offline.boxes::lang.section_common')
            ),
        ]);

        return [
            'boxes' => $newBoxData,
        ];
    }

    /**
     * Handle Partial creation.
     */
    protected function handleNewDocument(string $newHandle, string $fileName, string $markup, string $config)
    {
        try {
            $existing = PartialReader::instance()->findByHandle($newHandle);
        } catch (PartialNotFoundException $e) {
            $existing = null;
        }

        if ($existing) {
            throw new ValidationException(['fileName' => 'Partial with the same handle already exists.']);
        }

        $theme = ThemeResolver::instance()->getThemeCode();

        $partialPath = themes_path($theme . '/partials/' . $fileName . '.htm');
        $configPath = str_replace('.htm', '.yaml', $partialPath);

        if (file_exists($partialPath)) {
            throw new ValidationException(['fileName' => 'Partial with the same name already exists.']);
        }

        file_put_contents($partialPath, $markup);
        file_put_contents($configPath, $config);

        return $this->buildActionReturnValue($newHandle);
    }

    /**
     * Handle Partial saving.
     */
    protected function handleSaveDocument(string $newHandle, string $oldHandle, string $fileName, string $markup, string $config)
    {
        if (!$oldHandle) {
            throw new SystemException('Document handle is not provided');
        }

        /** @var PartialReader $reader */
        $reader = PartialReader::instance();

        $partial = $reader->findByHandle($oldHandle);

        if (!$partial) {
            throw new SystemException('Partial not found');
        }

        // Save YAML
        if (!$partial->config->isSingleFile) {
            file_put_contents($partial->config->path, $config);
        }

        // Save Partial
        file_put_contents($partial->path, $markup);

        // Handle rename.
        [$relativePartialName, $isExternal] = $this->getRelativePartialPath($partial->path);

        $relativePartialName = trim($relativePartialName, " \n\r\t\v\0/\\");
        $ext = pathinfo($relativePartialName, PATHINFO_EXTENSION);

        $relativePartialName = str_replace('.' . $ext, '', $relativePartialName);

        if ($fileName !== $relativePartialName) {
            if ($isExternal) {
                throw new ValidationException(['fileName' => 'Renaming external partials is currently not supported.']);
            }

            $oldMarkupName = $this->getPartialsPath($relativePartialName . '.htm');
            $newMarkupName = $this->getPartialsPath($fileName . '.htm');

            $partialDir = realpath(dirname($newMarkupName));

            if (!str_starts_with($partialDir, $this->getPartialsPath())) {
                throw new ValidationException(['fileName' => 'Renaming partials outside of the theme directory is not allowed.']);
            }

            // Ensure the directory structure exists.
            if (!is_dir(dirname($newMarkupName))) {
                mkdir(dirname($newMarkupName), 0774, true);
            }

            rename($oldMarkupName, $newMarkupName);

            if (!$partial->config->isSingleFile) {
                $ext = pathinfo($partial->config->path, PATHINFO_EXTENSION);

                $oldConfigName = $this->getPartialsPath(str_replace('.htm', '', $relativePartialName) . '.' . $ext);
                $newConfigName = $this->getPartialsPath(str_replace('.htm', '', $fileName) . '.' . $ext);

                if (file_exists($oldConfigName)) {
                    rename($oldConfigName, $newConfigName);
                }
            }
        }

        return $this->buildActionReturnValue($newHandle);
    }

    protected function buildActionReturnValue(string $handle)
    {
        // Make sure there is no cached data since we may have changed
        // the partials in the previous action
        PartialReader::forgetInstance();

        /** @var PartialReader $reader */
        $reader = PartialReader::instance();

        $partial = $reader->findByHandle($handle);

        if (!$partial) {
            throw new SystemException('Partial not found');
        }

        [$filename, $isExternal] = $this->getRelativePartialPath($partial->path);

        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        $document = [
            'markup' => file_get_contents($partial->path),
            'fileName' => str_replace('.' . $ext, '', $filename),
            'name' => $partial->config->name,
        ];

        $configPath = $partial->config?->path;

        if (!$partial->config->isSingleFile && $configPath) {
            $document['config'] = file_get_contents($configPath);
        }

        $metadata = [
            'mtime' => filemtime($partial->path),
            'handle' => $partial->config->handle,
            'fileName' => $filename,
            'name' => $partial->config->name,
            'uniqueKey' => base64_encode($handle),
            'type' => 'offline-boxes',
            'isExternal' => $isExternal,
        ];

        return [
            'document' => $document,
            'metadata' => $metadata,
        ];
    }

    protected function addSectionMenuItems(NodeDefinition $section, string $sectionKey, string $basePath)
    {
        $createMenuItem = new ItemDefinition(ItemDefinition::TYPE_TEXT, Lang::get('offline.boxes::lang.create_box'), 'offline.boxes:create-document@boxes');
        $createMenuItem->setIcon('octo-icon-create');

        $section->addRootMenuItem(
            ItemDefinition::TYPE_TEXT,
            Lang::get('offline.boxes::lang.create_box'),
            'offline.boxes:create-box-in-section@' . $sectionKey . '||' . $basePath,
        );
    }

    /**
     * Return the path to the partials directory of the current Theme.
     * @return string
     */
    protected function getPartialsPath(?string $filename = '')
    {
        $theme = ThemeResolver::instance()->getThemeCode();

        return rtrim(themes_path($theme . '/partials/' . $filename), '/');
    }

    /**
     * @param string $fileName
     * @return array{string, bool}
     */
    protected function getRelativePartialPath(string $path): array
    {
        // By default, use the partial's filename as the name.
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $isExternal = true;

        $partialsPath = $this->getPartialsPath();

        // If the partial is in our theme's partials directory, use the relative path as the name.
        if (str_starts_with($path, $partialsPath)) {
            $filename = str_replace($partialsPath, '', $path);
            $isExternal = false;
        }

        return [$filename, $isExternal];
    }
}
