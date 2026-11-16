<?php

namespace Puleeno\NhanhVn\Syncer\Sync\Mapper;

use Puleeno\NhanhVn\Syncer\Domain\Product\NhanhProduct;
use Puleeno\NhanhVn\Syncer\Sync\Policy\PricePolicy;
use Puleeno\NhanhVn\Syncer\Sync\Policy\PostStatusPolicy;

/**
 * Builds Woo variable/variation payloads from canonical product children/attributes.
 * Composite mapping: parent attributes + child attribute values => variations.
 */
class VariationMapper
{
    /** @var PricePolicy */
    private $pricePolicy;
    /** @var PostStatusPolicy */
    private $statusPolicy;

    public function __construct(?PricePolicy $pricePolicy = null, ?PostStatusPolicy $statusPolicy = null)
    {
        $this->pricePolicy = $pricePolicy ?: new PricePolicy();
        $this->statusPolicy = $statusPolicy ?: new PostStatusPolicy();
    }

    public function isVariable(NhanhProduct $p): bool
    {
        return !empty($p->children);
    }

    /**
     * @return array<int,array{name:string,options:array<int,string>,visible:bool,variation:bool}>
     */
    public function buildParentAttributes(NhanhProduct $p): array
    {
        $index = []; // name => [options set]
        // from explicit attributes on parent
        foreach ($p->attributes as $attr) {
            if (!is_array($attr)) continue;
            $name = (string) ($attr['name'] ?? $attr['attribute'] ?? $attr['label'] ?? '');
            $value = (string) ($attr['value'] ?? $attr['option'] ?? '');
            if ($name === '') continue;
            if (!isset($index[$name])) $index[$name] = [];
            if ($value !== '') $index[$name][$value] = true;
        }
        // from child attribute values
        foreach ($p->children as $child) {
            foreach ($child->attributes as $attr) {
                if (!is_array($attr)) continue;
                $name = (string) ($attr['name'] ?? $attr['attribute'] ?? $attr['label'] ?? '');
                $value = (string) ($attr['value'] ?? $attr['option'] ?? '');
                if ($name === '') continue;
                if (!isset($index[$name])) $index[$name] = [];
                if ($value !== '') $index[$name][$value] = true;
            }
            // infer from child name if no attributes (single-dimension fallback)
            if (empty($child->attributes) && $child->content->name !== '' && count($p->children) > 1) {
                $name = 'Phân loại';
                if (!isset($index[$name])) $index[$name] = [];
                $index[$name][$child->content->name] = true;
            }
        }
        $out = [];
        foreach ($index as $name => $opts) {
            $out[] = [
                'name' => $name,
                'options' => array_values(array_keys($opts)),
                'visible' => true,
                'variation' => true,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<int,array<string,mixed>> variation payloads for WooBridge
     */
    public function buildVariations(NhanhProduct $parent, array $settings = []): array
    {
        $vars = [];
        foreach ($parent->children as $child) {
            $price = $this->pricePolicy->resolve($child);
            $sku = $child->identity->code !== '' ? $child->identity->code : ($child->identity->id !== '' ? 'NHANH-' . $child->identity->id : '');
            $attrs = $this->childAttributeMap($child, $parent);
            $vars[] = [
                'sku' => $sku,
                'nhanh_id' => $child->identity->id,
                'nhanh_code' => $child->identity->code,
                'nhanh_barcode' => $child->identity->barcode,
                'regular_price' => $price['regular'],
                'sale_price' => $price['sale'],
                'price' => $price['active'],
                'stock' => $child->inventory->totalForSync(),
                'manage_stock' => true,
                'status' => $this->statusPolicy->resolve($child, $settings),
                'attributes' => $attrs,
                'weight' => $child->shipping->weight,
                'length' => $child->shipping->length,
                'width' => $child->shipping->width,
                'height' => $child->shipping->height,
                'description' => $child->content->content !== '' ? $child->content->content : $child->content->description,
                'image' => $child->images->avatar !== '' ? $child->images->avatar : ($child->images->all()[0] ?? ''),
                'meta' => [
                    '_nhanh_product_id' => $child->identity->id,
                    '_nhanh_barcode' => $child->identity->barcode,
                    '_nhanh_type' => $child->identity->type,
                    '_nhanh_units' => wp_json_encode($child->units),
                    '_nhanh_combos' => wp_json_encode($child->combos),
                    '_nhanh_inventory' => wp_json_encode($child->inventory),
                    '_nhanh_raw_snapshot' => wp_json_encode($child->raw),
                ],
                'depots' => $child->inventory->depots,
            ];
        }
        return $vars;
    }

    /** @return array<string,string> */
    private function childAttributeMap(NhanhProduct $child, NhanhProduct $parent): array
    {
        $map = [];
        foreach ($child->attributes as $attr) {
            if (!is_array($attr)) continue;
            $name = (string) ($attr['name'] ?? $attr['attribute'] ?? $attr['label'] ?? '');
            $value = (string) ($attr['value'] ?? $attr['option'] ?? '');
            if ($name !== '' && $value !== '') $map[$name] = $value;
        }
        if (!$map && $child->content->name !== '' && count($parent->children) > 1) {
            $map['Phân loại'] = $child->content->name;
        }
        return $map;
    }
}
