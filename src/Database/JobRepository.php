<?php

namespace Puleeno\NhanhVn\Syncer\Database;

/**
 * Repository over `nhanh_sync_jobs` for cron/manual import tracking.
 *
 * Keeps the schema abstraction in one place so the import flow only talks
 * about job types/statuses, not raw SQL.
 */
class JobRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';

    private function table(): string
    {
        return Schema::table('jobs');
    }

    private function logsTable(): string
    {
        return Schema::table('logs');
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 20): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Create a fresh pending job and return its id (0 on failure).
     */
    public function create(string $jobType, array $summary = []): int
    {
        global $wpdb;
        $res = $wpdb->insert($this->table(), [
            'job_type' => $jobType,
            'status' => self::STATUS_PENDING,
            'started_at' => null,
            'finished_at' => null,
            'scanned' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'summary_json' => $summary ? wp_json_encode($summary) : null,
            'created_at' => current_time('mysql', 1),
        ]);
        return $res ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Mark a job running (started_at = now).
     */
    public function start(int $jobId): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $this->table(),
            ['status' => self::STATUS_RUNNING, 'started_at' => current_time('mysql', 1)],
            ['id' => $jobId]
        );
    }

    /**
     * Finish a job with a status and counters.
     */
    public function finish(int $jobId, string $status, int $scanned, int $updated, int $failed, array $summary = []): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $this->table(),
            [
                'status' => $status,
                'finished_at' => current_time('mysql', 1),
                'scanned' => $scanned,
                'updated_count' => $updated,
                'failed_count' => $failed,
                'summary_json' => $summary ? wp_json_encode($summary) : null,
            ],
            ['id' => $jobId]
        );
    }

    /**
     * Append an audit log row (best-effort; never throws).
     *
     * @param array<string,mixed> $extra Optional: product_map_id,wc_order_id,nhanh_order_id,payload_json,response_json,dedupe_key,direction,source
     */
    public function log(int $jobId, string $type, string $status, string $title, string $detail = '', ?int $durationMs = null, ?string $referenceCode = null, array $extra = []): void
    {
        $row = [
            'event_time' => current_time('mysql', 1),
            'direction' => isset($extra['direction']) ? (string) $extra['direction'] : 'nhanh_to_wc',
            'type' => $type,
            'status' => $status,
            'reference_code' => $referenceCode,
            'title' => $title,
            'detail' => $detail,
            'duration_ms' => $durationMs,
            'source' => isset($extra['source']) ? (string) $extra['source'] : 'cron',
            'created_at' => current_time('mysql', 1),
        ];
        foreach (['product_map_id', 'wc_order_id', 'nhanh_order_id', 'payload_json', 'response_json', 'dedupe_key'] as $k) {
            if (array_key_exists($k, $extra) && $extra[$k] !== null && $extra[$k] !== '') {
                $row[$k] = $extra[$k];
            }
        }
        // Lightweight link to job in payload for traceability (table has no job_id column)
        if ($jobId > 0) {
            $meta = ['job_id' => $jobId];
            if (!empty($row['payload_json'])) {
                $decoded = json_decode((string) $row['payload_json'], true);
                if (is_array($decoded)) {
                    $meta = array_merge($meta, $decoded);
                } else {
                    $meta['payload'] = $row['payload_json'];
                }
            }
            $row['payload_json'] = function_exists('wp_json_encode') ? wp_json_encode($meta) : json_encode($meta);
        }

        $this->insertLog($row);
    }

    /**
     * @param array<string,mixed> $row
     */
    public function insertLog(array $row): bool
    {
        global $wpdb;
        if (empty($row['event_time'])) {
            $row['event_time'] = current_time('mysql', 1);
        }
        if (empty($row['created_at'])) {
            $row['created_at'] = current_time('mysql', 1);
        }
        $ok = $wpdb->insert($this->logsTable(), $row);
        return (bool) $ok;
    }
}
