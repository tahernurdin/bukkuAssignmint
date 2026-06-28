<?php

namespace App\Exceptions;

use App\Exceptions\Concerns\RendersAsValidationError;
use RuntimeException;

/**
 * Thrown when recording or moving a transaction onto a date a product's ledger
 * already uses. Each product allows one live transaction per date (soft-deleted
 * rows release their date for reuse).
 *
 * Self-renders as HTTP 422 in Laravel's validation-error shape, keyed to `date`,
 * so a collision reads as a field error rather than a constraint-violation 500.
 */
class DuplicateTransactionDateException extends RuntimeException
{
    use RendersAsValidationError;

    public function __construct(
        public readonly int $productId,
        public readonly string $date,
    ) {
        parent::__construct(sprintf(
            'Product %d already has a transaction on %s.',
            $productId,
            $date,
        ));
    }

    protected function validationField(): string
    {
        return 'date';
    }
}
