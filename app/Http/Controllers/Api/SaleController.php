<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Http\Resources\SaleResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    /**
     * List sale transactions with costing information, oldest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return SaleResource::collection(
            $this->transactions->listByType(TransactionType::Sale)
        );
    }

    /**
     * Record a new sale. Its cost of goods sold is derived from the WAC at the
     * sale's date; selling more than is on hand is rejected with 422.
     */
    public function store(StoreTransactionRequest $request): Response
    {
        $sale = $this->transactions->create(
            TransactionDTO::forCreate($request, TransactionType::Sale)
        );

        return SaleResource::make($sale->load('product'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an existing sale (bonus) and recost the affected chain.
     */
    public function update(UpdateTransactionRequest $request, Transaction $sale): SaleResource
    {
        $this->ensureSale($sale);

        $updated = $this->transactions->update(
            $sale,
            TransactionDTO::forUpdate($request, $sale)
        );

        return SaleResource::make($updated->load('product'));
    }

    /**
     * Delete a sale (bonus) and recost everything after it.
     */
    public function destroy(Transaction $sale): Response
    {
        $this->ensureSale($sale);
        $this->transactions->delete($sale);

        return response()->noContent();
    }

    /**
     * Guard against operating on a purchase through the sales endpoint.
     */
    private function ensureSale(Transaction $transaction): void
    {
        abort_unless($transaction->type === TransactionType::Sale, Response::HTTP_NOT_FOUND);
    }
}
