<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Admin\Menu;
use Puleeno\NhanhVn\Syncer\Admin\Pages\Data;
use Puleeno\NhanhVn\Syncer\Admin\Pages\Renderer;

class AdminStructureTest extends TestCase
{
    public function testDataExposesEveryCatalogKey(): void
    {
        $data = Data::get();

        foreach (['products', 'term_maps', 'order_maps', 'logs', 'revenue', 'turnover'] as $key) {
            $this->assertArrayHasKey($key, $data, "Data::get() missing {$key}");
        }
        $this->assertNotEmpty($data['products']);
        $this->assertNotEmpty($data['term_maps']);
    }

    public function testEveryProductRowHasRequiredFields(): void
    {
        $required = [
            'nhanh_code',
            'nhanh_name',
            'nhanh_price',
            'nhanh_stock',
            'sync_status',
            'web_id',
            'web_price',
            'web_stock',
        ];

        foreach (Data::get()['products'] as $index => $product) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $product, "Product #{$index} missing {$field}");
            }
        }
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
}