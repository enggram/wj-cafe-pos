<?php

namespace App\DTOs;

class ProfitLossDTO
{
    public function __construct(
        public readonly float $totalEarnings,
        public readonly float $totalSpending,
        public readonly float $netAmount,
        public readonly string $status,      // 'profit' | 'loss' | 'break-even'
        public readonly string $periodLabel,
    ) {}
}
