<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new purchase or sale. The transaction *type* is decided by the
 * endpoint (purchase vs sale controller), not the payload.
 *
 * Note: "cannot oversell" is a stateful rule that depends on the WAC chain at
 * the transaction's date, so it is enforced in the engine, not here.
 */
class StoreTransactionRequest extends FormRequest
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
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date' => [
                'required',
                'date_format:Y-m-d',
                // One transaction per product per date (each product is its own ledger).
                Rule::unique('transactions')->where(
                    fn ($query) => $query->where('product_id', $this->input('product_id'))
                ),
            ],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }
}
