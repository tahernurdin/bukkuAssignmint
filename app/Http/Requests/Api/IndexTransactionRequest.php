<?php

namespace App\Http\Requests\Api;

use App\DTOs\TransactionFilterDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query validation shared by the purchases and sales listings: the params are
 * identical, and the transaction type is fixed by the controller rather than
 * the query string.
 */
class IndexTransactionRequest extends FormRequest
{
    /** Page size used when the client does not ask for one. */
    public const DEFAULT_PER_PAGE = 15;

    /** Hard ceiling on page size, so a client cannot ask for the whole ledger. */
    public const MAX_PER_PAGE = 100;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Build the filter for a transactions listing.
     */
    public function toFilter(): TransactionFilterDTO
    {
        return new TransactionFilterDTO(
            productId: $this->validated('product_id') !== null
                ? (int) $this->validated('product_id')
                : null,
            dateFrom: $this->validated('date_from'),
            dateTo: $this->validated('date_to'),
            perPage: (int) $this->validated('per_page', self::DEFAULT_PER_PAGE),
            page: (int) $this->validated('page', 1),
        );
    }
}
