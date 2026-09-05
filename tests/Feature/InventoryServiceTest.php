<?php

use App\Contracts\InventoryServiceInterface;
use App\DTOs\DailySpendingDTO;
use App\DTOs\MonthlySpendingDTO;
use App\Models\PurchaseEntry;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(InventoryServiceInterface::class);
});

describe('recordPurchase', function () {
    it('creates a purchase entry with valid data', function () {
        $entry = $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 5.0,
            'cost' => 250.00,
            'purchase_date' => '2024-01-15',
        ]);

        expect($entry)->toBeInstanceOf(PurchaseEntry::class)
            ->and($entry->item_name)->toBe('Milk')
            ->and((float) $entry->quantity)->toBe(5.0)
            ->and((float) $entry->cost)->toBe(250.00)
            ->and($entry->purchase_date->toDateString())->toBe('2024-01-15');
    });

    it('trims whitespace from item_name', function () {
        $entry = $this->service->recordPurchase([
            'item_name' => '  Sugar  ',
            'quantity' => 2.0,
            'cost' => 50.00,
            'purchase_date' => '2024-01-15',
        ]);

        expect($entry->item_name)->toBe('Sugar');
    });

    it('persists the entry in the database', function () {
        $entry = $this->service->recordPurchase([
            'item_name' => 'Eggs',
            'quantity' => 30,
            'cost' => 180.00,
            'purchase_date' => '2024-01-15',
        ]);

        $this->assertDatabaseHas('purchase_entries', [
            'id' => $entry->id,
            'item_name' => 'Eggs',
        ]);
    });

    it('accepts boundary cost of 0.01', function () {
        $entry = $this->service->recordPurchase([
            'item_name' => 'Cheap Item',
            'quantity' => 1,
            'cost' => 0.01,
            'purchase_date' => '2024-01-15',
        ]);

        expect((float) $entry->cost)->toBe(0.01);
    });

    it('accepts boundary cost of 999999.99', function () {
        $entry = $this->service->recordPurchase([
            'item_name' => 'Expensive Item',
            'quantity' => 1,
            'cost' => 999999.99,
            'purchase_date' => '2024-01-15',
        ]);

        expect((float) $entry->cost)->toBe(999999.99);
    });

    it('accepts today as purchase_date', function () {
        $today = Carbon::today()->toDateString();

        $entry = $this->service->recordPurchase([
            'item_name' => 'Fresh Bread',
            'quantity' => 10,
            'cost' => 100.00,
            'purchase_date' => $today,
        ]);

        expect($entry->purchase_date->toDateString())->toBe($today);
    });

    it('accepts a name exactly 100 characters long', function () {
        $name = str_repeat('a', 100);

        $entry = $this->service->recordPurchase([
            'item_name' => $name,
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);

        expect(mb_strlen($entry->item_name))->toBe(100);
    });

    it('accepts a name exactly 1 character long', function () {
        $entry = $this->service->recordPurchase([
            'item_name' => 'X',
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);

        expect($entry->item_name)->toBe('X');
    });

    // Validation failures
    it('rejects empty item_name', function () {
        $this->service->recordPurchase([
            'item_name' => '',
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects whitespace-only item_name', function () {
        $this->service->recordPurchase([
            'item_name' => '   ',
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects item_name exceeding 100 characters', function () {
        $this->service->recordPurchase([
            'item_name' => str_repeat('a', 101),
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects quantity of zero', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 0,
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects negative quantity', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => -1,
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects non-numeric quantity', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 'abc',
            'cost' => 10.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects cost below 0.01', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 1,
            'cost' => 0.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects cost above 999999.99', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 1,
            'cost' => 1000000.00,
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects non-numeric cost', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 1,
            'cost' => 'expensive',
            'purchase_date' => '2024-01-15',
        ]);
    })->throws(ValidationException::class);

    it('rejects a future purchase_date', function () {
        $futureDate = Carbon::tomorrow()->toDateString();

        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => $futureDate,
        ]);
    })->throws(ValidationException::class);

    it('rejects missing purchase_date', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => '',
        ]);
    })->throws(ValidationException::class);

    it('rejects invalid date format', function () {
        $this->service->recordPurchase([
            'item_name' => 'Milk',
            'quantity' => 1,
            'cost' => 10.00,
            'purchase_date' => 'not-a-date',
        ]);
    })->throws(ValidationException::class);

    it('provides field-specific error messages', function () {
        try {
            $this->service->recordPurchase([
                'item_name' => '',
                'quantity' => 0,
                'cost' => 0.00,
                'purchase_date' => '',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            expect($errors)->toHaveKey('item_name')
                ->and($errors)->toHaveKey('quantity')
                ->and($errors)->toHaveKey('cost')
                ->and($errors)->toHaveKey('purchase_date');

            return;
        }

        $this->fail('Expected ValidationException was not thrown');
    });
});

describe('dailySpending', function () {
    it('returns all entries for a given date', function () {
        PurchaseEntry::factory()->create([
            'item_name' => 'Milk',
            'quantity' => 5,
            'cost' => 250.00,
            'purchase_date' => '2024-01-15',
        ]);
        PurchaseEntry::factory()->create([
            'item_name' => 'Sugar',
            'quantity' => 2,
            'cost' => 80.00,
            'purchase_date' => '2024-01-15',
        ]);
        // Entry on a different date — should not appear
        PurchaseEntry::factory()->create([
            'item_name' => 'Flour',
            'quantity' => 3,
            'cost' => 120.00,
            'purchase_date' => '2024-01-16',
        ]);

        $result = $this->service->dailySpending(Carbon::parse('2024-01-15'));

        expect($result)->toBeInstanceOf(DailySpendingDTO::class)
            ->and($result->date->toDateString())->toBe('2024-01-15')
            ->and($result->entries)->toHaveCount(2)
            ->and($result->totalCost)->toBe(330.00);
    });

    it('returns empty entries and zero total for a date with no purchases', function () {
        $result = $this->service->dailySpending(Carbon::parse('2024-06-01'));

        expect($result->entries)->toHaveCount(0)
            ->and($result->totalCost)->toBe(0.0);
    });

    it('entry items contain item_name, quantity, and cost', function () {
        PurchaseEntry::factory()->create([
            'item_name' => 'Eggs',
            'quantity' => 30,
            'cost' => 180.00,
            'purchase_date' => '2024-02-10',
        ]);

        $result = $this->service->dailySpending(Carbon::parse('2024-02-10'));

        $entry = $result->entries[0];
        expect($entry)->toHaveKey('item_name', 'Eggs')
            ->and($entry)->toHaveKey('quantity', 30.0)
            ->and($entry)->toHaveKey('cost', 180.0);
    });

    it('allows multiple entries for same day with different items', function () {
        PurchaseEntry::factory()->create([
            'item_name' => 'Milk',
            'quantity' => 5,
            'cost' => 250.00,
            'purchase_date' => '2024-03-01',
        ]);
        PurchaseEntry::factory()->create([
            'item_name' => 'Sugar',
            'quantity' => 10,
            'cost' => 100.00,
            'purchase_date' => '2024-03-01',
        ]);
        PurchaseEntry::factory()->create([
            'item_name' => 'Coffee Beans',
            'quantity' => 2,
            'cost' => 500.00,
            'purchase_date' => '2024-03-01',
        ]);

        $result = $this->service->dailySpending(Carbon::parse('2024-03-01'));

        expect($result->entries)->toHaveCount(3)
            ->and($result->totalCost)->toBe(850.00);
    });
});

describe('monthlySpending', function () {
    it('sums costs per item for the given month', function () {
        // Two Milk purchases in January
        PurchaseEntry::factory()->create([
            'item_name' => 'Milk',
            'quantity' => 5,
            'cost' => 250.00,
            'purchase_date' => '2024-01-10',
        ]);
        PurchaseEntry::factory()->create([
            'item_name' => 'Milk',
            'quantity' => 3,
            'cost' => 150.00,
            'purchase_date' => '2024-01-20',
        ]);
        // One Sugar purchase in January
        PurchaseEntry::factory()->create([
            'item_name' => 'Sugar',
            'quantity' => 10,
            'cost' => 200.00,
            'purchase_date' => '2024-01-15',
        ]);
        // One Milk purchase in February — should not appear
        PurchaseEntry::factory()->create([
            'item_name' => 'Milk',
            'quantity' => 2,
            'cost' => 100.00,
            'purchase_date' => '2024-02-05',
        ]);

        $result = $this->service->monthlySpending(2024, 1);

        expect($result)->toBeInstanceOf(MonthlySpendingDTO::class)
            ->and($result->year)->toBe(2024)
            ->and($result->month)->toBe(1)
            ->and($result->itemTotals)->toHaveCount(2)
            ->and($result->grandTotal)->toBe(600.00);

        // Verify individual item totals
        $milkTotal = collect($result->itemTotals)->firstWhere('item_name', 'Milk');
        $sugarTotal = collect($result->itemTotals)->firstWhere('item_name', 'Sugar');

        expect($milkTotal['total_cost'])->toBe(400.00)
            ->and($sugarTotal['total_cost'])->toBe(200.00);
    });

    it('returns empty item totals and zero grand total for a month with no purchases', function () {
        $result = $this->service->monthlySpending(2024, 6);

        expect($result->itemTotals)->toHaveCount(0)
            ->and($result->grandTotal)->toBe(0.0);
    });

    it('item totals contain item_name and total_cost', function () {
        PurchaseEntry::factory()->create([
            'item_name' => 'Flour',
            'quantity' => 5,
            'cost' => 300.00,
            'purchase_date' => '2024-03-10',
        ]);

        $result = $this->service->monthlySpending(2024, 3);

        expect($result->itemTotals[0])->toHaveKey('item_name', 'Flour')
            ->and($result->itemTotals[0])->toHaveKey('total_cost', 300.00);
    });

    it('groups different items separately in monthly report', function () {
        PurchaseEntry::factory()->create([
            'item_name' => 'Milk',
            'quantity' => 5,
            'cost' => 250.00,
            'purchase_date' => '2024-04-01',
        ]);
        PurchaseEntry::factory()->create([
            'item_name' => 'Sugar',
            'quantity' => 10,
            'cost' => 100.00,
            'purchase_date' => '2024-04-01',
        ]);
        PurchaseEntry::factory()->create([
            'item_name' => 'Eggs',
            'quantity' => 30,
            'cost' => 180.00,
            'purchase_date' => '2024-04-15',
        ]);

        $result = $this->service->monthlySpending(2024, 4);

        expect($result->itemTotals)->toHaveCount(3)
            ->and($result->grandTotal)->toBe(530.00);
    });
});
