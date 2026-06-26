<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PurchaseController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    /**
     * List purchase transactions, oldest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return PurchaseResource::collection(
            $this->transactions->listByType(TransactionType::Purchase)
        );
    }

    /**
     * Record a new purchase.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $purchase = $this->transactions->create(
            TransactionDTO::forCreate($request, TransactionType::Purchase)
        );

        return PurchaseResource::make($purchase->load('product'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing purchase (bonus) and recost the affected chain.
     */
    public function update(UpdateTransactionRequest $request, Transaction $purchase): PurchaseResource
    {
        $this->ensurePurchase($purchase);

        $updated = $this->transactions->update(
            $purchase,
            TransactionDTO::forUpdate($request, $purchase)
        );

        return PurchaseResource::make($updated->load('product'));
    }

    /**
     * Delete a purchase (bonus) and recost everything after it.
     */
    public function destroy(Transaction $purchase): Response
    {
        $this->ensurePurchase($purchase);
        $this->transactions->delete($purchase);

        return response()->noContent();
    }

    /**
     * Guard against operating on a sale through the purchases endpoint.
     */
    private function ensurePurchase(Transaction $transaction): void
    {
        abort_unless($transaction->type === TransactionType::Purchase, Response::HTTP_NOT_FOUND);
    }
}
