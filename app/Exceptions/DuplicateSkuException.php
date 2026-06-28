<?php

namespace App\Exceptions;

use App\Exceptions\Concerns\RendersAsValidationError;
use RuntimeException;

/**
 * Thrown when creating or updating a product would reuse a sku already held by
 * another live product. (Soft-deleted products release their sku for reuse.)
 *
 * Self-renders as HTTP 422 in Laravel's validation-error shape.
 */
class DuplicateSkuException extends RuntimeException
{
    use RendersAsValidationError;

    public function __construct(public readonly string $sku)
    {
        parent::__construct(sprintf('The sku "%s" is already taken.', $sku));
    }

    protected function validationField(): string
    {
        return 'sku';
    }
}
