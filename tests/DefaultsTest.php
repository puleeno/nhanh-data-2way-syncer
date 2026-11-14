<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Config\Defaults;
use Puleeno\NhanhVn\Syncer\Repository\SettingsRepository;

class DefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
    }

    public function testSettingsExposesAllKeys(): void
    {
        $settings = Defaults::settings();

        $expected = [
            'app_id',
            'secret_key',
            'access_token',
            'business_id',
            'business_name',
            'return_link',
            'webhook_url',
            'webhook_secret',
            'webhook_active',
            'auto_sync_enabled',
            'sync_interval_minutes',
            'safety_stock_buffer',
            'auto_price_update',
            'auto_stock_update',
            'auto_create_missing_web_product',
            'brand_taxonomy',
            'import_post_status',
            'ui_mode',
            'products_per_page_compact',
            'products_per_page_advanced',
            'terms_per_page_compact',
            'terms_per_page_advanced',
            'orders_per_page_compact',
            'orders_per_page_advanced',
            'logs_per_page_compact',
            'logs_per_page_advanced',
            'is_connected',
            'ping_latency_ms',
            'last_heartbeat',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $settings, "Missing default: {$key}");
        }
    }

    public function testDefaultsAreSafeValues(): void
    {
        $settings = Defaults::settings();

        $this->assertSame('compact', $settings['ui_mode']);
        $this->assertSame(15, $settings['sync_interval_minutes']);
        $this->assertSame('product_brand', $settings['brand_taxonomy']);
        $this->assertSame('draft', $settings['import_post_status']);
        $this->assertSame(0, $settings['is_connected']);
    }

    public function testSettingsOptionName(): void
    {
        $this->assertSame('nhanh_syncer_settings', Defaults::OPTION_SETTINGS);
    }
}
