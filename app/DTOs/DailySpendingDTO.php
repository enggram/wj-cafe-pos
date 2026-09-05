<?php

namespace App\DTOs;

use Carbon\Carbon;

class DailySpendingDTO
{
    public function __construct(
        public readonly Carbon $date,
        public readonly array $entries,     // [{item_name, quantity, cost}]
        public readonly float $totalCost,
    ) {}
}
