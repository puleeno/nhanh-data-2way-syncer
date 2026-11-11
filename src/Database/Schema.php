<?php

namespace Puleeno\NhanhVn\Syncer\Database;

class Schema
{
    public const DB_VERSION_OPTION = 'nhanh_syncer_db_version';

    public static function maybe_upgrade(): void
    {
        $installed = get_option(self::DB_VERSION_OPTION);
        if ($installed === NHANH_SYNCER_DB_VERSION) {
            return;
        }
        self::install();
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        $tables = [];

        // 1. Product mapping (core entity, links to WC products/variations)
        $tables[] = "CREATE TABLE {$p}nhanh_sync_product_map (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            nhanh_id varchar(64) DEFAULT NULL,
            nhanh_code varchar(191) NOT NULL,
            nhanh_barcode varchar(64) DEFAULT NULL,
            nhanh_name varchar(255) DEFAULT NULL,
            nhanh_category_id varchar(64) DEFAULT NULL,
            nhanh_category_name varchar(191) DEFAULT NULL,
            nhanh_brand_id varchar(64) DEFAULT NULL,
            nhanh_brand_name varchar(191) DEFAULT NULL,
            nhanh_price decimal(18,2) DEFAULT NULL,
            nhanh_original_price decimal(18,2) DEFAULT NULL,
            nhanh_stock_total int(11) NOT NULL DEFAULT 0,
            wc_product_id bigint(20) unsigned DEFAULT NULL,
            wc_variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            wc_sku varchar(191) DEFAULT NULL,
            sync_status varchar(32) NOT NULL DEFAULT 'unlinked',
            sell_velocity varchar(32) DEFAULT NULL,
            turnover_days smallint(5) unsigned DEFAULT NULL,
            units_sold_month int(10) unsigned NOT NULL DEFAULT 0,
            revenue_month decimal(18,2) NOT NULL DEFAULT 0.00,
            image_url text DEFAULT NULL,
            tags_json text DEFAULT NULL,
            last_compared_at datetime DEFAULT NULL,
            last_synced_at datetime DEFAULT NULL,
            last_error varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_nhanh_code (nhanh_code),
            KEY idx_nhanh_id (nhanh_id),
            KEY idx_nhanh_barcode (nhanh_barcode),
            KEY idx_wc_product (wc_product_id, wc_variation_id),
            KEY idx_sync_status (sync_status),
            KEY idx_nhanh_category (nhanh_category_id),
            KEY idx_nhanh_brand (nhanh_brand_id),
            KEY idx_updated_at (updated_at)
        ) $charset_collate;";

        // 2. Per-warehouse stock (Nhanh multi-warehouse)
        $tables[] = "CREATE TABLE {$p}nhanh_sync_product_warehouses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_map_id bigint(20) unsigned NOT NULL,
            warehouse_id varchar(64) NOT NULL,
            warehouse_name varchar(191) DEFAULT NULL,
            stock int(11) NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_product_warehouse (product_map_id, warehouse_id),
            KEY idx_product_map (product_map_id),
            KEY idx_warehouse (warehouse_id)
        ) $charset_collate;";

        // 3. Taxonomy mapping for product categories and brands (product_cat, product_brand)
        $tables[] = "CREATE TABLE {$p}nhanh_sync_term_map (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            nhanh_term_type varchar(20) NOT NULL,
            nhanh_term_id varchar(64) NOT NULL,
            nhanh_term_name varchar(191) DEFAULT NULL,
            nhanh_parent_id varchar(64) DEFAULT NULL,
            wc_taxonomy varchar(32) NOT NULL,
            wc_term_id bigint(20) unsigned DEFAULT NULL,
            wc_term_name varchar(191) DEFAULT NULL,
            product_count int(10) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'unmapped',
            auto_create_new tinyint(1) NOT NULL DEFAULT 0,
            last_synced_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_nhanh_wc_term (nhanh_term_type, nhanh_term_id, wc_taxonomy),
            KEY idx_wc_term (wc_taxonomy, wc_term_id),
            KEY idx_status (status),
            KEY idx_nhanh_type_parent (nhanh_term_type, nhanh_parent_id)
        ) $charset_collate;";

        // 4. Product <-> taxonomy term relations (N-N, multiple categories/brands per product)
        $tables[] = "CREATE TABLE {$p}nhanh_sync_product_terms (
            product_map_id bigint(20) unsigned NOT NULL,
            term_map_id bigint(20) unsigned NOT NULL,
            relation varchar(20) NOT NULL,
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (product_map_id, term_map_id),
            KEY idx_term_map (term_map_id),
            KEY idx_product_relation (product_map_id, relation)
        ) $charset_collate;";

        // 5. Sync/audit logs
        $tables[] = "CREATE TABLE {$p}nhanh_sync_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_time datetime NOT NULL,
            direction varchar(20) NOT NULL DEFAULT 'system',
            type varchar(32) NOT NULL,
            status varchar(20) NOT NULL,
            reference_code varchar(191) DEFAULT NULL,
            product_map_id bigint(20) unsigned DEFAULT NULL,
            wc_order_id bigint(20) unsigned DEFAULT NULL,
            nhanh_order_id varchar(64) DEFAULT NULL,
            title varchar(255) DEFAULT NULL,
            detail text DEFAULT NULL,
            duration_ms int(10) unsigned DEFAULT NULL,
            payload_json longtext DEFAULT NULL,
            response_json longtext DEFAULT NULL,
            source varchar(32) DEFAULT NULL,
            dedupe_key varchar(191) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_dedupe_key (dedupe_key),
            KEY idx_event_time (event_time),
            KEY idx_type_status (type, status),
            KEY idx_reference (reference_code),
            KEY idx_product_map (product_map_id),
            KEY idx_wc_order (wc_order_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // 6. Order mapping (WC <-> Nhanh, HPOS-aware by wc_order_id)
        $tables[] = "CREATE TABLE {$p}nhanh_sync_order_map (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wc_order_id bigint(20) unsigned NOT NULL,
            nhanh_order_id varchar(64) NOT NULL,
            nhanh_order_code varchar(64) DEFAULT NULL,
            sync_status varchar(32) NOT NULL DEFAULT 'created',
            total_amount decimal(18,2) DEFAULT NULL,
            last_synced_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_wc_order (wc_order_id),
            UNIQUE KEY uniq_nhanh_order (nhanh_order_id),
            KEY idx_sync_status (sync_status)
        ) $charset_collate;";

        // 7. Sync jobs (batch/cron/manual tracking)
        $tables[] = "CREATE TABLE {$p}nhanh_sync_jobs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(32) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            started_at datetime DEFAULT NULL,
            finished_at datetime DEFAULT NULL,
            scanned int(10) unsigned NOT NULL DEFAULT 0,
            updated_count int(10) unsigned NOT NULL DEFAULT 0,
            failed_count int(10) unsigned NOT NULL DEFAULT 0,
            summary_json longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_type_status (job_type, status),
            KEY idx_started_at (started_at)
        ) $charset_collate;";

        // 8. Daily stats for reports
        $tables[] = "CREATE TABLE {$p}nhanh_sync_stats_daily (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            stat_date date NOT NULL,
            channel varchar(20) NOT NULL,
            revenue decimal(18,2) NOT NULL DEFAULT 0.00,
            order_count int(10) unsigned NOT NULL DEFAULT 0,
            units_sold int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_date_channel (stat_date, channel),
            KEY idx_stat_date (stat_date)
        ) $charset_collate;";

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        update_option(self::DB_VERSION_OPTION, NHANH_SYNCER_DB_VERSION);
    }

    public static function table(string $name): string
    {
        global $wpdb;
        $map = [
            'product_map' => $wpdb->prefix . 'nhanh_sync_product_map',
            'product_warehouses' => $wpdb->prefix . 'nhanh_sync_product_warehouses',
            'term_map' => $wpdb->prefix . 'nhanh_sync_term_map',
            'product_terms' => $wpdb->prefix . 'nhanh_sync_product_terms',
            'logs' => $wpdb->prefix . 'nhanh_sync_logs',
            'order_map' => $wpdb->prefix . 'nhanh_sync_order_map',
            'jobs' => $wpdb->prefix . 'nhanh_sync_jobs',
            'stats_daily' => $wpdb->prefix . 'nhanh_sync_stats_daily',
        ];
        return $map[$name] ?? ($wpdb->prefix . 'nhanh_sync_' . $name);
    }
}
