<?php

namespace Puleeno\NhanhVn\Syncer\Service;

use Puleeno\NhanhVn\Syncer\Database\JobRepository;

/**
 * WP Cron glue for Nhanh Data 2-Way Syncer imports.
 *
 * Registers the recurring import hook (`nhanh_syncer_run_import`) whenever the
 * plugin is activated or the sync interval is saved, and clears it on
 * deactivation or when auto-sync is turned off. A WP-cron-driven job means no
 * long-running background worker is required — wp-cron.php picks the event up
 * on the next scheduled tick.
 */
class SyncCronService
{
    public const HOOK = 'nhanh_syncer_run_import';

    /** @var JobRepository */
    private $jobs;

    public function __construct(JobRepository $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * Activate plugin: schedule recurring import event if auto sync is enabled.
     */
    public function activate(): void
    {
        $settings = $this->settings();
        $interval = $this->resolveInterval($settings);
        if ($interval <= 0 || empty($settings['auto_sync_enabled'])) {
            $this->clearCron();
            return;
        }
        $this->scheduleRecurring($interval);
    }

    /**
     * Deactivate plugin: clear the scheduled event.
     */
    public function deactivate(): void
    {
        $this->clearCron();
    }

    /**
     * Settings save hook: reschedule based on new auto_sync_enabled + interval.
     */
    public function onSettingsSave(array $settings): void
    {
        $interval = $this->resolveInterval($settings);
        if ($interval <= 0 || empty($settings['auto_sync_enabled'])) {
            $this->clearCron();
            return;
        }
        $this->scheduleRecurring($interval);
    }

    /**
     * Schedule an immediate one-shot import (used by admin button / manual run).
     */
    public function runImmediately(): int
    {
        $timestamp = time();
        if (function_exists('wp_schedule_single_event')) {
            wp_clear_scheduled_hook(self::HOOK . '_once');
            wp_schedule_single_event($timestamp, self::HOOK . '_once');
        }
        return $timestamp;
    }

    /**
     * Dispatch the import service now. Called by admin action or external cron.
     *
     * @return array<string,mixed>
     */
    public function dispatchNow(array $settings = []): array
    {
        $settings = $settings ?: $this->settings();
        $interval = $this->resolveInterval($settings);
        if ($interval <= 0) {
            return ['ok' => false, 'error' => 'auto_sync_disabled'];
        }

        // Prevent duplicate concurrent runs.
        $running = $this->jobs->recent(5);
        foreach ($running as $job) {
            if ($job['job_type'] === 'import' && $job['status'] === 'running') {
                return ['ok' => false, 'error' => 'already_running'];
            }
        }

        $import = new ImportService(
            $this->jobs,
            new \Puleeno\NhanhVn\Syncer\Database\ProductMapRepository(),
            new \Puleeno\NhanhVn\Syncer\Database\TermMapRepository(),
            new \Puleeno\NhanhVn\Syncer\Woo\WooBridge()
        );

        return $import->run(['trigger' => 'manual']);
    }

    // ---------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------

    /**
     * @param array<string,mixed> $settings
     */
    private function resolveInterval(array $settings): int
    {
        $interval = $settings['sync_interval_minutes'] ?? 0;
        if (!is_numeric($interval)) {
            return 0;
        }
        $interval = (int) $interval;
        return $interval >= 5 ? $interval : 0;
    }

    private function scheduleRecurring(int $intervalMinutes): void
    {
        if (!function_exists('wp_schedule_event')) {
            return;
        }
        $timestamp = time();
        wp_clear_scheduled_hook(self::HOOK);
        $args = ['job_type' => 'import'];
        wp_schedule_event($timestamp + ($intervalMinutes * 60), 'nhanh_syncer_' . $intervalMinutes, self::HOOK, $args);
    }

    private function clearCron(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function settings(): array
    {
        if (class_exists(\Puleeno\NhanhVn\Syncer\Plugin::class)) {
            $s = \Puleeno\NhanhVn\Syncer\Plugin::get_settings();
            if (is_array($s)) {
                return $s;
            }
        }
        return [];
    }
}