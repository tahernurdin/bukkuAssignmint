<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * List all products available to transact against.
     */
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::orderBy('name')->get());
    }

    /**
     * Show a single product.
     */
    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product);
    }
}
