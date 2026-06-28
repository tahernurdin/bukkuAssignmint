<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionUpdateDTO;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an update to an existing transaction (bonus feature).
 *
 * Only date, quantity and price are mutable; product and type are fixed for
 * the life of a transaction so it never jumps between ledgers. Overselling
 * introduced anywhere downstream is caught during recalculation in the engine.
 */
class UpdateTransactionRequest extends FormRequest
{
    /**
     * The transaction being edited, resolved once from the route id (soft-deleted
     * rows read as missing). The routes are id-based — no model binding — so this
     * request looks the row up itself; the controller separately scopes the same
     * id to the endpoint's type and answers 404 when it can't.
     */
    private ?Transaction $existing = null;

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
        return [
            'date' => $this->dateRules(),
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * Build the DTO for the update. Only date/quantity/price may change (product
     * and type are immutable), so this carries just those — no need to load the
     * existing row here; the service applies it onto the row it looks up by id.
     */
    public function toDto(): TransactionUpdateDTO
    {
        return new TransactionUpdateDTO(
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            price: (string) $this->validated('price'),
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
