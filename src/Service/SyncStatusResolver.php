<?php

namespace Puleeno\NhanhVn\Syncer\Service;

use Puleeno\NhanhVn\Syncer\Contracts\SyncStatusResolverInterface;

/**
 * Centralizes sync status comparison rules between Nhanh and WooCommerce.
 */
class SyncStatusResolver implements SyncStatusResolverInterface
{
    public function resolve(?int $nhanhPrice, ?int $nhanhStock, ?int $webPrice, ?int $webStock, bool $isLinked): string
    {
        if (!$isLinked || $webPrice === null || $webStock === null) {
            return 'unlinked';
        }
        if ($nhanhPrice !== null && $nhanhPrice !== $webPrice) {
            return 'price_mismatch';
        }
        if ($nhanhStock !== null && $nhanhStock !== $webStock) {
            return 'stock_mismatch';
        }
        return 'synced';
    }
}
