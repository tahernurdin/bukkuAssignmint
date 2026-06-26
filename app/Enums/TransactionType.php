<?php

namespace App\Enums;

/**
 * The kind of inventory movement a transaction represents.
 *
 * A purchase increases quantity on hand; a sale decreases it and incurs a
 * cost-of-sale derived from the Weighted Average Cost (WAC) at that moment.
 */
enum TransactionType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
}
