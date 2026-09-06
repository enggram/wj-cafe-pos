<?php

namespace App\DTOs;

class MonthlyExpenseDTO
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly array $categoryTotals, // [{category_name, total}]
        public readonly float $grandTotal,
    ) {}
}
