<?php

namespace Puleeno\NhanhVn\Syncer\Support;

/**
 * Pure formatting/value helpers for UI and domain display.
 * Kept free of WP hooks to stay easily testable.
 */
class Formatter
{
    public static function vnd($amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }
        return number_format((float) $amount, 0, ',', '.') . ' ₫';
    }

    public static function vndCompact($amount): string
    {
        $n = (float) $amount;
        $abs = abs($n);
        if ($abs >= 1000000000) {
            return self::trimFloat($n / 1000000000, 2) . ' tỷ ₫';
        }
        if ($abs >= 1000000) {
            return self::trimFloat($n / 1000000, 1) . ' tr ₫';
        }
        if ($abs >= 1000) {
            return self::trimFloat($n / 1000, 0) . ' k ₫';
        }
        return self::vnd($n);
    }

    public static function statusBadge(string $status): array
    {
        $map = [
            'synced' => ['ok', 'Đã khớp & Đồng bộ'],
            'price_mismatch' => ['warn', 'Lệch giá bán'],
            'stock_mismatch' => ['warn', 'Lệch tồn kho'],
            'unlinked' => ['bad', 'Chưa liên kết'],
            'syncing' => ['neutral', 'Đang đồng bộ'],
        ];
        return $map[$status] ?? ['neutral', $status];
    }

    public static function velocityBadge(string $velocity): array
    {
        $map = [
            'fast' => ['ok', 'Luân chuyển nhanh'],
            'steady' => ['neutral', 'Bán đều'],
            'slow' => ['warn', 'Chậm luân chuyển'],
            'stagnant' => ['bad', 'Tồn đọng vốn'],
        ];
        return $map[$velocity] ?? ['neutral', $velocity];
    }

    public static function logTypeLabel(string $type): string
    {
        $map = [
            'stock_update' => 'Tồn kho',
            'price_update' => 'Giá bán',
            'product_sync' => 'Sản phẩm',
            'category_sync' => 'Danh mục/Thương hiệu',
            'webhook_order' => 'Đơn hàng',
            'manual_batch' => 'Quét Batch',
        ];
        return $map[$type] ?? $type;
    }

    private static function trimFloat(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, '.', '');
        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }
        return str_replace('.', ',', $formatted);
    }
}
