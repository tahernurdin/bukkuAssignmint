<?php

namespace App\Exceptions;

use App\Exceptions\Concerns\RendersAsValidationError;
use RuntimeException;

/**
 * Thrown when a sale (or a recalculation triggered by an out-of-order insert,
 * update, or delete) would drive a product's quantity on hand below zero.
 *
 * Self-renders as HTTP 422 in Laravel's validation-error shape, so API clients
 * can handle it uniformly.
 */
class InsufficientStockException extends RuntimeException
{
    use RendersAsValidationError;

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

    protected function validationField(): string
    {
        return 'quantity';
    }

    protected function validationMessage(): string
    {
        return 'Insufficient stock to record this sale.';
    }
}
