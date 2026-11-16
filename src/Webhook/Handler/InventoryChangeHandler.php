<?php

namespace Puleeno\NhanhVn\Syncer\Webhook\Handler;

use Puleeno\NhanhVn\Syncer\Nhanh\Normalizer\InventoryNormalizer;
use Puleeno\NhanhVn\Syncer\Sync\InventorySyncService;

class InventoryChangeHandler implements WebhookHandlerInterface
{
    /** @var array<string,mixed> */
    private $settings;
    public function __construct(array $settings = []) { $this->settings = $settings; }

    public function handle(array $event): array
    {
        $normalizer = new InventoryNormalizer();
        $items = $normalizer->normalizePayload($event);
        $svc = new InventorySyncService();
        $applied = 0; $failed = 0; $unmapped = 0;
        foreach ($items as $item) {
            try {
                $res = $svc->apply($item);
                if (!empty($res['ok'])) $applied++;
                elseif (($res['product_map_id'] ?? 0) === 0) $unmapped++;
                else $failed++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }
        return ['ok' => $failed === 0, 'scanned' => count($items), 'applied' => $applied, 'unmapped' => $unmapped, 'failed' => $failed];
    }
}
