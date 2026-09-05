<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bill>
 */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->billed(),
            'table_id' => Table::factory(),
            'grand_total' => fake()->randomFloat(2, 1, 9999.99),
            'status' => BillStatus::Unpaid,
            'billed_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Paid,
        ]);
    }
}
