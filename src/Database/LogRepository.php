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

    /**
     * Paginated search with filters (for admin log screen).
     *
     * @param array<string,mixed> $filters q,status,type
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        global $wpdb;
        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $where = [];
        $args = [];

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(title LIKE %s OR detail LIKE %s OR reference_code LIKE %s OR type LIKE %s OR status LIKE %s)';
            array_push($args, $like, $like, $like, $like, $like);
        }
        $status = isset($filters['status']) ? sanitize_key((string) $filters['status']) : '';
        if ($status !== '' && $status !== 'all') {
            $where[] = 'status = %s';
            $args[] = $status;
        }
        $type = isset($filters['type']) ? sanitize_key((string) $filters['type']) : '';
        if ($type !== '' && $type !== 'all') {
            $where[] = 'type = %s';
            $args[] = $type;
        }

        $table = $this->table();
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countSql = "SELECT COUNT(*) FROM {$table} {$whereSql}";
        $countSql = $args ? $wpdb->prepare($countSql, $args) : $countSql;
        $total = (int) $wpdb->get_var($countSql);

        $totalPages = (int) max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$table} {$whereSql} ORDER BY event_time DESC, id DESC LIMIT %d OFFSET %d";
        $rowsArgs = array_merge($args, [$perPage, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $rowsArgs), ARRAY_A);

        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }
}
