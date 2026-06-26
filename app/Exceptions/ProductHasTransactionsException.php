<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when deleting a product that still has transactions. Allowing the
 * delete would cascade-remove the product's entire ledger, so it is blocked.
 *
 * Self-renders as HTTP 409 Conflict.
 */
class ProductHasTransactionsException extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct(sprintf(
            'Product %d cannot be deleted because it has recorded transactions.',
            $productId,
        ));
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 409);
    }
}
