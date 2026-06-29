<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDecimals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sale transaction including its costing information: the cost of goods sold
 * (`cost`) is the WAC at the time of sale multiplied by the quantity sold.
 *
 * @mixin \App\Models\Transaction
 */
class SaleResource extends JsonResource
{
    use FormatsDecimals;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'date' => $this->date->toDateString(),
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),

            // What was sold (a sale carries no price of its own — its cost is
            // derived from the running WAC, below).
            'quantity' => $this->decimal($this->quantity),

            // Costing: WAC applied and the resulting cost of goods sold.
            'wac' => $this->decimal($this->wac_at_time),
            'cost' => $this->decimal($this->calculated_cost),

            // Inventory state after this sale.
            'quantity_on_hand' => $this->decimal($this->quantity_on_hand),
            'value_on_hand' => $this->decimal($this->value_on_hand),

            // When the row was recorded — distinct from `date`, the ledger date.
            // (updated_at is omitted: recosting rewrites downstream snapshots, so
            // it would reflect the last recost rather than a direct edit.)
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
