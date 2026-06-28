<?php

namespace App\Http\Requests\Api;

use App\DTOs\ProductFilterDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    /** Page size used when the client does not ask for one. */
    public const DEFAULT_PER_PAGE = 15;

    /** Hard ceiling on page size, so a client cannot ask for the whole table. */
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
            'search' => ['nullable', 'string', 'max:255'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Build the filter for the products listing.
     */
    public function toFilter(): ProductFilterDTO
    {
        return new ProductFilterDTO(
            search: $this->validated('search'),
            createdFrom: $this->validated('created_from'),
            createdTo: $this->validated('created_to'),
            perPage: (int) $this->validated('per_page', self::DEFAULT_PER_PAGE),
            page: (int) $this->validated('page', 1),
        );
    }
}
