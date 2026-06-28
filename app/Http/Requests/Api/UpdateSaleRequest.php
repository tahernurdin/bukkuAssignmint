<?php

namespace App\Http\Requests\Api;

/**
 * Validates an update to a sale (bonus feature). A sale carries no price, so
 * only its date and quantity may change.
 */
class UpdateSaleRequest extends AbstractUpdateTransactionRequest
{
}
