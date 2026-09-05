<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
