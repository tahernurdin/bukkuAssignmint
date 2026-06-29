<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDecimals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
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
            'name' => $this->name,
            'sku' => $this->sku,

            // Current inventory state, included only when the latest-transaction
            // snapshot has been eager-loaded (the product endpoints). Omitted
            // when a product is embedded in a sale/purchase payload, so those
            // responses neither grow nor trigger an N+1 lookup.
            $this->mergeWhen(
                $this->resource->relationLoaded('latestTransaction'),
                fn () => $this->currentInventory(),
            ),

            // Audit timestamps, exposed on the same (full) product views as the
            // inventory above — notably the listing the created_from/created_to
            // filter queries against. Kept out of the embedded product reference
            // in sale/purchase payloads, which never loads the snapshot relation.
            $this->mergeWhen(
                $this->resource->relationLoaded('latestTransaction'),
                fn () => [
                    'created_at' => $this->created_at?->toJSON(),
                    'updated_at' => $this->updated_at?->toJSON(),
                ],
            ),
        ];
    }

    /**
     * The product's current inventory, taken from the snapshot on its latest
     * transaction. A product with no transactions yet holds nothing.
     *
     * @return array<string, string|null>
     */
    private function currentInventory(): array
    {
        $snapshot = $this->latestTransaction;
        $quantityOnHand = $snapshot?->quantity_on_hand ?? '0';

        return [
            'quantity_on_hand' => $this->decimal($quantityOnHand),
            'value_on_hand' => $this->decimal($snapshot?->value_on_hand ?? '0'),

            // WAC is meaningful only while stock is held; a depleted or
            // never-transacted product reports null rather than a stale rate.
            // (While quantity > 0 the stored rate equals value / quantity.)
            'wac' => bccomp($quantityOnHand, '0', 2) > 0
                ? $this->decimal($snapshot->wac_at_time)
                : null,
        ];
    }
}
