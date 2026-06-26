<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed a small, stable catalogue of dummy products to transact against.
     * Idempotent so re-seeding does not create duplicates.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'Widget', 'sku' => 'WIDGET-001'],
            ['name' => 'Gadget', 'sku' => 'GADGET-001'],
            ['name' => 'Gizmo', 'sku' => 'GIZMO-001'],
            ['name' => 'Doohickey', 'sku' => 'DOOHICKEY-001'],
            ['name' => 'Thingamajig', 'sku' => 'THINGAMAJIG-001'],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(['sku' => $product['sku']], $product);
        }
    }
}
