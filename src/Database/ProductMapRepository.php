<?php

namespace Puleeno\NhanhVn\Syncer\Database;

/**
 * Repository over `nhanh_sync_product_map` — the canonical Nhanh→WC link.
 *
 * Import flow writes here first; the actual WooCommerce product create/update
 * is delegated to a dedicated WooBridge so the mapping table stays the source
 * of truth even if a WC call fails midway.
 */
class ProductMapRepository
{
    private function table(): string
    {
        return Schema::table('product_map');
    }

    /**
     * Upsert a product mapping row. Returns the row id (0 on failure).
     */
    public function upsert(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', 1);

        $payload = array_merge([
            'updated_at' => $now,
        ], $data);

        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table()} WHERE nhanh_code = %s", $payload['nhanh_code'] ?? '')
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
     * Get one row by nhanh_code.
     *
     * @return array<string,mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE nhanh_code = %s", $code),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Get one row by Nhanh product id.
     *
     * @return array<string,mixed>|null
     */
    public function findByNhanhId(string $nhanhId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE nhanh_id = %s", $nhanhId),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Update the WooCommerce-side link after a product is created/updated.
     */
    public function linkWoo(int $mapId, int $wcProductId, int $wcVariationId = 0, ?string $wcSku = null): bool
    {
        global $wpdb;
        return (bool) $wpdb->update($this->table(), [
            'wc_product_id' => $wcProductId,
            'wc_variation_id' => $wcVariationId,
            'wc_sku' => $wcSku,
            'sync_status' => 'synced',
            'last_synced_at' => current_time('mysql', 1),
        ], ['id' => $mapId]);
    }

    /**
     * Mark a mapping row as failed with an error message.
     */
    public function markError(int $mapId, string $error): void
    {
        global $wpdb;
        $wpdb->update($this->table(), [
            'sync_status' => 'error',
            'last_error' => substr($error, 0, 255),
            'last_synced_at' => current_time('mysql', 1),
        ], ['id' => $mapId]);
    }
}