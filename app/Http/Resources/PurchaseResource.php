<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDecimals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A purchase transaction with the inventory snapshot it produced.
 *
 * @mixin \App\Models\Transaction
 */
class PurchaseResource extends JsonResource
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

            // What was purchased.
            'quantity' => $this->decimal($this->quantity),
            'buying_price' => $this->decimal($this->buying_price),

            // Inventory state after this purchase (WAC method).
            'wac' => $this->decimal($this->wac_at_time),
            'quantity_on_hand' => $this->decimal($this->quantity_on_hand),
            'value_on_hand' => $this->decimal($this->value_on_hand),

            // When the row was recorded — distinct from `date`, the ledger date.
            // (updated_at is omitted: recosting rewrites downstream snapshots, so
            // it would reflect the last recost rather than a direct edit.)
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
