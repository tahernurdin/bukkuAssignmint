<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'type',
    'date',
    'quantity',
    'price',
    'calculated_cost',
    'wac_at_time',
    'quantity_on_hand',
    'value_on_hand',
])]
class Transaction extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'quantity' => 'decimal:2',
            'price' => 'decimal:2',
            'calculated_cost' => 'decimal:2',
            'wac_at_time' => 'decimal:2',
            'quantity_on_hand' => 'decimal:2',
            'value_on_hand' => 'decimal:2',
        ];
    }

    /**
     * Get the product that owns this transaction.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
