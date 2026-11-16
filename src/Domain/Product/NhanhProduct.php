<?php

namespace Puleeno\NhanhVn\Syncer\Domain\Product;

class NhanhProduct
{
    /** @var ProductIdentity */
    public $identity;
    /** @var ProductContent */
    public $content;
    /** @var CategoryRef|null */
    public $category = null;
    /** @var CategoryRef|null */
    public $internalCategory = null;
    /** @var BrandRef|null */
    public $brand = null;
    /** @var PriceSet */
    public $prices;
    /** @var InventorySet */
    public $inventory;
    /** @var ImageSet */
    public $images;
    /** @var ShippingInfo */
    public $shipping;
    /** @var WarrantyInfo */
    public $warranty;
    /** @var array<int,array<string,mixed>> */
    public $attributes = [];
    /** @var array<int,NhanhProduct> */
    public $children = [];
    /** @var array<int,array<string,mixed>> */
    public $units = [];
    /** @var array<int,array<string,mixed>> */
    public $combos = [];
    /** @var array<int,TagRef> */
    public $tags = [];
    /** @var array<int|string,mixed> */
    public $suppliers = [];
    /** @var array<string,mixed> */
    public $raw = [];

    public function __construct()
    {
        $this->identity = new ProductIdentity();
        $this->content = new ProductContent();
        $this->prices = new PriceSet();
        $this->inventory = new InventorySet();
        $this->images = new ImageSet();
        $this->shipping = new ShippingInfo();
        $this->warranty = new WarrantyInfo();
    }
}
