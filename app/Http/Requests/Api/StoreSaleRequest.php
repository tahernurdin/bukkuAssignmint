<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionDTO;
use App\Enums\TransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new sale. A sale carries no price of its own: its cost of goods
 * sold is derived from the weighted-average cost at its date, so the payload is
 * just product, date and quantity.
 */
class StoreSaleRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
        ];
    }

    /**
     * Build the DTO for a new sale (no price; cost is derived from the WAC).
     */
    public function toDto(): TransactionDTO
    {
        return new TransactionDTO(
            productId: (int) $this->validated('product_id'),
            type: TransactionType::Sale,
            date: $this->validated('date'),
            quantity: (string) $this->validated('quantity'),
            buyingPrice: null,
        );
    }
}
