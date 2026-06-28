<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexTransactionRequest;
use App\Http\Requests\Api\StorePurchaseRequest;
use App\Http\Requests\Api\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Purchase endpoints. Each method wires HTTP to TransactionService, which owns
 * the real behaviour (the DB transaction, WAC recalculation and oversell
 * rollback); update and destroy are the bonus features.
 */
class PurchaseController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    /**
     * List purchases, oldest first, paginated; optionally filtered by product
     * and a ledger-date range.
     */
    public function index(IndexTransactionRequest $request): AnonymousResourceCollection
    {
        return PurchaseResource::collection(
            $this->transactions->listByType(TransactionType::Purchase, $request->toFilter())
        );
    }

    /**
     * Record a new purchase and present the resulting snapshot.
     */
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $transaction = $this->transactions->create($request->toDto());

        return PurchaseResource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a purchase and recost the affected chain. The service looks the id
     * up scoped to purchases, so a sale's id (or a soft-deleted one) is a 404.
     */
    public function update(UpdatePurchaseRequest $request, int $id): PurchaseResource
    {
        return PurchaseResource::make(
            $this->transactions->update(TransactionType::Purchase, $id, $request->toDto())
        );
    }

    /**
     * Delete a purchase and recost everything after it. As with update, the
     * lookup is scoped to purchases, so a wrong-type or soft-deleted id is a 404.
     */
    public function destroy(int $id): Response
    {
        $this->transactions->delete(TransactionType::Purchase, $id);

        return response()->noContent();
    }
}
