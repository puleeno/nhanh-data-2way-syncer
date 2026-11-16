<?php

namespace Puleeno\NhanhVn\Syncer\Sync;

use Puleeno\NhanhVn\Syncer\Database\JobRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductTermRepository;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Database\WarehouseStockRepository;
use Puleeno\NhanhVn\Syncer\Domain\Product\NhanhProduct;
use Puleeno\NhanhVn\Syncer\Nhanh\Normalizer\ProductNormalizer;
use Puleeno\NhanhVn\Syncer\Sync\Mapper\VariationMapper;
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
    /** @var VariationMapper */
    private $variationMapper;
    /** @var array<string,mixed> */
    private $settings;
    /** @var JobRepository|null */
    private $jobLogs;
    /** @var int */
    private $jobId;

    public function __construct(
        ProductMapRepository $productMaps,
        TermMapRepository $termMaps,
        WooBridge $woo,
        array $settings = [],
        ?WarehouseStockRepository $warehouseStocks = null,
        ?ProductTermRepository $productTerms = null,
        int $jobId = 0,
        ?JobRepository $jobLogs = null
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
        $this->variationMapper = new VariationMapper($this->pricePolicy, $this->statusPolicy);
        $this->jobId = $jobId;
        $this->jobLogs = $jobLogs;
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
                $p = ($src instanceof NhanhProduct) ? $src : $this->normalizer->normalize($src);
                $result = $this->syncOne($p, $categoryMap, $importImages);
                if (!empty($result['ok'])) {
                    $updated++;
                    $this->logProductEvent($p, $result);
                } else {
                    $skipped++;
                    $result['ok'] = false;
                    $result['reason'] = $result['reason'] ?? 'skipped';
                    $this->logProductEvent($p, $result);
                }
            } catch (\Throwable $e) {
                $failed++;
                $ref = $this->productRefFromSource($src);
                $this->writeLog(
                    'product_sync',
                    'error',
                    'Lỗi import sản phẩm: ' . $ref['label'],
                    'Nhanh[' . $ref['ref'] . '] ' . $e->getMessage(),
                    $ref['ref'],
                    ['error' => $e->getMessage()]
                );
            }
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed, 'skipped' => $skipped, 'deferred' => $deferred];
    }

    /** @return array{map:array<string,int>} */
    public function buildCategoryMap(): array
    {
        $map = [];
        foreach ($this->termMaps->all('product_cat') as $row) {
            if (($row['nhanh_term_type'] ?? '') === 'category' && !empty($row['nhanh_term_id']) && !empty($row['wc_term_id'])) {
                $map[(string) $row['nhanh_term_id']] = (int) $row['wc_term_id'];
            }
        }
        return ['map' => $map];
    }

    /**
     * @return array{ok:bool,reason?:string,wc_product_id?:int,map_id?:int,status?:string,type?:string,images?:array<string,mixed>,is_new?:bool}
     */
    private function syncOne(NhanhProduct $p, array $categoryMap, bool $importImages): array
    {
        if ($this->variationMapper->isVariable($p)) {
            return $this->syncVariable($p, $categoryMap, $importImages);
        }
        return $this->syncSimple($p, $categoryMap, $importImages);
    }

    /**
     * Whether the product already has (or will have) an image.
     *
     * Images disabled => no wait. Image already attached => ready. Otherwise the
     * product stays draft until a later run finishes the download.
     */
    private function hasUsableImage(int $wcProductId, bool $importImages): bool
    {
        if (!$importImages) {
            return true;
        }
        if ($wcProductId > 0 && $this->woo->hasThumbnail($wcProductId)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{ok:bool,reason?:string,wc_product_id?:int,map_id?:int,status?:string,type?:string,images?:array<string,mixed>,is_new?:bool}
     */
    private function syncSimple(NhanhProduct $p, array $categoryMap, bool $importImages): array
    {
        $nhanhId = $p->identity->id;
        $code = $p->identity->code;
        if ($nhanhId === '' && $code === '') {
            return ['ok' => false, 'reason' => 'missing_identity'];
        }
        $sku = $code !== '' ? $code : 'NHANH-' . $nhanhId;
        $key = $code !== '' ? $code : $sku;

        $existing = $this->findExistingMap($p, $sku);
        $isNew = !$existing || empty($existing['wc_product_id']);
        $wcData = $this->mapToWoo($p, $sku, $categoryMap, $importImages);
        $wcData['type'] = 'simple';
        if ($existing && !empty($existing['wc_product_id'])) {
            $wcData['wc_product_id'] = (int) $existing['wc_product_id'];
        }

        // Publish only once the product actually has an image (when images are enabled).
        $existingWcId = $existing ? (int) ($existing['wc_product_id'] ?? 0) : 0;
        $imagesReady = $this->hasUsableImage($existingWcId, $importImages);
        $wcData['status'] = $this->statusPolicy->resolve($p, $this->settings, $imagesReady);

        $productId = $this->woo->upsertProduct($wcData);
        if ($productId <= 0) {
            return ['ok' => false, 'reason' => 'wc_upsert_failed', 'is_new' => $isNew];
        }

        $imageStats = $this->woo->getLastImageStats();
        $finalStatus = $this->resolveFinalStatus($productId, $wcData['status']);

        $mapPayload = $this->buildMapPayload($p, $key, $sku, $productId, 0);
        $mapId = $this->productMaps->upsert($mapPayload);
        if ($mapId <= 0) {
            return ['ok' => false, 'reason' => 'map_upsert_failed', 'wc_product_id' => $productId, 'status' => $finalStatus, 'type' => 'simple', 'images' => $imageStats, 'is_new' => $isNew];
        }

        $this->warehouseStocks->syncForProductMap($mapId, $p->inventory->depots);
        $this->syncProductTerms($mapId, $p, $categoryMap);

        return [
            'ok' => true,
            'wc_product_id' => $productId,
            'map_id' => $mapId,
            'status' => $finalStatus,
            'type' => 'simple',
            'images' => $imageStats,
            'is_new' => $isNew,
        ];
    }

    /**
     * Composite: parent variable + child variations/attributes/units.
     * @return array{ok:bool,reason?:string,wc_product_id?:int,map_id?:int,status?:string,type?:string,images?:array<string,mixed>,is_new?:bool,variations?:int}
     */
    private function syncVariable(NhanhProduct $p, array $categoryMap, bool $importImages): array
    {
        $nhanhId = $p->identity->id;
        $code = $p->identity->code;
        if ($nhanhId === '' && $code === '') {
            return ['ok' => false, 'reason' => 'missing_identity'];
        }
        $sku = $code !== '' ? $code : 'NHANH-' . $nhanhId;
        $key = $code !== '' ? $code : $sku;

        $existing = $this->findExistingMap($p, $sku);
        $isNew = !$existing || empty($existing['wc_product_id']);
        $wcData = $this->mapToWoo($p, $sku, $categoryMap, $importImages);
        $wcData['type'] = 'variable';
        $wcData['attributes'] = $this->variationMapper->buildParentAttributes($p);
        $wcData['manage_stock'] = false; // variations own stock
        if ($existing && !empty($existing['wc_product_id'])) {
            $wcData['wc_product_id'] = (int) $existing['wc_product_id'];
        }

        $existingWcId = $existing ? (int) ($existing['wc_product_id'] ?? 0) : 0;
        $imagesReady = $this->hasUsableImage($existingWcId, $importImages);
        $wcData['status'] = $this->statusPolicy->resolve($p, $this->settings, $imagesReady);

        $productId = $this->woo->upsertProduct($wcData);
        if ($productId <= 0) {
            return ['ok' => false, 'reason' => 'wc_upsert_failed', 'is_new' => $isNew, 'type' => 'variable'];
        }

        $imageStats = $this->woo->getLastImageStats();

        // sync variations
        $variations = $this->variationMapper->buildVariations($p, $this->settings);
        $syncedVarIds = [];
        $variationCount = 0;
        foreach ($variations as $v) {
            $vSku = isset($v['sku']) ? (string) $v['sku'] : '';
            $vCode = isset($v['nhanh_code']) ? (string) $v['nhanh_code'] : $vSku;
            $vId = isset($v['nhanh_id']) ? (string) $v['nhanh_id'] : '';
            $vExisting = $this->findExistingVariationMap($vId, $vCode, $vSku);
            $variationId = $this->woo->upsertVariation($productId, $v, $vExisting ? (int) ($vExisting['wc_variation_id'] ?? 0) : 0);
            if ($variationId <= 0) continue;
            $syncedVarIds[] = $variationId;
            $variationCount++;

            $vPayload = $this->buildVariationMapPayload($v, $vCode !== '' ? $vCode : ($vSku !== '' ? $vSku : 'NHANH-VAR-' . $vId), $productId, $variationId);
            $vMapId = $this->productMaps->upsert($vPayload);
            if ($vMapId > 0 && isset($v['depots']) && is_array($v['depots'])) {
                $this->warehouseStocks->syncForProductMap($vMapId, $v['depots']);
            }
        }
        // detach stale variations
        $this->woo->syncVariations($productId, $syncedVarIds);

        $finalStatus = $this->resolveFinalStatus($productId, $wcData['status']);

        $mapPayload = $this->buildMapPayload($p, $key, $sku, $productId, 0);
        $mapId = $this->productMaps->upsert($mapPayload);
        if ($mapId <= 0) {
            return ['ok' => false, 'reason' => 'map_upsert_failed', 'wc_product_id' => $productId, 'status' => $finalStatus, 'type' => 'variable', 'images' => $imageStats, 'is_new' => $isNew, 'variations' => $variationCount];
        }
        $this->warehouseStocks->syncForProductMap($mapId, $p->inventory->depots);
        $this->syncProductTerms($mapId, $p, $categoryMap);

        return [
            'ok' => true,
            'wc_product_id' => $productId,
            'map_id' => $mapId,
            'status' => $finalStatus,
            'type' => 'variable',
            'images' => $imageStats,
            'is_new' => $isNew,
            'variations' => $variationCount,
        ];
    }

    private function findExistingMap(NhanhProduct $p, string $sku): ?array
    {
        $existing = null;
        if ($p->identity->code !== '') $existing = $this->productMaps->findByCode($p->identity->code);
        if (!$existing && $p->identity->id !== '') $existing = $this->productMaps->findByNhanhId($p->identity->id);
        if (!$existing && $sku !== '') $existing = $this->productMaps->findByCode($sku);
        return $existing;
    }

    private function findExistingVariationMap(string $id, string $code, string $sku): ?array
    {
        $existing = null;
        if ($code !== '') $existing = $this->productMaps->findByCode($code);
        if (!$existing && $id !== '') $existing = $this->productMaps->findByNhanhId($id);
        if (!$existing && $sku !== '') $existing = $this->productMaps->findByCode($sku);
        return $existing;
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
        $desc = $p->content->content !== '' ? $p->content->content : $p->content->description;

        $meta = [
            '_nhanh_product_id' => $p->identity->id,
            '_nhanh_parent_id' => $p->identity->parentId,
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

        $taxonomyTerms = [];
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

    private function buildMapPayload(NhanhProduct $p, string $key, string $sku, int $productId, int $variationId): array
    {
        $firstImage = $p->images->avatar !== '' ? $p->images->avatar : ($p->images->all()[0] ?? null);
        $retail = $p->prices->retail !== null ? $p->prices->retail : 0.0;
        $old = $p->prices->old !== null ? $p->prices->old : $retail;
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
            'wc_variation_id' => $variationId,
            'wc_sku' => $sku,
            'sync_status' => 'synced',
            'image_url' => $firstImage,
            'tags_json' => wp_json_encode(array_map(function ($t) { return ['id' => $t->id, 'name' => $t->name]; }, $p->tags)),
            'last_compared_at' => current_time('mysql', 1),
            'last_synced_at' => current_time('mysql', 1),
        ];
    }

    /** @param array<string,mixed> $v */
    private function buildVariationMapPayload(array $v, string $key, int $parentId, int $variationId): array
    {
        $retail = isset($v['price']) && is_numeric($v['price']) ? (float) $v['price'] : 0.0;
        return [
            'nhanh_id' => (string) ($v['nhanh_id'] ?? ''),
            'nhanh_code' => $key,
            'nhanh_barcode' => (string) ($v['nhanh_barcode'] ?? ''),
            'nhanh_name' => (string) ($v['name'] ?? ''),
            'nhanh_category_id' => '',
            'nhanh_category_name' => '',
            'nhanh_brand_id' => '',
            'nhanh_brand_name' => '',
            'nhanh_price' => $retail,
            'nhanh_original_price' => $retail,
            'nhanh_stock_total' => (int) ($v['stock'] ?? 0),
            'wc_product_id' => $parentId,
            'wc_variation_id' => $variationId,
            'wc_sku' => (string) ($v['sku'] ?? ''),
            'sync_status' => 'synced',
            'image_url' => (string) ($v['image'] ?? ''),
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

    private function resolveFinalStatus(int $productId, string $default): string
    {
        if ($productId > 0 && function_exists('get_post_status')) {
            $st = get_post_status($productId);
            if (is_string($st) && $st !== '') {
                return $st;
            }
        }
        return $default;
    }

    /**
     * @param array<string,mixed> $result
     */
    private function logProductEvent(NhanhProduct $p, array $result): void
    {
        if (!$this->jobLogs || $this->jobId <= 0) {
            return;
        }
        $ok = !empty($result['ok']);
        $isNew = !empty($result['is_new']);
        $name = trim((string) ($p->content->name ?? ''));
        $code = $p->identity->code !== '' ? $p->identity->code : $p->identity->id;
        $label = $name !== '' ? $name : ($code !== '' ? $code : '(no-name)');
        $title = ($ok ? ($isNew ? 'Sản phẩm tạo mới: ' : 'Sản phẩm cập nhật: ') : 'Sản phẩm bỏ qua/lỗi: ') . $label;

        $images = isset($result['images']) && is_array($result['images']) ? $result['images'] : [];
        $imgTotal = (int) ($images['provided'] ?? 0);
        $imgImported = (int) ($images['imported'] ?? 0);
        $imgExisting = (int) ($images['existing'] ?? 0);
        $imgFailed = (int) ($images['failed'] ?? 0);
        $imgSkipped = (int) ($images['skipped'] ?? 0);
        $status = (string) ($result['status'] ?? 'n/a');
        $type = (string) ($result['type'] ?? 'simple');

        $detail = sprintf(
            'Nhanh code=%s id=%s | WC product_id=%s | type=%s | status=%s | ảnh: tổng=%d, import=%d, đã có=%d, lỗi=%d, bỏ qua=%d',
            $p->identity->code !== '' ? $p->identity->code : '-',
            $p->identity->id !== '' ? $p->identity->id : '-',
            !empty($result['wc_product_id']) ? (string) $result['wc_product_id'] : '-',
            $type,
            $status,
            $imgTotal,
            $imgImported,
            $imgExisting,
            $imgFailed,
            $imgSkipped
        );
        if (!empty($result['variations'])) {
            $detail .= ' | variations=' . (int) $result['variations'];
        }
        if (!$ok && !empty($result['reason'])) {
            $detail .= ' | reason=' . $result['reason'];
        }

        $payload = [
            'job_id' => $this->jobId,
            'nhanh' => [
                'id' => $p->identity->id,
                'code' => $p->identity->code,
                'name' => $p->content->name,
                'type' => $p->identity->type,
            ],
            'wc' => [
                'product_id' => $result['wc_product_id'] ?? null,
                'map_id' => $result['map_id'] ?? null,
                'status' => $status,
                'product_type' => $type,
                'is_new' => $isNew,
            ],
            'images' => $images,
            'variations' => $result['variations'] ?? null,
            'ok' => $ok,
            'reason' => $result['reason'] ?? null,
        ];

        $ref = $p->identity->code !== '' ? $p->identity->code : ($p->identity->id !== '' ? $p->identity->id : null);
        $logStatus = $ok ? 'success' : ((($result['reason'] ?? '') === 'skipped') ? 'warning' : 'error');
        $extra = [
            'direction' => 'nhanh_to_wc',
            'source' => 'import',
            'payload_json' => function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload),
        ];
        if (!empty($result['map_id'])) {
            $extra['product_map_id'] = (int) $result['map_id'];
        }
        $this->jobLogs->log($this->jobId, 'product_sync', $logStatus, $title, $detail, null, $ref, $extra);

        // Log each image result as a separate event for easy debugging.
        if (!empty($images['items']) && is_array($images['items'])) {
            foreach ($images['items'] as $img) {
                $imgStatus = (string) ($img['status'] ?? 'unknown');
                $imgUrl = (string) ($img['url'] ?? '');
                $imgId = isset($img['id']) ? (int) $img['id'] : 0;
                $statusMap = ['imported' => 'success', 'existing' => 'success', 'failed' => 'error', 'invalid_url' => 'warning', 'skipped' => 'warning'];
                $sev = $statusMap[$imgStatus] ?? 'info';
                $imgTitle = sprintf('Ảnh %s: %s', $imgStatus, $label);
                $imgDetail = sprintf('Sản phẩm %s (WC #%s) | ảnh: %s | attachment_id=%s', $code, !empty($result['wc_product_id']) ? $result['wc_product_id'] : '-', $imgUrl, $imgId > 0 ? (string) $imgId : '-');
                $this->jobLogs->log($this->jobId, 'product_image', $sev, $imgTitle, $imgDetail, null, $ref, [
                    'direction' => 'nhanh_to_wc',
                    'source' => 'import',
                    'product_map_id' => !empty($result['map_id']) ? (int) $result['map_id'] : null,
                    'payload_json' => function_exists('wp_json_encode') ? wp_json_encode(['image' => $img, 'wc_product_id' => $result['wc_product_id'] ?? null]) : null,
                ]);
            }
        }

        // Publish/status note when newly published
        if ($ok && $status === 'publish') {
            $this->jobLogs->log($this->jobId, 'product_publish', 'success', 'Sản phẩm publish: ' . $label, sprintf('WC #%s (%s) đã ở trạng thái publish', $result['wc_product_id'] ?? '-', $code), null, $ref, [
                'source' => 'import',
                'product_map_id' => !empty($result['map_id']) ? (int) $result['map_id'] : null,
            ]);
        } elseif ($ok && $status === 'draft') {
            $reason = '';
            if (!empty($images) && (int) ($images['provided'] ?? 0) > 0 && ((int) ($images['imported'] ?? 0) + (int) ($images['existing'] ?? 0)) === 0) {
                $reason = ' (chưa có ảnh hợp lệ, chờ import ảnh ở lần sau)';
            }
            $this->jobLogs->log($this->jobId, 'product_status', 'info', 'Sản phẩm ở trạng thái draft: ' . $label, sprintf('WC #%s (%s) đang draft%s', $result['wc_product_id'] ?? '-', $code, $reason), null, $ref, [
                'source' => 'import',
                'product_map_id' => !empty($result['map_id']) ? (int) $result['map_id'] : null,
            ]);
        }
    }

    /**
     * @param mixed $src
     * @return array{ref:string,label:string}
     */
    private function productRefFromSource($src): array
    {
        if ($src instanceof NhanhProduct) {
            $ref = $src->identity->code !== '' ? $src->identity->code : $src->identity->id;
            return ['ref' => $ref, 'label' => $src->content->name !== '' ? $src->content->name : $ref];
        }
        if (is_array($src)) {
            $code = isset($src['code']) ? (string) $src['code'] : (isset($src['id']) ? (string) $src['id'] : '?');
            $name = isset($src['name']) ? (string) $src['name'] : $code;
            return ['ref' => $code, 'label' => $name];
        }
        if (is_object($src)) {
            $code = method_exists($src, 'getCode') ? (string) $src->getCode() : (method_exists($src, 'getId') ? (string) $src->getId() : '?');
            $name = method_exists($src, 'getName') ? (string) $src->getName() : $code;
            return ['ref' => $code, 'label' => $name];
        }
        return ['ref' => '?', 'label' => 'unknown'];
    }

    private function writeLog(string $type, string $status, string $title, string $detail = '', ?string $ref = null, array $payload = []): void
    {
        if (!$this->jobLogs || $this->jobId <= 0) {
            return;
        }
        $extra = ['source' => 'import'];
        if ($payload) {
            $extra['payload_json'] = function_exists('wp_json_encode') ? wp_json_encode(array_merge(['job_id' => $this->jobId], $payload)) : null;
        }
        $this->jobLogs->log($this->jobId, $type, $status, $title, $detail, null, $ref, $extra);
    }
}
