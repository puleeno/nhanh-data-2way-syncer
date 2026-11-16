<?php

namespace Puleeno\NhanhVn\Syncer\Domain\Product;

class ProductContent
{
    /** @var string */
    public $name = '';
    /** @var string */
    public $otherName = '';
    /** @var string */
    public $description = '';
    /** @var string */
    public $content = '';
    /** @var array<int,mixed> */
    public $videos = [];
}

class ImageSet
{
    /** @var string */
    public $avatar = '';
    /** @var array<int,string> */
    public $others = [];

    /** @return array<int,string> */
    public function all(): array
    {
        $out = [];
        if ($this->avatar !== '') $out[] = $this->avatar;
        foreach ($this->others as $u) {
            $u = is_string($u) ? $u : '';
            if ($u !== '' && !in_array($u, $out, true)) $out[] = $u;
        }
        return $out;
    }
}

class ShippingInfo
{
    /** @var float|null */
    public $weight = null;
    /** @var float|null */
    public $length = null;
    /** @var float|null */
    public $width = null;
    /** @var float|null */
    public $height = null;
}

class WarrantyInfo
{
    /** @var int|null */
    public $month = null;
    /** @var string */
    public $phone = '';
    /** @var string */
    public $address = '';
}
