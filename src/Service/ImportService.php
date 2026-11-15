<?php

namespace Puleeno\NhanhVn\Syncer\Service;

use Puleeno\NhanhVn\Syncer\Database\JobRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Woo\WooBridge;

/**
 * Nhanh.vn -> WooCommerce one-shot import (used by WP cron).
 *
 * Flow: fetch categories -> brands -> products from Nhanh v3 API
 *      -> upsert term maps -> create/update WC products -> upsert product maps.
 *
 * Failures are captured into job + logs rows; never fatal to the caller.
 */
class ImportService
{
    private const JOB_TYPE_IMPORT = 'import';

    /** @var JobRepository */
    private $jobs;

    /** @var ProductMapRepository */
    private $productMaps;

    /** @var TermMapRepository */
    private $termMaps;

    /** @var WooBridge */
    private $woo;

    /** @var object|null */
    private $client;

    /** @var array<string,mixed>|null */
    private $settings;

    /** @var array<string,string> */
    private $lastSectionErrors = [];

    /** @var float */
    private $deadline = 0.0;

    /** @var int */
    private $maxProducts = 50;

    /**
     * @param object|null $client   Injectable Nhanh client (tests); built from settings when null.
     * @param array<string,mixed>|null $settings Injectable settings (tests); read from Plugin when null.
     */
    public function __construct(
        JobRepository $jobs,
        ProductMapRepository $productMaps,
        TermMapRepository $termMaps,
        WooBridge $woo,
        $client = null,
        ?array $settings = null
    ) {
        $this->jobs = $jobs;
        $this->productMaps = $productMaps;
        $this->termMaps = $termMaps;
        $this->woo = $woo;
        $this->client = $client;
        $this->settings = $settings;
    }

    /**
     * Run a full Nhanh -> Woo import. Returns a small summary array.
     *
     * @return array<string,mixed>
     */
    public function run(array $context = []): array
    {
        $jobId = $this->jobs->create(self::JOB_TYPE_IMPORT, ['trigger' => $context['trigger'] ?? 'cron']);
        if ($jobId <= 0) {
            return ['ok' => false, 'job_id' => 0, 'error' => 'cannot_create_job'];
        }
        $this->jobs->start($jobId);

        $started = microtime(true);
        $scanned = 0;
        $updated = 0;
        $failed = 0;
        $failures = [];
        $errorDetail = '';
        $deferred = 0;
        $categoryResult = ['scanned' => 0, 'updated' => 0, 'failed' => 0];
        $brandResult = ['scanned' => 0, 'updated' => 0, 'failed' => 0];
        $prodResult = ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0, 'deferred' => 0];

        // Per-run budget so a cron tick always finishes; the remainder is picked
        // up by the next scheduled run (upserts are idempotent).
        $maxSeconds = isset($this->settings()['import_max_seconds'])
            ? max(10, (int) $this->settings()['import_max_seconds'])
            : 600;
        $this->maxProducts = isset($this->settings()['import_max_per_run'])
            ? max(1, (int) $this->settings()['import_max_per_run'])
            : 100;
        $this->deadline = $started + $maxSeconds;

        try {
            $client = $this->client();
            if ($client === null) {
                throw new \RuntimeException('missing_credentials');
            }

            // Each entity type is isolated: a failing Nhanh endpoint degrades that
            // section only instead of aborting the whole import.
            $categoryResult = $this->safeSection('categories', function () use ($client) {
                $categories = $client->products()->getCategories([], ['size' => 100]);
                return $this->importCategories($client, $categories);
            }, ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'map' => []]);
            $scanned += $categoryResult['scanned'];
            $updated += $categoryResult['updated'];
            if ($categoryResult['failed'] > 0) {
                $failures[] = 'categories';
                $failed += $categoryResult['failed'];
            }

            $brandResult = $this->safeSection('brands', function () use ($client) {
                $brands = $client->products()->getBrands();
                if (empty($brands)) {
                    return ['scanned' => 0, 'updated' => 0, 'failed' => 0];
                }
                return $this->importBrands($brands);
            }, ['scanned' => 0, 'updated' => 0, 'failed' => 0]);
            $scanned += $brandResult['scanned'];
            $updated += $brandResult['updated'];
            if ($brandResult['failed'] > 0) {
                $failures[] = 'brands';
                $failed += $brandResult['failed'];
            }

            $prodResult = $this->safeSection('products', function () use ($client, $categoryResult) {
                $products = $client->products()->search(['paginator' => ['size' => 100, 'sort' => ['id' => 'desc']]]);
                $products = $products instanceof \Illuminate\Support\Collection ? $products->all() : (array) $products;

                return $this->importProducts($products, $categoryResult['map']);
            }, ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0]);
            $scanned += $prodResult['scanned'];
            $updated += $prodResult['updated'];
            $failed += $prodResult['failed'];
            $deferred += (int) ($prodResult['deferred'] ?? 0);
            if ($prodResult['failed'] > 0) {
                $failures[] = 'products';
            }

            $status = ($failed > 0) ? 'partial' : 'success';

            // Surface per-section failures without losing the overall result.
            foreach ($this->lastSectionErrors as $section => $message) {
                $failures[] = $section . ': ' . $message;
                if ($status === 'success') {
                    $status = 'partial';
                }
                $errorDetail = trim($errorDetail . ($errorDetail === '' ? '' : '; ') . $section . ': ' . $message);
            }

            if ($status === 'partial' && $errorDetail === '' && $failures) {
                $errorDetail = 'one or more sections failed';
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $errorDetail = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $this->jobs->finish($jobId, $status, $scanned, $updated, $failed, [
            'trigger' => $context['trigger'] ?? 'cron',
            'scanned' => $scanned,
            'updated' => $updated,
            'failed' => $failed,
            'deferred' => $deferred,
            'error' => $errorDetail,
            'section_errors' => $this->lastSectionErrors,
        ]);

        $detail = self::buildLogDetail(
            $context['trigger'] ?? 'cron',
            $categoryResult,
            $brandResult,
            $prodResult,
            $scanned,
            $updated,
            $failed,
            $deferred,
            $durationMs,
            $errorDetail
        );

        $this->jobs->log(
            $jobId,
            'product_sync',
            $status,
            self::logTitle($status),
            $detail,
            $durationMs,
            'JOB-' . $jobId
        );

        return [
            'ok' => $status === 'success',
            'job_id' => $jobId,
            'status' => $status,
            'scanned' => $scanned,
            'updated' => $updated,
            'failed' => $failed,
            'deferred' => $deferred,
            'error' => $errorDetail,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Build a readable audit-log detail line for a job run.
     *
     * @param array{scanned?:int,updated?:int,failed?:int} $categories
     * @param array{scanned?:int,updated?:int,failed?:int} $brands
     * @param array{scanned?:int,updated?:int,failed?:int,skipped?:int,deferred?:int} $products
     */
    private static function buildLogDetail(
        string $trigger,
        array $categories,
        array $brands,
        array $products,
        int $scanned,
        int $updated,
        int $failed,
        int $deferred,
        int $durationMs,
        string $errorDetail = ''
    ): string {
        $parts = [];
        $parts[] = 'Nguồn: ' . $trigger;
        $parts[] = sprintf(
            'Danh mục: %d quét, %d cập nhật%s',
            (int) ($categories['scanned'] ?? 0),
            (int) ($categories['updated'] ?? 0),
            (int) ($categories['failed'] ?? 0) > 0 ? ', ' . (int) $categories['failed'] . ' lỗi' : ''
        );
        $parts[] = sprintf(
            'Thương hiệu: %d quét, %d cập nhật%s',
            (int) ($brands['scanned'] ?? 0),
            (int) ($brands['updated'] ?? 0),
            (int) ($brands['failed'] ?? 0) > 0 ? ', ' . (int) $brands['failed'] . ' lỗi' : ''
        );
        $parts[] = sprintf(
            'Sản phẩm: %d quét, %d cập nhật%s%s',
            (int) ($products['scanned'] ?? 0),
            (int) ($products['updated'] ?? 0),
            (int) ($products['failed'] ?? 0) > 0 ? ', ' . (int) $products['failed'] . ' lỗi' : '',
            (int) ($products['deferred'] ?? 0) > 0 ? ', ' . (int) $products['deferred'] . ' tồn đọng' : ''
        );
        $parts[] = sprintf('Tổng: %d quét, %d cập nhật, %d lỗi', $scanned, $updated, $failed);

        if ($deferred > 0) {
            $parts[] = $deferred . ' mục tồn đọng (nhịp sau chạy tiếp)';
        }
        $parts[] = 'Thời lượng: ' . $durationMs . ' ms';

        $detail = implode(' · ', $parts);

        if ($errorDetail !== '') {
            $detail .= ' — Lỗi: ' . $errorDetail;
        }

        return $detail;
    }

    /**
     * Human-readable log title for a job status.
     */
    private static function logTitle(string $status): string
    {
        switch ($status) {
            case 'success':
                return 'Đồng bộ Nhanh.vn → WooCommerce thành công';
            case 'partial':
                return 'Đồng bộ Nhanh.vn → WooCommerce hoàn tất một phần';
            default:
                return 'Đồng bộ Nhanh.vn → WooCommerce thất bại';
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function settings(): array
    {
        if (is_array($this->settings)) {
            return $this->settings;
        }
        if (class_exists('Puleeno\\NhanhVn\\Syncer\\Plugin')) {
            $s = \Puleeno\NhanhVn\Syncer\Plugin::get_settings();
            if (is_array($s) && !empty($s)) {
                return $s;
            }
        }
        return [];
    }

    /**
     * Resolve the Nhanh client (injected instance, or built from settings).
     *
     * @return object|null
     */
    private function client()
    {
        if (is_object($this->client)) {
            return $this->client;
        }
        return (new NhanhClientFactory($this->settings()))->make();
    }

    /**
     * Run one import section, converting a thrown SDK/transport error into a
     * failed-section result so the remaining sections still run.
     *
     * @param string $name
     * @param callable $work
     * @param array<string,mixed> $empty
     * @return array<string,mixed>
     */
    private function safeSection(string $name, callable $work, array $empty): array
    {
        try {
            $result = $work();
            return is_array($result) ? array_merge($empty, $result) : $empty;
        } catch (\Throwable $e) {
            $this->lastSectionErrors[$name] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * WooCommerce taxonomy used for Nhanh product categories. Always `product_cat`;
     * the brand taxonomy is a separate concern handled by importBrands().
     */
    private function categoryTaxonomy(): string
    {
        return 'product_cat';
    }

    /**
     * Import Nhanh product categories into WooCommerce `product_cat`.
     *
     * @param mixed $client
     * @param mixed $categories
     * @return array{scanned:int,updated:int,failed:int,map:array<string,int>} nhanh term id => wc term id
     */
    private function importCategories($client, $categories): array
    {
        $list = [];
        if ($categories instanceof \Illuminate\Support\Collection) {
            $list = $categories->all();
        } elseif (is_array($categories)) {
            $list = $categories;
        }

        $wcTax = $this->categoryTaxonomy();

        // Index by Nhanh id so parents can be resolved before children.
        $items = [];
        foreach ($list as $cat) {
            if (!$cat instanceof \Puleeno\NhanhVn\Entities\Product\ProductCategory) {
                continue;
            }
            $id = (string) $cat->getId();
            $name = (string) $cat->getName();
            if ($id === '' || $name === '') {
                continue;
            }
            $items[$id] = [
                'name' => $name,
                'parent' => (int) $cat->getParentId(),
                'description' => (string) $cat->getContent(),
            ];
        }

        $scanned = count($list);
        $updated = 0;
        $failed = 0;
        $map = [];

        // Reuse mappings from previous runs so existing terms keep their parent.
        foreach (array_keys($items) as $id) {
            $existing = $this->termMaps->findByNhanh('category', $id, $wcTax);
            if ($existing && (int) ($existing['wc_term_id'] ?? 0) > 0) {
                $map[$id] = (int) $existing['wc_term_id'];
            }
        }

        $remaining = $items;
        while ($remaining) {
            $progress = false;

            foreach ($remaining as $id => $item) {
                $parentId = $item['parent'];
                $parentReady = ($parentId <= 0) || !isset($items[$parentId]) || isset($map[$parentId]);
                if (!$parentReady) {
                    continue;
                }

                $parentTermId = ($parentId > 0 && isset($map[$parentId])) ? (int) $map[$parentId] : 0;

                unset($remaining[$id]);
                $progress = true;

                $termId = $this->persistCategory($wcTax, $id, $item, $parentTermId);
                if ($termId <= 0) {
                    $failed++;
                    continue;
                }
                $map[$id] = $termId;
                $updated++;
            }

            if (!$progress) {
                // Orphan branch (parent absent from payload): import as root.
                foreach ($remaining as $id => $item) {
                    unset($remaining[$id]);
                    $termId = $this->persistCategory($wcTax, $id, $item, 0);
                    if ($termId <= 0) {
                        $failed++;
                        continue;
                    }
                    $map[$id] = $termId;
                    $updated++;
                }
                break;
            }
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed, 'map' => $map];
    }

    /**
     * Create/update one category term and record its mapping.
     *
     * @param array{name:string,parent:int,description:string} $item
     */
    private function persistCategory(string $wcTax, string $nhanTermId, array $item, int $parentTermId): int
    {
        $termId = $this->woo->upsertTerm($wcTax, $item['name'], $item['description'], $parentTermId);
        if ($termId <= 0) {
            return 0;
        }

        $this->termMaps->upsert([
            'nhanh_term_type' => 'category',
            'nhan_term_id' => $nhanTermId,
            'nhan_term_name' => $item['name'],
            'nhan_parent_id' => (string) $item['parent'],
            'wc_taxonomy' => $wcTax,
            'wc_term_id' => $termId,
            'wc_term_name' => $item['name'],
            'product_count' => 0,
            'status' => 'synced',
            'auto_create_new' => 0,
        ]);

        return $termId;
    }

    /**
     * @param mixed $brands
     * @return array{scanned:int,updated:int,failed:int}
     */
    private function importBrands($brands): array
    {
        $scanned = 0;
        $updated = 0;
        $failed = 0;

        $list = [];
        if ($brands instanceof \Illuminate\Support\Collection) {
            $list = $brands->all();
        } elseif (is_array($brands)) {
            $list = $brands;
        }

        foreach ($list as $brand) {
            if (!$brand instanceof \Puleeno\NhanhVn\Entities\Product\ProductBrand) {
                continue;
            }
            $scanned++;
            $nhanhId = (string) $brand->getId();
            $name = (string) $brand->getName();
            if ($name === '') {
                continue;
            }
            $wcTax = (string) ($this->settings()['brand_taxonomy'] ?? 'product_brand');
            $wcTax = in_array($wcTax, ['product_cat', 'product_brand'], true) ? $wcTax : 'product_brand';

            $termId = $this->woo->upsertTerm($wcTax, $name, (string) $brand->getDescription());
            if ($termId <= 0) {
                $failed++;
                continue;
            }

            $termMapId = $this->termMaps->upsert([
                'nhanh_term_type' => 'brand',
                'nhanh_term_id' => $nhanhId,
                'nhanh_term_name' => $name,
                'nhanh_parent_id' => '',
                'wc_taxonomy' => $wcTax,
                'wc_term_id' => $termId,
                'wc_term_name' => $name,
                'product_count' => 0,
                'status' => 'synced',
                'auto_create_new' => 0,
            ]);

            $updated++;
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed];
    }

    /**
     * @param array<int,mixed> $products
     * @param array<string,int> $categoryMap nhanh term id => wc term id
     * @return array{scanned:int,updated:int,failed:int,skipped:int}
     */
    private function importProducts(array $products, array $categoryMap = []): array
    {
        $scanned = 0;
        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $deferred = 0;
        $processed = 0;
        $importImages = !empty($this->settings()['import_images']);

        foreach ($products as $p) {
            if (!$p instanceof \Puleeno\NhanhVn\Entities\Product\Product) {
                continue;
            }

            // Budget guard: stop cleanly and let the next cron tick continue.
            if ($processed >= $this->maxProducts || microtime(true) >= $this->deadline) {
                $deferred = max(0, count($products) - $processed);
                break;
            }
            $processed++;
            $scanned++;

            $nhanhId = (string) ($p->getAttribute('id') ?? $p->getAttribute('idNhanh') ?? '');
            $code = (string) ($p->getCode() ?? '');
            if ($nhanhId === '' && $code === '') {
                $skipped++;
                continue;
            }

            $sku = $code !== '' ? $code : 'NHANH-' . $nhanhId;
            $price = $this->resolvePrice($p);
            $stock = $this->resolveStock($p);

            $categoryId = (string) ($p->getCategoryId() ?? '');
            $categoryTermIds = [];
            if ($categoryId !== '' && isset($categoryMap[$categoryId])) {
                $categoryTermIds[] = (int) $categoryMap[$categoryId];
            }

            $data = [
                'sku' => $sku,
                'name' => (string) $p->getName(),
                'price' => $price,
                'stock' => $stock,
                'status' => $this->resolvePostStatus($p),
                'categories' => $categoryTermIds,
                'images' => [],
            ];

            $image = $p->getImage();
            $images = $p->getImages();
            $imageList = [];
            if (is_array($images)) {
                if (isset($images['avatar']) && is_string($images['avatar']) && $images['avatar'] !== '') {
                    $imageList[] = $images['avatar'];
                }
                if (isset($images['others']) && is_array($images['others'])) {
                    foreach ($images['others'] as $url) {
                        if (is_string($url) && $url !== '') {
                            $imageList[] = $url;
                        }
                    }
                }
                foreach ($images as $img) {
                    if (is_array($img) && isset($img['src'])) {
                        $imageList[] = (string) $img['src'];
                    }
                }
            }
            if ($image && !in_array((string) $image, $imageList, true)) {
                array_unshift($imageList, (string) $image);
            }
            // Image sideload is optional: it is the slowest part of the import.
            $data['images'] = $importImages ? $imageList : [];

            $productId = $this->woo->upsertProduct($data);
            if ($productId <= 0) {
                $failed++;
                continue;
            }

            $mapId = $this->productMaps->upsert([
                'nhanh_id' => $nhanhId,
                'nhanh_code' => $sku,
                'nhanh_barcode' => '',
                'nhanh_name' => (string) $p->getName(),
                'nhanh_category_id' => $categoryId,
                'nhanh_category_name' => (string) ($p->getCategoryName() ?? ''),
                'nhanh_brand_id' => (string) ($p->getBrandId() ?? 0),
                'nhanh_brand_name' => (string) ($p->getBrandName() ?? ''),
                'nhanh_price' => $price,
                'nhanh_original_price' => $price,
                'nhanh_stock_total' => $stock,
                'wc_product_id' => $productId,
                'wc_variation_id' => 0,
                'wc_sku' => $sku,
                'sync_status' => 'synced',
                'image_url' => $imageList ? (string) $imageList[0] : (!empty($image) ? (string) $image : null),
                'last_compared_at' => current_time('mysql', 1),
                'last_synced_at' => current_time('mysql', 1),
            ]);

            if ($mapId > 0) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed, 'skipped' => $skipped, 'deferred' => $deferred];
    }

    /**
     * Decide the WooCommerce post status for an imported product.
     *
     * Active products publish; inactive/draft products stay drafts.
     */
    private function resolvePostStatus($product): string
    {
        $configured = isset($this->settings()['import_post_status'])
            ? (string) $this->settings()['import_post_status']
            : 'draft';

        return in_array($configured, ['publish', 'draft'], true) ? $configured : 'draft';
    }

    /**
     * Resolve the retail price from either the v3 `price` or `prices.retail` shape.
     */
    private function resolvePrice($product): float
    {
        $price = $product->getAttribute('price');
        if ($price === null) {
            $prices = $product->getAttribute('prices');
            if (is_array($prices)) {
                $price = $prices['retail'] ?? null;
            }
        }
        return (float) ($price ?? 0);
    }

    /**
     * Resolve available stock across v3 inventory shapes.
     */
    private function resolveStock($product): int
    {
        $available = $product->getAttribute('available');
        if ($available !== null) {
            return (int) $available;
        }

        $inventory = $product->getAttribute('inventory');
        if (is_array($inventory)) {
            if (isset($inventory['available'])) {
                return (int) $inventory['available'];
            }
            if (isset($inventory['remain'])) {
                return (int) $inventory['remain'];
            }
        }

        return (int) $product->getTotalQuantity();
    }
}
