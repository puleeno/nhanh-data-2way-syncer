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
