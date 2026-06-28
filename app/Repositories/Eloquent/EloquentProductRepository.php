<?php

namespace App\Repositories\Eloquent;

use App\DTOs\ProductDTO;
use App\DTOs\ProductFilterDTO;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function paginate(ProductFilterDTO $filter): LengthAwarePaginator
    {
        return Product::with('latestTransaction')
            ->when($filter->search, fn ($query) => $query->where(
                fn ($search) => $search
                    ->where('name', 'like', "%{$filter->search}%")
                    ->orWhere('sku', 'like', "%{$filter->search}%")
            ))
            ->when($filter->createdFrom, fn ($query) => $query->whereDate('created_at', '>=', $filter->createdFrom))
            ->when($filter->createdTo, fn ($query) => $query->whereDate('created_at', '<=', $filter->createdTo))
            ->orderBy('name')
            ->paginate($filter->perPage, ['*'], 'page', $filter->page);
    }

    public function find(int $id): ?Product
    {
        return Product::with('latestTransaction')->find($id);
    }

    public function create(ProductDTO $dto): Product
    {
        $product = Product::create([
            'name' => $dto->name,
            'sku' => $dto->sku,
        ]);

        // A brand-new product holds nothing; seed the (empty) snapshot relation
        // so its response carries the same inventory fields as list/show without
        // an extra query.
        $product->setRelation('latestTransaction', null);

        return $product;
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

    public function existsLiveSku(string $sku, ?int $ignoreId = null): bool
    {
        // The SoftDeletes scope already excludes deleted products, so this only
        // ever sees live rows.
        return Product::where('sku', $sku)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
