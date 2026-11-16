<?php

namespace Puleeno\NhanhVn\Syncer\Domain\Product;

class ProductIdentity
{
    /** @var string */
    public $id = '';
    /** @var string */
    public $parentId = '';
    /** @var string */
    public $code = '';
    /** @var string */
    public $barcode = '';
    /** @var string */
    public $type = '';
    /** @var mixed */
    public $status = null;
    /** @var int|null */
    public $updatedAt = null;
    /** @var int|null */
    public $createdAt = null;

    public function key(): string
    {
        if ($this->code !== '') return $this->code;
        if ($this->id !== '') return 'NHANH-' . $this->id;
        return '';
    }
}
