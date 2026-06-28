<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionUpdateDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an update to a purchase (bonus feature). Only date, quantity and the
 * buying price are mutable; product and type are fixed for the life of a
 * transaction so it never jumps ledgers. A change recosts the chain from the
 * affected date forward.
 */
class UpdatePurchaseRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'buying_price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * Build the DTO for the update (only the mutable fields).
     */
    public function toDto(): TransactionUpdateDTO
    {
        return new TransactionUpdateDTO(
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            buyingPrice: (string) $this->validated('buying_price'),
        );
    }
}
