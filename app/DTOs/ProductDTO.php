<?php

namespace App\DTOs;

/**
 * Immutable, layer-neutral carrier for the persistable fields of a product.
 * The FormRequest builds one (request -> DTO); the repository maps it to
 * columns (DTO -> storage).
 */
readonly class ProductDTO
{
    public function __construct(
        public string $name,
        public string $sku,
    ) {}
}
