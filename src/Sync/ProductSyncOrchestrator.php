<?php

namespace Puleeno\NhanhVn\Syncer\Sync;

use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductTermRepository;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Database\WarehouseStockRepository;
use Puleeno\NhanhVn\Syncer\Domain\Product\NhanhProduct;
use Puleeno\NhanhVn\Syncer\Nhanh\Normalizer\ProductNormalizer;
use Puleeno\NhanhVn\Syncer\Sync\Policy\PostStatusPolicy;
use Puleeno\NhanhVn\Syncer\Sync\Policy\PricePolicy;
use Puleeno\NhanhVn\Syncer\Woo\WooBridge;

class ProductSyncOrchestrator
{
    /** @var ProductMapRepository */
    private $productMaps;
    /** @var TermMapRepository */
    private $termMaps;
    /** @var WarehouseStockRepository */
    private $warehouseStocks;
    /** @var ProductTermRepository */
    private $productTerms;
    /** @var WooBridge */
    private $woo;
    /** @var ProductNormalizer */
    private $normalizer;
    /** @var PricePolicy */
    private $pricePolicy;
    /** @var PostStatusPolicy */
    private $statusPolicy;
    /** @var array<string,mixed> */
    private $settings;

    public function __construct(
        ProductMapRepository $productMaps,
        TermMapRepository $termMaps,
        WooBridge $woo,
        array $settings = [],
        ?WarehouseStockRepository $warehouseStocks = null,
        ?ProductTermRepository $productTerms = null
    ) {
        $this->productMaps = $productMaps;
        $this->termMaps = $termMaps;
        $this->woo = $woo;
        $this->settings = $settings;
        $this->warehouseStocks = $warehouseStocks ?: new WarehouseStockRepository();
        $this->productTerms = $productTerms ?: new ProductTermRepository();
        $this->normalizer = new ProductNormalizer();
        $this->pricePolicy = new PricePolicy();
        $this->statusPolicy = new PostStatusPolicy();
    }

    /**
     * @param array<int,mixed> $products raw entities/arrays
     * @param array<string,int> $categoryMap nhanh term id => wc term id
     * @return array{scanned:int,updated:int,failed:int,skipped:int,deferred:int}
     */
    public function syncProducts(array $products, array $categoryMap = [], int $maxProducts = 0, float $deadline = 0.0): array
    {
        $scanned = 0; $updated = 0; $failed = 0; $skipped = 0; $deferred = 0; $processed = 0;
        $importImages = !empty($this->settings['import_images']);

        foreach ($products as $src) {
            if ($maxProducts > 0 && $processed >= $maxProducts) { $deferred = max(0, count($products) - $processed); break; }
            if ($deadline > 0 && microtime(true) >= $deadline) { $deferred = max(0, count($products) - $processed); break; }
            $processed++;
            $scanned++;

            try {
                $p = $this->normalizer->normalize($src);
                $ok = $this->syncOne($p, $categoryMap, $importImages);
                if ($ok > 0) $updated++; else $skipped++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed, 'skipped' => $skipped, 'deferred' => $deferred];
    }

    private function syncOne(NhanhProduct $p, array $categoryMap, bool $importImages): int
    {
        $nhanhId = $p->identity->id;
        $code = $p->identity->code;
        if ($nhanhId === '' && $code === '') return 0;
        $sku = $code !== '' ? $code : 'NHANH-' . $nhanhId;
        $key = $code !== '' ? $code : $sku;

        $existing = null;
        if ($code !== '') $existing = $this->productMaps->findByCode($code);
        if (!$existing && $nhanhId !== '') $existing = $this->productMaps->findByNhanhId($nhanhId);
        if (!$existing && $sku !== '') $existing = $this->productMaps->findByCode($sku);

        $wcData = $this->mapToWoo($p, $sku, $categoryMap, $importImages);
        if ($existing && !empty($existing['wc_product_id'])) {
            $wcData['wc_product_id'] = (int) $existing['wc_product_id'];
        }
        $productId = $this->woo->upsertProduct($wcData);
        if ($productId <= 0) return 0;

        $mapPayload = $this->buildMapPayload($p, $sku, $productId);
        $mapId = $this->productMaps->upsert($mapPayload);
        if ($mapId <= 0) return 0;

        // relations
        $this->warehouseStocks->syncForProductMap($mapId, $p->inventory->depots);
        $this->syncProductTerms($mapId, $p, $categoryMap);

        return $mapId;
    }

    /** @param array<string,int> $categoryMap */
    private function mapToWoo(NhanhProduct $p, string $sku, array $categoryMap, bool $importImages): array
    {
        $price = $this->pricePolicy->resolve($p);
        $categoryTermIds = [];
        if ($p->category && $p->category->id !== '' && isset($categoryMap[$p->category->id])) {
            $categoryTermIds[] = (int) $categoryMap[$p->category->id];
        }

        $images = $importImages ? $p->images->all() : [];

        $meta = [
            '_nhanh_product_id' => $p->identity->id,
            '_nhanh_barcode' => $p->identity->barcode,
            '_nhanh_type' => $p->identity->type,
            '_nhanh_status' => is_scalar($p->identity->status) ? (string) $p->identity->status : wp_json_encode($p->identity->status),
            '_nhanh_prices' => wp_json_encode($this->pricesArray($p)),
            '_nhanh_attributes' => wp_json_encode($p->attributes),
            '_nhanh_units' => wp_json_encode($p->units),
            '_nhanh_combos' => wp_json_encode($p->combos),
            '_nhanh_inventory' => wp_json_encode($this->inventoryArray($p)),
            '_nhanh_shipping' => wp_json_encode(['weight' => $p->shipping->weight, 'length' => $p->shipping->length, 'width' => $p->shipping->width, 'height' => $p->shipping->height]),
            '_nhanh_warranty' => wp_json_encode(['month' => $p->warranty->month, 'phone' => $p->warranty->phone, 'address' => $p->warranty->address]),
            '_nhanh_raw_snapshot' => wp_json_encode($p->raw),
        ];
        if ($p->content->otherName !== '') $meta['_nhanh_other_name'] = $p->content->otherName;

        $desc = $p->content->content !== '' ? $p->content->content : $p->content->description;

        $taxonomyTerms = [];
        // brand term assignment
        if ($p->brand && $p->brand->id !== '') {
            $brandTax = isset($this->settings['brand_taxonomy']) ? (string) $this->settings['brand_taxonomy'] : 'product_brand';
            $bmap = $this->termMaps->findByNhanh('brand', $p->brand->id, $brandTax);
            if ($bmap && !empty($bmap['wc_term_id'])) {
                $taxonomyTerms[$brandTax] = [(int) $bmap['wc_term_id']];
            }
        }

        return [
            'sku' => $sku,
            'name' => $p->content->name,
            'regular_price' => $price['regular'],
            'sale_price' => $price['sale'],
            'price' => $price['active'],
            'stock' => $p->inventory->totalForSync(),
            'manage_stock' => true,
            'status' => $this->statusPolicy->resolve($p, $this->settings),
            'categories' => $categoryTermIds,
            'description' => $desc,
            'short_description' => $p->content->description,
            'images' => $images,
            'force_images' => false,
            'weight' => $p->shipping->weight,
            'length' => $p->shipping->length,
            'width' => $p->shipping->width,
            'height' => $p->shipping->height,
            'taxonomy_terms' => $taxonomyTerms,
            'meta' => $meta,
        ];
    }

    private function pricesArray(NhanhProduct $p): array
    {
        return [
            'retail' => $p->prices->retail, 'old' => $p->prices->old, 'wholesale' => $p->prices->wholesale,
            'import' => $p->prices->import, 'avgCost' => $p->prices->avgCost,
            'retailVat' => $p->prices->retailVat, 'importVat' => $p->prices->importVat,
            'vat' => $p->prices->vat, 'priceVatMode' => $p->prices->priceVatMode,
            'importPriceVatMode' => $p->prices->importPriceVatMode, 'branchPrices' => $p->prices->branchPrices,
        ];
    }

    private function inventoryArray(NhanhProduct $p): array
    {
        $depots = [];
        foreach ($p->inventory->depots as $d) {
            $depots[] = [
                'id' => $d->id, 'name' => $d->name, 'remain' => $d->remain, 'available' => $d->available,
                'shipping' => $d->shipping, 'damaged' => $d->damaged, 'holding' => $d->holding,
                'transfering' => $d->transfering, 'warranty' => $d->warranty,
            ];
        }
        return [
            'remain' => $p->inventory->remain, 'available' => $p->inventory->available,
            'shipping' => $p->inventory->shipping, 'damaged' => $p->inventory->damaged,
            'holding' => $p->inventory->holding, 'transfering' => $p->inventory->transfering,
            'warranty' => $p->inventory->warranty, 'depots' => $depots,
        ];
    }

    private function buildMapPayload(NhanhProduct $p, string $sku, int $productId): array
    {
        $firstImage = $p->images->avatar !== '' ? $p->images->avatar : ($p->images->all()[0] ?? null);
        $retail = $p->prices->retail !== null ? $p->prices->retail : 0.0;
        $old = $p->prices->old !== null ? $p->prices->old : $retail;
        $key = $p->identity->code !== '' ? $p->identity->code : $sku;
        return [
            'nhanh_id' => $p->identity->id,
            'nhanh_code' => $key,
            'nhanh_barcode' => $p->identity->barcode,
            'nhanh_name' => $p->content->name,
            'nhanh_category_id' => $p->category ? $p->category->id : '',
            'nhanh_category_name' => $p->category ? $p->category->name : '',
            'nhanh_brand_id' => $p->brand ? $p->brand->id : '',
            'nhanh_brand_name' => $p->brand ? $p->brand->name : '',
            'nhanh_price' => $retail,
            'nhanh_original_price' => $old,
            'nhanh_stock_total' => $p->inventory->totalForSync(),
            'wc_product_id' => $productId,
            'wc_variation_id' => 0,
            'wc_sku' => $sku,
            'sync_status' => 'synced',
            'image_url' => $firstImage,
            'tags_json' => wp_json_encode(array_map(function ($t) { return ['id' => $t->id, 'name' => $t->name]; }, $p->tags)),
            'last_compared_at' => current_time('mysql', 1),
            'last_synced_at' => current_time('mysql', 1),
        ];
    }

    /** @param array<string,int> $categoryMap */
    private function syncProductTerms(int $mapId, NhanhProduct $p, array $categoryMap): void
    {
        $links = [];
        if ($p->category && $p->category->id !== '' && isset($categoryMap[$p->category->id])) {
            $catTax = 'product_cat';
            $tm = $this->termMaps->findByNhanh('category', $p->category->id, $catTax);
            if ($tm && !empty($tm['id'])) $links[] = ['term_map_id' => (int) $tm['id'], 'relation' => 'category', 'is_primary' => 1];
        }
        if ($p->brand && $p->brand->id !== '') {
            $brandTax = isset($this->settings['brand_taxonomy']) ? (string) $this->settings['brand_taxonomy'] : 'product_brand';
            $tm = $this->termMaps->findByNhanh('brand', $p->brand->id, $brandTax);
            if ($tm && !empty($tm['id'])) $links[] = ['term_map_id' => (int) $tm['id'], 'relation' => 'brand', 'is_primary' => 1];
        }
        $this->productTerms->syncForProductMap($mapId, $links);
    }
}
