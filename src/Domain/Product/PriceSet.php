<?php

namespace Puleeno\NhanhVn\Syncer\Domain\Product;

class PriceSet
{
    /** @var float|null */
    public $retail = null;
    /** @var float|null */
    public $old = null;
    /** @var float|null */
    public $wholesale = null;
    /** @var float|null */
    public $import = null;
    /** @var float|null */
    public $avgCost = null;
    /** @var float|null */
    public $retailVat = null;
    /** @var float|null */
    public $importVat = null;
    /** @var mixed */
    public $vat = null;
    /** @var mixed */
    public $priceVatMode = null;
    /** @var mixed */
    public $importPriceVatMode = null;
    /** @var array<int,mixed> */
    public $branchPrices = [];
}
