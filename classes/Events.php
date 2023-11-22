<?php

namespace OFFLINE\Boxes\Classes;

class Events
{
    public const REGISTER_PARTIAL_PATH = 'offline.boxes.registerPartialPath';

    public const BEFORE_BOX_RENDER = 'offline.boxes.beforeBoxRender';

    public const AFTER_BOX_RENDER = 'offline.boxes.afterBoxRender';

    public const BEFORE_PAGE_RENDER = 'offline.boxes.beforePageRender';

    public const AFTER_PAGE_RENDER = 'offline.boxes.afterPageRender';

    public const EDITOR_RENDER = 'offline.boxes.editorRender';

    public const EDITOR_EXTEND_PAGES = 'offline.boxes.editorExtendPages';

    public const BEFORE_FILTER_PARTIALS = 'offline.boxes.beforeFilterPartials';

    public const FILTER_PARTIALS = 'offline.boxes.filterPartials';
}
