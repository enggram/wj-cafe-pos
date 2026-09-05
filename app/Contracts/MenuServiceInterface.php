<?php

namespace App\Contracts;

use App\Models\MenuItem;
use App\Models\SubVariety;
use Illuminate\Support\Collection;

interface MenuServiceInterface
{
    public function createItem(array $data): MenuItem;

    public function updateItem(int $id, array $data): MenuItem;

    public function deactivateItem(int $id): void;

    public function listByCategory(): Collection;

    public function getActiveItems(): Collection;

    public function createSubVariety(int $menuItemId, array $data): SubVariety;
}
