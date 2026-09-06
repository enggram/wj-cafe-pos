<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 0.01, 99999.99),
            'parcel_rate' => fake()->randomFloat(2, 0, 9999.99),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function parcelRate(float $r): static
    {
        return $this->state(fn (array $attributes) => [
            'parcel_rate' => $r,
        ]);
    }

    public function withoutParcelRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'parcel_rate' => 0.00,
        ]);
    }
}
