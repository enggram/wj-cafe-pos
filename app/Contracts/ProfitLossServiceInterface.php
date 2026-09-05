<?php

namespace App\Contracts;

use App\DTOs\ProfitLossDTO;
use Carbon\Carbon;

interface ProfitLossServiceInterface
{
    public function weeklyReport(Carbon $startDate): ProfitLossDTO;

    public function monthlyReport(int $year, int $month): ProfitLossDTO;

    public function yearlyReport(int $year): ProfitLossDTO;
}
