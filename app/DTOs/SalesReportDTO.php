<?php

namespace App\DTOs;

class SalesReportDTO
{
    public function __construct(
        public readonly float $totalRevenue,
        public readonly int $totalOrders,
        public readonly array $itemSales,   // [{name, quantity_sold, revenue}]
        public readonly array $topItems,    // top 5 by quantity
        public readonly string $periodLabel,
    ) {}
}
