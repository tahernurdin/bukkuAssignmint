<?php

namespace App\Http\Requests\Api;

use App\DTOs\ProductDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
        // Shape only. Sku uniqueness is a stateful domain rule, enforced in
        // ProductService (and backstopped by a DB index), not here.
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Build the DTO for the product update.
     */
    public function toDto(): ProductDTO
    {
        return new ProductDTO(
            name: $this->validated('name'),
            sku: $this->validated('sku'),
        );
    }
}
