<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SaleController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);

    // Products. ID-based routes (no model binding) so the controller delegates
    // fetching to ProductService.
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->where('id', '[0-9]+');
    Route::patch('/products/{id}', [ProductController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->where('id', '[0-9]+');

    // Purchases and sales. update/destroy are the bonus features; the base
    // requirement only needs index + store. ID-based routes (no model binding)
    // so the controller delegates fetching to TransactionService, which scopes
    // the lookup to the endpoint's type.
    Route::apiResource('purchases', PurchaseController::class)
        ->parameters(['purchases' => 'id'])
        ->except(['show']);
    Route::apiResource('sales', SaleController::class)
        ->parameters(['sales' => 'id'])
        ->except(['show']);

    // Demonstration of Role Middleware
    Route::middleware('role:admin')->get('/admin-dashboard', function () {
        return response()->json(['message' => 'Welcome to the Admin Dashboard!']);
    });

    Route::middleware('role:user')->get('/user-dashboard', function () {
        return response()->json(['message' => 'Welcome to the User Dashboard!']);
    });
});
