<?php

namespace App\Repositories\Contracts;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use App\Models\Transaction;
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
     * Persist a new transaction.
     */
    public function create(TransactionDTO $dto): Transaction;

    /**
     * Update an existing transaction.
     */
    public function update(Transaction $transaction, TransactionDTO $dto): Transaction;

    /**
     * Delete a transaction.
     */
    public function delete(Transaction $transaction): void;

    /**
     * List transactions of a given type, oldest first, optionally for one product.
     *
     * @return Collection<int, Transaction>
     */
    public function listByType(TransactionType $type, ?int $productId = null): Collection;

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
