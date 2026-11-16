<?php

namespace Puleeno\NhanhVn\Syncer\Nhanh\Gateway;

use Puleeno\NhanhVn\Syncer\Service\NhanhClientFactory;

/**
 * Specialized gateway for Nhanh v3 product endpoints.
 * Centralizes API calls behind a stable interface (Adapter/Gateway).
 */
class NhanhProductGateway
{
    /** @var object|null */
    private $client;
    /** @var array<string,mixed> */
    private $settings;

    /** @param array<string,mixed> $settings */
    public function __construct(array $settings = [], $client = null)
    {
        $this->settings = $settings;
        if ($client !== null) {
            $this->client = $client;
        } else {
            $this->client = (new NhanhClientFactory($settings))->make();
        }
    }

    public function isReady(): bool
    {
        return $this->client !== null;
    }

    /** @return object|null */
    public function client()
    {
        return $this->client;
    }

    /** @return array<int,mixed> */
    public function listCategories(array $filters = [], array $paginator = ['size' => 100]): array
    {
        if (!$this->client) return [];
        $api = $this->client->products();
        $res = $api->getCategories($filters, $paginator);
        return $this->toList($res);
    }

    /** @return array<int,mixed> */
    public function listBrands(): array
    {
        if (!$this->client) return [];
        $api = $this->client->products();
        $res = method_exists($api, 'getBrands') ? $api->getBrands() : [];
        return $this->toList($res);
    }

    /** @return array<int,mixed> */
    public function searchProducts(array $criteria = []): array
    {
        if (!$this->client) return [];
        $api = $this->client->products();
        $res = $api->search($criteria);
        return $this->toList($res);
    }

    /** @return mixed */
    public function getProductDetail($id)
    {
        if (!$this->client) return null;
        $api = $this->client->products();
        foreach (['detail', 'getDetail', 'get'] as $m) {
            if (method_exists($api, $m)) {
                try { return $api->$m(is_array($id) ? $id : ['id' => $id]); } catch (\Throwable $e) {}
            }
        }
        return null;
    }

    /** @return array<int,mixed> */
    private function toList($res): array
    {
        if ($res instanceof \Illuminate\Support\Collection) return $res->all();
        if (is_array($res)) return $res;
        if (is_object($res) && method_exists($res, 'toArray')) {
            $a = $res->toArray(); return is_array($a) ? $a : [];
        }
        return [];
    }
}
