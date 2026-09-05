<?php

use App\Models\Category;
use App\Models\MenuItem;
use App\Services\MenuService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = new MenuService();
    $this->category = Category::factory()->create(['name' => 'Tea']);
    $this->menuItem = MenuItem::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Green Tea',
        'price' => 5.00,
    ]);
});

it('creates a sub-variety with valid data', function () {
    $subVariety = $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Lemon Green Tea',
        'price_adjustment' => 1.50,
    ]);

    expect($subVariety)->not->toBeNull();
    expect($subVariety->name)->toBe('Lemon Green Tea');
    expect((float) $subVariety->price_adjustment)->toBe(1.50);
    expect($subVariety->menu_item_id)->toBe($this->menuItem->id);
    expect($subVariety->is_active)->toBeTrue();
});

it('creates a sub-variety without price adjustment', function () {
    $subVariety = $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Iced Green Tea',
    ]);

    expect($subVariety->name)->toBe('Iced Green Tea');
    expect((float) $subVariety->price_adjustment)->toBe(0.00);
});

it('creates a sub-variety with null price adjustment', function () {
    $subVariety = $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Mint Tea',
        'price_adjustment' => null,
    ]);

    expect($subVariety->name)->toBe('Mint Tea');
    expect((float) $subVariety->price_adjustment)->toBe(0.00);
});

it('creates a sub-variety with negative price adjustment', function () {
    $subVariety = $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Small Green Tea',
        'price_adjustment' => -1.00,
    ]);

    expect((float) $subVariety->price_adjustment)->toBe(-1.00);
});

it('trims whitespace from sub-variety name', function () {
    $subVariety = $this->service->createSubVariety($this->menuItem->id, [
        'name' => '  Honey Green Tea  ',
        'price_adjustment' => 2.00,
    ]);

    expect($subVariety->name)->toBe('Honey Green Tea');
});

it('rejects sub-variety with empty name', function () {
    $this->service->createSubVariety($this->menuItem->id, [
        'name' => '',
    ]);
})->throws(ValidationException::class);

it('rejects sub-variety with whitespace-only name', function () {
    $this->service->createSubVariety($this->menuItem->id, [
        'name' => '   ',
    ]);
})->throws(ValidationException::class);

it('rejects sub-variety with name exceeding 100 characters', function () {
    $this->service->createSubVariety($this->menuItem->id, [
        'name' => str_repeat('a', 101),
    ]);
})->throws(ValidationException::class);

it('accepts sub-variety with name at exactly 100 characters', function () {
    $subVariety = $this->service->createSubVariety($this->menuItem->id, [
        'name' => str_repeat('a', 100),
    ]);

    expect(mb_strlen($subVariety->name))->toBe(100);
});

it('rejects sub-variety for non-existent menu item', function () {
    $this->service->createSubVariety(99999, [
        'name' => 'Ghost Tea',
    ]);
})->throws(ValidationException::class);

it('rejects sub-variety with non-numeric price adjustment', function () {
    $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Bad Tea',
        'price_adjustment' => 'not-a-number',
    ]);
})->throws(ValidationException::class);

it('rejects sub-variety with price adjustment exceeding range', function () {
    $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Expensive Tea',
        'price_adjustment' => 100000.00,
    ]);
})->throws(ValidationException::class);

it('associates sub-variety with parent menu item', function () {
    $subVariety = $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Jasmine Green Tea',
        'price_adjustment' => 0.75,
    ]);

    $subVariety->refresh();
    expect($subVariety->menuItem->id)->toBe($this->menuItem->id);
    expect($subVariety->menuItem->category_id)->toBe($this->category->id);
});

it('returns sub-varieties grouped under parent category in listing', function () {
    $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Lemon Tea',
        'price_adjustment' => 0.50,
    ]);
    $this->service->createSubVariety($this->menuItem->id, [
        'name' => 'Ginger Tea',
        'price_adjustment' => 0.75,
    ]);

    $categories = $this->service->listByCategory();

    $teaCategory = $categories->firstWhere('name', 'Tea');
    expect($teaCategory)->not->toBeNull();

    $greenTea = $teaCategory->menuItems->firstWhere('name', 'Green Tea');
    expect($greenTea)->not->toBeNull();
    expect($greenTea->subVarieties)->toHaveCount(2);

    $subVarietyNames = $greenTea->subVarieties->pluck('name')->toArray();
    expect($subVarietyNames)->toContain('Lemon Tea');
    expect($subVarietyNames)->toContain('Ginger Tea');
});
