<?php

namespace OFFLINE\Boxes\Components;

use Backend\Facades\BackendAuth;
use Closure;
use Cms\Classes\ComponentBase;
use October\Rain\Database\Scopes\MultisiteScope;
use October\Rain\Support\Facades\Site;
use OFFLINE\Boxes\Classes\Features;
use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Models\Content;
use OFFLINE\Boxes\Models\Page;

/**
 * Render a BoxesPage.
 */
class BoxesPage extends ComponentBase
{
    public null|Page|Content $boxesPage;

    public string $locale;

    public string $modelType;

    public function componentDetails()
    {
        return [
            'name' => 'Boxes Page',
            'description' => 'Displays a Boxes Page.',
        ];
    }

    public function defineProperties()
    {
        return [
            'url' => [
                'type' => 'string',
            ],
            'slug' => [
                'type' => 'string',
            ],
            'id' => [
                'type' => 'string',
            ],
            'modelType' => [
                'type' => 'string',
            ],
        ];
    }

    public function init()
    {
        $this->setData();

        if ($this->boxesPage) {
            $this->addAssets();
            $this->addComponents();
            $this->setMetaInformation();
            $this->setLocale();
        }
    }

    public function onRun()
    {
        if (!$this->boxesPage) {
            return $this->controller->run(404);
        }

        $this->page['boxesPage'] = $this->boxesPage;
    }

    protected function setData()
    {
        $this->modelType = $this->property('modelType');

        $model = new ($this->modelType);

        $this->boxesPage = $this->page[$this->alias] = $model->query()
            ->with([
                'boxes' => $this->eagerLoadBoxes(),
            ])
            ->when(
                $this->modelType === Page::class,
                fn ($q) => $q->with(['children.boxes' => $this->eagerLoadBoxes()]),
            )
            ->when(
                // Hide hidden pages from non-admins.
                !BackendAuth::getUser(),
                fn ($q) => $q->where('is_hidden', '<>', true)
            )
            ->when(
                // Hide unpublished pages from non-admins.
                Features::instance()->revisions && !BackendAuth::getUser(),
                fn ($q) => $q->where('published_state', PublishedState::PUBLISHED)
            )
            ->when(
                $url = $this->property('url'),
                fn ($q) => $q->where('url', $url)
            )
            ->when(
                $slug = $this->property('slug'),
                fn ($q) => $q->withoutGlobalScope(MultisiteScope::class)->where('slug', $slug)
            )
            ->when(
                $id = $this->property('id'),
                fn ($q) => $q->withoutGlobalScope(MultisiteScope::class)->where('id', $id)
            )
            ->first();
    }

    /**
     * Returns a query scope that only returns enabled boxes (for non-admins).
     */
    protected function eagerLoadBoxes(): Closure
    {
        return static fn ($q) => $q->when(
            !BackendAuth::getUser(),
            fn ($q) => $q->where('is_enabled', true)->when(Features::instance()->references, fn ($q) => $q->with('reference')),
        );
    }

    /**
     * Adds all assets from all partials of this collection to the page.
     */
    protected function addAssets(): void
    {
        $this->boxesPage->addAssets($this->controller);
    }

    /**
     * Adds dynamically defined components to the page.
     */
    protected function addComponents(): void
    {
        $this->boxesPage->addComponents($this->controller);
    }

    /**
     * Sets meta title and meta description to the page.
     */
    protected function setMetaInformation(): void
    {
        if ($this->boxesPage->meta_title) {
            $this->page->meta_title = $this->boxesPage->meta_title;
        }

        if ($this->boxesPage->meta_description) {
            $this->page->meta_description = $this->boxesPage->meta_description;
        }
    }

    /**
     * Guess the locale for the current execution context.
     */
    protected function setLocale(): void
    {
        $this->locale = Site::getSiteFromContext()->hard_locale;

        if (class_exists(\RainLab\Translate\Classes\Translator::class)) {
            $this->locale = \RainLab\Translate\Classes\Translator::instance()->getLocale();
        }
    }
}
