<?php

namespace App\Repositories\Eloquent;

use App\DTOs\TransactionDTO;
use App\DTOs\TransactionUpdateDTO;
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

    public function findOfType(TransactionType $type, int $id): ?Transaction
    {
        return Transaction::where('type', $type)->find($id);
    }

    public function create(TransactionDTO $dto): Transaction
    {
        return Transaction::create($this->toColumns($dto));
    }

    public function update(Transaction $transaction, TransactionUpdateDTO $dto): Transaction
    {
        $transaction->update([
            'date' => $dto->date,
            'quantity' => $dto->quantity,
            'price' => $dto->price,
        ]);

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

    /**
     * Map the DTO onto the transaction's persistable columns.
     *
     * @return array<string, mixed>
     */
    private function toColumns(TransactionDTO $dto): array
    {
        return [
            'product_id' => $dto->productId,
            'type' => $dto->type,
            'date' => $dto->date,
            'quantity' => $dto->quantity,
            'price' => $dto->price,
        ];
    }
}
