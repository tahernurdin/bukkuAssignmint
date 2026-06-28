<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAuth;

    /**
     * Seed the assignment's two purchases (150 @ 2.00, then 10 @ 1.50).
     *
     * @return array{0: Product, 1: array<string, string>}
     */
    private function seedAssignmentStock(): array
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '150', 'buying_price' => '2.00',
        ])->assertStatus(201);

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-05', 'quantity' => '10', 'buying_price' => '1.50',
        ])->assertStatus(201);

        return [$product, $headers];
    }

    public function test_it_records_a_sale_with_costing_information(): void
    {
        [$product, $headers] = $this->seedAssignmentStock();

        // Sell 5 units. WAC is 315/160 = 1.96875 (displayed 1.97); cost 9.84375
        // (displayed 9.84); 155 units remain worth 305.15625 (displayed 305.16).
        // A sale carries no price of its own — only product, date and quantity.
        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-07', 'quantity' => '5',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.wac', '1.97')
            ->assertJsonPath('data.cost', '9.84')
            ->assertJsonPath('data.quantity_on_hand', '155.00')
            ->assertJsonPath('data.value_on_hand', '305.16');
    }

    public function test_a_sale_response_carries_no_price(): void
    {
        [$product, $headers] = $this->seedAssignmentStock();

        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-07', 'quantity' => '5',
        ])
            ->assertStatus(201)
            ->assertJsonMissingPath('data.price')
            ->assertJsonMissingPath('data.buying_price');
    }

    public function test_it_lists_sales_with_costing(): void
    {
        [$product, $headers] = $this->seedAssignmentStock();

        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-07', 'quantity' => '5',
        ]);

        $this->withHeaders($headers)->getJson('/api/sales')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cost', '9.84');
    }

    public function test_it_rejects_a_sale_that_exceeds_quantity_on_hand(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'buying_price' => '2.00',
        ])->assertStatus(201);

        $this->withHeaders($headers)->postJson('/api/sales', [
            'product_id' => $product->id, 'date' => '2022-01-02', 'quantity' => '15',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        // The rejected sale must not have been persisted.
        $this->assertDatabaseMissing('transactions', [
            'product_id' => $product->id,
            'type' => 'sale',
        ]);
    }
}
