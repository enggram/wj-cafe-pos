<?php

use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds default categories', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    expect(Category::count())->toBe(4);
    expect(Category::pluck('name')->sort()->values()->toArray())
        ->toBe(['Coffee', 'Food', 'Juices', 'Tea']);

    // All categories should be active
    expect(Category::where('is_active', true)->count())->toBe(4);
});

it('seeds 10 tables numbered 1-10 all vacant', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    expect(Table::count())->toBe(10);

    for ($i = 1; $i <= 10; $i++) {
        $table = Table::where('table_number', $i)->first();
        expect($table)->not->toBeNull();
        expect($table->status)->toBe(TableStatus::Vacant);
    }
});

it('is idempotent - running seeder twice does not duplicate data', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    expect(Category::count())->toBe(4);
    expect(Table::count())->toBe(10);
});
