<?php

namespace Puleeno\NhanhVn\Syncer\Admin\Pages;

use Puleeno\NhanhVn\Syncer\Database\LogRepository;
use Puleeno\NhanhVn\Syncer\Database\OrderMapRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\Schema;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Service\SyncStatusResolver;

/**
 * Assembles the view models for the admin screens.
 *
 * Every list screen reads from the plugin's own tables:
 *   products    <- nhanh_sync_product_map   (+ WooCommerce price/stock)
 *   term_maps   <- nhanh_sync_term_map
 *   order_maps  <- nhanh_sync_order_map
 *   logs        <- nhanh_sync_logs
 *   revenue     <- nhanh_sync_stats_daily
 *   turnover    <- nhanh_sync_product_map (aggregated)
 */
class Data
{
    /**
     * @return array<string,mixed>
     */
    public static function get(): array
    {
        $products = self::products();
        $termMaps = self::termMaps();
        $orderMaps = self::orderMaps();
        $logs = self::logs();

        return [
            'products' => $products,
            'term_maps' => $termMaps,
            'order_maps' => $orderMaps,
            'logs' => $logs,
            'revenue' => self::revenue(),
            'turnover' => self::turnover($products),
            'stats' => [
                'products' => self::productStats(),
                'orders' => self::orderStats(),
            ],
        ];
    }

    /**
     * Product mapping rows joined with the live WooCommerce price/stock.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function products(): array
    {
        $rows = (new ProductMapRepository())->allForAdmin();
        $resolver = new SyncStatusResolver();

        // Nhanh category/brand names live in the term map, keyed by Nhanh id.
        $termNames = ['category' => [], 'brand' => []];
        foreach ((new TermMapRepository())->all() as $term) {
            $type = (string) ($term['nhanh_term_type'] ?? 'category');
            $id = (string) ($term['nhanh_term_id'] ?? '');
            if ($id !== '') {
                $termNames[$type === 'brand' ? 'brand' : 'category'][$id] = (string) ($term['nhanh_term_name'] ?? '');
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $wcId = (int) ($row['wc_product_id'] ?? 0);
            $webPrice = null;
            $webStock = null;

            if ($wcId > 0 && function_exists('wc_get_product')) {
                $product = wc_get_product($wcId);
                if ($product) {
                    $webPrice = $product->get_price() !== '' ? (float) $product->get_price() : null;
                    $webStock = $product->get_stock_quantity();
                    if ($webStock === null) {
                        $webStock = $product->get_stock_status() === 'instock' ? null : 0;
                    }
                }
            }

            $nhanhPrice = isset($row['nhanh_price']) && $row['nhanh_price'] !== null ? (float) $row['nhanh_price'] : null;
            $nhanhStock = (int) ($row['nhanh_stock_total'] ?? 0);

            $syncStatus = $resolver->resolve(
                $nhanhPrice,
                $nhanhStock,
                $webPrice,
                $webStock,
                $wcId > 0
            );

            $out[] = [
                'nhanh_code' => (string) ($row['nhanh_code'] ?? ''),
                'nhanh_name' => (string) ($row['nhanh_name'] ?? ''),
                'category' => self::termName($termNames['category'], $row['nhanh_category_id'] ?? null, $row['nhanh_category_name'] ?? null),
                'brand' => self::termName($termNames['brand'], $row['nhanh_brand_id'] ?? null, $row['nhanh_brand_name'] ?? null),
                'nhanh_price' => $nhanhPrice,
                'nhanh_stock' => $nhanhStock,
                'web_id' => $wcId > 0 ? (string) $wcId : null,
                'web_price' => $webPrice,
                'web_stock' => $webStock,
                'sync_status' => $syncStatus,
                'sell_velocity' => $row['sell_velocity'] ?? null,
                'turnover_days' => isset($row['turnover_days']) ? (int) $row['turnover_days'] : null,
                'units_sold_month' => (int) ($row['units_sold_month'] ?? 0),
                'revenue_month' => (float) ($row['revenue_month'] ?? 0),
                'last_synced' => self::when($row['last_synced_at'] ?? ($row['updated_at'] ?? null)),
            ];
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function termMaps(): array
    {
        $rows = (new TermMapRepository())->all();

        $out = [];
        foreach ($rows as $row) {
            $wcTermId = (int) ($row['wc_term_id'] ?? 0);
            $wcName = $row['wc_term_name'] ? (string) $row['wc_term_name'] : '';

            if ($wcName === '' && $wcTermId > 0 && function_exists('get_term')) {
                $term = get_term($wcTermId, (string) $row['wc_taxonomy']);
                if ($term && !is_wp_error($term)) {
                    $wcName = $term->name;
                }
            }

            $out[] = [
                'type' => (string) ($row['nhanh_term_type'] ?? 'category'),
                'nhanh_id' => (string) ($row['nhanh_term_id'] ?? ''),
                'nhanh' => (string) ($row['nhanh_term_name'] ?? ''),
                'wc_taxonomy' => (string) ($row['wc_taxonomy'] ?? ''),
                'web' => $wcName !== '' ? $wcName : null,
                'wc_term_id' => $wcTermId > 0 ? $wcTermId : null,
                'count' => (int) ($row['product_count'] ?? 0),
                'status' => (string) ($row['status'] ?? 'unmapped'),
                'time' => self::when($row['last_synced_at'] ?? ($row['updated_at'] ?? null)),
            ];
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function orderMaps(): array
    {
        $rows = (new OrderMapRepository())->all();

        $out = [];
        foreach ($rows as $row) {
            $source = (string) ($row['source'] ?? 'wc_to_nhanh');
            if (!in_array($source, ['wc_to_nhanh', 'nhanh_to_wc'], true)) {
                $source = 'wc_to_nhanh';
            }

            $out[] = [
                'wc_order_id' => (int) ($row['wc_order_id'] ?? 0),
                'nhanh_order_id' => (string) ($row['nhanh_order_id'] ?? ''),
                'nhanh_order_code' => (string) ($row['nhanh_order_code'] ?? ''),
                'sync_status' => (string) ($row['sync_status'] ?? 'created'),
                'total_amount' => isset($row['total_amount']) && $row['total_amount'] !== null ? (float) $row['total_amount'] : null,
                'source' => $source,
                'last_synced_at' => self::when($row['last_synced_at'] ?? ($row['updated_at'] ?? null)),
            ];
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function logs(): array
    {
        // Higher limit so detailed per-item import logs remain visible for debugging.
        $rows = (new LogRepository())->recent(5000);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'time' => self::when($row['event_time'] ?? ($row['created_at'] ?? null)),
                'type' => (string) ($row['type'] ?? ''),
                'ref' => (string) ($row['reference_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'detail' => (string) ($row['detail'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'ms' => (int) ($row['duration_ms'] ?? 0),
                'product_map_id' => isset($row['product_map_id']) ? (int) $row['product_map_id'] : null,
                'wc_order_id' => isset($row['wc_order_id']) ? (int) $row['wc_order_id'] : null,
            ];
        }

        return $out;
    }

    /**
     * Daily revenue series from nhanh_sync_stats_daily.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function revenue(): array
    {
        global $wpdb;
        $table = Schema::table('stats_daily');
        $rows = $wpdb->get_results(
            "SELECT stat_date, channel, revenue, order_count FROM {$table} ORDER BY stat_date ASC",
            ARRAY_A
        );

        $out = [];
        foreach ((array) $rows as $row) {
            $out[] = [
                'label' => substr((string) $row['stat_date'], 5),
                'web' => (float) ($row['channel'] === 'web' ? $row['revenue'] : 0),
                'nhanh' => (float) ($row['channel'] === 'nhanh' ? $row['revenue'] : 0),
                'orders' => (int) $row['order_count'],
            ];
        }

        return $out;
    }

    /**
     * Turnover summary aggregated from the product map.
     *
     * @param array<int,array<string,mixed>> $products
     * @return array<int,array<string,mixed>>
     */
    private static function turnover(array $products): array
    {
        $byCategory = [];
        foreach ($products as $p) {
            $cat = $p['category'] !== '' ? $p['category'] : 'Chưa phân loại';
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = ['value' => 0, 'sold' => 0, 'fast' => 0, 'steady' => 0, 'slow' => 0, 'stagnant' => 0, 'count' => 0];
            }
            $byCategory[$cat]['count']++;
            $byCategory[$cat]['value'] += (float) $p['revenue_month'];
            $byCategory[$cat]['sold'] += (int) $p['units_sold_month'];

            $days = $p['turnover_days'];
            if ($days === null || $days <= 0) {
                continue;
            }
            if ($days <= 14) {
                $byCategory[$cat]['fast']++;
            } elseif ($days <= 30) {
                $byCategory[$cat]['steady']++;
            } elseif ($days <= 60) {
                $byCategory[$cat]['slow']++;
            } else {
                $byCategory[$cat]['stagnant']++;
            }
        }

        $out = [];
        foreach ($byCategory as $cat => $b) {
            if ($b['count'] === 0) {
                continue;
            }
            $out[] = [
                'category' => $cat,
                'ratio' => round($b['value'] / max(1, $b['count']), 2),
                'dsi' => 0,
                'value' => $b['value'],
                'sold' => $b['sold'],
                'fast' => (int) round($b['fast'] / $b['count'] * 100),
                'steady' => (int) round($b['steady'] / $b['count'] * 100),
                'slow' => (int) round($b['slow'] / $b['count'] * 100),
                'stagnant' => (int) round($b['stagnant'] / $b['count'] * 100),
            ];
        }

        return $out;
    }

    /**
     * @return array{total:int,linked:int,with_image:int}
     */
    private static function productStats(): array
    {
        return (new ProductMapRepository())->counts();
    }

    /**
     * @return array{total:int,synced:int,pending:int,failed:int}
     */
    private static function orderStats(): array
    {
        return (new OrderMapRepository())->counts();
    }

    /**
     * Resolve a Nhanh term name: prefer the term map, then the stored name.
     *
     * @param array<string,string> $map
     */
    private static function termName(array $map, $id, $fallback): string
    {
        $id = (string) ($id ?? '');
        if ($id !== '' && isset($map[$id]) && $map[$id] !== '') {
            return $map[$id];
        }
        $name = trim((string) ($fallback ?? ''));

        return $name !== '' ? $name : ($id !== '' && $id !== '0' ? '#' . $id : '');
    }

    /**
     * Format a datetime for display; empty string when missing.
     */
    private static function when($value): string
    {
        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return '—';
        }

        $ts = strtotime((string) $value . ' UTC');

        return $ts ? date_i18n('d/m/Y H:i', $ts) : (string) $value;
    }
}