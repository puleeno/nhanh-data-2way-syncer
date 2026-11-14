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
        add_action('admin_post_nhanh_syncer_oauth_authorize', [$this, 'oauth_authorize']);
        add_action('admin_init', [$this, 'maybe_handle_oauth_callback']);
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'nhanh-data-2way-syncer'));
        }
        check_admin_referer('nhanh_syncer_save_settings');

        $sanitized = $this->settingsService->sanitize($_POST);
        $this->settingsService->save($sanitized);

        // Notify other parts of the plugin (e.g. cron scheduler) that settings changed.
        do_action('nhanh_syncer_settings_saved', $sanitized);

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

    /**
     * Redirect user to Nhanh.vn OAuth authorization page.
     */
    public function oauth_authorize(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'nhanh-data-2way-syncer'));
        }
        check_admin_referer('nhanh_syncer_oauth_authorize');

        $settings = $this->settingsService->all();
        $appId = isset($settings['app_id']) ? trim((string) $settings['app_id']) : '';
        $secretKey = isset($settings['secret_key']) ? trim((string) $settings['secret_key']) : '';
        if ($appId === '') {
            $this->redirect_settings(['nhanh_oauth' => 'missing_app_id']);
        }
        if ($secretKey === '') {
            $this->redirect_settings(['nhanh_oauth' => 'missing_credentials']);
        }

        $returnLink = $this->oauth_return_link($settings);
        $verifyCode = $this->generate_verify_code();
        $service = 'pos';
        // store verifyCode (spec: check on callback and send when exchanging token)
        set_transient('nhanh_syncer_oauth_verify_' . get_current_user_id(), $verifyCode, 10 * MINUTE_IN_SECONDS);
        $url = $this->build_oauth_url($appId, $secretKey, $returnLink, $service, $verifyCode);

        if ($url === '') {
            $this->redirect_settings(['nhanh_oauth' => 'error']);
        }

        wp_redirect($url);
        exit;
    }

    /**
     * Handle OAuth callback redirected back from Nhanh.vn (accessCode).
     */
    public function maybe_handle_oauth_callback(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'nhanh-syncer-settings') {
            return;
        }
        $accessCode = isset($_GET['accessCode']) ? sanitize_text_field(wp_unslash($_GET['accessCode'])) : '';
        if ($accessCode === '') {
            $err = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
            $errCode = isset($_GET['errorCode']) ? sanitize_text_field(wp_unslash($_GET['errorCode'])) : '';
            if ($err !== '' || $errCode !== '') {
                $this->redirect_settings(['nhanh_oauth' => 'denied']);
            }
            return;
        }

        $settings = $this->settingsService->all();
        $appId = isset($settings['app_id']) ? trim((string) $settings['app_id']) : '';
        $secretKey = isset($settings['secret_key']) ? trim((string) $settings['secret_key']) : '';
        if ($appId === '' || $secretKey === '') {
            $this->redirect_settings(['nhanh_oauth' => 'missing_credentials']);
        }

        $verifyKey = 'nhanh_syncer_oauth_verify_' . get_current_user_id();
        $expectedVerify = get_transient($verifyKey);
        $returnedVerify = isset($_GET['verifyCode']) ? sanitize_text_field(wp_unslash($_GET['verifyCode'])) : '';
        if ($expectedVerify && $returnedVerify !== '' && !hash_equals((string) $expectedVerify, (string) $returnedVerify)) {
            delete_transient($verifyKey);
            $this->redirect_settings(['nhanh_oauth' => 'invalid_verify']);
        }

        try {
            $result = $this->exchange_access_code($appId, $secretKey, $accessCode, $settings, $expectedVerify ? (string) $expectedVerify : ($returnedVerify ?: null));
            $token = '';
            $businessId = isset($settings['business_id']) ? (string) $settings['business_id'] : '';

            if (is_array($result)) {
                if (!empty($result['accessToken'])) {
                    $token = (string) $result['accessToken'];
                } elseif (!empty($result['access_token'])) {
                    $token = (string) $result['access_token'];
                } elseif (!empty($result['data']) && is_array($result['data'])) {
                    if (!empty($result['data']['accessToken'])) $token = (string) $result['data']['accessToken'];
                    if (!empty($result['data']['access_token'])) $token = (string) $result['data']['access_token'];
                    if (!empty($result['data']['businessId'])) $businessId = (string) $result['data']['businessId'];
                    if (!empty($result['data']['business_id'])) $businessId = (string) $result['data']['business_id'];
                }
                if (!empty($result['businessId'])) {
                    $businessId = (string) $result['businessId'];
                }
                if (!empty($result['business_id'])) {
                    $businessId = (string) $result['business_id'];
                }
            }

            if ($token === '') {
                $this->redirect_settings(['nhanh_oauth' => 'no_token']);
            }

            $settings['access_token'] = $token;
            $settings['business_id'] = $businessId;
            $settings['is_connected'] = 0;
            $this->settingsService->save($settings);
            delete_transient($verifyKey);
            $this->redirect_settings(['nhanh_oauth' => 'ok']);
        } catch (\Throwable $e) {
            $this->redirect_settings(['nhanh_oauth' => 'error']);
        }
    }

    /**
     * Return link must EXACTLY match the one registered in Nhanh.vn app settings.
     */
    private function oauth_return_link(array $settings = []): string
    {
        $configured = isset($settings['return_link']) ? trim((string) $settings['return_link']) : '';
        if ($configured !== '') {
            return $configured;
        }
        return admin_url('admin.php?page=nhanh-syncer-settings');
    }

    private function build_oauth_url(string $appId, string $secretKey, string $returnLink, string $service = 'pos', ?string $verifyCode = null): string
    {
        $returnLink = trim($returnLink);
        $appId = trim($appId);
        if ($returnLink === '' || $appId === '') {
            return '';
        }

        // v3 spec (common/2.access-token.md): appId, service, verifyCode, returnLink
        if (class_exists('\\Puleeno\\NhanhVn\\Config\\ClientConfig') && class_exists('\\Puleeno\\NhanhVn\\Client\\NhanhVnClient')) {
            try {
                $cfg = [
                    'appId' => $appId,
                    'secretKey' => $secretKey,
                    'returnLink' => $returnLink,
                    'apiVersion' => '3.0',
                    'oauthService' => $service,
                    'verifyCode' => $verifyCode,
                ];
                $clientConfig = new \Puleeno\NhanhVn\Config\ClientConfig($cfg);
                return $clientConfig->getOAuthUrl($verifyCode, $service);
            } catch (\Throwable $e) {
                // fallback
            }
        }

        $params = [
            'appId' => $appId,
            'service' => $service,
            'returnLink' => $returnLink,
        ];
        if (!empty($verifyCode)) {
            $params['verifyCode'] = $verifyCode;
        }
        return 'https://nhanh.vn/oauth?' . http_build_query($params);
    }

    /**
     * @return mixed
     * @throws \Throwable
     */
    private function exchange_access_code(string $appId, string $secretKey, string $accessCode, array $settings = [], ?string $verifyCode = null)
    {
        $returnLink = $this->oauth_return_link($settings);

        if (class_exists('\\Puleeno\\NhanhVn\\Config\\ClientConfig') && class_exists('\\Puleeno\\NhanhVn\\Client\\NhanhVnClient')) {
            $config = new \Puleeno\NhanhVn\Config\ClientConfig([
                'appId' => $appId,
                'secretKey' => $secretKey,
                'returnLink' => $returnLink,
                'apiVersion' => '3.0',
                'oauthService' => 'pos',
                'verifyCode' => $verifyCode,
            ]);
            $client = \Puleeno\NhanhVn\Client\NhanhVnClient::getInstance($config);
            return $client->oauth()->exchangeAccessCode($accessCode, $verifyCode);
        }

        throw new \RuntimeException('Nhanh.vn SDK is not available.');
    }

    private function generate_verify_code(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = 32;
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    /**
     * @param array<string,string> $args
     */
    private function redirect_settings(array $args): void
    {
        $redirect = add_query_arg(array_merge(['page' => 'nhanh-syncer-settings'], $args), admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}
