<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Entities\Product\Product;
use Puleeno\NhanhVn\Entities\Product\ProductBrand;
use Puleeno\NhanhVn\Entities\Product\ProductCategory;
use Puleeno\NhanhVn\Syncer\Database\JobRepository;
use Puleeno\NhanhVn\Syncer\Database\ProductMapRepository;
use Puleeno\NhanhVn\Syncer\Database\TermMapRepository;
use Puleeno\NhanhVn\Syncer\Service\ImportService;
use Puleeno\NhanhVn\Syncer\Woo\WooBridge;

class FakeProductsApi
{
    /** @var array<int,ProductCategory> */
    public $categories;
    /** @var array<int,ProductBrand> */
    public $brands;
    /** @var array<int,Product> */
    public $products;

    public function __construct(array $categories, array $brands, array $products)
    {
        $this->categories = $categories;
        $this->brands = $brands;
        $this->products = $products;
    }

    public function getCategories(array $filters = [], array $paginator = []): array
    {
        return $this->categories;
    }

    public function getBrands(): array
    {
        return $this->brands;
    }

    public function search(array $criteria = []): Collection
    {
        return new Collection($this->products);
    }
}

class FakeNhanhClient
{
    /** @var FakeProductsApi */
    private $api;

    public function __construct(FakeProductsApi $api)
    {
        $this->api = $api;
    }

    public function products(): FakeProductsApi
    {
        return $this->api;
    }
}

class ImportServiceTest extends TestCase
{
    private JobRepository $jobs;
    private ProductMapRepository $productMaps;
    private TermMapRepository $termMaps;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['__wc_products'] = [];
        $GLOBALS['__next_wc_product_id'] = 1000;
        $GLOBALS['__wp_terms'] = [];
        $GLOBALS['__next_term_id'] = 100;

        $this->jobs = new JobRepository();
        $this->productMaps = new ProductMapRepository();
        $this->termMaps = new TermMapRepository();
    }

    /**
     * @param array<int,ProductCategory> $categories
     * @param array<int,ProductBrand> $brands
     * @param array<int,Product> $products
     */
    private function service(array $categories = [], array $brands = [], array $products = [], array $settings = []): ImportService
    {
        $client = new FakeNhanhClient(new FakeProductsApi($categories, $brands, $products));

        return new ImportService(
            $this->jobs,
            $this->productMaps,
            $this->termMaps,
            new WooBridge(),
            $client,
            array_merge(['auto_sync_enabled' => 1, 'brand_taxonomy' => 'product_brand', 'import_post_status' => 'draft'], $settings)
        );
    }

    public function testMissingCredentialsFailsFast(): void
    {
        $service = new ImportService(
            $this->jobs,
            $this->productMaps,
            $this->termMaps,
            new WooBridge(),
            null,
            ['app_id' => '', 'business_id' => '', 'access_token' => '']
        );

        $result = $service->run(['trigger' => 'test']);

        $this->assertFalse($result['ok']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('missing_credentials', $result['error']);
    }

    public function testImportsCategoriesIntoProductCat(): void
    {
        $categories = [
            new ProductCategory(['id' => 101, 'name' => 'Thời Trang Nam', 'parentId' => 0]),
            new ProductCategory(['id' => 102, 'name' => 'Thời Trang Nữ', 'parentId' => 0]),
        ];

        $result = $this->service($categories)->run(['trigger' => 'test']);

        $this->assertTrue($result['ok'], 'Import should succeed');
        $this->assertSame(2, $result['updated']);

        $map = $this->termMaps->findByNhanh('category', '101', 'product_cat');
        $this->assertNotNull($map);
        $this->assertSame('synced', $map['status']);

        // Categories must NOT be written into the brand taxonomy.
        $this->assertNull($this->termMaps->findByNhanh('category', '101', 'product_brand'));
    }

    public function testCategoriesAreImportedAsNestedTree(): void
    {
        $categories = [
            new ProductCategory(['id' => 1, 'name' => 'Sách', 'parentId' => 0]),
            new ProductCategory(['id' => 2, 'name' => 'Văn học', 'parentId' => 1]),
            new ProductCategory(['id' => 3, 'name' => 'Kinh dị', 'parentId' => 1]),
            new ProductCategory(['id' => 4, 'name' => 'Truyện ngắn', 'parentId' => 2]),
        ];

        $this->service($categories)->run(['trigger' => 'test']);

        $root = $this->termMaps->findByNhanh('category', '1', 'product_cat');
        $child = $this->termMaps->findByNhanh('category', '2', 'product_cat');
        $grand = $this->termMaps->findByNhanh('category', '4', 'product_cat');

        $this->assertNotNull($root);
        $this->assertNotNull($child);
        $this->assertNotNull($grand);

        $rootTerm = get_term((int) $root['wc_term_id'], 'product_cat');
        $childTerm = get_term((int) $child['wc_term_id'], 'product_cat');
        $grandTerm = get_term((int) $grand['wc_term_id'], 'product_cat');

        $this->assertSame(0, (int) $rootTerm->parent, 'Root category must have no parent');
        $this->assertSame((int) $root['wc_term_id'], (int) $childTerm->parent, 'Child must point at root term');
        $this->assertSame((int) $child['wc_term_id'], (int) $grandTerm->parent, 'Grandchild must point at child term');
    }

    public function testChildIsImportedEvenWhenListedBeforeParent(): void
    {
        $categories = [
            new ProductCategory(['id' => 20, 'name' => 'Con', 'parentId' => 10]),
            new ProductCategory(['id' => 10, 'name' => 'Cha', 'parentId' => 0]),
        ];

        $this->service($categories)->run(['trigger' => 'test']);

        $parent = $this->termMaps->findByNhanh('category', '10', 'product_cat');
        $child = $this->termMaps->findByNhanh('category', '20', 'product_cat');
        $childTerm = get_term((int) $child['wc_term_id'], 'product_cat');

        $this->assertSame((int) $parent['wc_term_id'], (int) $childTerm->parent);
    }

    public function testOrphanCategoryIsImportedAsRoot(): void
    {
        $categories = [
            new ProductCategory(['id' => 30, 'name' => 'Mồ côi', 'parentId' => 999]),
        ];

        $result = $this->service($categories)->run(['trigger' => 'test']);

        $map = $this->termMaps->findByNhanh('category', '30', 'product_cat');
        $term = get_term((int) $map['wc_term_id'], 'product_cat');

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, (int) $term->parent, 'Orphan parent should fall back to root');
    }

    public function testRenamedCategoryKeepsLinkOnResync(): void
    {
        $categories = [
            new ProductCategory(['id' => 1, 'name' => 'Sách', 'parentId' => 0]),
            new ProductCategory(['id' => 2, 'name' => 'Văn học', 'parentId' => 1]),
        ];

        // First import.
        $this->service($categories)->run(['trigger' => 'test']);
        $before = $this->termMaps->findByNhanh('category', '2', 'product_cat');
        $this->assertNotNull($before);
        $termId = (int) $before['wc_term_id'];

        // Shop manager renames the category in WooCommerce.
        wp_update_term($termId, 'product_cat', ['name' => 'Văn học Việt Nam']);

        // Second import must reuse the same term and keep the custom name.
        $this->service($categories)->run(['trigger' => 'test']);
        $after = $this->termMaps->findByNhanh('category', '2', 'product_cat');

        $this->assertSame($termId, (int) $after['wc_term_id'], 'Mapping must still point at the same term');
        $term = get_term($termId, 'product_cat');
        $this->assertSame('Văn học Việt Nam', $term->name, 'Custom name must be preserved');
        $this->assertSame('Văn học Việt Nam', $after['wc_term_name'], 'Mapping should reflect the Woo name');
    }

    public function testRenamedCategoryDoesNotCreateDuplicateTerm(): void
    {
        $categories = [new ProductCategory(['id' => 5, 'name' => 'Kỹ năng', 'parentId' => 0])];

        $this->service($categories)->run(['trigger' => 'test']);
        $first = $this->termMaps->findByNhanh('category', '5', 'product_cat');
        wp_update_term((int) $first['wc_term_id'], 'product_cat', ['name' => 'Kỹ năng sống']);

        $this->service($categories)->run(['trigger' => 'test']);

        $all = array_filter($GLOBALS['__wp_terms'], function ($t) {
            return $t->taxonomy === 'product_cat' && in_array($t->name, ['Kỹ năng', 'Kỹ năng sống'], true);
        });
        $this->assertCount(1, $all, 'No duplicate term should be created after a rename');
    }

    public function testSyncTermNamesOptInRenamesFromNhanh(): void
    {
        $categories = [new ProductCategory(['id' => 6, 'name' => 'Sách mới', 'parentId' => 0])];

        $this->service($categories)->run(['trigger' => 'test']);
        $map = $this->termMaps->findByNhanh('category', '6', 'product_cat');
        wp_update_term((int) $map['wc_term_id'], 'product_cat', ['name' => 'Tên cũ']);

        $this->service($categories, [], [], ['sync_term_names' => 1])->run(['trigger' => 'test']);

        $term = get_term((int) $map['wc_term_id'], 'product_cat');
        $this->assertSame('Sách mới', $term->name);
    }

    public function testRenamedBrandKeepsLinkOnResync(): void
    {
        $brands = [new ProductBrand(['id' => 1, 'name' => 'NOVA'])];

        $this->service([], $brands)->run(['trigger' => 'test']);
        $before = $this->termMaps->findByNhanh('brand', '1', 'product_brand');
        $termId = (int) $before['wc_term_id'];
        wp_update_term($termId, 'product_brand', ['name' => 'NOVA Basic']);

        $this->service([], $brands)->run(['trigger' => 'test']);
        $after = $this->termMaps->findByNhanh('brand', '1', 'product_brand');

        $this->assertSame($termId, (int) $after['wc_term_id']);
        $this->assertSame('NOVA Basic', get_term($termId, 'product_brand')->name);
    }

    public function testDeletedMappedTermIsRecreated(): void
    {
        $categories = [new ProductCategory(['id' => 7, 'name' => 'Tạm', 'parentId' => 0])];

        $this->service($categories)->run(['trigger' => 'test']);
        $map = $this->termMaps->findByNhanh('category', '7', 'product_cat');
        $oldId = (int) $map['wc_term_id'];
        wp_delete_term($oldId, 'product_cat');

        $this->service($categories)->run(['trigger' => 'test']);

        $fresh = $this->termMaps->findByNhanh('category', '7', 'product_cat');
        $this->assertNotNull($fresh);
        $this->assertNotSame($oldId, (int) $fresh['wc_term_id'], 'A deleted term should be recreated');
    }

    public function testBrandsStayFlatEvenWhenTaxonomyIsHierarchical(): void
    {
        $brands = [
            new ProductBrand(['id' => 1, 'name' => 'Thương hiệu A']),
        ];

        $this->service([], $brands)->run(['trigger' => 'test']);

        $map = $this->termMaps->findByNhanh('brand', '1', 'product_brand');
        $term = get_term((int) $map['wc_term_id'], 'product_brand');

        $this->assertSame(0, (int) $term->parent);
    }

    public function testImportsBrandsIntoConfiguredTaxonomy(): void
    {
        $brands = [new ProductBrand(['id' => 201, 'name' => 'NOVA Basic'])];

        $result = $this->service([], $brands)->run(['trigger' => 'test']);

        $this->assertTrue($result['ok']);
        $map = $this->termMaps->findByNhanh('brand', '201', 'product_brand');
        $this->assertNotNull($map);
        $this->assertSame('NOVA Basic', $map['nhanh_term_name']);
    }

    public function testSkipsCategoryWithoutName(): void
    {
        $categories = [new ProductCategory(['id' => 101, 'name' => ''])];

        $result = $this->service($categories)->run(['trigger' => 'test']);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['scanned']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame([], $this->termMaps->all('product_cat'));
    }

    public function testImportsProductsWithPriceStockAndCategory(): void
    {
        $categories = [new ProductCategory(['id' => 1, 'name' => 'Áo'])];
        $products = [
            new Product([
                'id' => 5001,
                'code' => 'SKU-5001',
                'name' => 'Áo Polo',
                'price' => 250000,
                'status' => 1,
                'categoryId' => 1,
                'brandId' => 9,
                'brandName' => 'NOVA',
                'inventory' => ['available' => 7, 'remain' => 9],
                'images' => ['avatar' => 'https://cdn/a.jpg', 'others' => ['https://cdn/b.jpg']],
            ]),
        ];

        $result = $this->service($categories, [], $products)->run(['trigger' => 'test']);

        $this->assertTrue($result['ok'], 'Import should succeed');
        // 1 category + 1 product.
        $this->assertSame(2, $result['updated']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(2, $result['scanned']);

        $map = $this->productMaps->findByCode('SKU-5001');
        $this->assertNotNull($map);
        $this->assertSame('5001', $map['nhanh_id']);
        $this->assertSame(250000.0, (float) $map['nhanh_price']);
        $this->assertSame(7, (int) $map['nhanh_stock_total']);
        $this->assertSame('synced', $map['sync_status']);

        // Product is linked to a real WC product.
        $this->assertGreaterThan(0, (int) $map['wc_product_id']);
        $wc = \wc_get_product((int) $map['wc_product_id']);
        $this->assertNotNull($wc);
        $data = $wc->get_data();
        $this->assertSame('draft', $data['status']);
        $this->assertSame('SKU-5001', $data['sku']);
        $this->assertSame(250000.0, (float) $data['price']);
        $this->assertSame(7, (int) $data['stock_qty']);

        // Category term was resolved from the Nhanh category id.
        $catMap = $this->termMaps->findByNhanh('category', '1', 'product_cat');
        $this->assertNotNull($catMap);
        $this->assertSame([(int) $catMap['wc_term_id']], array_map('intval', $data['category_ids']));
    }

    public function testFallsBackToPricesRetailWhenPriceMissing(): void
    {
        $products = [
            new Product([
                'id' => 5002,
                'code' => 'SKU-5002',
                'name' => 'Quần Jean',
                'prices' => ['retail' => 199000],
                'status' => 1,
                'inventory' => ['remain' => 4],
            ]),
        ];

        $result = $this->service([], [], $products)->run(['trigger' => 'test']);

        $this->assertTrue($result['ok']);
        $map = $this->productMaps->findByCode('SKU-5002');
        $this->assertSame(199000.0, (float) $map['nhanh_price']);
        $this->assertSame(4, (int) $map['nhanh_stock_total']);
    }

    public function testInactiveProductStillFollowsConfiguredPostStatus(): void
    {
        $products = [
            new Product([
                'id' => 5003,
                'code' => 'SKU-5003',
                'name' => 'Hết hàng',
                'price' => 1000,
                'status' => 2,
                'inventory' => ['available' => 0],
            ]),
        ];

        // Default is draft, regardless of Nhanh's active flag.
        $this->service([], [], $products)->run(['trigger' => 'test']);
        $map = $this->productMaps->findByCode('SKU-5003');
        $wc = \wc_get_product((int) $map['wc_product_id']);
        $this->assertSame('draft', $wc->get_data()['status']);
    }

    public function testConfiguredPublishWinsOverInactiveFlag(): void
    {
        $products = [
            new Product(['id' => 5005, 'code' => 'SKU-5005', 'name' => 'Inactive', 'price' => 1000, 'status' => 2]),
        ];

        // Images disabled -> nothing to wait for, so publish immediately.
        $this->service([], [], $products, ['import_post_status' => 'publish', 'import_images' => 0])->run(['trigger' => 'test']);

        $map = $this->productMaps->findByCode('SKU-5005');
        $wc = \wc_get_product((int) $map['wc_product_id']);
        $this->assertSame('publish', $wc->get_data()['status']);
    }

    public function testPublishWaitsForImageWhenImagesEnabled(): void
    {
        $products = [
            new Product(['id' => 5007, 'code' => 'SKU-5007', 'name' => 'Chưa có ảnh', 'price' => 1000, 'status' => 1]),
        ];

        $this->service([], [], $products, ['import_post_status' => 'publish', 'import_images' => 1])->run(['trigger' => 'test']);

        $map = $this->productMaps->findByCode('SKU-5007');
        $wc = \wc_get_product((int) $map['wc_product_id']);
        $this->assertSame('draft', $wc->get_data()['status'], 'No image yet -> stay draft until images are ready');
    }

    public function testImageListPrefersAvatarAndPopulatesMap(): void
    {
        $products = [
            new Product([
                'id' => 5006,
                'code' => 'SKU-5006',
                'name' => 'With image',
                'price' => 1000,
                'status' => 1,
                'images' => ['avatar' => 'https://cdn/avatar.jpg', 'others' => ['https://cdn/other.jpg']],
            ]),
        ];

        $this->service([], [], $products, ['import_images' => 0])->run(['trigger' => 'test']);

        $map = $this->productMaps->findByCode('SKU-5006');
        $this->assertSame('https://cdn/avatar.jpg', $map['image_url']);
    }

    public function testImportPostStatusDraftOverridesActivity(): void
    {
        $products = [
            new Product(['id' => 5004, 'code' => 'SKU-5004', 'name' => 'Active', 'price' => 1000, 'status' => 1]),
        ];

        $this->service([], [], $products, ['import_post_status' => 'draft'])->run(['trigger' => 'test']);

        $map = $this->productMaps->findByCode('SKU-5004');
        $wc = \wc_get_product((int) $map['wc_product_id']);
        $this->assertSame('draft', $wc->get_data()['status']);
    }

    public function testScannedCountsEachEntityExactlyOnce(): void
    {
        $categories = [new ProductCategory(['id' => 1, 'name' => 'Cat'])];
        $brands = [new ProductBrand(['id' => 2, 'name' => 'Brand'])];
        $products = [
            new Product(['id' => 10, 'code' => 'A', 'name' => 'A', 'price' => 1, 'status' => 1]),
            new Product(['id' => 11, 'code' => 'B', 'name' => 'B', 'price' => 2, 'status' => 1]),
        ];

        $result = $this->service($categories, $brands, $products)->run(['trigger' => 'test']);

        // 1 category + 1 brand + 2 products, counted once each (no double count).
        $this->assertSame(4, $result['scanned']);
        $this->assertSame(4, $result['updated']);
    }

    public function testProductWithoutIdentityIsSkipped(): void
    {
        $products = [new Product(['name' => 'No id', 'price' => 5, 'status' => 1])];

        $result = $this->service([], [], $products)->run(['trigger' => 'test']);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['scanned']);
        $this->assertSame(0, $result['updated']);
    }

    public function testFailingSectionYieldsPartialStatusAndTitle(): void
    {
        $client = new class extends FakeNhanhClient {
            public function __construct()
            {
            }
            public function products(): FakeProductsApi
            {
                throw new \RuntimeException('brands endpoint down');
            }
        };

        $service = new ImportService(
            $this->jobs,
            $this->productMaps,
            $this->termMaps,
            new WooBridge(),
            $client,
            ['auto_sync_enabled' => 1, 'brand_taxonomy' => 'product_brand']
        );

        $result = $service->run(['trigger' => 'test']);

        $this->assertSame('partial', $result['status']);
        $this->assertStringContainsString('brands endpoint down', $result['error']);

        $job = $this->jobs->find($result['job_id']);
        $this->assertSame('partial', $job['status']);

        $logs = $this->jobs->recent(5);
        $found = false;
        foreach ($logs as $row) {
            if ((int) $row['id'] === (int) $result['job_id']) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testJobIsPersistedWithCounters(): void
    {
        $products = [new Product(['id' => 20, 'code' => 'SKU-20', 'name' => 'X', 'price' => 10, 'status' => 1])];

        $result = $this->service([], [], $products)->run(['trigger' => 'cron']);
        $job = $this->jobs->find($result['job_id']);

        $this->assertNotNull($job);
        $this->assertSame('import', $job['job_type']);
        $this->assertSame('success', $job['status']);
        $this->assertSame(1, (int) $job['scanned']);
        $this->assertSame(1, (int) $job['updated_count']);
        $this->assertNotNull($job['started_at']);
        $this->assertNotNull($job['finished_at']);
    }
}