<?php

namespace Tests\Feature;

use App\Models\Product;
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
}
