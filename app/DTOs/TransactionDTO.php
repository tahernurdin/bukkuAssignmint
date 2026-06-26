<?php

namespace App\DTOs;

use App\Enums\TransactionType;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Models\Transaction;

/**
 * Immutable carrier for the persistable fields of a transaction.
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
        public string $price,
    ) {}

    /**
     * Build from a create request; the type comes from the endpoint.
     */
    public static function forCreate(StoreTransactionRequest $request, TransactionType $type): self
    {
        return new self(
            productId: (int) $request->validated('product_id'),
            type: $type,
            date: $request->validated('date'),
            quantity: (string) $request->validated('quantity'),
            price: (string) $request->validated('price'),
        );
    }

    /**
     * Build from an update request; product and type are inherited from the
     * existing transaction (immutable), only date/quantity/price may change.
     */
    public static function forUpdate(UpdateTransactionRequest $request, Transaction $existing): self
    {
        return new self(
            productId: $existing->product_id,
            type: $existing->type,
            date: $request->validated('date'),
            quantity: (string) $request->validated('quantity'),
            price: (string) $request->validated('price'),
        );
    }

    /**
     * The fillable attributes for persisting the transaction row.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'product_id' => $this->productId,
            'type' => $this->type,
            'date' => $this->date,
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];
    }
}
