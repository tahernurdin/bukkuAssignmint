<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Transaction deletes are soft: the row is kept (for audit) but drops out of
 * every read and frees its product+date slot so an equivalent transaction can
 * be recorded again.
 */
class TransactionSoftDeleteTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAuth;

    public function test_a_date_can_be_reused_after_its_transaction_is_deleted(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $purchaseId = $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'price' => '2.00',
        ])->json('data.id');

        $this->withHeaders($headers)->deleteJson("/api/purchases/{$purchaseId}")
            ->assertStatus(204);

        // The row is kept, just flagged deleted.
        $this->assertSoftDeleted('transactions', ['id' => $purchaseId]);

        // The same product+date is free again (the unique index used to block this).
        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '5', 'price' => '3.00',
        ])->assertStatus(201);
    }

    public function test_a_second_live_transaction_for_the_same_date_is_still_rejected(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'price' => '2.00',
        ])->assertStatus(201);

        // No delete in between: the date is still occupied by a live transaction.
        $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '5', 'price' => '3.00',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }

    public function test_deleted_transactions_are_excluded_from_listings(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authHeaders();

        $keptId = $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-01', 'quantity' => '10', 'price' => '2.00',
        ])->json('data.id');
        $deletedId = $this->withHeaders($headers)->postJson('/api/purchases', [
            'product_id' => $product->id, 'date' => '2022-01-03', 'quantity' => '10', 'price' => '4.00',
        ])->json('data.id');

        $this->withHeaders($headers)->deleteJson("/api/purchases/{$deletedId}")
            ->assertStatus(204);

        $this->withHeaders($headers)->getJson('/api/purchases')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $keptId);
    }
}
