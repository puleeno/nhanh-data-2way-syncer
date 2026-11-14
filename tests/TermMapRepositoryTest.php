<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;

class TermMapRepositoryTest extends TestCase
{
    private TermMapRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TermMapRepository();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
    }

    public function testUpsertCreatesNewRow(): void
    {
        $id = $this->repo->upsert([
            'nhanh_term_type' => 'category',
            'nhanh_term_id' => '101',
            'nhanh_term_name' => 'Category A',
            'wc_taxonomy' => 'product_cat',
            'wc_term_id' => 501,
        ]);
        $this->assertGreaterThan(0, $id);
    }

    public function testUpsertUpdatesExistingRow(): void
    {
        $id1 = $this->repo->upsert(['nhanh_term_type' => 'category', 'nhanh_term_id' => '101', 'nhan_term_name' => 'Cat A', 'wc_taxonomy' => 'product_cat', 'wc_term_id' => 501]);
        $id2 = $this->repo->upsert(['nhanh_term_type' => 'category', 'nhanh_term_id' => '101', 'nhan_term_name' => 'Cat B', 'wc_taxonomy' => 'product_cat', 'wc_term_id' => 501]);
        $this->assertSame($id1, $id2);
    }

    public function testFindByNhanhReturnsRow(): void
    {
        $this->repo->upsert(['nhanh_term_type' => 'category', 'nhanh_term_id' => '101', 'nhanh_term_name' => 'Cat', 'wc_taxonomy' => 'product_cat', 'wc_term_id' => 501]);
        $row = $this->repo->findByNhanh('category', '101', 'product_cat');
        $this->assertIsArray($row);
        $this->assertSame('101', $row['nhanh_term_id']);
    }

    public function testFindByNhanhReturnsNullWhenMissing(): void
    {
        $row = $this->repo->findByNhanh('category', '999', 'product_cat');
        $this->assertNull($row);
    }

    public function testFindByWcTermReturnsRow(): void
    {
        $this->repo->upsert(['nhanh_term_type' => 'brand', 'nhanh_term_id' => '201', 'wc_taxonomy' => 'product_brand', 'wc_term_id' => 601]);
        $row = $this->repo->findByWcTerm('product_brand', 601);
        $this->assertIsArray($row);
        $this->assertSame('201', $row['nhanh_term_id']);
    }

    public function testAllReturnsRows(): void
    {
        $this->repo->upsert(['nhanh_term_type' => 'category', 'nhanh_term_id' => '101', 'wc_taxonomy' => 'product_cat', 'wc_term_id' => 501]);
        $this->repo->upsert(['nhanh_term_type' => 'brand', 'nhanh_term_id' => '201', 'wc_taxonomy' => 'product_brand', 'wc_term_id' => 601]);
        $all = $this->repo->all();
        $this->assertCount(2, $all);
    }

    public function testAllWithTaxonomyFilter(): void
    {
        $this->repo->upsert(['nhanh_term_type' => 'category', 'nhanh_term_id' => '101', 'wc_taxonomy' => 'product_cat', 'wc_term_id' => 501]);
        $this->repo->upsert(['nhanh_term_type' => 'brand', 'nhanh_term_id' => '201', 'wc_taxonomy' => 'product_brand', 'wc_term_id' => 601]);
        $all = $this->repo->all('product_cat');
        $this->assertCount(1, $all);
        $this->assertSame('101', $all[0]['nhanh_term_id']);
    }
}