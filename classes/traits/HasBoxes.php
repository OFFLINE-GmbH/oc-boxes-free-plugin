<?php

namespace OFFLINE\Boxes\Classes\Traits;

use Cms\Classes\Controller;
use Event;
use LogicException;
use October\Rain\Support\Collection;
use OFFLINE\Boxes\Classes\Events;
use OFFLINE\Boxes\Classes\Partial\RenderContext;
use OFFLINE\Boxes\Models\Box;
use System\Models\File;

trait HasBoxes
{
    use HasContext;

    /**
     * Marks if the components have been added to the page already.
     * This must only ever happen once.
     */
    protected $componentsAdded = false;

    /**
     * Marks if the assets have been added to the page already.
     * This must only ever happen once.
     */
    protected $assetsAdded = false;

    /**
     * Render the related Boxes.
     *
     * @throws \Cms\Classes\CmsException
     */
    public function render(RenderContext|array $context = []): string
    {
        $context = $this->wrapContext($context);

        $context->model = $this;

        /** @var Collection $eagerLoads */
        $eagerLoads = $this->boxes
            ->flatMap(fn (Box $box) => $box->getPartial()?->config?->eagerLoad ?? [])
            ->filter()
            ->map(fn (string $eagerLoad) => 'boxes.' . $eagerLoad)
            ->when(
                class_exists(\RainLab\Translate\Plugin::class),
                fn ($collection) => $collection->push('translations')
            );

        if ($eagerLoads->count()) {
            $this->load($eagerLoads->toArray());
        }

        $boxes = $this->boxes->toNested();

        Event::fire(Events::BEFORE_PAGE_RENDER, [$this, $context, $boxes]);

        $index = 0;

        $loop = $this->buildLoopHelper($boxes->count());

        $contents = $boxes->implode(function (Box $box) use ($context, &$index, $loop) {
            $context->loop = $loop($index++);

            return $box->render($context);
        });

        Event::fire(Events::AFTER_PAGE_RENDER, [$this, $context, $boxes, &$contents]);

        return $contents;
    }

    /**
     * Registers the components used by the Boxes.
     * This method is usually only called when the page is used in a plugin
     * as we cannot init the components/assets in the BoxesPage component.
     */
    public function init(): void
    {
        $this->addComponents();
        $this->addAssets();
    }

    /**
     * Adds assets to the controller.
     */
    public function addAssets(?Controller $controller = null): void
    {
        if ($this->assetsAdded) {
            return;
        }

        if ($controller === null) {
            $controller = Controller::getController() ?? new Controller();
        }

        $this->boxes->each(function (Box $box) use ($controller) {
            // Make sure to include assets of referenced boxes if a reference is set.
            if ($box->reference) {
                $box = $box->reference;
            }

            foreach (array_wrap($box->getAssets('css')) as $asset) {
                $asset = $this->normalizeAsset($box, $asset);

                if ($asset['bundle']) {
                    $controller->addCssBundle($asset['name'], $asset['attributes']);
                } else {
                    $controller->addCss($asset['name'], $asset['attributes']);
                }
            }

            foreach (array_wrap($box->getAssets('js')) as $asset) {
                $asset = $this->normalizeAsset($box, $asset);

                if ($asset['bundle']) {
                    $controller->addJsBundle($asset['name'], $asset['attributes']);
                } else {
                    $controller->addJs($asset['name'], $asset['attributes']);
                }
            }
        });

        $this->assetsAdded = true;
    }

    /**
     * Adds components to a page or layout.
     */
    public function addComponents(?Controller $controller = null): void
    {
        if ($this->componentsAdded) {
            return;
        }

        if ($controller === null) {
            $controller = Controller::getController() ?? new Controller();
        }

        $this->boxes->each(function (Box $box) use ($controller) {
            // Make sure to include components of referenced boxes if a reference is set.
            if ($box->reference) {
                $box = $box->reference;
            }

            if ($components = $box->getComponents()) {
                foreach ($components as $alias => $details) {
                    $componentName = array_get($details, 'component', $alias);

                    if (array_get($details, 'uniqueAlias', false)) {
                        $alias .= $box->unique_id;
                    }

                    $component = $controller->addComponent(
                        $componentName,
                        $alias,
                        array_get($details, 'properties', []),
                        array_get($details, 'addToLayout', false),
                    );

                    if ($component) {
                        $component->addDynamicMethod('getBoxesBox', fn () => $box);
                        $component->addDynamicMethod('getBoxesPage', fn () => $this);
                        $component->addDynamicMethod(
                            'setBoxesPageOpenGraphImage',
                            fn (File $file) => $this->setOpenGraphImage($file)
                        );

                        if (method_exists($component, 'boxesInit')) {
                            $component->boxesInit($this);
                        }
                    }
                }
            }
        });

        $this->componentsAdded = true;
    }
    
    /**
     * Validates the asset definition and adds default values for missing keys.
     */
    protected function normalizeAsset(Box $box, array $asset): array
    {
        if (!isset($asset['name'])) {
            throw new LogicException(
                sprintf(
                    '[OFFLINE.Boxes] An asset in the partial "%s" is missing the "name" property, it is required.',
                    $box->getPartial()?->path ?? $box->partial
                )
            );
        }

        $asset['attributes'] = array_get($asset, 'attributes', []);
        $asset['bundle'] = array_get($asset, 'bundle', false);

        return $asset;
    }
}
