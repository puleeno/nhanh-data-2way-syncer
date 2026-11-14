<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Service\SettingsService;
use Puleeno\NhanhVn\Syncer\Repository\SettingsRepository;

class SettingsServiceTest extends TestCase
{
    private SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $repo = new SettingsRepository();
        $this->service = new SettingsService($repo);
    }

    public function testAllReturnsDefaultsWhenEmpty(): void
    {
        $settings = $this->service->all();
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('app_id', $settings);
    }

    public function testAllReturnsStoredSettings(): void
    {
        update_option('nhanh_syncer_settings', ['app_id' => '123', 'business_id' => '456']);
        $settings = $this->service->all();
        $this->assertSame('123', $settings['app_id']);
    }

    public function testSaveMergesWithDefaults(): void
    {
        $this->service->save(['app_id' => '123']);
        $settings = $this->service->all();
        $this->assertSame('123', $settings['app_id']);
        $this->assertArrayHasKey('secret_key', $settings);
    }

    public function testEnsureDefaultsCreatesDefaults(): void
    {
        $this->service->ensureDefaults();
        $settings = $this->service->all();
        $this->assertArrayHasKey('app_id', $settings);
    }

    public function testSanitizeCleansInput(): void
    {
        $input = ['app_id' => '  123  ', 'brand_taxonomy' => 'product_brand'];
        $clean = $this->service->sanitize($input);
        $this->assertSame('123', $clean['app_id']);
    }

    public function testSanitizeClampsPerPage(): void
    {
        $input = ['products_per_page_compact' => 1000];
        $clean = $this->service->sanitize($input);
        $this->assertLessThanOrEqual(500, $clean['products_per_page_compact']);
    }

    public function testSanitizeClampsInterval(): void
    {
        $input = ['sync_interval_minutes' => 1];
        $clean = $this->service->sanitize($input);
        $this->assertGreaterThanOrEqual(5, $clean['sync_interval_minutes']);
    }

    public function testTestConnectionReturnsSettings(): void
    {
        $settings = ['app_id' => '123', 'access_token' => 'token'];
        $result = $this->service->testConnection($settings);
        $this->assertArrayHasKey('is_connected', $result);
    }
}