<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $modelVariants = [
            'iPhone SE',
            'iPhone SE (2020)',
            'iPhone 11',
            'iPhone 13',
            'iPhone 14',
        ];

        $capacities = [
            '2GB/16GB',
            '2GB/32GB',
            '2GB/64GB',
            '2GB/128GB',
            '3GB/64GB',
            '4GB/128GB',
        ];

        return [
            'id' => $this->faker->unique()->numberBetween(4000, 9999),
            'type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => $this->faker->randomElement($modelVariants),
            'capacity' => $this->faker->randomElement($capacities),
            'quantity' => $this->faker->numberBetween(5, 50),
        ];
    }
}
