<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Bonus features: out-of-order inserts and updates/deletes, each recosting the
 * affected chain (and rolling back when an edit would oversell downstream).
 */
class TransactionRecalculationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAuth;

    public function test_inserting_an_earlier_purchase_recosts_a_later_sale(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        // 100 @ 1.00 then sell 40 => sale cost 40.00 at average 1.00.
        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '100', 'price' => '1.00',
        ]);
        $saleId = $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-10', 'quantity' => '40', 'price' => '2.00',
        ])->json('data.id');

        // Insert a pricier purchase in between (out of order): 100 @ 3.00.
        // New average before the sale = 400 / 200 = 2.00, so cost becomes 80.00.
        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-05', 'quantity' => '100', 'price' => '3.00',
        ])->assertStatus(201);

        $this->withHeaders($headers)->getJson('/api/sales')
            ->assertJsonPath('data.0.id', $saleId)
            ->assertJsonPath('data.0.cost', '80.00')
            ->assertJsonPath('data.0.quantity_on_hand', '160.00');
    }

    public function test_updating_a_purchase_recosts_the_later_sale(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $purchaseId = $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'price' => '2.00',
        ])->json('data.id');
        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-05', 'quantity' => '5', 'price' => '9.00',
        ]);

        // Bump the purchase price to 4.00: average becomes 4.00, sale cost 20.00.
        $this->withHeaders($headers)->patchJson("/api/purchases/{$purchaseId}", [
            'date' => '2022-01-01', 'quantity' => '10', 'price' => '4.00',
        ])->assertStatus(200);

        $this->withHeaders($headers)->getJson('/api/sales')
            ->assertJsonPath('data.0.cost', '20.00');
    }

    public function test_deleting_a_purchase_recosts_the_later_sale(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'price' => '2.00',
        ]);
        $deletableId = $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-03', 'quantity' => '10', 'price' => '4.00',
        ])->json('data.id');
        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-05', 'quantity' => '5', 'price' => '9.00',
        ]);

        // With both purchases the average is 3.00 (cost 15.00). Remove the
        // pricier one and the average drops to 2.00 (cost 10.00).
        $this->withHeaders($headers)->deleteJson("/api/purchases/{$deletableId}")
            ->assertStatus(204);

        $this->withHeaders($headers)->getJson('/api/sales')
            ->assertJsonPath('data.0.cost', '10.00');
    }

    public function test_an_update_that_would_oversell_downstream_is_rejected_and_rolled_back(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $purchaseId = $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'price' => '2.00',
        ])->json('data.id');
        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-05', 'quantity' => '8', 'price' => '9.00',
        ]);

        // Reducing the purchase to 5 units would leave the 8-unit sale oversold.
        $this->withHeaders($headers)->patchJson("/api/purchases/{$purchaseId}", [
            'date' => '2022-01-01', 'quantity' => '5', 'price' => '2.00',
        ])->assertStatus(422);

        // The whole operation rolled back: the purchase is still 10 units.
        $this->assertDatabaseHas('transactions', [
            'id' => $purchaseId,
            'quantity' => '10.00',
        ]);
    }

    public function test_a_sale_cannot_be_reached_through_the_purchases_endpoint(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'price' => '2.00',
        ]);
        $saleId = $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-05', 'quantity' => '5', 'price' => '9.00',
        ])->json('data.id');

        $this->withHeaders($headers)->deleteJson("/api/purchases/{$saleId}")
            ->assertStatus(404);
    }
}
