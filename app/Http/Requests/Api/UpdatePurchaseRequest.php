<?php

namespace App\Http\Requests\Api;

/**
 * Validates an update to a purchase (bonus feature). A purchase's buying_price
 * is mutable and recosts the chain from the affected date forward.
 */
class UpdatePurchaseRequest extends AbstractUpdateTransactionRequest
{
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
