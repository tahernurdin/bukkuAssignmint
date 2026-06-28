<?php

namespace App\DTOs;

/**
 * Immutable, layer-neutral carrier for a transactions listing query (shared by
 * the purchases and sales endpoints; the type is fixed by the controller, not
 * the query string). The FormRequest builds one (request -> DTO) and the
 * repository turns it into a paginated query (DTO -> storage).
 */
readonly class TransactionFilterDTO
{
    public function __construct(
        public ?int $productId,
        public ?string $dateFrom, // ledger date >= this date (Y-m-d)
        public ?string $dateTo,   // ledger date <= this date (Y-m-d)
        public int $perPage,
        public int $page,
    ) {}
}
