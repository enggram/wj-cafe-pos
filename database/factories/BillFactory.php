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
        $subtotal = fake()->randomFloat(2, 1, 9999.99);
        $parcel = fake()->randomFloat(2, 0, 500);

        return [
            'order_id' => Order::factory()->billed(),
            'table_id' => Table::factory(),
            'items_subtotal' => $subtotal,
            'parcel_charges_total' => $parcel,
            'grand_total' => round($subtotal + $parcel, 2),
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
