<?php

namespace App\Repositories\Eloquent;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentTransactionRepository implements TransactionRepositoryInterface
{
    public function find(int $id): ?Transaction
    {
        return Transaction::find($id);
    }

    public function create(array $attributes): Transaction
    {
        return Transaction::create($attributes);
    }

    public function update(Transaction $transaction, array $attributes): Transaction
    {
        $transaction->update($attributes);

        return $transaction;
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }

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

    public function snapshotBefore(int $productId, string $date): ?Transaction
    {
        return Transaction::forProduct($productId)
            ->where('date', '<', $date)
            ->orderByDesc('date')
            ->lockForUpdate()
            ->first();
    }

    public function chainFrom(int $productId, string $date): Collection
    {
        return Transaction::forProduct($productId)
            ->where('date', '>=', $date)
            ->orderBy('date')
            ->lockForUpdate()
            ->get();
    }

    public function save(Transaction $transaction): void
    {
        $transaction->save();
    }
}
