<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
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
            $request->toDto($this->type())
        );

        return $resource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing transaction (bonus) and recost the affected chain. The
     * service looks the id up scoped to this endpoint's type, so a wrong-type id
     * (e.g. a sale via the purchases endpoint) or a soft-deleted one is a 404.
     */
    public function update(UpdateTransactionRequest $request, int $id): JsonResource
    {
        $resource = $this->resourceClass();

        return $resource::make(
            $this->transactions->update($this->type(), $id, $request->toDto())
        );
    }

    /**
     * Delete a transaction (bonus) and recost everything after it. As with
     * update, the lookup is scoped to this endpoint's type, so a wrong-type or
     * soft-deleted id is a 404.
     */
    public function destroy(int $id): Response
    {
        $this->transactions->delete($this->type(), $id);

        return response()->noContent();
    }
}
