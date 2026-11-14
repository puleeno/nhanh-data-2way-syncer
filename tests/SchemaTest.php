<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Database\Schema;

class SchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
    }

    public function testInstallCreatesAllTables(): void
    {
        Schema::install();

        $wpdb = $GLOBALS['wpdb'];
        $tables = ['nhanh_sync_product_map', 'nhanh_sync_product_warehouses', 'nhanh_sync_term_map', 'nhanh_sync_product_terms', 'nhanh_sync_logs', 'nhanh_sync_order_map', 'nhanh_sync_jobs', 'nhanh_sync_stats_daily'];

        foreach ($tables as $table) {
            $this->assertArrayHasKey('wp_' . $table, $wpdb->tables, "Table wp_{$table} should exist");
        }
    }

    public function testMaybeUpgradeSkipsWhenVersionMatches(): void
    {
        update_option('nhanh_syncer_db_version', '1.0.0');

        $this->assertTrue(Schema::maybe_upgrade());
    }

    public function testMaybeUpgradeInstallsWhenVersionMismatch(): void
    {
        update_option('nhanh_syncer_db_version', '0.9.0');

        $this->assertTrue(Schema::maybe_upgrade());
    }

    public function testTableReturnsCorrectPrefix(): void
    {
        $this->assertSame('wp_nhanh_sync_product_map', Schema::table('product_map'));
        $this->assertSame('wp_nhanh_sync_term_map', Schema::table('term_map'));
    }
}