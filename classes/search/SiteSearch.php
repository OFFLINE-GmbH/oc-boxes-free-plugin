<?php

namespace OFFLINE\Boxes\Classes\Search;

use OFFLINE\Boxes\Models\Page;
use OFFLINE\SiteSearch\Classes\Providers\ResultsProvider;

class SiteSearch extends ResultsProvider
{
    public function search()
    {
        return Page::query()->search($this->query)->get()->map(function (Page $page) {
            $result = $this->newResult();

            $result->relevance = str_contains($page->name, $this->query) ? 55 : 50;
            $result->title = $page->name;
            $result->text = $page->meta_description;
            $result->url = $page->absolute_url;
            $result->thumb = $page->images?->first();
            $result->model = $page;
            $result->meta = [
                'offline_boxes' => true,
            ];

            $this->addResult($result);
        });
    }

    public function displayName()
    {
        return trans('offline.boxes::lang.content');
    }

    public function identifier()
    {
        return 'OFFLINE.Boxes';
    }
}
