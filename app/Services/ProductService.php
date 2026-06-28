<?php

namespace App\Services;

use App\DTOs\ProductDTO;
use App\Exceptions\ProductHasTransactionsException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Application service for products. Persistence is delegated to the repository;
 * this layer owns the domain rules (e.g. not deleting a product still in use).
 */
class ProductService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * List all products, alphabetically.
     *
     * @return Collection<int, Product>
     */
    public function all(): Collection
    {
        return $this->products->all();
    }

    /**
     * Find a product by id, or throw a 404.
     */
    public function findById(int $id): Product
    {
        return $this->products->find($id)
            ?? throw (new ModelNotFoundException())->setModel(Product::class, [$id]);
    }

    /**
     * Create a product.
     */
    public function create(ProductDTO $dto): Product
    {
        return $this->products->create($dto);
    }

    /**
     * Update an existing product.
     */
    public function update(int $id, ProductDTO $dto): Product
    {
        return $this->products->update($this->findById($id), $dto);
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

        if ($this->products->hasTransactions($product)) {
            throw new ProductHasTransactionsException($product->id);
        }

        $this->products->delete($product);
    }
}
