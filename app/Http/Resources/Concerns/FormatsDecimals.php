<?php

namespace App\Http\Resources\Concerns;

/**
 * Formats the high-precision values stored internally down to a fixed number
 * of decimal places for API output. Display rounding lives here so the stored
 * ledger keeps its full precision.
 */
trait FormatsDecimals
{
    /**
     * Round a numeric value to $places decimals as a string, preserving
     * trailing zeros (e.g. "1.97"). Returns null for null input.
     */
    protected function decimal(int|float|string|null $value, int $places = 2): ?string
    {
        return $value === null ? null : number_format((float) $value, $places, '.', '');
    }
}
