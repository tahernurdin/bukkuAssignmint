<?php

namespace App\Repositories\Eloquent;

use App\DTOs\ProductDTO;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function all(): Collection
    {
        return Product::orderBy('name')->get();
    }

    public function find(int $id): ?Product
    {
        return Product::find($id);
    }

    public function create(ProductDTO $dto): Product
    {
        return Product::create([
            'name' => $dto->name,
            'sku' => $dto->sku,
        ]);
    }

    public function update(Product $product, ProductDTO $dto): Product
    {
        $product->update([
            'name' => $dto->name,
            'sku' => $dto->sku,
        ]);

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function hasTransactions(Product $product): bool
    {
        return $product->transactions()->exists();
    }
}
