<?php

namespace App\Services;

use App\Contracts\MenuServiceInterface;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\SubVariety;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MenuService implements MenuServiceInterface
{
    public function createItem(array $data): MenuItem
    {
        $data = $this->validateItemData($data);

        $this->checkDuplicateName($data['name'], $data['category_id']);

        return MenuItem::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'parcel_rate' => $data['parcel_rate'],
            'category_id' => $data['category_id'],
            'is_active' => true,
        ]);
    }

    public function updateItem(int $id, array $data): MenuItem
    {
        $menuItem = MenuItem::findOrFail($id);

        $data = $this->validateItemData($data);

        $this->checkDuplicateName($data['name'], $data['category_id'], $id);

        $menuItem->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'parcel_rate' => $data['parcel_rate'],
            'category_id' => $data['category_id'],
        ]);

        return $menuItem->fresh();
    }

    public function deactivateItem(int $id): void
    {
        $menuItem = MenuItem::findOrFail($id);
        $menuItem->update(['is_active' => false]);
    }

    public function listByCategory(): Collection
    {
        return Category::with(['menuItems' => function ($query) {
            $query->where('is_active', true);
        }, 'menuItems.subVarieties' => function ($query) {
            $query->where('is_active', true);
        }])->get();
    }

    public function getActiveItems(): Collection
    {
        return MenuItem::where('is_active', true)->get();
    }

    public function createSubVariety(int $menuItemId, array $data): SubVariety
    {
        $menuItem = MenuItem::find($menuItemId);

        if (!$menuItem) {
            throw ValidationException::withMessages([
                'menu_item_id' => ['The specified menu item does not exist.'],
            ]);
        }

        $errors = [];

        // Validate name
        $name = isset($data['name']) ? trim($data['name']) : '';
        if ($name === '') {
            $errors['name'] = ['The name field is required.'];
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = ['The name must not exceed 100 characters.'];
        }

        // Validate optional price_adjustment
        if (array_key_exists('price_adjustment', $data) && $data['price_adjustment'] !== null) {
            $priceAdjustment = $data['price_adjustment'];
            if (!is_numeric($priceAdjustment)) {
                $errors['price_adjustment'] = ['The price adjustment must be a numeric value.'];
            } elseif ((float) $priceAdjustment < -99999.99 || (float) $priceAdjustment > 99999.99) {
                $errors['price_adjustment'] = ['The price adjustment must be between -99999.99 and 99999.99.'];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return SubVariety::create([
            'menu_item_id' => $menuItem->id,
            'name' => $name,
            'price_adjustment' => $data['price_adjustment'] ?? 0.00,
            'is_active' => true,
        ]);
    }

    private function validateItemData(array $data): array
    {
        $errors = [];

        // Validate name
        $name = isset($data['name']) ? trim($data['name']) : '';
        if ($name === '') {
            $errors['name'] = ['The name field is required.'];
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = ['The name must not exceed 100 characters.'];
        }
        $data['name'] = $name;

        // Validate price
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors['price'] = ['The price field is required and must be numeric.'];
        } elseif ((float) $data['price'] < 0.01 || (float) $data['price'] > 99999.99) {
            $errors['price'] = ['The price must be between 0.01 and 99999.99.'];
        }

        // Validate category
        if (!isset($data['category_id'])) {
            $errors['category_id'] = ['The category field is required.'];
        } elseif (!Category::where('id', $data['category_id'])->exists()) {
            $errors['category_id'] = ['The selected category does not exist.'];
        }

        // Validate optional parcel_rate (default 0.00 when omitted/empty)
        if (array_key_exists('parcel_rate', $data) && $data['parcel_rate'] !== null && $data['parcel_rate'] !== '') {
            if (!is_numeric($data['parcel_rate'])) {
                $errors['parcel_rate'] = ['The parcel rate must be a numeric value.'];
            } elseif ((float) $data['parcel_rate'] < 0.00 || (float) $data['parcel_rate'] > 9999.99) {
                $errors['parcel_rate'] = ['The parcel rate must be between 0.00 and 9999.99.'];
            }
        } else {
            $data['parcel_rate'] = 0.00;
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function checkDuplicateName(string $name, int $categoryId, ?int $excludeId = null): void
    {
        $query = MenuItem::where('name', $name)->where('category_id', $categoryId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => ['A menu item with this name already exists in this category.'],
            ]);
        }
    }
}
