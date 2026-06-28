<?php

namespace App\DTOs;

/**
 * Immutable carrier for the *mutable* fields of a transaction update.
 *
 * Product and type are fixed for the life of a transaction (it never jumps
 * ledgers or flips between purchase and sale), so an update only ever changes
 * date, quantity and (for purchases) the buying price. The immutable fields
 * aren't carried here: the
 * service applies this onto the existing row it has already loaded by id.
 *
 * Monetary/quantity values are kept as strings so they flow into the BCMath
 * WAC engine without ever passing through a binary float.
 */
readonly class TransactionUpdateDTO
{
    public function __construct(
        public string $date,
        public string $quantity,
        public ?string $buyingPrice, // unit purchase cost; null for sales
    ) {}
}
