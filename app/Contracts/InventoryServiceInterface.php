<?php

namespace App\Contracts;

use App\DTOs\DailySpendingDTO;
use App\DTOs\MonthlySpendingDTO;
use App\Models\PurchaseEntry;
use Carbon\Carbon;

interface InventoryServiceInterface
{
    public function recordPurchase(array $data): PurchaseEntry;

    public function dailySpending(Carbon $date): DailySpendingDTO;

    public function monthlySpending(int $year, int $month): MonthlySpendingDTO;
}
