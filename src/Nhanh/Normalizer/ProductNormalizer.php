<?php

namespace Puleeno\NhanhVn\Syncer\Nhanh\Normalizer;

use Puleeno\NhanhVn\Syncer\Domain\Product\BrandRef;
use Puleeno\NhanhVn\Syncer\Domain\Product\CategoryRef;
use Puleeno\NhanhVn\Syncer\Domain\Product\DepotStock;
use Puleeno\NhanhVn\Syncer\Domain\Product\NhanhProduct;
use Puleeno\NhanhVn\Syncer\Domain\Product\TagRef;

class ProductNormalizer
{
    /**
     * @param mixed $source entity object or array (list/detail/webhook/inventory item)
     */
    public function normalize($source, string $shape = 'auto'): NhanhProduct
    {
        $raw = $this->toArray($source);
        $p = new NhanhProduct();
        $p->raw = $raw;

        $this->fillIdentity($p, $raw);
        $this->fillContent($p, $raw);
        $this->fillRefs($p, $raw);
        $this->fillPrices($p, $raw);
        $this->fillInventory($p, $raw);
        $this->fillImages($p, $raw);
        $this->fillShipping($p, $raw);
        $this->fillWarranty($p, $raw);
        $this->fillCollections($p, $raw);

        return $p;
    }

    /** @return array<string,mixed> */
    private function toArray($source): array
    {
        if (is_array($source)) return $source;
        if (is_object($source)) {
            if (method_exists($source, 'toArray')) {
                $a = $source->toArray();
                if (is_array($a)) return $a;
            }
            if (method_exists($source, 'getAttributes')) {
                $a = $source->getAttributes();
                if (is_array($a)) return $a;
            }
        }
        return [];
    }

    private function val(array $raw, array $keys, $default = null)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $raw) && $raw[$k] !== null && $raw[$k] !== '') {
                return $raw[$k];
            }
        }
        return $default;
    }

    private function fillIdentity(NhanhProduct $p, array $raw): void
    {
        $id = $this->val($raw, ['id', 'idNhanh', 'nhanh_id']);
        $p->identity->id = $id !== null ? (string) $id : '';
        $parent = $this->val($raw, ['parentId', 'parent_id']);
        $p->identity->parentId = $parent !== null ? (string) $parent : '';
        $p->identity->code = (string) ($this->val($raw, ['code', 'sku']) ?? '');
        $p->identity->barcode = (string) ($this->val($raw, ['barcode']) ?? '');
        $type = $this->val($raw, ['type', 'productType']);
        $p->identity->type = is_array($type) ? (string) ($type['id'] ?? '') : ($type !== null ? (string) $type : '');
        $p->identity->status = $this->val($raw, ['status']);
        $ua = $this->val($raw, ['updatedAt', 'updated_at']);
        $ca = $this->val($raw, ['createdAt', 'created_at']);
        $p->identity->updatedAt = is_numeric($ua) ? (int) $ua : null;
        $p->identity->createdAt = is_numeric($ca) ? (int) $ca : null;
    }

    private function fillContent(NhanhProduct $p, array $raw): void
    {
        $p->content->name = (string) ($this->val($raw, ['name']) ?? '');
        $p->content->otherName = (string) ($this->val($raw, ['otherName', 'other_name']) ?? '');
        $p->content->description = (string) ($this->val($raw, ['description']) ?? '');
        $p->content->content = (string) ($this->val($raw, ['content']) ?? '');
        $videos = $this->val($raw, ['videos'], []);
        $p->content->videos = is_array($videos) ? $videos : [];
    }

    private function fillRefs(NhanhProduct $p, array $raw): void
    {
        // category: list has categoryId/categoryName, detail/webhook has category{id,name,code}
        $cat = $this->val($raw, ['category']);
        if (is_array($cat)) {
            $ref = new CategoryRef();
            $ref->id = isset($cat['id']) ? (string) $cat['id'] : '';
            $ref->code = isset($cat['code']) ? (string) $cat['code'] : '';
            $ref->name = isset($cat['name']) ? (string) $cat['name'] : '';
            $p->category = $ref;
        } else {
            $cid = $this->val($raw, ['categoryId', 'category_id']);
            $cname = $this->val($raw, ['categoryName', 'category_name']);
            if ($cid !== null || $cname !== null) {
                $ref = new CategoryRef();
                $ref->id = $cid !== null ? (string) $cid : '';
                $ref->name = $cname !== null ? (string) $cname : '';
                $p->category = $ref;
            }
        }

        $icat = $this->val($raw, ['internalCategory', 'internal_category']);
        if (is_array($icat)) {
            $ref = new CategoryRef();
            $ref->id = isset($icat['id']) ? (string) $icat['id'] : '';
            $ref->code = isset($icat['code']) ? (string) $icat['code'] : '';
            $ref->name = isset($icat['name']) ? (string) $icat['name'] : '';
            $p->internalCategory = $ref;
        }

        $brand = $this->val($raw, ['brand']);
        if (is_array($brand)) {
            $ref = new BrandRef();
            $ref->id = isset($brand['id']) ? (string) $brand['id'] : '';
            $ref->code = isset($brand['code']) ? (string) $brand['code'] : '';
            $ref->name = isset($brand['name']) ? (string) $brand['name'] : '';
            $p->brand = $ref;
        } else {
            $bid = $this->val($raw, ['brandId', 'brand_id']);
            $bname = $this->val($raw, ['brandName', 'brand_name']);
            if ($bid !== null || $bname !== null) {
                $ref = new BrandRef();
                $ref->id = $bid !== null ? (string) $bid : '';
                $ref->name = $bname !== null ? (string) $bname : '';
                $p->brand = $ref;
            }
        }

        $tags = $this->val($raw, ['tagIds', 'tag_ids'], []);
        if (is_array($tags)) {
            foreach ($tags as $t) {
                $tr = new TagRef();
                $tr->id = is_array($t) ? (string) ($t['id'] ?? '') : (string) $t;
                $tr->name = is_array($t) ? (string) ($t['name'] ?? '') : '';
                if ($tr->id !== '') $p->tags[] = $tr;
            }
        }
    }

    private function fillPrices(NhanhProduct $p, array $raw): void
    {
        $prices = $this->val($raw, ['prices']);
        $map = is_array($prices) ? $prices : [];
        $retail = $map['retail'] ?? $this->val($raw, ['price', 'retailPrice']);
        $p->prices->retail = is_numeric($retail) ? (float) $retail : null;
        foreach (['old' => ['old', 'oldPrice'], 'wholesale' => ['wholesale', 'wholesalePrice'], 'import' => ['import', 'importPrice'], 'avgCost' => ['avgCost'], 'retailVat' => ['retailVat'], 'importVat' => ['importVat']] as $prop => $keys) {
            $v = $map[$keys[0]] ?? $this->val($raw, array_slice($keys, 1), null);
            $p->prices->$prop = is_numeric($v) ? (float) $v : null;
        }
        $p->prices->vat = $map['vat'] ?? $this->val($raw, ['vat']);
        $p->prices->priceVatMode = $map['priceVatMode'] ?? $this->val($raw, ['priceVatMode']);
        $p->prices->importPriceVatMode = $map['importPriceVatMode'] ?? $this->val($raw, ['importPriceVatMode']);
        $bp = $this->val($raw, ['branchPrices', 'branch_prices'], []);
        $p->prices->branchPrices = is_array($bp) ? $bp : [];
    }

    private function fillInventory(NhanhProduct $p, array $raw): void
    {
        $inv = $this->val($raw, ['inventory'], []);
        if (!is_array($inv)) $inv = [];
        foreach (['remain', 'available', 'shipping', 'damaged', 'holding', 'transfering'] as $k) {
            if (isset($inv[$k]) && is_numeric($inv[$k])) $p->inventory->$k = (float) $inv[$k];
        }
        // flat available/remain on some shapes
        if ($p->inventory->available === null) {
            $a = $this->val($raw, ['available']);
            if (is_numeric($a)) $p->inventory->available = (float) $a;
        }
        if ($p->inventory->remain === null) {
            $r = $this->val($raw, ['remain', 'totalQuantity']);
            if (is_numeric($r)) $p->inventory->remain = (float) $r;
        }
        $w = $inv['warranty'] ?? $this->val($raw, ['warrantyInventory'], []);
        $p->inventory->warranty = is_array($w) ? $w : [];
        $depots = $inv['depots'] ?? $this->val($raw, ['depots'], []);
        if (is_array($depots)) {
            foreach ($depots as $d) {
                if (!is_array($d)) continue;
                $ds = new DepotStock();
                $ds->id = isset($d['id']) ? (string) $d['id'] : (isset($d['depotId']) ? (string) $d['depotId'] : '');
                $ds->name = isset($d['name']) ? (string) $d['name'] : '';
                foreach (['remain', 'available', 'shipping', 'damaged', 'holding', 'transfering'] as $k) {
                    if (isset($d[$k]) && is_numeric($d[$k])) $ds->$k = (float) $d[$k];
                }
                $dw = $d['warranty'] ?? [];
                $ds->warranty = is_array($dw) ? $dw : [];
                $ds->raw = $d;
                $p->inventory->depots[] = $ds;
            }
        }
    }

    private function fillImages(NhanhProduct $p, array $raw): void
    {
        $images = $this->val($raw, ['images']);
        $list = [];
        if (is_array($images)) {
            if (isset($images['avatar']) && is_string($images['avatar']) && $images['avatar'] !== '') {
                $p->images->avatar = $images['avatar'];
                $list[] = $images['avatar'];
            }
            if (isset($images['others']) && is_array($images['others'])) {
                foreach ($images['others'] as $u) {
                    if (is_string($u) && $u !== '' && !in_array($u, $list, true)) $list[] = $u;
                }
            }
            foreach ($images as $img) {
                if (is_array($img) && isset($img['src']) && is_string($img['src']) && $img['src'] !== '' && !in_array($img['src'], $list, true)) {
                    $list[] = $img['src'];
                }
            }
        }
        $single = $this->val($raw, ['image']);
        if (is_string($single) && $single !== '' && !in_array($single, $list, true)) {
            array_unshift($list, $single);
            if ($p->images->avatar === '') $p->images->avatar = $single;
        }
        $p->images->others = array_values(array_filter($list, function ($u) use ($p) { return $u !== $p->images->avatar; }));
    }

    private function fillShipping(NhanhProduct $p, array $raw): void
    {
        $s = $this->val($raw, ['shipping'], []);
        if (!is_array($s)) $s = [];
        foreach (['weight' => ['weight', 'shippingWeight'], 'length' => ['length'], 'width' => ['width'], 'height' => ['height']] as $prop => $keys) {
            $v = $s[$keys[0]] ?? $this->val($raw, array_slice($keys, 1), null);
            $p->shipping->$prop = is_numeric($v) ? (float) $v : null;
        }
    }

    private function fillWarranty(NhanhProduct $p, array $raw): void
    {
        $w = $this->val($raw, ['warranty'], []);
        if (!is_array($w)) { $p->warranty = new \Puleeno\NhanhVn\Syncer\Domain\Product\WarrantyInfo(); return; }
        $m = $w['month'] ?? $w['months'] ?? null;
        $p->warranty->month = is_numeric($m) ? (int) $m : null;
        $p->warranty->phone = isset($w['phone']) ? (string) $w['phone'] : '';
        $p->warranty->address = isset($w['address']) ? (string) $w['address'] : '';
    }

    private function fillCollections(NhanhProduct $p, array $raw): void
    {
        $attrs = $this->val($raw, ['attributes'], []);
        $p->attributes = is_array($attrs) ? array_values($attrs) : [];
        $units = $this->val($raw, ['units'], []);
        // detail: units{name, list[]}; normalize to list
        if (is_array($units) && isset($units['list']) && is_array($units['list'])) {
            $p->units = $units['list'];
        } else {
            $p->units = is_array($units) ? $units : [];
        }
        $combos = $this->val($raw, ['combos'], []);
        $p->combos = is_array($combos) ? array_values($combos) : [];
        $sup = $this->val($raw, ['suppliers'], []);
        $p->suppliers = is_array($sup) ? $sup : [];
        $childs = $this->val($raw, ['childs', 'children'], []);
        if (is_array($childs)) {
            foreach ($childs as $c) {
                if (is_array($c) || is_object($c)) $p->children[] = $this->normalize($c);
            }
        }
    }
}
