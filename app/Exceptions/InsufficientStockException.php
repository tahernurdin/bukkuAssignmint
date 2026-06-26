<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a sale (or a recalculation triggered by an out-of-order insert,
 * update, or delete) would drive a product's quantity on hand below zero.
 *
 * Self-renders as HTTP 422 using the same JSON shape Laravel uses for
 * validation errors, so API clients can handle it uniformly.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly string $date,
        public readonly string $requested,
        public readonly string $available,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for product %d on %s: tried to sell %s but only %s on hand.',
            $productId,
            $date,
            $requested,
            $available,
        ));
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Insufficient stock to record this sale.',
            'errors' => [
                'quantity' => [$this->getMessage()],
            ],
        ], 422);
    }
}
