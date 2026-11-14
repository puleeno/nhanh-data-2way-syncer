<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Woo\WooBridge;

class WooBridgeTest extends TestCase
{
    private WooBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge = new WooBridge();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['__wc_products'] = [];
        $GLOBALS['__next_wc_product_id'] = 1000;
        $GLOBALS['__wp_terms'] = [];
        $GLOBALS['__next_term_id'] = 100;
    }

    public function testUpsertProductCreatesNewProduct(): void
    {
        $id = $this->bridge->upsertProduct([
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'price' => 100000,
            'stock' => 50,
        ]);
        $this->assertGreaterThan(0, $id);
    }

    public function testUpsertProductUpdatesExistingProduct(): void
    {
        $id1 = $this->bridge->upsertProduct(['sku' => 'SKU-001', 'name' => 'Old Name']);
        $id2 = $this->bridge->upsertProduct(['sku' => 'SKU-001', 'name' => 'New Name']);
        $this->assertSame($id1, $id2);
    }

    public function testUpsertTermCreatesNewTerm(): void
    {
        $id = $this->bridge->upsertTerm('product_cat', 'Test Category', 'Description');
        $this->assertGreaterThan(0, $id);
    }

    public function testUpsertTermReturnsExistingTerm(): void
    {
        $id1 = $this->bridge->upsertTerm('product_cat', 'Test Category');
        $id2 = $this->bridge->upsertTerm('product_cat', 'Test Category');
        $this->assertSame($id1, $id2);
    }
}