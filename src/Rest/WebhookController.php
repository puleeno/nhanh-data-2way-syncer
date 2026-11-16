<?php

namespace Puleeno\NhanhVn\Syncer\Rest;

use Puleeno\NhanhVn\Syncer\Service\SettingsService;
use Puleeno\NhanhVn\Syncer\Webhook\WebhookDispatcher;

class WebhookController
{
    public const NAMESPACE = 'nhanh-syncer/v1';

    /** @var SettingsService */
    private $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        if (!function_exists('register_rest_route')) return;
        register_rest_route(self::NAMESPACE, '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @param mixed $request WP_REST_Request or array-like
     * @return mixed WP_REST_Response|array
     */
    public function handle($request)
    {
        $settings = $this->settingsService->all();
        if (empty($settings['webhook_active'])) {
            return $this->response(['ok' => false, 'error' => 'webhook_disabled'], 403);
        }

        $payload = $this->extractPayload($request);
        if (!$this->verifySecret($request, $settings, $payload)) {
            return $this->response(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        $dispatcher = new WebhookDispatcher($settings);
        $result = $dispatcher->dispatch(is_array($payload) ? $payload : []);
        return $this->response($result, !empty($result['ok']) ? 200 : 202);
    }

    /** @return array<string,mixed> */
    private function extractPayload($request): array
    {
        $params = [];
        if (is_object($request) && method_exists($request, 'get_json_params')) {
            $params = $request->get_json_params();
        }
        if (!is_array($params) || !$params) {
            $body = is_object($request) && method_exists($request, 'get_body') ? $request->get_body() : file_get_contents('php://input');
            $decoded = json_decode((string) $body, true);
            if (is_array($decoded)) $params = $decoded;
        }
        if (!is_array($params) && is_object($request) && method_exists($request, 'get_params')) {
            $params = (array) $request->get_params();
        }
        return is_array($params) ? $params : [];
    }

    private function verifySecret($request, array $settings, array $payload): bool
    {
        $secret = isset($settings['webhook_secret']) ? (string) $settings['webhook_secret'] : '';
        if ($secret === '') return true; // no secret configured => accept

        $provided = '';
        if (is_object($request) && method_exists($request, 'get_header')) {
            $provided = (string) ($request->get_header('x-nhanh-secret') ?: $request->get_header('x-webhook-secret') ?: '');
        }
        if ($provided === '' && isset($_SERVER['HTTP_X_NHANH_SECRET'])) $provided = (string) $_SERVER['HTTP_X_NHANH_SECRET'];
        if ($provided !== '' && hash_equals($secret, $provided)) return true;

        // optional HMAC over raw body
        $sig = '';
        if (is_object($request) && method_exists($request, 'get_header')) {
            $sig = (string) ($request->get_header('x-nhanh-signature') ?: $request->get_header('x-hub-signature-256') ?: '');
        }
        if ($sig !== '') {
            $body = is_object($request) && method_exists($request, 'get_body') ? (string) $request->get_body() : wp_json_encode($payload);
            $calc = 'sha256=' . hash_hmac('sha256', $body, $secret);
            if (hash_equals($calc, $sig) || hash_equals(hash_hmac('sha256', $body, $secret), $sig)) return true;
        }

        return false;
    }

    private function response(array $data, int $status = 200)
    {
        if (class_exists('WP_REST_Response')) {
            return new \WP_REST_Response($data, $status);
        }
        if (function_exists('wp_send_json')) { wp_send_json($data, $status); }
        return $data;
    }
}
