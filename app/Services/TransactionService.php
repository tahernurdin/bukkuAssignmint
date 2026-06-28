<?php

namespace App\Services;

use App\DTOs\TransactionDTO;
use App\DTOs\TransactionUpdateDTO;
use App\Enums\TransactionType;
use App\Exceptions\DuplicateTransactionDateException;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\Inventory\WacLedgerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
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
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly WacLedgerService $ledger,
    ) {}

    /**
     * Find a transaction of the given type by id, or throw a 404. Scoping the
     * lookup to the type is what turns a cross-type id (e.g. a sale reached via
     * the purchases endpoint) into a 404 instead of an edit of the wrong ledger,
     * folding the old per-controller type guard into the read itself.
     */
    public function findOfTypeOrFail(TransactionType $type, int $id): Transaction
    {
        return $this->transactions->findOfType($type, $id)
            ?? throw (new ModelNotFoundException())->setModel(Transaction::class, [$id]);
    }

    /**
     * Record a new transaction and cost the affected chain.
     *
     * @throws DuplicateTransactionDateException if the product already has a live
     *                                           transaction on this date
     */
    public function create(TransactionDTO $dto): Transaction
    {
        try {
            return DB::transaction(function () use ($dto) {
                $transaction = $this->transactions->create($dto);
                $this->ledger->recalculateFrom($dto->productId, $dto->date);

                return $this->transactions->find($transaction->id)->load('product');
            });
        } catch (UniqueConstraintViolationException) {
            // The only unique index on transactions is one live row per
            // product+date, so a violation here can only be a clashing date.
            throw new DuplicateTransactionDateException($dto->productId, $dto->date);
        }
    }

    /**
     * Update a transaction of the given type (looked up here; 404 if missing or
     * the wrong type) and recalculate from the earliest affected date — its old
     * date or new date, whichever is earlier. Product and type are immutable, so
     * the update carries only the mutable fields.
     *
     * @throws DuplicateTransactionDateException if moving the row would collide
     *                                           with another live date
     */
    public function update(TransactionType $type, int $id, TransactionUpdateDTO $dto): Transaction
    {
        $transaction = $this->findOfTypeOrFail($type, $id);

        try {
            return DB::transaction(function () use ($transaction, $dto) {
                $earliestAffected = min($transaction->date->toDateString(), $dto->date);
                $this->transactions->update($transaction, $dto);
                $this->ledger->recalculateFrom($transaction->product_id, $earliestAffected);

                return $this->transactions->find($transaction->id)->load('product');
            });
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateTransactionDateException($transaction->product_id, $dto->date);
        }
    }

    /**
     * Delete a transaction of the given type (looked up here; 404 if missing or
     * the wrong type) and recalculate everything after it.
     */
    public function delete(TransactionType $type, int $id): void
    {
        $transaction = $this->findOfTypeOrFail($type, $id);

        DB::transaction(function () use ($transaction) {
            $productId = $transaction->product_id;
            $fromDate = $transaction->date->toDateString();
            $this->transactions->delete($transaction);
            $this->ledger->recalculateFrom($productId, $fromDate);
        });
    }

    /**
     * List transactions of a given type, oldest first, optionally for one product.
     *
     * @return Collection<int, Transaction>
     */
    public function listByType(TransactionType $type, ?int $productId = null): Collection
    {
        return $this->transactions->listByType($type, $productId);
    }
}
