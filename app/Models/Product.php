<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
