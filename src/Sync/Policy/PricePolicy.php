<?php

namespace Puleeno\NhanhVn\Syncer\Sync\Policy;

use Puleeno\NhanhVn\Syncer\Domain\Product\NhanhProduct;

class PricePolicy
{
    /** @return array{regular:string,sale:string,active:string} */
    public function resolve(NhanhProduct $p): array
    {
        $retail = $p->prices->retail;
        $old = $p->prices->old;
        $regular = $retail !== null ? $retail : 0.0;
        $sale = '';
        // if old price exists and is higher than retail, treat retail as sale
        if ($old !== null && $retail !== null && $old > $retail) {
            $regular = $old;
            $sale = (string) $retail;
        }
        $active = $sale !== '' ? $sale : (string) $regular;
        return [
            'regular' => (string) $regular,
            'sale' => $sale,
            'active' => $active,
        ];
    }
}
