<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Resources\SaleResource;

/**
 * Sale endpoints. Behaviour is inherited from AbstractTransactionController;
 * this class only declares the type and resource.
 *
 * Sales are presented with costing information: each sale's cost of goods sold
 * is the WAC at its date times the quantity sold (see SaleResource), and a sale
 * that would oversell available stock is rejected with 422 by the WAC engine.
 */
class SaleController extends AbstractTransactionController
{
    protected function type(): TransactionType
    {
        return TransactionType::Sale;
    }

    protected function resourceClass(): string
    {
        return SaleResource::class;
    }
}
