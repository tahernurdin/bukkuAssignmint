<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new purchase. A purchase records the unit cost it was bought at
 * (buying_price), which feeds the running weighted-average cost.
 */
class StorePurchaseRequest extends FormRequest
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
     * Shape only. One live transaction per product per date is a stateful domain
     * rule, enforced by TransactionService (via the DB unique index), not here.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'buying_price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * Build the DTO for a new purchase.
     */
    public function toDto(): TransactionDTO
    {
        return new TransactionDTO(
            productId: (int) $this->validated('product_id'),
            type: TransactionType::Purchase,
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            buyingPrice: (string) $this->validated('buying_price'),
        );
    }
}
