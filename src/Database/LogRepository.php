<?php

namespace Puleeno\NhanhVn\Syncer\Database;

/**
 * Read-only queries for the admin audit log screen.
 */
class LogRepository
{
    private function table(): string
    {
        return Schema::table('logs');
    }

    /**
     * Most recent log rows, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 500): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} ORDER BY event_time DESC, id DESC LIMIT %d", $limit),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }
}