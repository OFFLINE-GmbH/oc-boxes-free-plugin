<?php

namespace OFFLINE\Boxes\Classes\Transfer;

use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Models\Page;

/**
 * Map a Page from and to an array.
 */
class PageMapper extends Mapper
{
    /**
     * Attributes to transfer.
     * @var array<string>
     */
    protected array $attributes = [
        'name',
        'slug',
        'url',
        'layout',
        'meta_title',
        'meta_description',
        'site_id',
        'site_root_id',
        'is_hidden',
        'custom_config',
    ];

    public function toArray(Page $page): array
    {
        $data = $page->only($this->attributes);

        if ($page->children->count()) {
            $data['children'] = $page->children->map(
                fn (Page $child) => $this->toArray($child)
            )->values()->toArray();
        }

        $attachments = $this->attachmentsToArray($page);

        if (count($attachments)) {
            $data['attachments'] = $attachments;
        }

        if (!$data['custom_config']) {
            $data['custom_config'] = [];
        }

        return $data;
    }

    public function fromArray(array $data): Page
    {
        $activePage = Page::published()->where('slug', array_get($data, 'slug'))->first();

        $page = Page::make();
        $page->fill(array_only($data, $this->attributes));
        $page->nest_depth = $activePage?->nest_depth ?? 0;
        $page->nest_left = $activePage?->nest_left ?? 0;
        $page->nest_right = $activePage?->nest_right ?? 0;
        $page->parent_id = $activePage?->parent_id ?? 0;
        $page->published_state = $activePage ? PublishedState::PUBLISHED : PublishedState::DRAFT;

        if ($activePage) {
            $page->useNestedTreeStructure = false;
        }

        return $page;
    }
}
