<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionDTO;
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
        $transaction = $this->routeTransaction();

        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('transactions')
                    ->where(fn ($query) => $query->where('product_id', $transaction->product_id))
                    ->ignore($transaction->id),
            ],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * The transaction bound to the route. Both the purchases and sales endpoints
     * bind it under the {transaction} segment (see routes/api.php).
     */
    public function routeTransaction(): Transaction
    {
        return $this->route('transaction');
    }

    /**
     * Build the DTO for the update. Product and type are inherited from the
     * existing transaction (immutable); only date/quantity/price may change.
     */
    public function toDto(): TransactionDTO
    {
        $existing = $this->routeTransaction();

        return new TransactionDTO(
            productId: $existing->product_id,
            type: $existing->type,
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            price: (string) $this->validated('price'),
        );
    }
}
