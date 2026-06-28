<?php

namespace App\DTOs;

use App\Enums\TransactionType;

/**
 * Immutable, layer-neutral carrier for the persistable fields of a transaction.
 *
 * It knows about neither HTTP nor storage: the FormRequest builds one
 * (request -> DTO) and the repository maps it to columns (DTO -> storage).
 *
 * Monetary/quantity values are kept as strings so they flow into the BCMath
 * WAC engine without ever passing through a binary float.
 */
readonly class TransactionDTO
{
    public function __construct(
        public int $productId,
        public TransactionType $type,
        public string $date,
        public string $quantity,
        public ?string $buyingPrice, // unit purchase cost; null for sales
    ) {}
}
