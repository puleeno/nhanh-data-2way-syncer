<?php

namespace Puleeno\NhanhVn\Syncer\Database;

class ProductTermRepository
{
    private function table(): string
    {
        return Schema::table('product_terms');
    }

    /**
     * @param array<int,array{term_map_id:int,relation:string,is_primary:int}> $links
     */
    public function syncForProductMap(int $productMapId, array $links): void
    {
        global $wpdb;
        if ($productMapId <= 0) return;
        // simple replace strategy
        $wpdb->delete($this->table(), ['product_map_id' => $productMapId]);
        $seen = [];
        foreach ($links as $l) {
            $tmid = (int) ($l['term_map_id'] ?? 0);
            if ($tmid <= 0) continue;
            $key = $tmid;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $wpdb->insert($this->table(), [
                'product_map_id' => $productMapId,
                'term_map_id' => $tmid,
                'relation' => (string) ($l['relation'] ?? 'category'),
                'is_primary' => !empty($l['is_primary']) ? 1 : 0,
            ]);
        }
    }
}
