<?php

namespace Puleeno\NhanhVn\Syncer\Admin;

use Puleeno\NhanhVn\Syncer\Service\SettingsService;

class Settings
{
    /** @var SettingsService */
    private $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

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

        $sanitized = $this->settingsService->sanitize($_POST);
        $this->settingsService->save($sanitized);

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

        $settings = $this->settingsService->all();
        $tested = $this->settingsService->testConnection($settings);
        $this->settingsService->save($tested);

        $redirect = add_query_arg([
            'page' => 'nhanh-syncer-settings',
            'nhanh_test' => !empty($tested['is_connected']) ? 'ok' : 'fail',
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}
