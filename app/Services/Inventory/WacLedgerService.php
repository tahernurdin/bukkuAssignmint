<?php

namespace App\Services\Inventory;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;

/**
 * The Weighted Average Cost (WAC) engine.
 *
 * Responsible solely for (re)computing the per-row inventory snapshot
 * (quantity on hand, value on hand, running WAC and, for sales, cost of goods
 * sold) for a product's chronological chain of transactions.
 *
 * All arithmetic uses BCMath at a fixed scale so the running average never
 * suffers binary-float drift. Values are kept at high precision internally
 * (6 dp); callers round to 2 dp for display.
 */
class WacLedgerService
{
    /** Decimal places kept for monetary values (value on hand, WAC, cost). */
    private const SCALE = 6;

    /** Decimal places for quantities (matches the schema's decimal(15,2)). */
    private const QTY_SCALE = 2;

    public function __construct(private readonly TransactionRepositoryInterface $transactions) {}

    /**
     * Recompute the snapshot of every transaction for the product dated on or
     * after $fromDate, replaying them in chronological order.
     *
     * Efficiency: we seed the replay from the snapshot of the transaction
     * immediately *before* $fromDate, so only the rows that can actually be
     * affected by a change at $fromDate are touched — not the whole history.
     *
     * Must be called inside a database transaction (see TransactionService);
     * the repository's row locks keep concurrent writers from interleaving.
     *
     * @throws InsufficientStockException if any sale in the chain oversells.
     */
    public function recalculateFrom(int $productId, string $fromDate): void
    {
        // Starting state = inventory held just before the affected date.
        $previous = $this->transactions->snapshotBefore($productId, $fromDate);

        $quantityOnHand = $previous?->quantity_on_hand ?? '0';
        $valueOnHand = $previous?->value_on_hand ?? '0';

        foreach ($this->transactions->chainFrom($productId, $fromDate) as $transaction) {
            [$quantityOnHand, $valueOnHand] = $this->apply($transaction, $quantityOnHand, $valueOnHand);
        }
    }

    /**
     * Apply a single transaction to the running state, persist its snapshot,
     * and return the new [quantityOnHand, valueOnHand].
     *
     * @return array{0: string, 1: string}
     */
    private function apply(Transaction $transaction, string $quantityBefore, string $valueBefore): array
    {
        $quantity = (string) $transaction->quantity;

        if ($transaction->type === TransactionType::Purchase) {
            // Add purchased units and their value, then recompute the average.
            $buyingPrice = (string) $transaction->buying_price;
            $newQuantity = bcadd($quantityBefore, $quantity, self::QTY_SCALE);
            $newValue = bcadd($valueBefore, bcmul($quantity, $buyingPrice, self::SCALE), self::SCALE);
            $wac = $this->average($newValue, $newQuantity);

            return $this->persist($transaction, null, $wac, $newQuantity, $newValue);
        }

        // Sale: cannot sell more than is on hand.
        if (bccomp($quantity, $quantityBefore, self::QTY_SCALE) > 0) {
            throw new InsufficientStockException(
                productId: $transaction->product_id,
                date: $transaction->date->toDateString(),
                requested: $quantity,
                available: $quantityBefore,
            );
        }

        // Cost of goods sold at the average cost held *before* the sale:
        //   cost = valueBefore * quantity / quantityBefore   (== WAC_before * quantity)
        // Computed in one division to avoid rounding WAC first. quantityBefore > 0
        // here because quantity > 0 and quantity <= quantityBefore.
        $cost = bcdiv(
            bcmul($valueBefore, $quantity, self::SCALE + self::QTY_SCALE),
            $quantityBefore,
            self::SCALE,
        );

        // The WAC applied to this sale (the rate, for reporting).
        $wac = $this->average($valueBefore, $quantityBefore);

        $newQuantity = bcsub($quantityBefore, $quantity, self::QTY_SCALE);
        $newValue = bcsub($valueBefore, $cost, self::SCALE);

        // Fully depleted: hold exactly zero units worth zero (avoid residual dust).
        if (bccomp($newQuantity, '0', self::QTY_SCALE) === 0) {
            $newValue = '0';
        }

        return $this->persist($transaction, $cost, $wac, $newQuantity, $newValue);
    }

    /**
     * Weighted average unit cost = value / quantity, or 0 when nothing is held.
     */
    private function average(string $value, string $quantity): string
    {
        return bccomp($quantity, '0', self::QTY_SCALE) > 0
            ? bcdiv($value, $quantity, self::SCALE)
            : '0';
    }

    /**
     * Persist the computed snapshot onto the transaction row.
     *
     * @return array{0: string, 1: string} the new [quantityOnHand, valueOnHand]
     */
    private function persist(
        Transaction $transaction,
        ?string $calculatedCost,
        string $wac,
        string $quantityOnHand,
        string $valueOnHand,
    ): array {
        $transaction->forceFill([
            'calculated_cost' => $calculatedCost,
            'wac_at_time' => $wac,
            'quantity_on_hand' => $quantityOnHand,
            'value_on_hand' => $valueOnHand,
        ]);
        $this->transactions->save($transaction);

        return [$quantityOnHand, $valueOnHand];
    }
}
