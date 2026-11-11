<?php

namespace Puleeno\NhanhVn\Syncer;

use Puleeno\NhanhVn\Syncer\Admin\Menu;
use Puleeno\NhanhVn\Syncer\Admin\Assets;
use Puleeno\NhanhVn\Syncer\Admin\Settings;

class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if (is_admin()) {
            (new Menu())->register();
            (new Assets())->register();
            (new Settings())->register();
        }
    }

    public static function activate(): void
    {
        $defaults = [
            'app_id' => '',
            'secret_key' => '',
            'access_token' => '',
            'business_id' => '',
            'business_name' => '',
            'webhook_url' => home_url('/wp-json/nhanh-syncer/v1/webhook'),
            'webhook_secret' => wp_generate_password(24, false),
            'webhook_active' => 1,
            'auto_sync_enabled' => 1,
            'sync_interval_minutes' => 15,
            'safety_stock_buffer' => 2,
            'auto_price_update' => 1,
            'auto_stock_update' => 1,
            'auto_create_missing_web_product' => 0,
            'is_connected' => 0,
            'ping_latency_ms' => 0,
            'last_heartbeat' => '',
        ];
        $existing = get_option('nhanh_syncer_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }
        update_option('nhanh_syncer_settings', array_merge($defaults, $existing));
    }

    public static function deactivate(): void
    {
        // Keep settings/data for safety.
    }

    public static function get_settings(): array
    {
        $settings = get_option('nhanh_syncer_settings', []);
        return is_array($settings) ? $settings : [];
    }

    public static function update_settings(array $settings): void
    {
        update_option('nhanh_syncer_settings', $settings);
    }
}
