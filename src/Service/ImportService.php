<?php

namespace Puleeno\NhanhVn\Syncer\Service;

use Puleeno\NhanhVn\Syncer\Database\JobRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Sync\ProductSyncOrchestrator;
use Puleeno\NhanhVn\Syncer\Woo\WooBridge;

/**
 * Nhanh.vn -> WooCommerce import (used by WP cron / manual dispatch).
 * Refactored to delegate product normalization+sync to ProductSyncOrchestrator
 * while preserving job/logging/category/brand flows and public API.
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

    /** @return array<string,mixed> */
    public function run(array $context = []): array
    {
        $jobId = $this->jobs->create(self::JOB_TYPE_IMPORT, ['trigger' => $context['trigger'] ?? 'cron']);
        if ($jobId <= 0) {
            return ['ok' => false, 'job_id' => 0, 'error' => 'cannot_create_job'];
        }
        $this->jobs->start($jobId);

        $started = microtime(true);
        $scanned = 0; $updated = 0; $failed = 0;
        $failures = [];
        $errorDetail = '';
        $deferred = 0;
        $categoryResult = ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'map' => []];
        $brandResult = ['scanned' => 0, 'updated' => 0, 'failed' => 0];
        $prodResult = ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0, 'deferred' => 0];

        $maxSeconds = isset($this->settings()['import_max_seconds']) ? max(10, (int) $this->settings()['import_max_seconds']) : 600;
        $this->maxProducts = isset($this->settings()['import_max_per_run']) ? max(1, (int) $this->settings()['import_max_per_run']) : 100;
        $this->deadline = $started + $maxSeconds;

        try {
            $client = $this->client();
            if ($client === null) {
                throw new \RuntimeException('missing_credentials');
            }

            $categoryResult = $this->safeSection('categories', function () use ($client) {
                $categories = $client->products()->getCategories([], ['size' => 100]);
                return $this->importCategories($client, $categories);
            }, ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'map' => []]);
            $scanned += $categoryResult['scanned'];
            $updated += $categoryResult['updated'];
            if ($categoryResult['failed'] > 0) { $failures[] = 'categories'; $failed += $categoryResult['failed']; }

            $brandResult = $this->safeSection('brands', function () use ($client) {
                $brands = $client->products()->getBrands();
                if (empty($brands)) return ['scanned' => 0, 'updated' => 0, 'failed' => 0];
                return $this->importBrands($brands);
            }, ['scanned' => 0, 'updated' => 0, 'failed' => 0]);
            $scanned += $brandResult['scanned'];
            $updated += $brandResult['updated'];
            if ($brandResult['failed'] > 0) { $failures[] = 'brands'; $failed += $brandResult['failed']; }

            $prodResult = $this->safeSection('products', function () use ($client, $categoryResult) {
                return $this->importProductsPaged($client, isset($categoryResult['map']) ? $categoryResult['map'] : []);
            }, ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0, 'deferred' => 0]);
            $scanned += $prodResult['scanned'];
            $updated += $prodResult['updated'];
            $failed += $prodResult['failed'];
            $deferred = (int) ($prodResult['deferred'] ?? 0);
            if ($prodResult['failed'] > 0) { $failures[] = 'products'; }

            $status = ($failed > 0) ? 'partial' : 'success';
            $sectionErrors = $this->sectionErrorSummary();
            if ($sectionErrors !== '') {
                $errorDetail = $sectionErrors;
                if ($status === 'success') $status = 'partial';
            }
            if ($deferred > 0 && $status === 'success') $status = 'partial';

            if ($status === 'partial' && $errorDetail === '' && $failures) {
                $errorDetail = 'sections_failed:' . implode(',', $failures);
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $errorDetail = $e->getMessage();
        }

        $this->jobs->finish($jobId, $status, $scanned, $updated, $failed, [
            'trigger' => $context['trigger'] ?? 'cron',
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $errorDetail,
            'sections_failed' => $failures,
            'section_errors' => $this->lastSectionErrors,
            'deferred' => $deferred,
            'categories' => $categoryResult,
            'brands' => $brandResult,
            'products' => $prodResult,
        ]);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $this->jobs->log(
            $jobId,
            self::JOB_TYPE_IMPORT,
            $status,
            self::logTitle($status),
            $this->buildLogDetail($scanned, $updated, $failed, $categoryResult, $brandResult, $prodResult, $errorDetail),
            $durationMs
        );

        return [
            'ok' => $status === 'success',
            'job_id' => $jobId,
            'status' => $status,
            'scanned' => $scanned,
            'updated' => $updated,
            'failed' => $failed,
            'deferred' => $deferred,
            'duration_ms' => $durationMs,
            'error' => $errorDetail ?: null,
        ];
    }

    /** @param array{scanned?:int,updated?:int,failed?:int} $categories @param array{scanned?:int,updated?:int,failed?:int} $brands @param array{scanned?:int,updated?:int,failed?:int,skipped?:int,deferred?:int} $products */
    private function buildLogDetail(int $scanned, int $updated, int $failed, array $categories, array $brands, array $products, string $errorDetail): string
    {
        $parts = [];
        $catScanned = (int) ($categories['scanned'] ?? 0);
        $brandScanned = (int) ($brands['scanned'] ?? 0);
        $prodScanned = (int) ($products['scanned'] ?? 0);
        $prodUpdated = (int) ($products['updated'] ?? 0);
        $prodFailed = (int) ($products['failed'] ?? 0);
        $prodSkipped = (int) ($products['skipped'] ?? 0);
        $prodDeferred = (int) ($products['deferred'] ?? 0);
        if ($catScanned > 0) $parts[] = sprintf('Danh mục: %d/%d cập nhật', (int) ($categories['updated'] ?? 0), $catScanned);
        if ($brandScanned > 0) $parts[] = sprintf('Thương hiệu: %d/%d cập nhật', (int) ($brands['updated'] ?? 0), $brandScanned);
        $parts[] = sprintf('Sản phẩm: quét %d, cập nhật %d, bỏ qua %d, lỗi %d, hoãn lại %d', $prodScanned, $prodUpdated, $prodSkipped, $prodFailed, $prodDeferred);
        $parts[] = sprintf('Tổng: quét %d, cập nhật %d, lỗi %d', $scanned, $updated, $failed);
        $sections = $this->sectionErrorSummary();
        if ($sections !== '') $parts[] = $sections;
        if ($errorDetail !== '') $parts[] = 'Lỗi: ' . $errorDetail;
        return implode(' | ', $parts);
    }

    private static function logTitle(string $status): string
    {
        switch ($status) {
            case 'success': return 'Đồng bộ Nhanh → WooCommerce thành công';
            case 'partial': return 'Đồng bộ Nhanh → WooCommerce hoàn tất (một phần)';
            case 'failed': return 'Đồng bộ Nhanh → WooCommerce thất bại';
            default: return 'Đồng bộ Nhanh → WooCommerce';
        }
    }

    private function safeSection(string $name, callable $fn, array $fallback): array
    {
        try {
            $result = $fn();
            return is_array($result) ? $result : $fallback;
        } catch (\Throwable $e) {
            $this->lastSectionErrors[$name] = $e->getMessage();
            return $fallback;
        }
    }

    private function sectionErrorSummary(): string
    {
        if (!$this->lastSectionErrors) return '';
        $parts = [];
        foreach ($this->lastSectionErrors as $name => $msg) $parts[] = $name . ': ' . $msg;
        return 'sections=' . implode('; ', $parts);
    }

    /** @return array<string,mixed> */
    private function settings(): array
    {
        // Always merge over the defaults so injected/partial settings (tests,
        // manual runs) still expose every key the sync pipeline reads.
        $defaults = class_exists('Puleeno\\NhanhVn\\Syncer\\Config\\Defaults')
            ? \Puleeno\NhanhVn\Syncer\Config\Defaults::settings()
            : [];

        if (is_array($this->settings)) {
            return array_merge($defaults, $this->settings);
        }
        if (class_exists('Puleeno\\NhanhVn\\Syncer\\Plugin')) {
            return array_merge($defaults, (array) \Puleeno\NhanhVn\Syncer\Plugin::get_settings());
        }
        return $defaults;
    }

    /** @return object|null */
    private function client()
    {
        if ($this->client) return $this->client;
        $factory = new NhanhClientFactory($this->settings());
        $this->client = $factory->make();
        return $this->client;
    }

    private function categoryTaxonomy(): string
    {
        return 'product_cat';
    }

    /** @param object $client @param mixed $categories @return array{scanned:int,updated:int,failed:int,map:array<string,int>} */
    private function importCategories($client, $categories): array
    {
        $scanned = 0; $updated = 0; $failed = 0;
        $wcTax = $this->categoryTaxonomy();
        $items = [];
        $map = [];
        $resolved = [];

        $rawList = is_array($categories) ? $categories : [];
        foreach ($rawList as $cat) {
            $scanned++;
            if (!$cat instanceof \Puleeno\NhanhVn\Entities\Product\ProductCategory) continue;
            $id = (string) ($cat->getId() ?? '');
            $name = (string) $cat->getName();
            if ($id === '' || $name === '') continue;
            $items[$id] = [
                'name' => $name,
                'parent' => (string) ((int) ($cat->getParentId() ?? 0)),
                'description' => method_exists($cat, 'getContent') ? (string) $cat->getContent() : (string) ($cat->getAttribute('content') ?? ''),
            ];
            $existing = $this->termMaps->findByNhanh('category', $id, $wcTax);
            if ($existing && !empty($existing['wc_term_id'])) {
                $resolved[$id] = (int) $existing['wc_term_id'];
                $map[$id] = (int) $existing['wc_term_id'];
            }
        }

        $pending = array_keys($items);
        $passes = 0;
        while ($pending && $passes < 10) {
            $passes++;
            $next = [];
            foreach ($pending as $id) {
                $item = $items[$id];
                $parentNhanhId = $item['parent'];
                $parentTermId = 0;
                if ($parentNhanhId !== '' && $parentNhanhId !== '0') {
                    if (!isset($items[$parentNhanhId])) { $parentTermId = 0; }
                    elseif (isset($resolved[$parentNhanhId])) { $parentTermId = (int) $resolved[$parentNhanhId]; }
                    else { $next[] = $id; continue; }
                }
                $termId = $this->persistCategory($wcTax, $id, $item, $parentTermId, $map[$id] ?? null);
                if ($termId > 0) { $resolved[$id] = $termId; $map[$id] = $termId; $updated++; } else { $failed++; }
            }
            $pending = $next;
        }
        foreach ($pending as $id) {
            $termId = $this->persistCategory($wcTax, $id, $items[$id], 0, $map[$id] ?? null);
            if ($termId > 0) { $resolved[$id] = $termId; $map[$id] = $termId; $updated++; } else { $failed++; }
        }
        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed, 'map' => $map];
    }

    /** @param array{name:string,parent:string,description:string} $item */
    private function persistCategory(
        string $wcTax,
        string $nhanhTermId,
        array $item,
        int $parentTermId,
        ?int $existingTermId = null
    ): int {
        $termId = $this->woo->upsertTerm(
            $wcTax,
            $item['name'],
            $item['description'],
            $parentTermId,
            $existingTermId,
            !empty($this->settings()['sync_term_names'])
        );
        if ($termId <= 0) {
            return 0;
        }

        // Record the term name as it exists in WooCommerce, so the admin screen
        // reflects the shop manager's own wording after a rename.
        $wcName = $item['name'];
        if (function_exists('get_term')) {
            $term = get_term($termId, $wcTax);
            if ($term instanceof \WP_Term && !$this->isWpError($term)) {
                $wcName = (string) $term->name;
            }
        }

        $this->termMaps->upsert([
            'nhanh_term_type' => 'category',
            'nhanh_term_id' => $nhanhTermId,
            'nhanh_term_name' => $item['name'],
            'nhanh_parent_id' => (string) $item['parent'],
            'wc_taxonomy' => $wcTax,
            'wc_term_id' => $termId,
            'wc_term_name' => $wcName,
            'product_count' => 0,
            'status' => 'synced',
            'auto_create_new' => 0,
        ]);

        return $termId;
    }

    private function isWpError($thing): bool
    {
        return function_exists('is_wp_error') && is_wp_error($thing);
    }

    /** @param mixed $brands @return array{scanned:int,updated:int,failed:int} */
    private function importBrands($brands): array
    {
        $scanned = 0; $updated = 0; $failed = 0;
        $list = [];
        if ($brands instanceof \Illuminate\Support\Collection) $list = $brands->all();
        elseif (is_array($brands)) $list = $brands;

        foreach ($list as $brand) {
            $scanned++;
            if (!$brand instanceof \Puleeno\NhanhVn\Entities\Product\ProductBrand) continue;
            $nhanhBrandId = (string) $brand->getId();
            $name = (string) $brand->getName();
            if ($nhanhBrandId === '' || $name === '') continue;
            $wcTax = (string) ($this->settings()['brand_taxonomy'] ?? 'product_brand');
            $wcTax = in_array($wcTax, ['product_cat', 'product_brand'], true) ? $wcTax : 'product_brand';
            $desc = method_exists($brand, 'getDescription') ? (string) $brand->getDescription() : '';
            $existing = $this->termMaps->findByNhanh('brand', $nhanhBrandId, $wcTax);
            $existingTermId = ($existing && (int) ($existing['wc_term_id'] ?? 0) > 0) ? (int) $existing['wc_term_id'] : null;
            $termId = $this->woo->upsertTerm(
                $wcTax,
                $name,
                $desc,
                0,
                $existingTermId,
                !empty($this->settings()['sync_term_names'])
            );
            if ($termId <= 0) { $failed++; continue; }

            $wcName = $name;
            if (function_exists('get_term')) {
                $term = get_term($termId, $wcTax);
                if ($term instanceof \WP_Term && !$this->isWpError($term)) {
                    $wcName = (string) $term->name;
                }
            }
            $this->termMaps->upsert([
                'nhanh_term_type' => 'brand',
                'nhanh_term_id' => $nhanhBrandId,
                'nhanh_term_name' => $name,
                'nhanh_parent_id' => method_exists($brand, 'getParentId') ? (string) ($brand->getParentId() ?: '') : '',
                'wc_taxonomy' => $wcTax,
                'wc_term_id' => $termId,
                'wc_term_name' => $wcName,
                'status' => 'synced',
                'last_synced_at' => current_time('mysql', 1),
            ]);
            $updated++;
        }
        return ['scanned' => $scanned, 'updated' => $updated, 'failed' => $failed];
    }

    /**
     * Walk every page of the Nhanh product list.
     *
     * Nhanh returns one page per request (default 100), so a single search()
     * call would only ever cover the first page and the rest of the catalog
     * would never be imported.
     *
     * @param object $client
     * @return array<int,mixed>
     */
    /**
     * Import products page by page, resuming from a stored cursor.
     *
     * Nhanh paginates with `paginator.next` (not `page`). The cursor is kept in
     * an option so a run that hits its budget/time limit resumes where it
     * stopped on the next cron tick instead of reprocessing page 1 forever.
     *
     * @param object $client
     * @param array<string,int> $categoryMap
     * @return array{scanned:int,updated:int,failed:int,skipped:int,deferred:int}
     */
    private function importProductsPaged($client, array $categoryMap): array
    {
        $http = (new NhanhClientFactory($this->settings()))->httpService();

        $state = get_option('nhan_syncer_import_cursor');
        $next = (is_array($state) && !empty($state['next'])) ? (string) $state['next'] : null;

        $total = ['scanned' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0, 'deferred' => 0];
        $processed = 0;
        $guard = 0;

        while ($guard < 100) {
            $guard++;

            if ($http === null) {
                // Fallback without a raw HTTP service: single page via the SDK.
                $result = $client->products()->search(['paginator' => ['size' => 100]]);
                $batch = $result instanceof \Illuminate\Support\Collection ? $result->all() : (is_array($result) ? $result : []);
                $next = null;
            } else {
                $paginator = ['size' => 100];
                if (!empty($next)) {
                    $paginator['next'] = $next;
                }
                $response = $http->callApi('/v3.0/product/list', ['paginator' => $paginator]);
                $batch = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
                $next = $response['paginator']['next'] ?? null;
            }

            $pageResult = $this->importProducts($batch, $categoryMap);
            $total['scanned'] += (int) $pageResult['scanned'];
            $total['updated'] += (int) $pageResult['updated'];
            $total['failed'] += (int) $pageResult['failed'];
            $total['skipped'] += (int) ($pageResult['skipped'] ?? 0);
            $processed += (int) $pageResult['scanned'];

            $hitBudget = ($pageResult['deferred'] ?? 0) > 0
                || $processed >= $this->maxProducts
                || microtime(true) >= $this->deadline;

            if (empty($next)) {
                delete_option('nhan_syncer_import_cursor');
                break;
            }

            update_option('nhan_syncer_import_cursor', [
                'next' => (string) $next,
                'updated_at' => time(),
            ]);

            if ($hitBudget) {
                $total['deferred'] = 1;
                break;
            }
        }

        return $total;
    }

    /** @param array<int,mixed> $products @param array<string,int> $categoryMap @return array{scanned:int,updated:int,failed:int,skipped:int,deferred:int} */
    private function importProducts(array $products, array $categoryMap = []): array
    {
        $orchestrator = new ProductSyncOrchestrator($this->productMaps, $this->termMaps, $this->woo, $this->settings());
        return $orchestrator->syncProducts($products, $categoryMap, $this->maxProducts, $this->deadline);
    }
}
