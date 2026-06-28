<?php

namespace App\DTOs;

/**
 * Immutable, layer-neutral carrier for the products listing query: the optional
 * filters plus the page size. The FormRequest builds one (request -> DTO) and
 * the repository turns it into a paginated query (DTO -> storage).
 */
readonly class ProductFilterDTO
{
    public function __construct(
        public ?string $search,      // partial match on name or sku
        public ?string $createdFrom, // created_at >= this date (Y-m-d)
        public ?string $createdTo,   // created_at <= this date (Y-m-d)
        public int $perPage,
        public int $page,
    ) {}
}
