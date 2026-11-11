<?php

namespace Puleeno\NhanhVn\Syncer;

use Puleeno\NhanhVn\Syncer\Admin\Menu;
use Puleeno\NhanhVn\Syncer\Admin\Assets;
use Puleeno\NhanhVn\Syncer\Admin\Settings;
use Puleeno\NhanhVn\Syncer\Database\Schema;

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
        // Keep DB schema in sync when plugin version/db version changes.
        add_action('admin_init', [Schema::class, 'maybe_upgrade']);

        if (!self::is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'notice_woocommerce_required']);
            return;
        }

        if (is_admin()) {
            (new Menu())->register();
            (new Assets())->register();
            (new Settings())->register();
        }
    }

    public function notice_woocommerce_required(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Nhanh Data 2-Way Syncer yêu cầu WooCommerce đang hoạt động để đồng bộ sản phẩm, danh mục, thương hiệu và đơn hàng.', 'nhanh-data-2way-syncer');
        echo '</p></div>';
    }

    public static function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
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
            'brand_taxonomy' => 'product_brand',
            'is_connected' => 0,
            'ping_latency_ms' => 0,
            'last_heartbeat' => '',
        ];
        $existing = get_option('nhanh_syncer_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }
        update_option('nhanh_syncer_settings', array_merge($defaults, $existing));

        // Install DB schema (safe if called multiple times via dbDelta).
        Schema::install();
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
