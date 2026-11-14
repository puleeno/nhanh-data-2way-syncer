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

    public function __construct(JobRepository $jobs, ProductMapRepository $productMaps, TermMapRepository $termMaps, WooBridge $woo)
    {
        $this->jobs = $jobs;
        $this->productMaps = $productMaps;
        $this->termMaps = $termMaps;
        $this->woo = $woo;
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
            $client = (new NhanhClientFactory($this->settings()))->make();
            if ($client === null) {
                throw new \RuntimeException('missing_credentials');
            }

            $categories = $client->products()->getCategories([], ['size' => 100]);
            $brands = $client->products()->getBrands();

            $result = $this->importCategories($client, $categories);
            $scanned += $result['scanned'];
            $updated += $result['updated'];
            if ($result['failed'] > 0) {
                $failures[] = 'categories';
                $failed += $result['failed'];
            }

            if (empty($brands)) {
                // no brand yet
            } else {
                $result = $this->importBrands($brands);
                $scanned += $result['scanned'];
                $updated += $result['updated'];
                if ($result['failed'] > 0) {
                    $failures[] = 'brands';
                    $failed += $result['failed'];
                }
            }

            $products = $client->products()->search(['paginator' => ['size' => 100, 'sort' => ['id' => 'desc']]]);
            $products = $products instanceof \Illuminate\Support\Collection ? $products->all() : (array) $products;
            $scanned += count($products);

            $prodResult = $this->importProducts($products);
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
        if (class_exists(\Puleeno\NhanhVn\Syncer\Plugin::class)) {
            $s = \Puleeno\NhanhVn\Syncer\Plugin::get_settings();
            if (is_array($s) && !empty($s)) {
                return $s;
            }
        }
        return [];
    }

    /**
     * @param mixed $client
     * @return array{scanned:int,updated:int,failed:int}
     */
    private function importCategories($client, $categories): array
    {
        $scanned = 0;
        $updated = 0;
        $failed = 0;

        $list = [];
        if ($categories instanceof \Illuminate\Support\Collection) {
            $list = $categories->all();
        } elseif (is_array($categories)) {
            $list = $categories;
        }

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
            $wcTax = $this->settings()['brand_taxonomy'] ?: 'product_cat';
            $wcTax = in_array($wcTax, ['product_cat', 'product_brand'], true) ? $wcTax : 'product_cat';

            $termId = $this->woo->upsertTerm($wcTax, $name, (string) $cat->getContent());
            if ($termId <= 0) {
                $failed++;
                continue;
            }

            $termMapId = $this->termMaps->upsert([
                'nhanh_term_type' => 'category',
                'nhanh_term_id' => $nhanhId,
                'nhanh_term_name' => $name,
                'nhanh_parent_id' => (string) $cat->getParentId(),
                'wc_taxonomy' => $wcTax,
                'wc_term_id' => $termId,
                'wc_term_name' => $name,
                'product_count' => 0,
                'status' => $termId > 0 ? 'synced' : 'unmapped',
                'auto_create_new' => 0,
            ]);

            $updated++;
        }

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed];
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
            $wcTax = $this->settings()['brand_taxonomy'] ?: 'product_brand';
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
     * @param array<int,array<string,mixed>> $products
     * @return array{scanned:int,updated:int,failed:int}
     */
    private function importProducts(array $products): array
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

            $nhanhId = (string) $p->getIdNhanh();
            $code = (string) ($p->getCode() ?? '');
            if ($nhanhId === '' && $code === '') {
                continue;
            }

            $sku = $code !== '' ? $code : 'NHANH-' . $nhanhId;
            $price = (float) ($p->getPrice() ?? 0);
            $stock = (int) $p->getAvailableQuantity();

            $data = [
                'sku' => $sku,
                'name' => (string) $p->getName(),
                'price' => $price,
                'stock' => $stock,
                'status' => $this->settings()['auto_price_update'] ? 'publish' : 'draft',
                'categories' => [],
                'images' => [],
            ];

            $image = $p->getImage();
            if ($image) {
                $data['images'][] = (string) $image;
            }
            $images = $p->getImages();
            if (is_array($images)) {
                foreach ($images as $img) {
                    if (is_array($img) && isset($img['src'])) {
                        $data['images'][] = (string) $img['src'];
                    }
                }
            }

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
                'nhanh_category_id' => (string) ($p->getCategoryId() ?? 0),
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

        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed];
    }
}