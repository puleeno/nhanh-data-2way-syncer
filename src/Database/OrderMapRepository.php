<?php

namespace Puleeno\NhanhVn\Syncer\Database;

/**
 * Read-only queries for the admin list screens (order map).
 */
class OrderMapRepository
{
    private function table(): string
    {
        return Schema::table('order_map');
    }

    /**
     * All order mappings, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(int $limit = 5000): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Aggregate counters for the KPI row.
     *
     * @return array{total:int,synced:int,pending:int,failed:int}
     */
    public function counts(): array
    {
        global $wpdb;
        $table = $this->table();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $synced = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE sync_status = 'synced'");
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE sync_status IN ('pending','created')");

        $failed = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE sync_status IN ('failed','error')"
        );

        return ['total' => $total, 'synced' => $synced, 'pending' => $pending, 'failed' => $failed];
    }
}