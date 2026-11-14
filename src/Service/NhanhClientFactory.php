<?php

namespace Puleeno\NhanhVn\Syncer\Service;

use Puleeno\NhanhVn\Config\ClientConfig;
use Puleeno\NhanhVn\Client\NhanhVnClient;
use Puleeno\NhanhVn\Client\NhanhClientBuilder;

/**
 * Builds a Nhanh.vn v3 SDK client from plugin settings.
 *
 * The plugin stores OAuth-obtained `access_token` and `business_id` together
 * with `app_id`. This factory turns them into a ready-to-call client.
 */
class NhanhClientFactory
{
    /** @var array<string,mixed> */
    private $settings;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Build a fully-configured client, or null when credentials are missing.
     */
    public function make(): ?NhanhVnClient
    {
        $appId = isset($this->settings['app_id']) ? trim((string) $this->settings['app_id']) : '';
        $businessId = isset($this->settings['business_id']) ? trim((string) $this->settings['business_id']) : '';
        $accessToken = isset($this->settings['access_token']) ? trim((string) $this->settings['access_token']) : '';

        if ($appId === '' || $businessId === '' || $accessToken === '') {
            return null;
        }

        if (!class_exists(ClientConfig::class) || !class_exists(NhanhVnClient::class)) {
            return null;
        }

        try {
            $config = new ClientConfig([
                'appId' => $appId,
                'businessId' => $businessId,
                'accessToken' => $accessToken,
                'apiVersion' => '3.0',
                'oauthService' => 'pos',
            ]);
            return NhanhVnClient::getInstance($config);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build via the fluent builder (kept as a fallback path).
     */
    public function makeViaBuilder(): ?NhanhVnClient
    {
        if (!class_exists(NhanhClientBuilder::class)) {
            return null;
        }
        $appId = isset($this->settings['app_id']) ? trim((string) $this->settings['app_id']) : '';
        $businessId = isset($this->settings['business_id']) ? trim((string) $this->settings['business_id']) : '';
        $accessToken = isset($this->settings['access_token']) ? trim((string) $this->settings['access_token']) : '';
        if ($appId === '' || $businessId === '' || $accessToken === '') {
            return null;
        }
        try {
            return NhanhClientBuilder::create()
                ->withAppId($appId)
                ->withBusinessId($businessId)
                ->withAccessToken($accessToken)
                ->withApiVersion('3.0')
                ->build();
        } catch (\Throwable $e) {
            return null;
        }
    }
}