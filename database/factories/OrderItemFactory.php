<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'sub_variety_id' => null,
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->randomFloat(2, 0.01, 999.99),
            'is_parcel' => false,
            'parcel_rate' => 0.00,
        ];
    }

    public function parcel(float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'is_parcel' => true,
            'parcel_rate' => $rate,
        ]);
    }
}
