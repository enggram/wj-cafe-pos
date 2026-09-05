<?php

namespace App\Services;

use App\Contracts\InventoryServiceInterface;
use App\DTOs\DailySpendingDTO;
use App\DTOs\MonthlySpendingDTO;
use App\Models\PurchaseEntry;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class InventoryService implements InventoryServiceInterface
{
    public function recordPurchase(array $data): PurchaseEntry
    {
        $data = $this->validatePurchaseData($data);

        return PurchaseEntry::create([
            'item_name' => $data['item_name'],
            'quantity' => $data['quantity'],
            'cost' => $data['cost'],
            'purchase_date' => $data['purchase_date'],
        ]);
    }

    public function dailySpending(Carbon $date): DailySpendingDTO
    {
        $entries = PurchaseEntry::whereDate('purchase_date', $date->toDateString())->get();

        $entryArray = $entries->map(fn (PurchaseEntry $entry) => [
            'item_name' => $entry->item_name,
            'quantity' => (float) $entry->quantity,
            'cost' => (float) $entry->cost,
        ])->all();

        $totalCost = round($entries->sum(fn (PurchaseEntry $entry) => (float) $entry->cost), 2);

        return new DailySpendingDTO(
            date: $date,
            entries: $entryArray,
            totalCost: $totalCost,
        );
    }

    public function monthlySpending(int $year, int $month): MonthlySpendingDTO
    {
        $entries = PurchaseEntry::whereYear('purchase_date', $year)
            ->whereMonth('purchase_date', $month)
            ->get();

        $itemTotals = $entries->groupBy('item_name')
            ->map(fn ($group, $itemName) => [
                'item_name' => $itemName,
                'total_cost' => round($group->sum(fn ($entry) => (float) $entry->cost), 2),
            ])
            ->values()
            ->all();

        $grandTotal = round(array_sum(array_column($itemTotals, 'total_cost')), 2);

        return new MonthlySpendingDTO(
            year: $year,
            month: $month,
            itemTotals: $itemTotals,
            grandTotal: $grandTotal,
        );
    }

    private function validatePurchaseData(array $data): array
    {
        $errors = [];

        // Validate item_name
        $itemName = isset($data['item_name']) ? trim($data['item_name']) : '';
        if ($itemName === '') {
            $errors['item_name'] = ['The item name field is required.'];
        } elseif (mb_strlen($itemName) > 100) {
            $errors['item_name'] = ['The item name must not exceed 100 characters.'];
        }
        $data['item_name'] = $itemName;

        // Validate quantity
        if (!isset($data['quantity']) || !is_numeric($data['quantity'])) {
            $errors['quantity'] = ['The quantity field is required and must be numeric.'];
        } elseif ((float) $data['quantity'] <= 0) {
            $errors['quantity'] = ['The quantity must be greater than zero.'];
        }

        // Validate cost
        if (!isset($data['cost']) || !is_numeric($data['cost'])) {
            $errors['cost'] = ['The cost field is required and must be numeric.'];
        } elseif ((float) $data['cost'] < 0.01 || (float) $data['cost'] > 999999.99) {
            $errors['cost'] = ['The cost must be between 0.01 and 999999.99.'];
        }

        // Validate purchase_date
        if (!isset($data['purchase_date']) || $data['purchase_date'] === '') {
            $errors['purchase_date'] = ['The purchase date field is required.'];
        } else {
            try {
                $date = Carbon::parse($data['purchase_date']);
                if ($date->startOfDay()->gt(Carbon::today())) {
                    $errors['purchase_date'] = ['The purchase date must not be in the future.'];
                }
                $data['purchase_date'] = $date->toDateString();
            } catch (\Exception $e) {
                $errors['purchase_date'] = ['The purchase date must be a valid date.'];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
