<?php

namespace App\DTOs;

class MonthlySpendingDTO
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly array $itemTotals,  // [{item_name, total_cost}]
        public readonly float $grandTotal,
    ) {}
}
