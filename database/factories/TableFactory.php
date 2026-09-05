<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'table_number' => fake()->unique()->numberBetween(1, 100),
            'status' => TableStatus::Vacant,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableStatus::Occupied,
        ]);
    }
}
