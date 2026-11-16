<?php

namespace Puleeno\NhanhVn\Syncer\Webhook\Handler;

use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;

class ProductDeleteHandler implements WebhookHandlerInterface
{
    /** @var array<string,mixed> */
    private $settings;
    public function __construct(array $settings = []) { $this->settings = $settings; }

    public function handle(array $event): array
    {
        $ids = isset($event['data']) ? $event['data'] : [];
        if (!is_array($ids)) $ids = [$ids];
        $maps = new ProductMapRepository();
        $deleted = 0; $missing = 0;
        foreach ($ids as $id) {
            $nid = is_array($id) ? (string) ($id['id'] ?? $id['productId'] ?? '') : (string) $id;
            if ($nid === '' || $nid === '0') continue;
            $map = $maps->findByNhanhId($nid);
            if (!$map && is_numeric($nid)) $map = $maps->findByCode($nid);
            if (!$map) { $missing++; continue; }
            $wcId = (int) ($map['wc_product_id'] ?? 0);
            if ($wcId > 0) {
                if (function_exists('wp_trash_post')) { wp_trash_post($wcId); }
                elseif (function_exists('wp_delete_post')) { wp_delete_post($wcId, false); }
            }
            global $wpdb;
            if (isset($wpdb) && !empty($map['id'])) {
                $wpdb->update(\Puleeno\NhanhVn\Syncer\Database\Schema::table('product_map'), [
                    'sync_status' => 'deleted',
                    'updated_at' => current_time('mysql', 1),
                ], ['id' => (int) $map['id']]);
            }
            $deleted++;
        }
        return ['ok' => true, 'deleted' => $deleted, 'missing' => $missing];
    }
}
