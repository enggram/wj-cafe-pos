<?php

namespace Database\Factories;

use App\Models\PurchaseEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseEntry>
 */
class PurchaseEntryFactory extends Factory
{
    protected $model = PurchaseEntry::class;

    public function definition(): array
    {
        return [
            'item_name' => fake()->words(2, true),
            'quantity' => fake()->randomFloat(3, 0.1, 100.0),
            'cost' => fake()->randomFloat(2, 0.01, 999999.99),
            'purchase_date' => fake()->dateTimeBetween('-1 year', 'today')->format('Y-m-d'),
        ];
    }
}
