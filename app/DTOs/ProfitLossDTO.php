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
        // ── new fields (defaults preserve backward compatibility) ──
        public readonly float $inventoryPurchases = 0.0,
        public readonly float $totalExpenses = 0.0,
        public readonly array $expenseBreakdown = [], // [{category_name, total}]
    ) {}
}
