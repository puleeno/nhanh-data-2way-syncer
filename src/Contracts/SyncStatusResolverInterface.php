<?php

namespace Puleeno\NhanhVn\Syncer\Contracts;

interface SyncStatusResolverInterface
{
    /**
     * Resolve mapping/sync status from Nhanh and WooCommerce snapshots.
     */
    public function resolve(?int $nhanhPrice, ?int $nhanhStock, ?int $webPrice, ?int $webStock, bool $isLinked): string;
}
