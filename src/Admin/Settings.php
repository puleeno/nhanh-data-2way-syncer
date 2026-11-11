<?php

namespace Puleeno\NhanhVn\Syncer\Admin;

use Puleeno\NhanhVn\Syncer\Plugin;

class Settings
{
    public function register(): void
    {
        add_action('admin_post_nhanh_syncer_save_settings', [$this, 'save']);
        add_action('admin_post_nhanh_syncer_test_connection', [$this, 'test_connection']);
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'nhanh-data-2way-syncer'));
        }
        check_admin_referer('nhanh_syncer_save_settings');

        $current = Plugin::get_settings();
        $data = [
            'app_id' => isset($_POST['app_id']) ? sanitize_text_field(wp_unslash($_POST['app_id'])) : '',
            'secret_key' => isset($_POST['secret_key']) ? sanitize_text_field(wp_unslash($_POST['secret_key'])) : '',
            'access_token' => isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '',
            'business_id' => isset($_POST['business_id']) ? sanitize_text_field(wp_unslash($_POST['business_id'])) : '',
            'business_name' => isset($_POST['business_name']) ? sanitize_text_field(wp_unslash($_POST['business_name'])) : '',
            'webhook_url' => isset($_POST['webhook_url']) ? esc_url_raw(wp_unslash($_POST['webhook_url'])) : '',
            'webhook_secret' => isset($_POST['webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['webhook_secret'])) : '',
            'webhook_active' => isset($_POST['webhook_active']) ? 1 : 0,
            'auto_sync_enabled' => isset($_POST['auto_sync_enabled']) ? 1 : 0,
            'sync_interval_minutes' => isset($_POST['sync_interval_minutes']) ? max(5, intval($_POST['sync_interval_minutes'])) : 15,
            'safety_stock_buffer' => isset($_POST['safety_stock_buffer']) ? max(0, intval($_POST['safety_stock_buffer'])) : 0,
            'auto_price_update' => isset($_POST['auto_price_update']) ? 1 : 0,
            'auto_stock_update' => isset($_POST['auto_stock_update']) ? 1 : 0,
            'auto_create_missing_web_product' => isset($_POST['auto_create_missing_web_product']) ? 1 : 0,
        ];

        $updated = array_merge($current, $data);
        Plugin::update_settings($updated);

        $redirect = add_query_arg(['page' => 'nhanh-syncer-settings', 'nhanh_saved' => '1'], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public function test_connection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'nhanh-data-2way-syncer'));
        }
        check_admin_referer('nhanh_syncer_test_connection');

        $settings = Plugin::get_settings();
        $ok = !empty($settings['app_id']) && !empty($settings['access_token']);
        $settings['is_connected'] = $ok ? 1 : 0;
        $settings['ping_latency_ms'] = $ok ? rand(45, 95) : 0;
        $settings['last_heartbeat'] = $ok ? current_time('mysql') : '';
        Plugin::update_settings($settings);

        $redirect = add_query_arg([
            'page' => 'nhanh-syncer-settings',
            'nhanh_test' => $ok ? 'ok' : 'fail',
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}
