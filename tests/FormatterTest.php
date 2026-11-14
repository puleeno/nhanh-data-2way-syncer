<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Support\Formatter;

class FormatterTest extends TestCase
{
    public function testVndFormatsNumber(): void
    {
        $this->assertSame('100.000 ₫', Formatter::vnd(100000));
        $this->assertSame('1.000.000 ₫', Formatter::vnd(1000000));
    }

    public function testVndReturnsDashForNull(): void
    {
        $this->assertSame('—', Formatter::vnd(null));
    }

    public function testVndReturnsDashForEmpty(): void
    {
        $this->assertSame('—', Formatter::vnd(''));
    }

    public function testVndCompactFormatsSmall(): void
    {
        $this->assertSame('100 k ₫', Formatter::vndCompact(100000));
    }

    public function testVndCompactFormatsK(): void
    {
        $this->assertSame('100 k ₫', Formatter::vndCompact(100000));
    }

    public function testVndCompactFormatsM(): void
    {
        $this->assertSame('1 tr ₫', Formatter::vndCompact(1000000));
    }

    public function testVndCompactFormatsB(): void
    {
        $this->assertSame('1 tỷ ₫', Formatter::vndCompact(1000000000));
    }

    public function testStatusBadgeReturnsCorrectMapping(): void
    {
        $badge = Formatter::statusBadge('synced');
        $this->assertSame(['ok', 'Đã khớp & Đồng bộ'], $badge);
    }

    public function testStatusBadgeReturnsDefaultForUnknown(): void
    {
        $badge = Formatter::statusBadge('unknown');
        $this->assertSame(['neutral', 'unknown'], $badge);
    }

    public function testVelocityBadgeReturnsCorrectMapping(): void
    {
        $badge = Formatter::velocityBadge('fast');
        $this->assertSame(['ok', 'Luân chuyển nhanh'], $badge);
    }

    public function testLogTypeLabelReturnsCorrectMapping(): void
    {
        $this->assertSame('Tồn kho', Formatter::logTypeLabel('stock_update'));
        $this->assertSame('Giá bán', Formatter::logTypeLabel('price_update'));
    }

    public function testLogTypeLabelReturnsDefaultForUnknown(): void
    {
        $this->assertSame('unknown', Formatter::logTypeLabel('unknown'));
    }
}