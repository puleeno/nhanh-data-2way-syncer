<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Admin\Menu;
use Puleeno\NhanhVn\Syncer\Admin\Pages\Data;
use Puleeno\NhanhVn\Syncer\Admin\Pages\Renderer;
use Puleeno\NhanhVn\Syncer\Database\OrderMapRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\Schema;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;

class AdminStructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['__wc_products'] = [];
        $GLOBALS['__next_wc_product_id'] = 1000;
        $GLOBALS['__wp_terms'] = [];
        $GLOBALS['__next_term_id'] = 100;
    }

    public function testDataExposesEveryCatalogKey(): void
    {
        $data = Data::get();

        foreach (['products', 'term_maps', 'order_maps', 'logs', 'revenue', 'turnover', 'stats'] as $key) {
            $this->assertArrayHasKey($key, $data, "Data::get() missing {$key}");
        }
    }

    public function testEmptyDatabaseYieldsEmptyLists(): void
    {
        $data = Data::get();

        $this->assertSame([], $data['products']);
        $this->assertSame([], $data['term_maps']);
        $this->assertSame([], $data['order_maps']);
        $this->assertSame([], $data['logs']);
    }

    public function testProductRowsComeFromDatabase(): void
    {
        (new ProductMapRepository())->upsert([
            'nhanh_id' => '58607',
            'nhanh_code' => 'KDMVAVO131126-113',
            'nhanh_name' => 'Sách ABC',
            'nhanh_category_name' => 'Văn học',
            'nhanh_brand_name' => 'VAVO',
            'nhanh_price' => 200000,
            'nhanh_stock_total' => 7,
            'wc_product_id' => 0,
        ]);

        $data = Data::get();

        $this->assertCount(1, $data['products']);
        $row = $data['products'][0];
        $this->assertSame('KDMVAVO131126-113', $row['nhanh_code']);
        $this->assertSame('Sách ABC', $row['nhanh_name']);
        $this->assertSame('Văn học', $row['category']);
        $this->assertSame('VAVO', $row['brand']);
        $this->assertSame(200000.0, (float) $row['nhanh_price']);
        $this->assertSame(7, (int) $row['nhanh_stock']);
        $this->assertSame('unlinked', $row['sync_status']);
    }

    public function testProductRowRequiredFieldsAreAlwaysPresent(): void
    {
        (new ProductMapRepository())->upsert(['nhanh_code' => 'SKU-1', 'nhanh_name' => 'X']);

        $required = [
            'nhanh_code',
            'nhanh_name',
            'nhanh_price',
            'nhanh_stock',
            'sync_status',
            'web_id',
            'web_price',
            'web_stock',
            'category',
            'brand',
            'sell_velocity',
            'turnover_days',
            'units_sold_month',
            'revenue_month',
            'last_synced',
        ];

        foreach (Data::get()['products'] as $index => $product) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $product, "Product #{$index} missing {$field}");
            }
        }
    }

    public function testTermRowsComeFromDatabase(): void
    {
        (new TermMapRepository())->upsert([
            'nhanh_term_type' => 'category',
            'nhanh_term_id' => '14',
            'nhanh_term_name' => 'Văn học',
            'wc_taxonomy' => 'product_cat',
            'wc_term_id' => 55,
            'wc_term_name' => 'Văn học',
            'status' => 'synced',
            'product_count' => 12,
        ]);

        $rows = Data::get()['term_maps'];

        $this->assertCount(1, $rows);
        $this->assertSame('category', $rows[0]['type']);
        $this->assertSame('14', $rows[0]['nhanh_id']);
        $this->assertSame('Văn học', $rows[0]['nhanh']);
        $this->assertSame('product_cat', $rows[0]['wc_taxonomy']);
        $this->assertSame('Văn học', $rows[0]['web']);
        $this->assertSame(55, $rows[0]['wc_term_id']);
        $this->assertSame(12, $rows[0]['count']);
        $this->assertSame('synced', $rows[0]['status']);
    }

    public function testOrderRowsComeFromDatabase(): void
    {
        global $wpdb;
        $wpdb->insert(Schema::table('order_map'), [
            'wc_order_id' => 10421,
            'nhanh_order_id' => '90412',
            'nhanh_order_code' => 'ORD-NH-90412',
            'source' => 'nhanh_to_wc',
            'sync_status' => 'synced',
            'total_amount' => 1140000,
        ]);

        $rows = Data::get()['order_maps'];

        $this->assertCount(1, $rows);
        $this->assertSame(10421, $rows[0]['wc_order_id']);
        $this->assertSame('90412', $rows[0]['nhanh_order_id']);
        $this->assertSame('nhanh_to_wc', $rows[0]['source']);
        $this->assertSame('synced', $rows[0]['sync_status']);
        $this->assertSame(1140000.0, (float) $rows[0]['total_amount']);
    }

    public function testLogRowsComeFromDatabase(): void
    {
        global $wpdb;
        $wpdb->insert(Schema::table('logs'), [
            'event_time' => '2026-11-14 10:00:00',
            'direction' => 'nhanh_to_wc',
            'type' => 'product_sync',
            'status' => 'success',
            'reference_code' => 'BATCH-1',
            'title' => 'Đồng bộ thành công',
            'detail' => '100 sản phẩm',
            'duration_ms' => 1234,
            'source' => 'cron',
        ]);

        $rows = Data::get()['logs'];

        $this->assertCount(1, $rows);
        $this->assertSame('product_sync', $rows[0]['type']);
        $this->assertSame('BATCH-1', $rows[0]['ref']);
        $this->assertSame('success', $rows[0]['status']);
        $this->assertSame(1234, $rows[0]['ms']);
    }

    public function testSubmenuSlugsAreUniqueAndGrouped(): void
    {
        $submenus = (new Menu(new Renderer()))->submenus();

        $this->assertNotEmpty($submenus);
        foreach ($submenus as $slug => $item) {
            $this->assertArrayHasKey('title', $item, "Submenu {$slug} missing title");
            $this->assertArrayHasKey('menu_title', $item, "Submenu {$slug} missing menu_title");
            $this->assertArrayHasKey('group', $item, "Submenu {$slug} missing group");
            $this->assertContains($item['group'], ['dashboard', 'catalog', 'audit', 'settings']);
        }

        $this->assertArrayHasKey('nhanh-syncer', $submenus);
        $this->assertArrayHasKey('nhanh-syncer-settings', $submenus);
        $this->assertArrayHasKey('nhanh-syncer-orders', $submenus);
    }

    public function testRendererResolvesCompactModeByDefault(): void
    {
        $renderer = new Renderer();
        $method = new \ReflectionMethod($renderer, 'resolveUiMode');
        $method->setAccessible(true);

        $this->assertSame('compact', $method->invoke($renderer, []));
        $this->assertSame('advanced', $method->invoke($renderer, ['ui_mode' => 'advanced']));
        $this->assertSame('compact', $method->invoke($renderer, ['ui_mode' => 'nonsense']));
    }

    public function testOrderStatsReflectDatabase(): void
    {
        global $wpdb;
        $wpdb->insert(Schema::table('order_map'), ['wc_order_id' => 1, 'nhanh_order_id' => 'a', 'sync_status' => 'synced']);
        $wpdb->insert(Schema::table('order_map'), ['wc_order_id' => 2, 'nhanh_order_id' => 'b', 'sync_status' => 'pending']);
        $wpdb->insert(Schema::table('order_map'), ['wc_order_id' => 3, 'nhanh_order_id' => 'c', 'sync_status' => 'failed']);

        $stats = Data::get()['stats']['orders'];

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['synced']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['failed']);
    }
}