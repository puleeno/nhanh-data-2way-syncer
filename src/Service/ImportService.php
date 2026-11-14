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

        try {
            $client = $this->client();
            if ($client === null) {
                throw new \RuntimeException('missing_credentials');
            }

            $categories = $client->products()->getCategories([], ['size' => 100]);
            $brands = $client->products()->getBrands();

            $categoryResult = $this->importCategories($client, $categories);
            $scanned += $categoryResult['scanned'];
            $updated += $categoryResult['updated'];
            if ($categoryResult['failed'] > 0) {
                $failures[] = 'categories';
                $failed += $categoryResult['failed'];
            }

            if (empty($brands)) {
                // no brand yet
            } else {
                $brandResult = $this->importBrands($brands);
                $scanned += $brandResult['scanned'];
                $updated += $brandResult['updated'];
                if ($brandResult['failed'] > 0) {
                    $failures[] = 'brands';
                    $failed += $brandResult['failed'];
                }
            }

            $products = $client->products()->search(['paginator' => ['size' => 100, 'sort' => ['id' => 'desc']]]);
            $products = $products instanceof \Illuminate\Support\Collection ? $products->all() : (array) $products;

            $prodResult = $this->importProducts($products, $categoryResult['map']);
            $scanned += $prodResult['scanned'];
            $updated += $prodResult['updated'];
            $failed += $prodResult['failed'];
            if ($prodResult['failed'] > 0) {
                $failures[] = 'products';
            }

            $status = ($failed > 0) ? 'partial' : 'success';
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
            'error' => $errorDetail,
        ]);

        $this->jobs->log(
            $jobId,
            'product_sync',
            $status,
            $status === 'success' ? 'Đồng bộ Nhanh.vn → WooCommerce thành công' : 'Đồng bộ Nhanh.vn → WooCommerce thất bại',
            $errorDetail,
            $durationMs,
            null
        );

        return [
            'ok' => $status === 'success',
            'job_id' => $jobId,
            'status' => $status,
            'scanned' => $scanned,
            'updated' => $updated,
            'failed' => $failed,
            'error' => $errorDetail,
            'duration_ms' => $durationMs,
        ];
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
        $scanned = 0;
        $updated = 0;
        $failed = 0;
        $map = [];

        $list = [];
        if ($categories instanceof \Illuminate\Support\Collection) {
            $list = $categories->all();
        } elseif (is_array($categories)) {
            $list = $categories;
        }

        $wcTax = $this->categoryTaxonomy();

        foreach ($list as $cat) {
            if (!$cat instanceof \Puleeno\NhanhVn\Entities\Product\ProductCategory) {
                continue;
            }
            $scanned++;
            $nhanhId = (string) $cat->getId();
            $name = (string) $cat->getName();
            if ($name === '') {
                continue;
            }

            $termId = $this->woo->upsertTerm($wcTax, $name, (string) $cat->getContent());
            if ($termId <= 0) {
                $failed++;
                continue;
            }

            $this->termMaps->upsert([
                'nhanh_term_type' => 'category',
                'nhanh_term_id' => $nhanhId,
                'nhanh_term_name' => $name,
                'nhanh_parent_id' => (string) $cat->getParentId(),
                'wc_taxonomy' => $wcTax,
                'wc_term_id' => $termId,
                'wc_term_name' => $name,
                'product_count' => 0,
                'status' => 'synced',
                'auto_create_new' => 0,
            ]);

            $map[$nhanhId] = (int) $termId;
            $updated++;
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed, 'map' => $map];
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

        foreach ($products as $p) {
            if (!$p instanceof \Puleeno\NhanhVn\Entities\Product\Product) {
                continue;
            }
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
            $data['images'] = $imageList;

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
                'image_url' => !empty($image) ? (string) $image : null,
                'last_compared_at' => current_time('mysql', 1),
                'last_synced_at' => current_time('mysql', 1),
            ]);

            if ($mapId > 0) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed, 'skipped' => $skipped];
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
            : 'publish';
        $configured = in_array($configured, ['publish', 'draft'], true) ? $configured : 'publish';

        if ($configured === 'draft') {
            return 'draft';
        }

        $status = $product->getAttribute('status');

        return in_array($status, [1, '1', 'Active', 'active'], true) ? 'publish' : 'draft';
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