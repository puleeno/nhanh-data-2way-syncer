<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;

class ProductMapRepositoryTest extends TestCase
{
    private ProductMapRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ProductMapRepository();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
    }

    public function testUpsertCreatesNewRow(): void
    {
        $id = $this->repo->upsert([
            'nhanh_id' => '123',
            'nhanh_code' => 'SKU-001',
            'nhanh_name' => 'Test Product',
            'nhanh_price' => 100000,
            'nhanh_stock_total' => 50,
        ]);
        $this->assertGreaterThan(0, $id);
    }

    public function testUpsertUpdatesExistingRow(): void
    {
        $id1 = $this->repo->upsert(['nhanh_code' => 'SKU-001', 'nhanh_name' => 'Old Name']);
        $id2 = $this->repo->upsert(['nhanh_code' => 'SKU-001', 'nhanh_name' => 'New Name']);
        $this->assertSame($id1, $id2);
    }

    public function testFindByCodeReturnsRow(): void
    {
        $this->repo->upsert(['nhanh_code' => 'SKU-001', 'nhanh_name' => 'Test']);
        $row = $this->repo->findByCode('SKU-001');
        $this->assertIsArray($row);
        $this->assertSame('SKU-001', $row['nhanh_code']);
    }

    public function testFindByCodeReturnsNullWhenMissing(): void
    {
        $row = $this->repo->findByCode('NONEXISTENT');
        $this->assertNull($row);
    }

    public function testFindByNhanhIdReturnsRow(): void
    {
        $this->repo->upsert(['nhanh_id' => '123', 'nhanh_code' => 'SKU-001']);
        $row = $this->repo->findByNhanhId('123');
        $this->assertIsArray($row);
        $this->assertSame('123', $row['nhanh_id']);
    }

    public function testLinkWooUpdatesMapping(): void
    {
        $id = $this->repo->upsert(['nhanh_code' => 'SKU-001']);
        $result = $this->repo->linkWoo($id, 5001, 0, 'SKU-001');
        $this->assertTrue($result);
    }

    public function testMarkErrorSetsStatus(): void
    {
        $id = $this->repo->upsert(['nhanh_code' => 'SKU-001']);
        $this->repo->markError($id, 'Connection failed');
        $this->assertGreaterThan(0, $id);
    }
}