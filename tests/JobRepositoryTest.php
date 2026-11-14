<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Database\JobRepository;

class JobRepositoryTest extends TestCase
{
    private JobRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new JobRepository();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
    }

    public function testCreateReturnsJobId(): void
    {
        $id = $this->repo->create('import', ['trigger' => 'cron']);
        $this->assertGreaterThan(0, $id);
    }

    public function testStartMarksJobRunning(): void
    {
        $id = $this->repo->create('import');
        $result = $this->repo->start($id);
        $this->assertTrue($result);
    }

    public function testFinishMarksJobComplete(): void
    {
        $id = $this->repo->create('import');
        $this->repo->start($id);
        $result = $this->repo->finish($id, 'success', 10, 5, 0, ['status' => 'ok']);
        $this->assertTrue($result);
    }

    public function testLogCreatesAuditEntry(): void
    {
        $id = $this->repo->create('import');
        $this->repo->log($id, 'product_sync', 'success', 'Import completed', 'All good', 150);
        $this->assertGreaterThan(0, $id);
    }

    public function testFindReturnsJobRow(): void
    {
        $id = $this->repo->create('import');
        $row = $this->repo->find($id);
        $this->assertIsArray($row);
        $this->assertSame('import', $row['job_type']);
        $this->assertSame('pending', $row['status']);
    }

    public function testRecentReturnsJobs(): void
    {
        $this->repo->create('import');
        $this->repo->create('sync');
        $recent = $this->repo->recent(5);
        $this->assertCount(2, $recent);
    }
}