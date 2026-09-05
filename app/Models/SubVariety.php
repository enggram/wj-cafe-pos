<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubVariety extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'name',
        'price_adjustment',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
