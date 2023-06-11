<?php

return [
    /// PRO
    'revisions' => (bool)env('BOXES_REVISIONS_ENABLED', true),
    'multisite' => (bool)env('BOXES_MULTISITE_ENABLED', true),
    'references' => (bool)env('BOXES_REFERENCES_ENABLED', true),
    /// PRO
    'placeholderPreviews' => (bool)env('BOXES_PLACEHOLDER_PREVIEWS_ENABLED', true),
];
