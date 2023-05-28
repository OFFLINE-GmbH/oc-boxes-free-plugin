<?php

namespace OFFLINE\Boxes\Factories;

use OFFLINE\Boxes\Classes\Partial\PartialReader;
use OFFLINE\Seeder\Classes\Factory;

class BoxFactory extends Factory
{
    public function definition()
    {
        $partials = PartialReader::instance()->listPartials()->keys();

        return [
            'is_enabled' => true,
            'partial' => $this->faker->randomElement($partials->all()),
            'data' => [
                'title' => $this->faker->sentence,
                'text' => $this->faker->paragraph,
            ],
        ];
    }
}
