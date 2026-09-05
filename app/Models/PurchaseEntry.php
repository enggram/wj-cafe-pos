<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_name',
        'quantity',
        'cost',
        'purchase_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'cost' => 'decimal:2',
            'purchase_date' => 'date',
        ];
    }
}
