<?php

namespace App\Repositories\Contracts;

use App\DTOs\ProductDTO;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Persistence boundary for products. Services depend on this interface rather
 * than on Eloquent directly, so the storage implementation is swappable.
 */
interface ProductRepositoryInterface
{
    /**
     * All products, alphabetically.
     *
     * @return Collection<int, Product>
     */
    public function all(): Collection;

    /**
     * Find a product by id, or null if it does not exist.
     */
    public function find(int $id): ?Product;

    /**
     * Persist a new product.
     */
    public function create(ProductDTO $dto): Product;

    /**
     * Update an existing product.
     */
    public function update(Product $product, ProductDTO $dto): Product;

    /**
     * Delete a product.
     */
    public function delete(Product $product): void;

    /**
     * Whether the product has any transactions recorded against it.
     */
    public function hasTransactions(Product $product): bool;

    /**
     * Whether another live product already uses this sku. Soft-deleted products
     * are ignored, so their sku is free for reuse. Pass $ignoreId to exclude the
     * product being updated, so it can keep its own sku.
     */
    public function existsLiveSku(string $sku, ?int $ignoreId = null): bool;
}
