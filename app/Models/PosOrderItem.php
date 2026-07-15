<?php

namespace App\Models;

use Database\Factories\PosOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderItem extends Model
{
    /** @use HasFactory<PosOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'pos_order_id',
        'pos_menu_item_id',
        'name',
        'unit_price',
        'quantity',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(PosMenuItem::class, 'pos_menu_item_id');
    }
}
