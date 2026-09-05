<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'table_id' => Table::factory(),
            'status' => OrderStatus::Active,
            'ordered_at' => now(),
        ];
    }

    public function billed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Billed,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed,
        ]);
    }
}
