<?php

namespace Puleeno\NhanhVn\Syncer\Domain\Product;

class InventorySet
{
    /** @var float|null */
    public $remain = null;
    /** @var float|null */
    public $available = null;
    /** @var float|null */
    public $shipping = null;
    /** @var float|null */
    public $damaged = null;
    /** @var float|null */
    public $holding = null;
    /** @var float|null */
    public $transfering = null;
    /** @var array<string,mixed> */
    public $warranty = [];
    /** @var array<int,DepotStock> */
    public $depots = [];

    public function totalForSync(): int
    {
        if ($this->available !== null) return (int) $this->available;
        if ($this->remain !== null) return (int) $this->remain;
        return 0;
    }
}
