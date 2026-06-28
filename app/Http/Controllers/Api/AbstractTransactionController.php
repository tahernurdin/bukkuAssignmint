<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

/**
 * Shared REST handling for the single-type transaction endpoints (purchases and
 * sales). Purchases and sales are the same resource at the HTTP level — the only
 * things that vary are the transaction type they record and the resource used to
 * present it, so each is supplied by a concrete subclass (Template Method).
 *
 * All the real behaviour (the DB transaction, WAC recalculation and oversell
 * rollback) lives in TransactionService; this base only wires HTTP to it.
 */
abstract class AbstractTransactionController extends Controller
{
    public function __construct(protected readonly TransactionService $transactions) {}

    /**
     * The transaction type this endpoint records.
     */
    abstract protected function type(): TransactionType;

    /**
     * The API resource class used to present transactions of this type.
     *
     * @return class-string<JsonResource>
     */
    abstract protected function resourceClass(): string;

    /**
     * List this type's transactions, oldest first.
     */
    public function index(): AnonymousResourceCollection
    {
        $resource = $this->resourceClass();

        return $resource::collection(
            $this->transactions->listByType($this->type())
        );
    }

    /**
     * Record a new transaction and present it.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $resource = $this->resourceClass();

        $transaction = $this->transactions->create(
            TransactionDTO::forCreate($request, $this->type())
        );

        return $resource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing transaction (bonus) and recost the affected chain.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResource
    {
        $this->ensureType($transaction);

        $resource = $this->resourceClass();

        $updated = $this->transactions->update(
            $transaction,
            TransactionDTO::forUpdate($request, $transaction)
        );

        return $resource::make($updated);
    }

    /**
     * Delete a transaction (bonus) and recost everything after it.
     */
    public function destroy(Transaction $transaction): Response
    {
        $this->ensureType($transaction);
        $this->transactions->delete($transaction);

        return response()->noContent();
    }

    /**
     * Guard against operating on a transaction of the wrong type through this
     * endpoint (e.g. a sale via the purchases endpoint) — treated as a 404.
     */
    protected function ensureType(Transaction $transaction): void
    {
        abort_unless($transaction->type === $this->type(), Response::HTTP_NOT_FOUND);
    }
}
