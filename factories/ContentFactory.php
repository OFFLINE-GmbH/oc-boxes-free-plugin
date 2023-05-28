<?php

namespace OFFLINE\Boxes\Factories;

use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Seeder\Classes\Factory;

class ContentFactory extends Factory
{
    public function definition()
    {
        return [
            'layout' => 'default',
            'theme' => ThemeResolver::instance()?->getThemeCode(),
            'is_pending_content' => false,
        ];
    }
}
