<?php

namespace Puleeno\NhanhVn\Syncer\Database;

/**
 * Repository over `nhanh_sync_term_map` for category/brand mapping.
 */
class TermMapRepository
{
    private function table(): string
    {
        return Schema::table('term_map');
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByNhanh(string $type, string $nhanhTermId, string $wcTaxonomy = 'product_cat'): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE nhanh_term_type = %s AND nhanh_term_id = %s AND wc_taxonomy = %s",
                $type,
                $nhanhTermId,
                $wcTaxonomy
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByWcTerm(string $wcTaxonomy, int $wcTermId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE wc_taxonomy = %s AND wc_term_id = %d",
                $wcTaxonomy,
                $wcTermId
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Upsert a mapping row. Returns the row id (0 on failure).
     */
    public function upsert(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', 1);
        $payload = array_merge([
            'updated_at' => $now,
        ], $data);

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table()} WHERE nhanh_term_type = %s AND nhanh_term_id = %s AND wc_taxonomy = %s",
                $payload['nhanh_term_type'] ?? '',
                $payload['nhanh_term_id'] ?? '',
                $payload['wc_taxonomy'] ?? ''
            )
        );

        if ($exists) {
            $wpdb->update($this->table(), $payload, ['id' => (int) $exists]);
            return (int) $exists;
        }

        $payload['created_at'] = $now;
        $res = $wpdb->insert($this->table(), $payload);
        return $res ? (int) $wpdb->insert_id : 0;
    }

    /**
     * List all mapped rows for a taxonomy (used by UI).
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $wcTaxonomy = ''): array
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table()}";
        $params = [];
        if ($wcTaxonomy !== '') {
            $sql .= " WHERE wc_taxonomy = %s";
            $params = [$wcTaxonomy];
        }
        $sql .= " ORDER BY id ASC";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}