<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Resources\PurchaseResource;

/**
 * Purchase endpoints. All behaviour is inherited from
 * AbstractTransactionController; this class only declares the type it records
 * and the resource used to present it.
 */
class PurchaseController extends AbstractTransactionController
{
    protected function type(): TransactionType
    {
        return TransactionType::Purchase;
    }

    protected function resourceClass(): string
    {
        return PurchaseResource::class;
    }
}
