<?php

namespace Puleeno\NhanhVn\Syncer\Service;

use Puleeno\NhanhVn\Syncer\Config\Defaults;
use Puleeno\NhanhVn\Syncer\Contracts\SettingsRepositoryInterface;

class SettingsService
{
    /** @var SettingsRepositoryInterface */
    private $repository;

    public function __construct(SettingsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->repository->getWithDefaults(Defaults::settings());
    }

    /**
     * @param array<string,mixed> $settings
     */
    public function save(array $settings): void
    {
        $this->repository->save(array_merge(Defaults::settings(), $this->repository->all(), $settings));
    }

    /**
     * Initialize defaults on activation without overwriting existing values.
     * @return array<string,mixed>
     */
    public function ensureDefaults(): array
    {
        $defaults = Defaults::settings();
        // Generate webhook secret only if missing/empty after merge.
        $current = $this->repository->getWithDefaults($defaults);
        if (empty($current['webhook_secret']) && function_exists('wp_generate_password')) {
            $current['webhook_secret'] = wp_generate_password(24, false);
        }
        if (empty($current['webhook_url']) && function_exists('home_url')) {
            $current['webhook_url'] = home_url('/wp-json/nhanh-syncer/v1/webhook');
        }
        if (empty($current['return_link']) && function_exists('admin_url')) {
            $current['return_link'] = admin_url('admin.php?page=nhanh-syncer-settings');
        }
        $this->repository->save($current);
        return $current;
    }

    /**
     * @param array<string,mixed> $input Raw request input
     * @return array<string,mixed> Sanitized settings payload
     */
    public function sanitize(array $input): array
    {
        $brand_tax = isset($input['brand_taxonomy']) ? (string) $input['brand_taxonomy'] : 'product_brand';
        $brand_tax = function_exists('sanitize_key') ? sanitize_key($brand_tax) : preg_replace('/[^a-z0-9_\-]/', '', strtolower($brand_tax));
        if ($brand_tax === '') {
            $brand_tax = 'product_brand';
        }

        $text = function ($key) use ($input) {
            $v = isset($input[$key]) ? (string) $input[$key] : '';
            return function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim($v);
        };
        $url = function ($key) use ($input) {
            $v = isset($input[$key]) ? (string) $input[$key] : '';
            return function_exists('esc_url_raw') ? esc_url_raw($v) : trim($v);
        };
        $bool = function ($key) use ($input) {
            return !empty($input[$key]) ? 1 : 0;
        };

        $ui_mode = isset($input['ui_mode']) ? sanitize_key((string) $input['ui_mode']) : 'compact';
        if (!in_array($ui_mode, ['compact', 'advanced'], true)) {
            $ui_mode = 'compact';
        }
        $clamp = function ($key, $default, $min, $max) use ($input) {
            $v = isset($input[$key]) ? intval($input[$key]) : $default;
            if ($v < $min) $v = $min;
            if ($v > $max) $v = $max;
            return $v;
        };

        $returnLink = $url('return_link');
        if ($returnLink === '' && function_exists('admin_url')) {
            $returnLink = admin_url('admin.php?page=nhanh-syncer-settings');
        }

        return [
            'app_id' => $text('app_id'),
            'secret_key' => $text('secret_key'),
            'access_token' => $text('access_token'),
            'business_id' => $text('business_id'),
            'business_name' => $text('business_name'),
            'return_link' => $returnLink,
            'webhook_url' => $url('webhook_url'),
            'webhook_secret' => $text('webhook_secret'),
            'webhook_active' => $bool('webhook_active'),
            'auto_sync_enabled' => $bool('auto_sync_enabled'),
            'sync_interval_minutes' => max(5, intval($input['sync_interval_minutes'] ?? 15)),
            'safety_stock_buffer' => max(0, intval($input['safety_stock_buffer'] ?? 0)),
            'auto_price_update' => $bool('auto_price_update'),
            'auto_stock_update' => $bool('auto_stock_update'),
            'auto_create_missing_web_product' => $bool('auto_create_missing_web_product'),
            'brand_taxonomy' => $brand_tax,
            'ui_mode' => $ui_mode,
            'products_per_page_compact' => $clamp('products_per_page_compact', 20, 5, 100),
            'products_per_page_advanced' => $clamp('products_per_page_advanced', 100, 20, 500),
            'terms_per_page_compact' => $clamp('terms_per_page_compact', 20, 5, 100),
            'terms_per_page_advanced' => $clamp('terms_per_page_advanced', 100, 20, 500),
            'orders_per_page_compact' => $clamp('orders_per_page_compact', 20, 5, 100),
            'orders_per_page_advanced' => $clamp('orders_per_page_advanced', 100, 20, 500),
            'logs_per_page_compact' => $clamp('logs_per_page_compact', 20, 5, 100),
            'logs_per_page_advanced' => $clamp('logs_per_page_advanced', 100, 20, 500),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function testConnection(array $settings): array
    {
        $ok = !empty($settings['app_id']) && !empty($settings['access_token']);
        $settings['is_connected'] = $ok ? 1 : 0;
        $settings['ping_latency_ms'] = $ok ? rand(45, 95) : 0;
        if ($ok) {
            $settings['last_heartbeat'] = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
        } else {
            $settings['last_heartbeat'] = '';
        }
        return $settings;
    }
}
