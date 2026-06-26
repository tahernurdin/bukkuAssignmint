<?php

namespace Tests\Doubles;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * In-memory TransactionRepository for unit tests. Holds Transaction instances
 * in an array so the WAC engine can be exercised with no database at all.
 * `save` is a no-op because the engine mutates the instances in place.
 */
class InMemoryTransactionRepository implements TransactionRepositoryInterface
{
    /** @var list<Transaction> */
    private array $transactions = [];

    private int $autoId = 1;

    /**
     * Seed an instance into the repository (test helper), assigning an id.
     */
    public function add(Transaction $transaction): Transaction
    {
        if (! $transaction->getKey()) {
            $transaction->id = $this->autoId++;
        }
        $this->transactions[] = $transaction;

        return $transaction;
    }

    public function find(int $id): ?Transaction
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->id === $id) {
                return $transaction;
            }
        }

        return null;
    }

    public function create(array $attributes): Transaction
    {
        return $this->add(new Transaction($attributes));
    }

    public function update(Transaction $transaction, array $attributes): Transaction
    {
        $transaction->fill($attributes);

        return $transaction;
    }

    public function delete(Transaction $transaction): void
    {
        $this->transactions = array_values(
            array_filter($this->transactions, fn (Transaction $t) => $t !== $transaction)
        );
    }

    public function listByType(TransactionType $type, ?int $productId = null): Collection
    {
        return $this->sortedByDate(array_filter(
            $this->transactions,
            fn (Transaction $t) => $t->type === $type
                && ($productId === null || $t->product_id === $productId),
        ));
    }

    public function snapshotBefore(int $productId, string $date): ?Transaction
    {
        $before = $this->sortedByDate(array_filter(
            $this->transactions,
            fn (Transaction $t) => $t->product_id === $productId && $t->date->toDateString() < $date,
        ))->all();

        return empty($before) ? null : end($before);
    }

    public function chainFrom(int $productId, string $date): Collection
    {
        return $this->sortedByDate(array_filter(
            $this->transactions,
            fn (Transaction $t) => $t->product_id === $productId && $t->date->toDateString() >= $date,
        ));
    }

    public function save(Transaction $transaction): void
    {
        // The engine mutates the instance in place; nothing to persist in memory.
    }

    /**
     * @param iterable<Transaction> $items
     * @return Collection<int, Transaction>
     */
    private function sortedByDate(iterable $items): Collection
    {
        $items = array_values(is_array($items) ? $items : iterator_to_array($items));
        usort($items, fn (Transaction $a, Transaction $b) => $a->date->toDateString() <=> $b->date->toDateString());

        return new Collection($items);
    }
}
