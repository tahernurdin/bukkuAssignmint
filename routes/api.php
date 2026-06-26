<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Demonstration of Role Middleware
    Route::middleware('role:admin')->get('/admin-dashboard', function () {
        return response()->json(['message' => 'Welcome to the Admin Dashboard!']);
    });
    
    Route::middleware('role:user')->get('/user-dashboard', function () {
        return response()->json(['message' => 'Welcome to the User Dashboard!']);
    });
});
