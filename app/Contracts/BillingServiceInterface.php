<?php

namespace App\Contracts;

use App\Models\Bill;

interface BillingServiceInterface
{
    public function generateBill(int $tableId): Bill;

    public function settleBill(int $billId): Bill;

    public function getBillForTable(int $tableId): ?Bill;
}
