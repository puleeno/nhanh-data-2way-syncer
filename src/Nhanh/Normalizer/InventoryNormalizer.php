<?php

namespace Puleeno\NhanhVn\Syncer\Nhanh\Normalizer;

use Puleeno\NhanhVn\Syncer\Domain\Product\DepotStock;
use Puleeno\NhanhVn\Syncer\Domain\Product\InventorySet;

/**
 * Normalizes inventoryChange webhook payloads into inventory update models.
 * Payload: { event: "inventoryChange", businessId: ..., data: [ {id,code,remain,available,...,depots[]} ] }
 */
class InventoryNormalizer
{
    /**
     * @param mixed $payload raw webhook payload (array or object)
     * @return array<int,array{id:string,code:string,inventory:InventorySet,raw:array<string,mixed>}>
     */
    public function normalizePayload($payload): array
    {
        $data = $this->extractData($payload);
        $out = [];
        foreach ($data as $item) {
            $row = $this->toArray($item);
            if (!$row) continue;
            $id = isset($row['id']) ? (string) $row['id'] : (isset($row['productId']) ? (string) $row['productId'] : '');
            $code = isset($row['code']) ? (string) $row['code'] : '';
            $out[] = [
                'id' => $id,
                'code' => $code,
                'inventory' => $this->normalizeInventory($row),
                'raw' => $row,
            ];
        }
        return $out;
    }

    /** @return array<int,mixed> */
    private function extractData($payload): array
    {
        $arr = $this->toArray($payload);
        $data = $arr['data'] ?? $payload;
        if (is_array($data)) {
            // list of items or single associative item
            $isList = array_keys($data) === range(0, count($data) - 1);
            if ($isList) return $data;
            if (isset($data['id']) || isset($data['code']) || isset($data['depots']) || isset($data['available'])) return [$data];
            // maybe wrapped
            foreach ($data as $v) { if (is_array($v) || is_object($v)) return $data; }
        }
        return [];
    }

    private function normalizeInventory(array $row): InventorySet
    {
        $inv = new InventorySet();
        $src = isset($row['inventory']) && is_array($row['inventory']) ? $row['inventory'] : $row;
        foreach (['remain', 'available', 'shipping', 'damaged', 'holding', 'transfering'] as $k) {
            if (isset($src[$k]) && is_numeric($src[$k])) $inv->$k = (float) $src[$k];
        }
        $w = $src['warranty'] ?? [];
        $inv->warranty = is_array($w) ? $w : [];
        $depots = $src['depots'] ?? ($row['depots'] ?? []);
        if (is_array($depots)) {
            foreach ($depots as $d) {
                if (!is_array($d)) $d = $this->toArray($d);
                if (!is_array($d)) continue;
                $ds = new DepotStock();
                $ds->id = isset($d['id']) ? (string) $d['id'] : (isset($d['depotId']) ? (string) $d['depotId'] : '');
                $ds->name = isset($d['name']) ? (string) $d['name'] : '';
                foreach (['remain', 'available', 'shipping', 'damaged', 'holding', 'transfering'] as $k) {
                    if (isset($d[$k]) && is_numeric($d[$k])) $ds->$k = (float) $d[$k];
                }
                $dw = $d['warranty'] ?? [];
                $ds->warranty = is_array($dw) ? $dw : [];
                $ds->raw = $d;
                $inv->depots[] = $ds;
            }
        }
        return $inv;
    }

    /** @return array<string,mixed> */
    private function toArray($source): array
    {
        if (is_array($source)) return $source;
        if (is_object($source)) {
            if (method_exists($source, 'toArray')) { $a = $source->toArray(); if (is_array($a)) return $a; }
            if (method_exists($source, 'getAttributes')) { $a = $source->getAttributes(); if (is_array($a)) return $a; }
            $a = get_object_vars($source); if (is_array($a)) return $a;
        }
        return [];
    }
}
