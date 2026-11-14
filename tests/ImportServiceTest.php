<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Service\ImportService;
use Puleeno\NhanhVn\Syncer\Database\JobRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Woo\WooBridge;

class ImportServiceTest extends TestCase
{
    private ImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['__wc_products'] = [];
        $GLOBALS['__next_wc_product_id'] = 1000;
        $GLOBALS['__wp_terms'] = [];
        $GLOBALS['__next_term_id'] = 100;

        $jobs = new JobRepository();
        $productMaps = new ProductMapRepository();
        $termMaps = new TermMapRepository();
        $woo = new WooBridge();
        $this->service = new ImportService($jobs, $productMaps, $termMaps, $woo);
    }

    public function testRunCreatesJob(): void
    {
        $result = $this->service->run(['trigger' => 'test']);
        $this->assertArrayHasKey('job_id', $result);
        $this->assertGreaterThan(0, $result['job_id']);
    }

    public function testRunReturnsStatus(): void
    {
        $result = $this->service->run(['trigger' => 'test']);
        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['success', 'partial', 'failed']);
    }

    public function testRunReturnsCounts(): void
    {
        $result = $this->service->run(['trigger' => 'test']);
        $this->assertArrayHasKey('scanned', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertIsInt($result['scanned']);
        $this->assertIsInt($result['updated']);
        $this->assertIsInt($result['failed']);
    }

    public function testRunReturnsDuration(): void
    {
        $result = $this->service->run(['trigger' => 'test']);
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertIsInt($result['duration_ms']);
    }

    public function testRunHandlesMissingCredentials(): void
    {
        update_option('nhanh_syncer_settings', ['app_id' => '', 'business_id' => '', 'access_token' => '']);
        $result = $this->service->run(['trigger' => 'test']);
        $this->assertFalse($result['ok']);
    }
}