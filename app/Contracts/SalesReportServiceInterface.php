<?php

namespace App\Contracts;

use App\DTOs\SalesReportDTO;
use Carbon\Carbon;

interface SalesReportServiceInterface
{
    public function dailyReport(Carbon $date): SalesReportDTO;

    public function weeklyReport(Carbon $startDate): SalesReportDTO;

    public function monthlyReport(int $year, int $month): SalesReportDTO;

    public function yearlyReport(int $year): SalesReportDTO;
}
