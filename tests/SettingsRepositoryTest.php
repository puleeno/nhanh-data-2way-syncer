<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Repository\SettingsRepository;

class SettingsRepositoryTest extends TestCase
{
    private SettingsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $this->repo = new SettingsRepository();
    }

    public function testAllReturnsEmptyWhenUnset(): void
    {
        $this->assertSame([], $this->repo->all());
    }

    public function testSaveAndRead(): void
    {
        $this->repo->save(['app_id' => '123']);
        $this->assertSame(['app_id' => '123'], $this->repo->all());
    }

    public function testGetWithDefaultsMergesMissingKeys(): void
    {
        $merged = $this->repo->getWithDefaults(['app_id' => '', 'secret_key' => '']);
        $this->assertArrayHasKey('app_id', $merged);
        $this->assertArrayHasKey('secret_key', $merged);
    }

    public function testEnsureDefaultsPersistsWithoutClobbering(): void
    {
        $this->repo->save(['app_id' => 'keep-me']);
        $this->repo->ensureDefaults(['app_id' => '', 'secret_key' => 'x']);

        $all = $this->repo->all();
        $this->assertSame('keep-me', $all['app_id']);
        $this->assertSame('x', $all['secret_key']);
    }
}