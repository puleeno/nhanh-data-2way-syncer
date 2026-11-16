<?php

namespace Puleeno\NhanhVn\Syncer\Sync;

use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\WarehouseStockRepository;
use Puleeno\NhanhVn\Syncer\Domain\Product\InventorySet;

/**
 * Applies inventory-only updates (inventoryChange webhook / targeted sync).
 */
class InventorySyncService
{
    /** @var ProductMapRepository */
    private $productMaps;
    /** @var WarehouseStockRepository */
    private $warehouseStocks;

    public function __construct(?ProductMapRepository $productMaps = null, ?WarehouseStockRepository $warehouseStocks = null)
    {
        $this->productMaps = $productMaps ?: new ProductMapRepository();
        $this->warehouseStocks = $warehouseStocks ?: new WarehouseStockRepository();
    }

    /**
     * @param array{id?:string,code?:string,inventory:InventorySet,raw?:array<string,mixed>} $item
     * @return array{ok:bool,product_map_id:int,wc_product_id:int}
     */
    public function apply(array $item): array
    {
        $id = isset($item['id']) ? (string) $item['id'] : '';
        $code = isset($item['code']) ? (string) $item['code'] : '';
        $inv = $item['inventory'];

        $map = null;
        if ($code !== '') $map = $this->productMaps->findByCode($code);
        if (!$map && $id !== '') $map = $this->productMaps->findByNhanhId($id);
        if (!$map) return ['ok' => false, 'product_map_id' => 0, 'wc_product_id' => 0];

        $mapId = (int) ($map['id'] ?? 0);
        $wcId = (int) ($map['wc_product_id'] ?? 0);
        $qty = $inv->totalForSync();

        // update WC product stock
        if ($wcId > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product($wcId);
            if ($product) {
                if (method_exists($product, 'set_manage_stock')) $product->set_manage_stock(true);
                if (method_exists($product, 'set_stock_quantity')) $product->set_stock_quantity($qty);
                if (method_exists($product, 'set_stock_status')) $product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
                if (method_exists($product, 'update_meta_data')) {
                    $product->update_meta_data('_nhanh_inventory', wp_json_encode([
                        'remain' => $inv->remain, 'available' => $inv->available,
                        'shipping' => $inv->shipping, 'damaged' => $inv->damaged,
                        'holding' => $inv->holding, 'transfering' => $inv->transfering,
                        'warranty' => $inv->warranty,
                    ]));
                }
                $product->save();
            }
        }

        // persist depots + map total
        $this->warehouseStocks->syncForProductMap($mapId, $inv->depots);
        global $wpdb;
        if (isset($wpdb) && $mapId > 0) {
            $wpdb->update(\Puleeno\NhanhVn\Syncer\Database\Schema::table('product_map'), [
                'nhanh_stock_total' => $qty,
                'last_synced_at' => current_time('mysql', 1),
                'updated_at' => current_time('mysql', 1),
            ], ['id' => $mapId]);
        }

        return ['ok' => true, 'product_map_id' => $mapId, 'wc_product_id' => $wcId];
    }
}
