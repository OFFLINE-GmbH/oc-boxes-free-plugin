<?php

namespace OFFLINE\Boxes\Classes\Transfer;

use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\Page;

/**
 * Map a Box from and to an array.
 */
class BoxMapper extends Mapper
{
    /**
     * Attributes to transfer.
     * @var array<string>
     */
    protected array $attributes = [
        'unique_id',
        'is_enabled',
        'partial',
        'references_box_id',
    ];

    public function toArray(Box $box): array
    {
        $data = $box->only($this->attributes);

        if ($box->children->count()) {
            $data['children'] = $box->children->map(
                fn (Box $child) => $this->toArray($child)
            )->values()->toArray();
        }

        $attachments = $this->attachmentsToArray($box);

        if (count($attachments)) {
            $data['attachments'] = $attachments;
        }

        $data['data'] = $box->getDecodedData();

        return $data;
    }

    public function fromArray(array $data, Page $page): Box
    {
        $activePage = Page::published()->where('slug', $page->slug)->where('id', '<>', $page->id)->first();

        if (!$activePage) {
            $activePage = $page;
        }

        $existing = Box::where('unique_id', $data['unique_id'])
            ->where('holder_id', $activePage->id)
            ->where('holder_type', Page::class)
            ->first();

        $box = Box::make();
        $box->fill(array_only($data, $this->attributes));
        $box->nest_depth = $existing?->nest_depth ?? 0;
        $box->nest_left = $existing?->nest_left ?? 0;
        $box->nest_right = $existing?->nest_right ?? 0;
        $box->data = array_get($data, 'data', []);

        return $box;
    }
}
