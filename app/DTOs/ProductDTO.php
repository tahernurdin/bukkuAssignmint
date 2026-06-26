<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Immutable carrier for the persistable fields of a product. Create and update
 * share the same fields, so a single factory serves both requests.
 */
readonly class ProductDTO
{
    public function __construct(
        public string $name,
        public string $sku,
    ) {}

    /**
     * Build from a validated store/update request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            sku: $request->validated('sku'),
        );
    }

    /**
     * The fillable attributes for persisting the product row.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
        ];
    }
}
