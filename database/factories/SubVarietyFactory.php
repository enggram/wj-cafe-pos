<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\SubVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubVariety>
 */
class SubVarietyFactory extends Factory
{
    protected $model = SubVariety::class;

    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->unique()->words(2, true),
            'price_adjustment' => fake()->randomFloat(2, 0, 50.00),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
