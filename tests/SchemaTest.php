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
        $GLOBALS['__dbdelta_queries'] = [];
    }

    public function testInstallRunsDbDeltaForEveryTable(): void
    {
        Schema::install();

        $this->assertNotEmpty($GLOBALS['__dbdelta_queries']);
        $this->assertCount(8, $GLOBALS['__dbdelta_queries']);
        $this->assertSame(NHANH_SYNCER_DB_VERSION, get_option(Schema::DB_VERSION_OPTION));
    }

    public function testInstallCreatesExpectedTables(): void
    {
        Schema::install();

        $sql = implode("\n", $GLOBALS['__dbdelta_queries']);
        $expected = [
            'nhanh_sync_product_map',
            'nhanh_sync_product_warehouses',
            'nhanh_sync_term_map',
            'nhanh_sync_product_terms',
            'nhanh_sync_logs',
            'nhanh_sync_order_map',
            'nhanh_sync_jobs',
            'nhanh_sync_stats_daily',
        ];
        foreach ($expected as $table) {
            $this->assertStringContainsString($table, $sql, "Schema should create {$table}");
        }
    }

    public function testMaybeUpgradeSkipsWhenVersionMatches(): void
    {
        update_option(Schema::DB_VERSION_OPTION, NHANH_SYNCER_DB_VERSION);

        $result = Schema::maybe_upgrade();

        $this->assertNull($result);
        $this->assertEmpty($GLOBALS['__dbdelta_queries']);
    }

    public function testMaybeUpgradeInstallsWhenVersionMismatch(): void
    {
        update_option(Schema::DB_VERSION_OPTION, '0.9.0');

        Schema::maybe_upgrade();

        $this->assertNotEmpty($GLOBALS['__dbdelta_queries']);
        $this->assertSame(NHANH_SYNCER_DB_VERSION, get_option(Schema::DB_VERSION_OPTION));
    }

    public function testTableReturnsPrefixedName(): void
    {
        $this->assertSame('wp_nhanh_sync_product_map', Schema::table('product_map'));
        $this->assertSame('wp_nhanh_sync_term_map', Schema::table('term_map'));
        $this->assertSame('wp_nhanh_sync_jobs', Schema::table('jobs'));
        $this->assertSame('wp_nhanh_sync_logs', Schema::table('logs'));
    }
}