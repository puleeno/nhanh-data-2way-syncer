<?php

namespace Puleeno\NhanhVn\Syncer\Domain\Product;

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
