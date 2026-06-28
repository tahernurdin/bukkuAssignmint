<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionUpdateDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an update to a sale (bonus feature). A sale carries no price, so only
 * its date and quantity may change; product and type are fixed for the life of a
 * transaction. A change recosts the chain from the affected date forward.
 */
class UpdateSaleRequest extends FormRequest
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
     * Shape only. Date uniqueness within the product's live ledger is enforced by
     * TransactionService (via the DB unique index), not here.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
        ];
    }

    /**
     * Build the DTO for the update (only the mutable fields; no price).
     */
    public function toDto(): TransactionUpdateDTO
    {
        return new TransactionUpdateDTO(
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            buyingPrice: null,
        );
    }
}
