<?php

namespace App\Http\Requests\Api;

use App\Enums\TransactionType;

/**
 * Validates a new sale. A sale carries no price of its own: its cost of goods
 * sold is derived from the weighted-average cost at its date, so the payload is
 * just product, date and quantity.
 */
class StoreSaleRequest extends AbstractStoreTransactionRequest
{
    protected function transactionType(): TransactionType
    {
        return TransactionType::Sale;
    }
}
