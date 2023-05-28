<?php

return [
    /**
     * Determines if the scaffolding around Box partials should be rendered.
     */
    'render_scaffolding' => (bool)env('BOXES_RENDER_SCAFFOLDING', true),

    /**
     * Defines the main menu item position.
     */
    'main_menu_order' => (int)env('BOXES_MAIN_MENU_ORDER', 500),

    /**
     * Define spacings for your partials.
     * Use the `spacing` option on your YAML definition to select which groups
     * are available for a given partial.
     */
    'spacing' => [
        'before' => [
            'none' => [
                'label' => 'None',
                'class' => 'oc-boxes-spacing--before-none',
                'group' => 'general',
            ],
            'small' => [
                'label' => 'Small',
                'class' => 'oc-boxes-spacing--before-small',
                'group' => 'general',
            ],
            'medium' => [
                'label' => 'Medium',
                'class' => 'oc-boxes-spacing--before-medium',
                'group' => 'general',
            ],
            'large' => [
                'label' => 'Large',
                'class' => 'oc-boxes-spacing--before-large',
                'group' => 'general',
            ],
        ],

        'after' => [
            'none' => [
                'label' => 'None',
                'class' => 'oc-boxes-spacing--after-none',
                'group' => 'general',
            ],
            'small' => [
                'label' => 'Small',
                'class' => 'oc-boxes-spacing--after-small',
                'group' => 'general',
            ],
            'medium' => [
                'label' => 'Medium',
                'class' => 'oc-boxes-spacing--after-medium',
                'group' => 'general',
            ],
            'large' => [
                'label' => 'Large',
                'class' => 'oc-boxes-spacing--after-large',
                'group' => 'general',
            ],
        ],
    ],
];
