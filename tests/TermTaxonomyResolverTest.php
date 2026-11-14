<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Service\TermTaxonomyResolver;

class TermTaxonomyResolverTest extends TestCase
{
    public function testForTypeReturnsProductCatForCategory(): void
    {
        $resolver = new TermTaxonomyResolver('product_brand');
        $this->assertSame('product_cat', $resolver->forType('category'));
    }

    public function testForTypeReturnsBrandTaxonomyForBrand(): void
    {
        $resolver = new TermTaxonomyResolver('product_brand');
        $this->assertSame('product_brand', $resolver->forType('brand'));
    }

    public function testForTypeReturnsCustomBrandTaxonomy(): void
    {
        $resolver = new TermTaxonomyResolver('custom_brand');
        $this->assertSame('custom_brand', $resolver->forType('brand'));
    }

    public function testBrandTaxonomyReturnsConfigured(): void
    {
        $resolver = new TermTaxonomyResolver('custom_brand');
        $this->assertSame('custom_brand', $resolver->brandTaxonomy());
    }

    public function testIsSupportedWcTaxonomyReturnsTrueForProductCat(): void
    {
        $resolver = new TermTaxonomyResolver('product_brand');
        $this->assertTrue($resolver->isSupportedWcTaxonomy('product_cat'));
    }

    public function testIsSupportedWcTaxonomyReturnsTrueForBrand(): void
    {
        $resolver = new TermTaxonomyResolver('product_brand');
        $this->assertTrue($resolver->isSupportedWcTaxonomy('product_brand'));
    }

    public function testIsSupportedWcTaxonomyReturnsFalseForOther(): void
    {
        $resolver = new TermTaxonomyResolver('product_brand');
        $this->assertFalse($resolver->isSupportedWcTaxonomy('product_tag'));
    }

    public function testConstructorFallsBackToDefaultWhenEmpty(): void
    {
        $resolver = new TermTaxonomyResolver('');
        $this->assertSame('product_brand', $resolver->brandTaxonomy());
    }
}