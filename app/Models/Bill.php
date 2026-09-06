<?php

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'table_id',
        'grand_total',
        'items_subtotal',
        'parcel_charges_total',
        'status',
        'billed_at',
    ];

    protected function casts(): array
    {
        return [
            'grand_total' => 'decimal:2',
            'items_subtotal' => 'decimal:2',
            'parcel_charges_total' => 'decimal:2',
            'status' => BillStatus::class,
            'billed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }
}
