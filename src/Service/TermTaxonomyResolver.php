<?php

namespace Puleeno\NhanhVn\Syncer\Service;

/**
 * Resolves WooCommerce taxonomy names for Nhanh term types.
 * Categories -> product_cat, Brands -> configurable (default product_brand).
 */
class TermTaxonomyResolver
{
    /** @var string */
    private $brandTaxonomy;

    public function __construct(string $brandTaxonomy = 'product_brand')
    {
        $brandTaxonomy = trim($brandTaxonomy);
        $this->brandTaxonomy = $brandTaxonomy !== '' ? $brandTaxonomy : 'product_brand';
    }

    public function forType(string $nhanhTermType): string
    {
        if ($nhanhTermType === 'brand') {
            return $this->brandTaxonomy;
        }
        return 'product_cat';
    }

    public function brandTaxonomy(): string
    {
        return $this->brandTaxonomy;
    }

    public function isSupportedWcTaxonomy(string $taxonomy): bool
    {
        return $taxonomy === 'product_cat' || $taxonomy === $this->brandTaxonomy;
    }
}
