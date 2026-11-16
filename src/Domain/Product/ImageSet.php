<?php

namespace Puleeno\NhanhVn\Syncer\Domain\Product;

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
