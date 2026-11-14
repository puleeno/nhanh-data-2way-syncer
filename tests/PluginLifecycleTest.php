<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Plugin;
use Puleeno\NhanhVn\Syncer\Service\SyncCronService;

class PluginLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['__wp_cron_events'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
    }

    public function testIsWooCommerceActiveReflectsRuntime(): void
    {
        // WC_Product stub is always defined in bootstrap, so this asserts the helper's contract.
        $this->assertTrue(Plugin::is_woocommerce_active());
    }

    public function testActivateInstallsSchemaAndSchedulesCron(): void
    {
        Plugin::activate();

        $this->assertSame(NHANH_SYNCER_DB_VERSION, get_option('nhanh_syncer_db_version'));
        $this->assertArrayHasKey(SyncCronService::HOOK, $GLOBALS['__wp_cron_events']);
    }

    public function testDeactivateClearsCron(): void
    {
        Plugin::activate();
        $this->assertArrayHasKey(SyncCronService::HOOK, $GLOBALS['__wp_cron_events']);

        Plugin::deactivate();
        $this->assertArrayNotHasKey(SyncCronService::HOOK, $GLOBALS['__wp_cron_events']);
    }

    public function testSettingsRoundTrip(): void
    {
        Plugin::update_settings(['app_id' => '999', 'business_id' => '42']);

        $settings = Plugin::get_settings();

        $this->assertSame('999', $settings['app_id']);
        $this->assertSame('42', $settings['business_id']);
        // Defaults remain present after merge.
        $this->assertArrayHasKey('sync_interval_minutes', $settings);
    }

    public function testRunImportJobHandlesMissingCredentialsGracefully(): void
    {
        $plugin = Plugin::instance();
        $plugin->run_import_job();

        // No exception thrown; a failed job row was recorded.
        $jobs = new \Puleeno\NhanhVn\Syncer\Database\JobRepository();
        $recent = $jobs->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('failed', $recent[0]['status']);
    }
}