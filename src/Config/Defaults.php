<?php

namespace Puleeno\NhanhVn\Syncer\Config;

class Defaults
{
    public const OPTION_SETTINGS = 'nhanh_syncer_settings';

    /**
     * @return array<string,mixed>
     */
    public static function settings(): array
    {
        return [
            'app_id' => '',
            'secret_key' => '',
            'access_token' => '',
            'business_id' => '',
            'business_name' => '',
            'webhook_url' => function_exists('home_url') ? home_url('/wp-json/nhanh-syncer/v1/webhook') : '',
            'webhook_secret' => '',
            'webhook_active' => 1,
            'auto_sync_enabled' => 1,
            'sync_interval_minutes' => 15,
            'safety_stock_buffer' => 2,
            'auto_price_update' => 1,
            'auto_stock_update' => 1,
            'auto_create_missing_web_product' => 0,
            'brand_taxonomy' => 'product_brand',
            // UI/performance modes for catalogs with many products/variations.
            'ui_mode' => 'compact', // compact|advanced
            'products_per_page_compact' => 20,
            'products_per_page_advanced' => 100,
            'terms_per_page_compact' => 20,
            'terms_per_page_advanced' => 100,
            'logs_per_page_compact' => 20,
            'logs_per_page_advanced' => 100,
            'is_connected' => 0,
            'ping_latency_ms' => 0,
            'last_heartbeat' => '',
        ];
    }
}
