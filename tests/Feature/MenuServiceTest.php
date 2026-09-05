<?php

use App\Contracts\MenuServiceInterface;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\SubVariety;
use App\Services\MenuService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(MenuServiceInterface::class);
});

describe('createItem', function () {
    it('creates a menu item with valid data', function () {
        $category = Category::factory()->create();

        $item = $this->service->createItem([
            'name' => 'Green Tea',
            'price' => 4.50,
            'category_id' => $category->id,
        ]);

        expect($item)->toBeInstanceOf(MenuItem::class)
            ->and($item->name)->toBe('Green Tea')
            ->and((float) $item->price)->toBe(4.50)
            ->and($item->category_id)->toBe($category->id)
            ->and($item->is_active)->toBeTrue();
    });

    it('trims whitespace from name before saving', function () {
        $category = Category::factory()->create();

        $item = $this->service->createItem([
            'name' => '  Lemon Tea  ',
            'price' => 3.00,
            'category_id' => $category->id,
        ]);

        expect($item->name)->toBe('Lemon Tea');
    });

    it('rejects a name that is empty after trimming', function () {
        $category = Category::factory()->create();

        $this->service->createItem([
            'name' => '   ',
            'price' => 3.00,
            'category_id' => $category->id,
        ]);
    })->throws(ValidationException::class);

    it('rejects a name exceeding 100 characters', function () {
        $category = Category::factory()->create();

        $this->service->createItem([
            'name' => str_repeat('a', 101),
            'price' => 3.00,
            'category_id' => $category->id,
        ]);
    })->throws(ValidationException::class);

    it('rejects a price below 0.01', function () {
        $category = Category::factory()->create();

        $this->service->createItem([
            'name' => 'Test Item',
            'price' => 0.00,
            'category_id' => $category->id,
        ]);
    })->throws(ValidationException::class);

    it('rejects a price above 99999.99', function () {
        $category = Category::factory()->create();

        $this->service->createItem([
            'name' => 'Test Item',
            'price' => 100000.00,
            'category_id' => $category->id,
        ]);
    })->throws(ValidationException::class);

    it('rejects a non-existent category', function () {
        $this->service->createItem([
            'name' => 'Test Item',
            'price' => 5.00,
            'category_id' => 9999,
        ]);
    })->throws(ValidationException::class);

    it('rejects a duplicate name within the same category', function () {
        $category = Category::factory()->create();
        MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Espresso',
        ]);

        $this->service->createItem([
            'name' => 'Espresso',
            'price' => 5.00,
            'category_id' => $category->id,
        ]);
    })->throws(ValidationException::class);

    it('allows the same name in different categories', function () {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        MenuItem::factory()->create([
            'category_id' => $category1->id,
            'name' => 'Special',
        ]);

        $item = $this->service->createItem([
            'name' => 'Special',
            'price' => 5.00,
            'category_id' => $category2->id,
        ]);

        expect($item->name)->toBe('Special');
    });

    it('accepts boundary price of 0.01', function () {
        $category = Category::factory()->create();

        $item = $this->service->createItem([
            'name' => 'Cheapest Item',
            'price' => 0.01,
            'category_id' => $category->id,
        ]);

        expect((float) $item->price)->toBe(0.01);
    });

    it('accepts boundary price of 99999.99', function () {
        $category = Category::factory()->create();

        $item = $this->service->createItem([
            'name' => 'Most Expensive',
            'price' => 99999.99,
            'category_id' => $category->id,
        ]);

        expect((float) $item->price)->toBe(99999.99);
    });
});

describe('updateItem', function () {
    it('updates a menu item with valid data', function () {
        $category = Category::factory()->create();
        $item = MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Old Name',
            'price' => 3.00,
        ]);

        $updated = $this->service->updateItem($item->id, [
            'name' => 'New Name',
            'price' => 5.50,
            'category_id' => $category->id,
        ]);

        expect($updated->name)->toBe('New Name')
            ->and((float) $updated->price)->toBe(5.50);
    });

    it('rejects duplicate name on rename within same category', function () {
        $category = Category::factory()->create();
        MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Existing Item',
        ]);
        $item = MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Another Item',
        ]);

        $this->service->updateItem($item->id, [
            'name' => 'Existing Item',
            'price' => 5.00,
            'category_id' => $category->id,
        ]);
    })->throws(ValidationException::class);

    it('allows keeping the same name when updating other fields', function () {
        $category = Category::factory()->create();
        $item = MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Same Name',
            'price' => 3.00,
        ]);

        $updated = $this->service->updateItem($item->id, [
            'name' => 'Same Name',
            'price' => 7.00,
            'category_id' => $category->id,
        ]);

        expect((float) $updated->price)->toBe(7.00);
    });

    it('validates price on update', function () {
        $category = Category::factory()->create();
        $item = MenuItem::factory()->create([
            'category_id' => $category->id,
        ]);

        $this->service->updateItem($item->id, [
            'name' => 'Valid Name',
            'price' => -1.00,
            'category_id' => $category->id,
        ]);
    })->throws(ValidationException::class);
});

describe('deactivateItem', function () {
    it('sets is_active to false', function () {
        $item = MenuItem::factory()->create(['is_active' => true]);

        $this->service->deactivateItem($item->id);

        $item->refresh();
        expect($item->is_active)->toBeFalse();
    });

    it('does not delete the item', function () {
        $item = MenuItem::factory()->create(['is_active' => true]);

        $this->service->deactivateItem($item->id);

        expect(MenuItem::find($item->id))->not->toBeNull();
    });
});

describe('listByCategory', function () {
    it('returns categories with active menu items and sub-varieties', function () {
        $category = Category::factory()->create(['name' => 'Tea']);
        $activeItem = MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Green Tea',
            'is_active' => true,
        ]);
        MenuItem::factory()->create([
            'category_id' => $category->id,
            'name' => 'Inactive Tea',
            'is_active' => false,
        ]);
        SubVariety::factory()->create([
            'menu_item_id' => $activeItem->id,
            'name' => 'Honey Green Tea',
            'is_active' => true,
        ]);
        SubVariety::factory()->create([
            'menu_item_id' => $activeItem->id,
            'name' => 'Inactive Variety',
            'is_active' => false,
        ]);

        $result = $this->service->listByCategory();

        $teaCategory = $result->firstWhere('name', 'Tea');
        expect($teaCategory)->not->toBeNull();
        expect($teaCategory->menuItems)->toHaveCount(1);
        expect($teaCategory->menuItems->first()->name)->toBe('Green Tea');
        expect($teaCategory->menuItems->first()->subVarieties)->toHaveCount(1);
        expect($teaCategory->menuItems->first()->subVarieties->first()->name)->toBe('Honey Green Tea');
    });

    it('returns all categories even those without items', function () {
        Category::factory()->create(['name' => 'Empty Category']);

        $result = $this->service->listByCategory();

        $empty = $result->firstWhere('name', 'Empty Category');
        expect($empty)->not->toBeNull();
        expect($empty->menuItems)->toHaveCount(0);
    });
});

describe('getActiveItems', function () {
    it('returns only active items', function () {
        MenuItem::factory()->count(3)->create(['is_active' => true]);
        MenuItem::factory()->count(2)->create(['is_active' => false]);

        $result = $this->service->getActiveItems();

        expect($result)->toHaveCount(3);
        $result->each(fn ($item) => expect($item->is_active)->toBeTrue());
    });

    it('returns an empty collection when no active items exist', function () {
        MenuItem::factory()->count(2)->create(['is_active' => false]);

        $result = $this->service->getActiveItems();

        expect($result)->toHaveCount(0);
    });
});
