<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use InteractsWithAuth;
    use RefreshDatabase;

    public function test_listing_products_requires_authentication(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
    }

    public function test_it_lists_products(): void
    {
        Product::factory()->count(3)->create();

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'sku']]]);
    }

    public function test_listing_products_is_paginated_with_meta(): void
    {
        Product::factory()->count(3)->create();

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total', 'last_page']])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_products_can_be_paged(): void
    {
        Product::factory()->count(20)->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->getJson('/api/products?per_page=5')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonPath('meta.current_page', 1);

        $this->withHeaders($headers)->getJson('/api/products?per_page=5&page=2')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_products_can_be_searched_by_name_or_sku(): void
    {
        Product::factory()->create(['name' => 'Blue Widget', 'sku' => 'BLU-1']);
        Product::factory()->create(['name' => 'Red Gadget', 'sku' => 'RED-1']);
        $headers = $this->authHeaders();

        // Partial match on name.
        $this->withHeaders($headers)->getJson('/api/products?search=widget')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'BLU-1');

        // Partial match on sku.
        $this->withHeaders($headers)->getJson('/api/products?search=RED')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Red Gadget');
    }

    public function test_products_can_be_filtered_by_created_at_range(): void
    {
        Product::factory()->create(['name' => 'Old', 'sku' => 'OLD-1', 'created_at' => '2022-01-01 10:00:00']);
        Product::factory()->create(['name' => 'New', 'sku' => 'NEW-1', 'created_at' => '2022-06-15 10:00:00']);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?created_from=2022-06-01&created_to=2022-06-30')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'NEW-1');
    }

    public function test_per_page_is_capped_at_100(): void
    {
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_created_to_before_created_from_is_rejected(): void
    {
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?created_from=2022-06-30&created_to=2022-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('created_to');
    }

    public function test_it_shows_a_single_product(): void
    {
        $product = Product::factory()->create(['name' => 'Widget', 'sku' => 'WID-1']);

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.sku', 'WID-1');
    }

    public function test_showing_an_unknown_product_returns_404(): void
    {
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/products/9999')
            ->assertStatus(404);
    }

    public function test_it_creates_a_product(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', ['name' => 'Sprocket', 'sku' => 'SPR-1'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Sprocket')
            ->assertJsonPath('data.sku', 'SPR-1');

        $this->assertDatabaseHas('products', ['sku' => 'SPR-1']);
    }

    public function test_it_validates_product_creation(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku']);
    }

    public function test_it_rejects_a_duplicate_sku(): void
    {
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', ['name' => 'Other', 'sku' => 'DUP-1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    public function test_it_updates_a_product_and_allows_keeping_its_own_sku(): void
    {
        $product = Product::factory()->create(['name' => 'Old', 'sku' => 'KEEP-1']);

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/products/{$product->id}", ['name' => 'New', 'sku' => 'KEEP-1'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New']);
    }

    public function test_it_rejects_updating_to_another_products_sku(): void
    {
        Product::factory()->create(['sku' => 'TAKEN-1']);
        $product = Product::factory()->create(['sku' => 'MINE-1']);

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/products/{$product->id}", ['name' => 'X', 'sku' => 'TAKEN-1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sku');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'sku' => 'MINE-1']);
    }

    public function test_it_deletes_a_product_with_no_transactions(): void
    {
        $product = Product::factory()->create();

        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(204);

        // Soft delete: the row is kept (for audit) but no longer listed/shown.
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->withHeaders($this->authHeaders())
            ->getJson("/api/products/{$product->id}")
            ->assertStatus(404);
    }

    public function test_a_sku_can_be_reused_after_its_product_is_deleted(): void
    {
        $product = Product::factory()->create(['sku' => 'REUSE-1']);

        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(204);

        // The sku is free again now that its product is soft-deleted.
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', ['name' => 'Fresh', 'sku' => 'REUSE-1'])
            ->assertStatus(201);
    }

    public function test_it_refuses_to_delete_a_product_that_has_transactions(): void
    {
        $product = Product::factory()->create();
        Transaction::create([
            'product_id' => $product->id,
            'type' => TransactionType::Purchase,
            'date' => '2022-01-01',
            'quantity' => '10',
            'buying_price' => '2.00',
        ]);

        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_show_includes_current_inventory_and_wac(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '150', 'buying_price' => '2.00',
        ])->assertStatus(201);
        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-05', 'quantity' => '10', 'buying_price' => '1.50',
        ])->assertStatus(201);

        // 160 units worth 315.00; WAC 315/160 = 1.96875 (displayed 1.97).
        $this->withHeaders($headers)->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.quantity_on_hand', '160.00')
            ->assertJsonPath('data.value_on_hand', '315.00')
            ->assertJsonPath('data.wac', '1.97');
    }

    public function test_a_newly_created_product_reports_zero_stock_and_null_wac(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/products', ['name' => 'Fresh', 'sku' => 'FRESH-1'])
            ->assertStatus(201)
            ->assertJsonPath('data.quantity_on_hand', '0.00')
            ->assertJsonPath('data.value_on_hand', '0.00')
            ->assertJsonPath('data.wac', null);
    }

    public function test_a_depleted_product_reports_null_wac(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'buying_price' => '2.00',
        ])->assertStatus(201);
        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-02', 'quantity' => '10',
        ])->assertStatus(201);

        $this->withHeaders($headers)->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.quantity_on_hand', '0.00')
            ->assertJsonPath('data.value_on_hand', '0.00')
            ->assertJsonPath('data.wac', null);
    }

    public function test_a_product_embedded_in_a_sale_omits_inventory_fields(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'buying_price' => '2.00',
        ])->assertStatus(201);
        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-02', 'quantity' => '5',
        ])->assertStatus(201);

        // The nested product carries only identity, never the inventory snapshot.
        $this->withHeaders($headers)->getJson('/api/sales')
            ->assertStatus(200)
            ->assertJsonPath('data.0.product.id', $product->id)
            ->assertJsonMissingPath('data.0.product.wac')
            ->assertJsonMissingPath('data.0.product.quantity_on_hand');
    }
}
