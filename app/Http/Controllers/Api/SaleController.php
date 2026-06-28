<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSaleRequest;
use App\Http\Requests\Api\UpdateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sale endpoints. Each method wires HTTP to TransactionService, which owns the
 * real behaviour (the DB transaction, WAC recalculation and oversell rollback);
 * update and destroy are the bonus features.
 *
 * Sales are presented with costing information: each sale's cost of goods sold
 * is the WAC at its date times the quantity sold (see SaleResource), and a sale
 * that would oversell available stock is rejected with 422 by the WAC engine.
 */
class SaleController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    /**
     * List sales (with costing), oldest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return SaleResource::collection(
            $this->transactions->listByType(TransactionType::Sale)
        );
    }

    /**
     * Record a new sale and present it with its costing snapshot.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $transaction = $this->transactions->create($request->toDto());

        return SaleResource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a sale and recost the affected chain. The service looks the id up
     * scoped to sales, so a purchase's id (or a soft-deleted one) is a 404.
     */
    public function update(UpdateSaleRequest $request, int $id): SaleResource
    {
        return SaleResource::make(
            $this->transactions->update(TransactionType::Sale, $id, $request->toDto())
        );
    }

    /**
     * Delete a sale and recost everything after it. As with update, the lookup
     * is scoped to sales, so a wrong-type or soft-deleted id is a 404.
     */
    public function destroy(int $id): Response
    {
        $this->transactions->delete(TransactionType::Sale, $id);

        return response()->noContent();
    }
}
