<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Service\SyncStatusResolver;

class SyncStatusResolverTest extends TestCase
{
    public function testResolveReturnsSyncedWhenAllMatch(): void
    {
        $resolver = new SyncStatusResolver();
        $status = $resolver->resolve(100000, 50, 100000, 50, true);
        $this->assertSame('synced', $status);
    }

    public function testResolveReturnsUnlinkedWhenNotLinked(): void
    {
        $resolver = new SyncStatusResolver();
        $status = $resolver->resolve(100000, 50, 100000, 50, false);
        $this->assertSame('unlinked', $status);
    }

    public function testResolveReturnsUnlinkedWhenWebPriceNull(): void
    {
        $resolver = new SyncStatusResolver();
        $status = $resolver->resolve(100000, 50, null, 50, true);
        $this->assertSame('unlinked', $status);
    }

    public function testResolveReturnsUnlinkedWhenWebStockNull(): void
    {
        $resolver = new SyncStatusResolver();
        $status = $resolver->resolve(100000, 50, 100000, null, true);
        $this->assertSame('unlinked', $status);
    }

    public function testResolveReturnsPriceMismatch(): void
    {
        $resolver = new SyncStatusResolver();
        $status = $resolver->resolve(100000, 50, 90000, 50, true);
        $this->assertSame('price_mismatch', $status);
    }

    public function testResolveReturnsStockMismatch(): void
    {
        $resolver = new SyncStatusResolver();
        $status = $resolver->resolve(100000, 50, 100000, 40, true);
        $this->assertSame('stock_mismatch', $status);
    }

    public function testResolveReturnsPriceMismatchBeforeStockMismatch(): void
    {
        $resolver = new SyncStatusResolver();
        $status = $resolver->resolve(100000, 50, 90000, 40, true);
        $this->assertSame('price_mismatch', $status);
    }
}