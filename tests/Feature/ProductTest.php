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
    use RefreshDatabase;
    use InteractsWithAuth;

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
}
