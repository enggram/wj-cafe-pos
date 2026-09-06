<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\User;
use App\Contracts\OrderServiceInterface;
use App\Contracts\BillingServiceInterface;
use App\Services\MenuService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->orderService = app(OrderServiceInterface::class);
    $this->billingService = app(BillingServiceInterface::class);
    $this->category = Category::factory()->create();
});

function teaWithParcel(float $price, float $parcel): MenuItem
{
    return MenuItem::factory()->create([
        'price' => $price,
        'parcel_rate' => $parcel,
    ]);
}

// ── Menu parcel rate ──
it('persists a valid parcel rate on the menu item', function () {
    $svc = new MenuService();
    $item = $svc->createItem([
        'name' => 'Parcel Tea',
        'price' => 15.00,
        'category_id' => $this->category->id,
        'parcel_rate' => 5.55,
    ]);
    expect((float) $item->parcel_rate)->toBe(5.55);
});

it('defaults parcel rate to 0 when omitted', function () {
    $svc = new MenuService();
    $item = $svc->createItem([
        'name' => 'No Parcel Tea',
        'price' => 15.00,
        'category_id' => $this->category->id,
    ]);
    expect((float) $item->parcel_rate)->toBe(0.0);
});

it('rejects an out-of-range parcel rate', function () {
    $svc = new MenuService();
    $svc->createItem([
        'name' => 'Bad Tea',
        'price' => 15.00,
        'category_id' => $this->category->id,
        'parcel_rate' => 10000,
    ]);
})->throws(ValidationException::class);

// ── Order snapshot + merge ──
it('snapshots the parcel rate and flag onto the order item', function () {
    $tea = teaWithParcel(15.00, 5.00);
    $table = Table::factory()->create();
    $order = $this->orderService->createOrder($table->id, [
        ['menu_item_id' => $tea->id, 'quantity' => 3, 'is_parcel' => true],
    ]);
    $line = $order->orderItems->first();
    expect($line->is_parcel)->toBeTrue();
    expect((float) $line->parcel_rate)->toBe(5.00);
});

it('keeps parcel and dine-in lines of the same item separate', function () {
    $tea = teaWithParcel(15.00, 5.00);
    $table = Table::factory()->create();
    $order = $this->orderService->createOrder($table->id, [
        ['menu_item_id' => $tea->id, 'quantity' => 2, 'is_parcel' => false],
        ['menu_item_id' => $tea->id, 'quantity' => 3, 'is_parcel' => true],
    ]);
    expect($order->orderItems)->toHaveCount(2);
});

it('snapshot is immutable when menu rate later changes', function () {
    $tea = teaWithParcel(15.00, 5.00);
    $table = Table::factory()->create();
    $order = $this->orderService->createOrder($table->id, [
        ['menu_item_id' => $tea->id, 'quantity' => 1, 'is_parcel' => true],
    ]);
    $tea->update(['parcel_rate' => 99.00]);
    expect((float) $order->orderItems->first()->fresh()->parcel_rate)->toBe(5.00);
});

// ── Billing math ──
it('computes items subtotal, parcel charges, and grand total per unit', function () {
    $tea = teaWithParcel(15.00, 5.00);
    $table = Table::factory()->create();
    $this->orderService->createOrder($table->id, [
        ['menu_item_id' => $tea->id, 'quantity' => 2, 'is_parcel' => false],
        ['menu_item_id' => $tea->id, 'quantity' => 3, 'is_parcel' => true],
    ]);
    $bill = $this->billingService->generateBill($table->id);
    expect((float) $bill->items_subtotal)->toBe(75.0);       // 15 * 5
    expect((float) $bill->parcel_charges_total)->toBe(15.0); // 5 * 3
    expect((float) $bill->grand_total)->toBe(90.0);
});

it('a dine-in-only order has zero parcel charges', function () {
    $tea = teaWithParcel(20.00, 5.00);
    $table = Table::factory()->create();
    $this->orderService->createOrder($table->id, [
        ['menu_item_id' => $tea->id, 'quantity' => 2, 'is_parcel' => false],
    ]);
    $bill = $this->billingService->generateBill($table->id);
    expect((float) $bill->parcel_charges_total)->toBe(0.0);
    expect((float) $bill->grand_total)->toBe((float) $bill->items_subtotal);
});

// ── Access control ──
it('blocks staff from setting parcel rate via menu', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff)->post('/menu', [
        'name' => 'X', 'price' => 10, 'category_id' => $this->category->id, 'parcel_rate' => 5,
    ])->assertStatus(403);
});

it('allows admin to set parcel rate via menu', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/menu', [
        'name' => 'Admin Tea', 'price' => 10, 'category_id' => $this->category->id, 'parcel_rate' => 5,
    ])->assertRedirect();
    $this->assertDatabaseHas('menu_items', ['name' => 'Admin Tea', 'parcel_rate' => 5.00]);
});
