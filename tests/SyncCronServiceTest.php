<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Service\SyncCronService;
use Puleeno\NhanhVn\Syncer\Database\JobRepository;

class SyncCronServiceTest extends TestCase
{
    private SyncCronService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['__wp_cron_events'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
        $this->service = new SyncCronService(new JobRepository());
    }

    public function testActivateSchedulesWhenAutoSyncEnabled(): void
    {
        update_option('nhanh_syncer_settings', ['auto_sync_enabled' => 1, 'sync_interval_minutes' => 15]);
        $this->service->activate();
        $this->assertArrayHasKey(SyncCronService::HOOK, $GLOBALS['__wp_cron_events']);
    }

    public function testActivateDoesNotScheduleWhenAutoSyncDisabled(): void
    {
        update_option('nhanh_syncer_settings', ['auto_sync_enabled' => 0, 'sync_interval_minutes' => 15]);
        $this->service->activate();
        $this->assertArrayNotHasKey(SyncCronService::HOOK, $GLOBALS['__wp_cron_events']);
    }

    public function testDeactivateClearsCron(): void
    {
        update_option('nhanh_syncer_settings', ['auto_sync_enabled' => 1, 'sync_interval_minutes' => 15]);
        $this->service->activate();
        $this->service->deactivate();
        $this->assertArrayNotHasKey(SyncCronService::HOOK, $GLOBALS['__wp_cron_events']);
    }

    public function testOnSettingsSaveReschedules(): void
    {
        update_option('nhanh_syncer_settings', ['auto_sync_enabled' => 0]);
        $this->service->onSettingsSave(['auto_sync_enabled' => 1, 'sync_interval_minutes' => 30]);
        $this->assertArrayHasKey(SyncCronService::HOOK, $GLOBALS['__wp_cron_events']);
    }

    public function testRecurrenceIdentifier(): void
    {
        $this->assertSame('nhanh_syncer_15min', SyncCronService::recurrenceIdentifier(15));
        $this->assertSame('nhanh_syncer_60min', SyncCronService::recurrenceIdentifier(60));
    }

    public function testRegisterRecurrences(): void
    {
        $schedules = SyncCronService::registerRecurrences([]);
        $this->assertArrayHasKey('nhanh_syncer_5min', $schedules);
        $this->assertArrayHasKey('nhanh_syncer_15min', $schedules);
        $this->assertArrayHasKey('nhanh_syncer_30min', $schedules);
        $this->assertArrayHasKey('nhanh_syncer_60min', $schedules);
    }
}