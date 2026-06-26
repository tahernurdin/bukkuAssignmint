<?php

namespace Tests\Unit;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\Transaction;
use App\Services\Inventory\WacLedgerService;
use Tests\Doubles\InMemoryTransactionRepository;
use Tests\TestCase;

/**
 * Pure unit test for the WAC engine: it runs against an in-memory repository
 * double, so it touches no database (note: no RefreshDatabase). The Feature
 * suite covers the engine through the real Eloquent repository.
 */
class WacLedgerServiceTest extends TestCase
{
    private InMemoryTransactionRepository $repository;
    private WacLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemoryTransactionRepository();
        $this->ledger = new WacLedgerService($this->repository);
    }

    private function record(TransactionType $type, string $date, string $quantity, string $price): Transaction
    {
        return $this->repository->add(new Transaction([
            'product_id' => 1,
            'type' => $type,
            'date' => $date,
            'quantity' => $quantity,
            'price' => $price,
        ]));
    }

    public function test_it_reproduces_the_assignment_example_at_high_precision(): void
    {
        $buy1 = $this->record(TransactionType::Purchase, '2022-01-01', '150', '2.00');
        $buy2 = $this->record(TransactionType::Purchase, '2022-01-05', '10', '1.50');
        $sale = $this->record(TransactionType::Sale, '2022-01-07', '5', '5.00');

        $this->ledger->recalculateFrom(1, '2022-01-01');

        // 150 @ 2.00 => value 300, average 2.
        $this->assertSame('150.00', $buy1->quantity_on_hand);
        $this->assertSame('300.000000', $buy1->value_on_hand);
        $this->assertSame('2.000000', $buy1->wac_at_time);
        $this->assertNull($buy1->calculated_cost);

        // + 10 @ 1.50 => 160 units, value 315, average 315/160 = 1.96875.
        $this->assertSame('160.00', $buy2->quantity_on_hand);
        $this->assertSame('315.000000', $buy2->value_on_hand);
        $this->assertSame('1.968750', $buy2->wac_at_time);

        // Sell 5 at the average: cost 9.84375, leaving 155 units worth 305.15625.
        // The average is unchanged by a sale.
        $this->assertSame('9.843750', $sale->calculated_cost);
        $this->assertSame('1.968750', $sale->wac_at_time);
        $this->assertSame('155.00', $sale->quantity_on_hand);
        $this->assertSame('305.156250', $sale->value_on_hand);
    }

    public function test_selling_all_units_zeroes_the_ledger(): void
    {
        $this->record(TransactionType::Purchase, '2022-01-01', '10', '2.00');
        $sale = $this->record(TransactionType::Sale, '2022-01-02', '10', '3.00');

        $this->ledger->recalculateFrom(1, '2022-01-01');

        $this->assertSame('20.000000', $sale->calculated_cost); // 10 @ avg 2
        $this->assertSame('0.00', $sale->quantity_on_hand);
        $this->assertSame('0.000000', $sale->value_on_hand);
        // wac_at_time records the average applied to the sale (2.00), not the
        // post-sale state (which holds nothing).
        $this->assertSame('2.000000', $sale->wac_at_time);
    }

    public function test_it_rejects_a_sale_that_exceeds_quantity_on_hand(): void
    {
        $this->record(TransactionType::Purchase, '2022-01-01', '10', '2.00');
        $this->record(TransactionType::Sale, '2022-01-02', '15', '3.00');

        $this->expectException(InsufficientStockException::class);
        $this->ledger->recalculateFrom(1, '2022-01-01');
    }

    public function test_recalculating_from_a_date_replays_only_affected_rows(): void
    {
        $buy = $this->record(TransactionType::Purchase, '2022-01-01', '100', '1.00');
        $sale = $this->record(TransactionType::Sale, '2022-01-10', '40', '2.00');
        $this->ledger->recalculateFrom(1, '2022-01-01');

        // Insert a cheaper purchase in between and recompute only from its date.
        $insert = $this->record(TransactionType::Purchase, '2022-01-05', '100', '3.00');
        $this->ledger->recalculateFrom(1, '2022-01-05');

        // The first purchase is untouched; the inserted row and the later sale
        // reflect the new average (200 units, value 400 => avg 2.00).
        $this->assertSame('1.000000', $buy->wac_at_time);
        $this->assertSame('2.000000', $insert->wac_at_time);
        $this->assertSame('80.000000', $sale->calculated_cost); // 40 @ avg 2.00
        $this->assertSame('160.00', $sale->quantity_on_hand);
    }
}
