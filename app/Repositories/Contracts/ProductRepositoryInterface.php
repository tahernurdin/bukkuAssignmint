<?php

namespace App\Repositories\Contracts;

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
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Product;

    /**
     * Update an existing product.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(Product $product, array $attributes): Product;

    /**
     * Delete a product.
     */
    public function delete(Product $product): void;

    /**
     * Whether the product has any transactions recorded against it.
     */
    public function hasTransactions(Product $product): bool;
}
