<?php

namespace App\Repositories\Contracts;

use App\DTOs\TransactionDTO;
use App\DTOs\TransactionFilterDTO;
use App\DTOs\TransactionUpdateDTO;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Persistence boundary for transactions.
 *
 * Beyond plain CRUD, this exposes the three operations the WAC engine needs to
 * replay a product's ledger — modelled as domain methods (not query builders)
 * so the engine can be unit-tested against an in-memory double.
 */
interface TransactionRepositoryInterface
{
    /**
     * Find a transaction by id, or null if it does not exist.
     */
    public function find(int $id): ?Transaction;

    /**
     * Find a transaction of the given type by id, or null. Soft-deleted rows are
     * excluded, so a deleted transaction reads as missing — and an id of the
     * wrong type (a sale looked up as a purchase) also reads as missing.
     */
    public function findOfType(TransactionType $type, int $id): ?Transaction;

    /**
     * Persist a new transaction.
     */
    public function create(TransactionDTO $dto): Transaction;

    /**
     * Update an existing transaction's mutable fields (date, quantity,
     * buying_price); product and type are immutable.
     */
    public function update(Transaction $transaction, TransactionUpdateDTO $dto): Transaction;

    /**
     * Delete a transaction.
     */
    public function delete(Transaction $transaction): void;

    /**
     * A page of transactions of a given type, oldest first, narrowed by the
     * given filter (optional product and ledger-date range).
     *
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function listByType(TransactionType $type, TransactionFilterDTO $filter): LengthAwarePaginator;

    /**
     * The product's most recent transaction strictly before $date (the state to
     * seed a recalculation from), or null if there is none. Locked for update.
     */
    public function snapshotBefore(int $productId, string $date): ?Transaction;

    /**
     * The product's transactions on or after $date, in chronological order.
     * Locked for update.
     *
     * @return Collection<int, Transaction>
     */
    public function chainFrom(int $productId, string $date): Collection;

    /**
     * Persist a recomputed snapshot onto an existing transaction row.
     */
    public function save(Transaction $transaction): void;
}
