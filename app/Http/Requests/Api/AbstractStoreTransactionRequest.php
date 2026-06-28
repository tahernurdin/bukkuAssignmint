<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for recording a new transaction. The fields common to both
 * kinds (product, date, quantity) live here; a concrete request adds whatever
 * price field its kind carries and names the transaction type.
 *
 * The transaction *type* is decided by the endpoint (purchase vs sale request),
 * not the payload. "Cannot oversell" is a stateful rule that depends on the WAC
 * chain at the transaction's date, so it is enforced in the engine, not here.
 */
abstract class AbstractStoreTransactionRequest extends FormRequest
{
    /**
     * The transaction type this request records.
     */
    abstract protected function transactionType(): TransactionType;

    /**
     * Type-specific validation rules merged into the shared set (e.g. a
     * purchase's buying_price). Sales add none.
     *
     * @return array<string, mixed>
     */
    protected function priceRules(): array
    {
        return [];
    }

    /**
     * The unit purchase cost to persist, or null for a kind that carries no
     * price (a sale's cost is derived from the WAC, never from the payload).
     */
    protected function buyingPrice(): ?string
    {
        return null;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date' => [
                'required',
                'date_format:Y-m-d',
                // One *live* transaction per product per date (each product is its
                // own ledger). Soft-deleted rows don't count, so a date frees up
                // again once its transaction is deleted.
                Rule::unique('transactions')->where(
                    fn ($query) => $query
                        ->where('product_id', $this->input('product_id'))
                        ->whereNull('deleted_at')
                ),
            ],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
        ], $this->priceRules());
    }

    /**
     * Build the DTO for a new transaction; the type comes from the endpoint.
     */
    public function toDto(): TransactionDTO
    {
        return new TransactionDTO(
            productId: (int) $this->validated('product_id'),
            type: $this->transactionType(),
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            buyingPrice: $this->buyingPrice(),
        );
    }
}
