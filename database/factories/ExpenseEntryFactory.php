<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpenseEntry>
 */
class ExpenseEntryFactory extends Factory
{
    protected $model = ExpenseEntry::class;

    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => fake()->randomFloat(2, 0.01, 9999999.99),
            'description' => fake()->optional()->sentence(),
            'expense_date' => fake()->dateTimeBetween('-1 year', 'today')->format('Y-m-d'),
        ];
    }
}
