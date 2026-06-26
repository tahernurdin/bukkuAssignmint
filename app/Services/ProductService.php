<?php

namespace App\Services;

use App\DTOs\ProductDTO;
use App\Exceptions\ProductHasTransactionsException;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * The single place that reads and writes the Product model. Controllers pass
 * ids and DTOs; this service owns all persistence.
 */
class ProductService
{
    /**
     * List all products, alphabetically.
     *
     * @return Collection<int, Product>
     */
    public function all(): Collection
    {
        return Product::orderBy('name')->get();
    }

    /**
     * Find a product by id, or throw a 404.
     */
    public function findById(int $id): Product
    {
        return Product::findOrFail($id);
    }

    /**
     * Create a product.
     */
    public function create(ProductDTO $dto): Product
    {
        return Product::create($dto->toAttributes());
    }

    /**
     * Update an existing product.
     */
    public function update(int $id, ProductDTO $dto): Product
    {
        $product = $this->findById($id);
        $product->update($dto->toAttributes());

        return $product;
    }

    /**
     * Delete a product. Refused if it still has transactions, since the FK
     * cascade would otherwise wipe the product's entire ledger.
     *
     * @throws ProductHasTransactionsException
     */
    public function delete(int $id): void
    {
        $product = $this->findById($id);

        if ($product->transactions()->exists()) {
            throw new ProductHasTransactionsException($product->id);
        }

        $product->delete();
    }
}
