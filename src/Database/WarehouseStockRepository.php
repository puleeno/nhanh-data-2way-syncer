<?php

namespace Puleeno\NhanhVn\Syncer\Database;

class WarehouseStockRepository
{
    private function table(): string
    {
        return Schema::table('product_warehouses');
    }

    /**
     * @param array<int,\Puleeno\NhanhVn\Syncer\Domain\Product\DepotStock> $depots
     */
    public function syncForProductMap(int $productMapId, array $depots): void
    {
        global $wpdb;
        if ($productMapId <= 0) return;
        $now = current_time('mysql', 1);
        $seen = [];
        foreach ($depots as $d) {
            $wid = trim((string) $d->id);
            if ($wid === '') continue;
            $seen[] = $wid;
            $payload = [
                'product_map_id' => $productMapId,
                'warehouse_id' => $wid,
                'warehouse_name' => (string) $d->name,
                'stock' => (int) $d->available,
                'updated_at' => $now,
            ];
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table()} WHERE product_map_id = %d AND warehouse_id = %s",
                $productMapId,
                $wid
            ));
            if ($exists) {
                $wpdb->update($this->table(), $payload, ['id' => (int) $exists]);
            } else {
                $wpdb->insert($this->table(), $payload);
            }
        }
        // remove stale depots for this product
        if ($seen) {
            $placeholders = implode(',', array_fill(0, count($seen), '%s'));
            $sql = "DELETE FROM {$this->table()} WHERE product_map_id = %d AND warehouse_id NOT IN ($placeholders)";
            $wpdb->query($wpdb->prepare($sql, array_merge([$productMapId], $seen)));
        }
    }
}
