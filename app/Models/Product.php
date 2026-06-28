<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sku'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    // Deletes are soft (kept for audit). Soft-deleting a product also avoids
    // firing the product_id cascade, so its transaction history survives.
    use SoftDeletes;

    /**
     * Get the transactions (purchases and sales) recorded against this product.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The product's most recent transaction. Its stored snapshot (quantity and
     * value on hand, running WAC) is the product's current inventory state, so
     * eager-loading this one row is all the costing endpoints need.
     *
     * Dates are unique per product, so "latest by date" is unambiguous. The
     * SoftDeletes scope excludes deleted rows, so deleting the latest
     * transaction automatically promotes the prior one.
     */
    public function latestTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)->latestOfMany('date');
    }
}
