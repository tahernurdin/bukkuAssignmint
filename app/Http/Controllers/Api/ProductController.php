<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexProductRequest;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    /**
     * List products available to transact against, paginated; optionally
     * filtered by a name/sku search and a created_at range.
     */
    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $this->products->paginate($request->toFilter())
        );
    }

    /**
     * Create a product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->toDto());

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a single product by id.
     */
    public function show(int $id): ProductResource
    {
        return ProductResource::make($this->products->findById($id));
    }

    /**
     * Update a product by id.
     */
    public function update(UpdateProductRequest $request, int $id): ProductResource
    {
        return ProductResource::make(
            $this->products->update($id, $request->toDto())
        );
    }

    /**
     * Delete a product by id (refused with 409 if it has transactions).
     */
    public function destroy(int $id): Response
    {
        $this->products->delete($id);

        return response()->noContent();
    }
}
