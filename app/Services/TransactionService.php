<?php

namespace App\Services;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Inventory\WacLedgerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Application service for recording and listing transactions.
 *
 * Every mutation runs in a database transaction and then asks the WAC engine
 * to recalculate from the affected date forward, so the persisted snapshots
 * are always consistent. Because create, update and delete all funnel through
 * the same recalculation, out-of-order inserts and edits (the bonus features)
 * are handled by the same code path as a plain append — and any resulting
 * oversell rolls the whole operation back.
 */
class TransactionService
{
    public function __construct(private readonly WacLedgerService $ledger) {}

    /**
     * Record a new transaction and cost the affected chain.
     */
    public function create(TransactionDTO $dto): Transaction
    {
        return DB::transaction(function () use ($dto) {
            $transaction = Transaction::create($dto->toAttributes());
            $this->ledger->recalculateFrom($transaction->product, $dto->date);

            return $transaction->refresh();
        });
    }

    /**
     * Update a transaction and recalculate from the earliest affected date
     * (its old date or new date, whichever is earlier).
     */
    public function update(Transaction $transaction, TransactionDTO $dto): Transaction
    {
        return DB::transaction(function () use ($transaction, $dto) {
            $earliestAffected = min($transaction->date->toDateString(), $dto->date);
            $transaction->update($dto->toAttributes());
            $this->ledger->recalculateFrom($transaction->product, $earliestAffected);

            return $transaction->refresh();
        });
    }

    /**
     * Delete a transaction and recalculate everything after it.
     */
    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $product = $transaction->product;
            $fromDate = $transaction->date->toDateString();
            $transaction->delete();
            $this->ledger->recalculateFrom($product, $fromDate);
        });
    }

    /**
     * List transactions of a given type, oldest first, optionally for one product.
     *
     * @return Collection<int, Transaction>
     */
    public function listByType(TransactionType $type, ?int $productId = null): Collection
    {
        return Transaction::query()
            ->where('type', $type)
            ->when($productId, fn ($query) => $query->forProduct($productId))
            ->with('product')
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }
}
