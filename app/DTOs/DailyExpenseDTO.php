<?php

namespace App\DTOs;

use Carbon\Carbon;

class DailyExpenseDTO
{
    public function __construct(
        public readonly Carbon $date,
        public readonly array $categories,  // [{category_name, entries:[{description, amount}], total}]
        public readonly float $grandTotal,
    ) {}
}
