<?php

namespace Puleeno\NhanhVn\Syncer\Webhook\Handler;

use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Sync\ProductSyncOrchestrator;
use Puleeno\NhanhVn\Syncer\Woo\WooBridge;

class ProductAddHandler implements WebhookHandlerInterface
{
    /** @var array<string,mixed> */
    private $settings;
    public function __construct(array $settings = []) { $this->settings = $settings; }

    public function handle(array $event): array
    {
        $data = isset($event['data']) && is_array($event['data']) ? $event['data'] : [];
        $products = $this->normalizeProducts($data);
        $orch = new ProductSyncOrchestrator(new ProductMapRepository(), new TermMapRepository(), new WooBridge(), $this->settings);
        $res = $orch->syncProducts($products, []);
        return ['ok' => ($res['failed'] ?? 0) === 0, 'result' => $res];
    }

    /** @return array<int,mixed> */
    private function normalizeProducts($data): array
    {
        if (!$data) return [];
        $isList = array_keys($data) === range(0, count($data) - 1);
        if ($isList) return $data;
        if (isset($data['id']) || isset($data['code']) || isset($data['name'])) return [$data];
        return [];
    }
}
