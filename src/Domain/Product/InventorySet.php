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

class DepotStock
{
    /** @var string */
    public $id = '';
    /** @var string */
    public $name = '';
    /** @var float */
    public $remain = 0.0;
    /** @var float */
    public $available = 0.0;
    /** @var float */
    public $shipping = 0.0;
    /** @var float */
    public $damaged = 0.0;
    /** @var float */
    public $holding = 0.0;
    /** @var float */
    public $transfering = 0.0;
    /** @var array<string,mixed> */
    public $warranty = [];
    /** @var array<string,mixed> */
    public $raw = [];
}
