<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-product purchase or sale, plus a snapshot of the resulting
 * inventory state (running WAC, quantity and value on hand).
 *
 * @property TransactionType $type
 */
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
            'type' => TransactionType::class,
            'date' => 'date:Y-m-d',
            'quantity' => 'decimal:2',
            'price' => 'decimal:2',
            'quantity_on_hand' => 'decimal:2',
            // High precision retained internally; rounded for display in Resources.
            'calculated_cost' => 'decimal:6',
            'wac_at_time' => 'decimal:6',
            'value_on_hand' => 'decimal:6',
        ];
    }

    /**
     * Get the product that owns this transaction.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to purchase transactions only.
     */
    public function scopePurchases(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Purchase);
    }

    /**
     * Scope to sale transactions only.
     */
    public function scopeSales(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Sale);
    }

    /**
     * Scope to a single product's ledger.
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
