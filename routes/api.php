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

    // Products (seeded catalogue) — read only.
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);

    // Purchases and sales. update/destroy are the bonus features; the base
    // requirement only needs index + store.
    Route::apiResource('purchases', PurchaseController::class)->except(['show']);
    Route::apiResource('sales', SaleController::class)->except(['show']);

    // Demonstration of Role Middleware
    Route::middleware('role:admin')->get('/admin-dashboard', function () {
        return response()->json(['message' => 'Welcome to the Admin Dashboard!']);
    });

    Route::middleware('role:user')->get('/user-dashboard', function () {
        return response()->json(['message' => 'Welcome to the User Dashboard!']);
    });
});
