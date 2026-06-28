<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionUpdateDTO;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for updating an existing transaction (bonus feature).
 *
 * Only date, quantity and (for purchases) the buying price are mutable; product
 * and type are fixed for the life of a transaction so it never jumps between
 * ledgers. A concrete request adds whatever price field its kind carries.
 * Overselling introduced anywhere downstream is caught during recalculation in
 * the engine.
 */
abstract class AbstractUpdateTransactionRequest extends FormRequest
{
    /**
     * The transaction being edited, resolved once from the route id (soft-deleted
     * rows read as missing). The routes are id-based — no model binding — so this
     * request looks the row up itself; the controller separately scopes the same
     * id to the endpoint's type and answers 404 when it can't.
     */
    private ?Transaction $existing = null;

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
     * price.
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
            'date' => $this->dateRules(),
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
        ], $this->priceRules());
    }

    /**
     * Build the DTO for the update. Only date/quantity/buying_price may change
     * (product and type are immutable), so this carries just those — the service
     * applies it onto the existing row it looks up by id.
     */
    public function toDto(): TransactionUpdateDTO
    {
        return new TransactionUpdateDTO(
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            buyingPrice: $this->buyingPrice(),
        );
    }

    /**
     * Date is required and, where the edited transaction resolves, must stay
     * unique within that product's live ledger (ignoring itself). An id that
     * doesn't resolve is left to the controller, which answers 404, so there is
     * nothing to scope and the uniqueness rule is dropped.
     *
     * @return array<int, mixed>
     */
    private function dateRules(): array
    {
        $rules = ['required', 'date_format:Y-m-d'];

        $existing = $this->existingTransaction();
        if ($existing !== null) {
            $rules[] = Rule::unique('transactions')
                ->where(fn ($query) => $query
                    ->where('product_id', $existing->product_id)
                    ->whereNull('deleted_at'))
                ->ignore($existing->id);
        }

        return $rules;
    }

    /**
     * The transaction targeted by the route id, cached for reuse across rules()
     * and toDto(), or null if no live transaction has that id.
     */
    private function existingTransaction(): ?Transaction
    {
        return $this->existing ??= Transaction::find($this->route('id'));
    }
}
