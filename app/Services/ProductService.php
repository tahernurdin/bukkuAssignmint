<?php

namespace App\Services;

use App\DTOs\ProductDTO;
use App\DTOs\ProductFilterDTO;
use App\Exceptions\DuplicateSkuException;
use App\Exceptions\ProductHasTransactionsException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Application service for products. Persistence is delegated to the repository;
 * this layer owns the domain rules (e.g. not deleting a product still in use).
 */
class ProductService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /**
     * List a page of products, alphabetically, narrowed by the given filter.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(ProductFilterDTO $filter): LengthAwarePaginator
    {
        return $this->products->paginate($filter);
    }

    /**
     * Find a product by id, or throw a 404.
     */
    public function findById(int $id): Product
    {
        return $this->products->find($id)
            ?? throw (new ModelNotFoundException)->setModel(Product::class, [$id]);
    }

    /**
     * Create a product.
     *
     * @throws DuplicateSkuException
     */
    public function create(ProductDTO $dto): Product
    {
        $this->guardSkuIsUnique($dto->sku);

        return $this->products->create($dto);
    }

    /**
     * Update an existing product.
     *
     * @throws DuplicateSkuException
     */
    public function update(int $id, ProductDTO $dto): Product
    {
        $product = $this->findById($id);

        $this->guardSkuIsUnique($dto->sku, $product->id);

        return $this->products->update($product, $dto);
    }

    /**
     * Reject a sku already taken by another live product. The DB carries a
     * matching unique index as the race-safe backstop; this check is what turns
     * a collision into a clean 422 instead of a constraint-violation 500.
     *
     * @throws DuplicateSkuException
     */
    private function guardSkuIsUnique(string $sku, ?int $ignoreId = null): void
    {
        if ($this->products->existsLiveSku($sku, $ignoreId)) {
            throw new DuplicateSkuException($sku);
        }
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
