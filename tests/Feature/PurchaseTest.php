<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use InteractsWithAuth;
    use RefreshDatabase;

    private function purchasePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'date' => '2022-01-01',
            'quantity' => '150',
            'buying_price' => '2.00',
        ], $overrides);
    }

    public function test_recording_a_purchase_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/purchases', $this->purchasePayload($product))
            ->assertStatus(401);
    }

    public function test_it_records_a_purchase_and_returns_the_snapshot(): void
    {
        $product = Product::factory()->create();

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/purchases', $this->purchasePayload($product))
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'purchase')
            ->assertJsonPath('data.quantity', '150.00')
            ->assertJsonPath('data.wac', '2.00')
            ->assertJsonPath('data.quantity_on_hand', '150.00')
            ->assertJsonPath('data.value_on_hand', '300.00')
            ->assertJsonStructure(['data' => ['created_at']]);

        $this->assertDatabaseHas('transactions', [
            'product_id' => $product->id,
            'type' => 'purchase',
            'date' => '2022-01-01',
        ]);
    }

    public function test_it_lists_purchases_oldest_first(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($product, ['date' => '2022-01-05', 'quantity' => '10', 'buying_price' => '1.50']));
        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($product, ['date' => '2022-01-01']));

        $this->withHeaders($headers)->getJson('/api/purchases')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2022-01-01')
            ->assertJsonPath('data.1.date', '2022-01-05');
    }

    public function test_listing_purchases_is_paginated_with_meta(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($product));

        $this->withHeaders($headers)->getJson('/api/purchases')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total', 'last_page']])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_purchases_can_be_filtered_by_product(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($a));
        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($b));

        $this->withHeaders($headers)->getJson("/api/purchases?product_id={$a->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $a->id);
    }

    public function test_purchases_can_be_filtered_by_date_range(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($product, ['date' => '2022-01-01']));
        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($product, ['date' => '2022-02-01']));
        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($product, ['date' => '2022-03-01']));

        $this->withHeaders($headers)->getJson('/api/purchases?date_from=2022-01-15&date_to=2022-02-15')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2022-02-01');
    }

    public function test_filtering_by_an_unknown_product_is_rejected(): void
    {
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/purchases?product_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_it_rejects_a_second_transaction_on_the_same_date_for_a_product(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($product));

        $this->withHeaders($headers)
            ->postJson('/api/purchases', $this->purchasePayload($product, ['quantity' => '5']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }

    public function test_it_allows_the_same_date_for_different_products(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($a))->assertStatus(201);
        $this->withHeaders($headers)->postJson('/api/purchases', $this->purchasePayload($b))->assertStatus(201);
    }

    public function test_it_validates_required_fields(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/purchases', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'date', 'quantity', 'buying_price']);
    }

    public function test_it_rejects_non_positive_quantity(): void
    {
        $product = Product::factory()->create();

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/purchases', $this->purchasePayload($product, ['quantity' => '0']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }
}
