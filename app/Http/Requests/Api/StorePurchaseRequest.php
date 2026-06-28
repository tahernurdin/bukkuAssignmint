<?php

namespace App\Http\Requests\Api;

use App\Enums\TransactionType;

/**
 * Validates a new purchase. A purchase records the unit cost it was bought at
 * (buying_price), which feeds the running weighted-average cost.
 */
class StorePurchaseRequest extends AbstractStoreTransactionRequest
{
    protected function transactionType(): TransactionType
    {
        return TransactionType::Purchase;
    }

    /**
     * @return array<string, mixed>
     */
    protected function priceRules(): array
    {
        return ['buying_price' => ['required', 'numeric', 'decimal:0,2', 'min:0']];
    }

    protected function buyingPrice(): ?string
    {
        return (string) $this->validated('buying_price');
    }
}
