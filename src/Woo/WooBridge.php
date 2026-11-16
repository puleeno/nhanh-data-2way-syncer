<?php

namespace Puleeno\NhanhVn\Syncer\Woo;

use WC_Product;

/**
 * Gateway/Adapter over WooCommerce product & term APIs.
 * Accepts normalized data arrays (mapped from canonical Nhanh product).
 */
class WooBridge
{
    /** @var array<string,mixed>|null */
    private $lastImageStats = null;

    /**
     * @return array<string,mixed>|null
     */
    public function getLastImageStats(): ?array
    {
        return $this->lastImageStats;
    }

    /**
     * @param array<string,mixed> $data
     * @return int Product id (0 on failure)
     */
    public function upsertProduct(array $data): int
    {
        $this->lastImageStats = null;

        $sku = isset($data['sku']) ? (string) $data['sku'] : '';
        $existingId = 0;
        if (!empty($data['wc_product_id'])) {
            $existingId = (int) $data['wc_product_id'];
        } elseif ($sku !== '' && function_exists('wc_get_product_id_by_sku')) {
            $existingId = (int) wc_get_product_id_by_sku($sku);
        }

        $product = $this->loadOrCreate($existingId, (string) ($data['type'] ?? 'simple'));

        if (isset($data['name'])) {
            $product->set_name(sanitize_text_field((string) $data['name']));
        }
        if ($sku !== '' && method_exists($product, 'set_sku')) {
            $product->set_sku(sanitize_text_field($sku));
        }

        // Attributes for variable products (composite parent)
        if (($data['type'] ?? '') === 'variable' && !empty($data['attributes']) && is_array($data['attributes'])) {
            $this->applyVariableAttributes($product, $data['attributes']);
        }

        $this->applyCommonFields($product, $data);

        $productId = $product->save();
        if (function_exists('is_wp_error') && is_wp_error($productId)) return 0;
        $productId = (int) $productId;
        if ($productId <= 0) return 0;

        $this->applyTaxonomies($productId, $data);
        $this->maybeApplyImages($productId, $data);

        return $productId;
    }

    /**
     * Upsert a variation under a variable parent.
     * @param array<string,mixed> $data
     */
    public function upsertVariation(int $parentId, array $data, int $existingVariationId = 0): int
    {
        $variation = null;
        if ($existingVariationId > 0 && function_exists('wc_get_product')) {
            $variation = wc_get_product($existingVariationId);
        }
        if (!$variation || (function_exists('is_wp_error') && is_wp_error($variation))) {
            if (class_exists('WC_Product_Variation')) {
                $variation = new \WC_Product_Variation();
            } else {
                // fallback for test env without variation class
                $variation = new WC_Product();
            }
        }
        if (method_exists($variation, 'set_parent_id')) $variation->set_parent_id($parentId);
        $sku = isset($data['sku']) ? (string) $data['sku'] : '';
        if ($sku !== '' && method_exists($variation, 'set_sku')) $variation->set_sku(sanitize_text_field($sku));

        // map attribute name => option value
        if (!empty($data['attributes']) && is_array($data['attributes']) && method_exists($variation, 'set_attributes')) {
            $attrs = [];
            foreach ($data['attributes'] as $name => $value) {
                $key = function_exists('sanitize_title') ? sanitize_title((string) $name) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $name));
                $attrs[$key] = (string) $value;
            }
            $variation->set_attributes($attrs);
        }

        $this->applyCommonFields($variation, $data);

        // variation image
        if (!empty($data['image']) && is_string($data['image']) && method_exists($variation, 'set_image_id')) {
            $imgId = $this->resolveAttachmentId((string) $data['image'], $parentId);
            if ($imgId > 0) $variation->set_image_id($imgId);
        }

        $id = $variation->save();
        if (function_exists('is_wp_error') && is_wp_error($id)) return 0;
        return (int) $id;
    }

    /**
     * Remove variations not in synced set (trash them) to keep composite consistent.
     * @param array<int,int> $keepIds
     */
    public function syncVariations(int $parentId, array $keepIds): void
    {
        if (!function_exists('wc_get_product')) return;
        $parent = wc_get_product($parentId);
        if (!$parent || !method_exists($parent, 'get_children')) return;
        $keep = array_map('intval', $keepIds);
        foreach ((array) $parent->get_children() as $cid) {
            $cid = (int) $cid;
            if ($cid > 0 && !in_array($cid, $keep, true)) {
                if (function_exists('wp_trash_post')) wp_trash_post($cid);
            }
        }
    }

    private function loadOrCreate(int $existingId, string $type): WC_Product
    {
        $product = ($existingId > 0 && function_exists('wc_get_product')) ? wc_get_product($existingId) : null;
        if ($product instanceof WC_Product) return $product;
        if ($type === 'variable' && class_exists('WC_Product_Variable')) {
            return new \WC_Product_Variable();
        }
        return new WC_Product();
    }

    /** @param array<string,mixed> $data */
    private function applyCommonFields(WC_Product $product, array $data): void
    {
        if (array_key_exists('regular_price', $data) || array_key_exists('price', $data)) {
            $regular = array_key_exists('regular_price', $data) ? (string) $data['regular_price'] : (string) ($data['price'] ?? '');
            $sale = array_key_exists('sale_price', $data) ? (string) $data['sale_price'] : '';
            if (method_exists($product, 'set_regular_price')) $product->set_regular_price($regular);
            if (method_exists($product, 'set_sale_price')) $product->set_sale_price($sale);
            $active = $sale !== '' ? $sale : $regular;
            if (method_exists($product, 'set_price')) $product->set_price($active);
        }

        $manage = array_key_exists('manage_stock', $data) ? (bool) $data['manage_stock'] : true;
        if (method_exists($product, 'set_manage_stock')) $product->set_manage_stock($manage);
        if (isset($data['stock'])) {
            $qty = (int) $data['stock'];
            if (method_exists($product, 'set_stock_quantity')) $product->set_stock_quantity($qty);
            if (method_exists($product, 'set_stock_status')) $product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
        }

        if (array_key_exists('description', $data)) {
            $desc = (string) $data['description'];
            if (function_exists('wpautop')) $desc = wpautop($desc);
            if (function_exists('wp_filter_post_html')) $desc = wp_filter_post_html($desc);
            if (method_exists($product, 'set_description')) $product->set_description($desc);
        }
        if (array_key_exists('short_description', $data) && method_exists($product, 'set_short_description')) {
            $sd = (string) $data['short_description'];
            if (function_exists('wp_filter_post_html')) $sd = wp_filter_post_html($sd);
            $product->set_short_description($sd);
        }
        if (isset($data['status']) && method_exists($product, 'set_status')) {
            $st = function_exists('sanitize_key') ? sanitize_key((string) $data['status']) : strtolower((string) $data['status']);
            $product->set_status($st);
        }
        if (isset($data['categories']) && is_array($data['categories']) && method_exists($product, 'set_category_ids')) {
            $product->set_category_ids(array_values(array_unique(array_map('intval', $data['categories']))));
        }
        foreach (['weight', 'length', 'width', 'height'] as $dim) {
            if (array_key_exists($dim, $data) && $data[$dim] !== null && $data[$dim] !== '') {
                $method = 'set_' . $dim;
                if (method_exists($product, $method)) $product->$method((string) $data[$dim]);
            }
        }
        if (method_exists($product, 'update_meta_data') && isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $k => $v) $product->update_meta_data((string) $k, $v);
        }
    }

    /** @param array<int,array{name:string,options:array<int,string>,visible?:bool,variation?:bool}> $attrs */
    private function applyVariableAttributes(WC_Product $product, array $attrs): void
    {
        if (!class_exists('WC_Product_Attribute') || !method_exists($product, 'set_attributes')) return;
        $objects = [];
        foreach ($attrs as $a) {
            if (!is_array($a) || empty($a['name'])) continue;
            $pa = new \WC_Product_Attribute();
            $name = (string) $a['name'];
            if (method_exists($pa, 'set_name')) $pa->set_name($name);
            if (method_exists($pa, 'set_options')) $pa->set_options(array_values(array_map('strval', $a['options'] ?? [])));
            if (method_exists($pa, 'set_visible')) $pa->set_visible(!empty($a['visible']));
            if (method_exists($pa, 'set_variation')) $pa->set_variation(!empty($a['variation']));
            $objects[] = $pa;
        }
        if ($objects) $product->set_attributes($objects);
    }

    /** @param array<string,mixed> $data */
    private function applyTaxonomies(int $productId, array $data): void
    {
        if (isset($data['taxonomy_terms']) && is_array($data['taxonomy_terms']) && function_exists('wp_set_object_terms')) {
            foreach ($data['taxonomy_terms'] as $tax => $termIds) {
                if (!is_array($termIds)) continue;
                $ids = array_values(array_unique(array_map('intval', $termIds)));
                wp_set_object_terms($productId, $ids, (string) $tax, false);
            }
        }
    }

    /** @param array<string,mixed> $data */
    private function maybeApplyImages(int $productId, array $data): void
    {
        $forceImages = !empty($data['force_images']);
        if (!function_exists('wc_get_product')) return;
        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) return;
        $hasImage = method_exists($product, 'get_image_id') ? ((int) $product->get_image_id() > 0) : false;
        if (!empty($data['images']) && is_array($data['images']) && ($forceImages || !$hasImage)) {
            $this->lastImageStats = $this->applyImages($productId, $data['images']);
        } else {
            $provided = (!empty($data['images']) && is_array($data['images'])) ? count(array_values(array_filter(array_map('strval', $data['images'])))) : 0;
            $this->lastImageStats = [
                'provided' => $provided,
                'imported' => 0,
                'skipped' => ($provided > 0 && $hasImage && !$forceImages) ? $provided : 0,
                'failed' => 0,
                'items' => [],
                'reason' => ($provided === 0) ? 'none_provided' : (($hasImage && !$forceImages) ? 'already_has_thumbnail' : 'not_attempted'),
            ];
        }
    }

    private function resolveAttachmentId(string $url, int $postId): array
    {
        $url = function_exists('esc_url_raw') ? esc_url_raw($url) : $url;
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return ['id' => 0, 'status' => 'invalid_url', 'url' => $url];
        }
        if (function_exists('attachment_url_to_postid')) {
            $existing = (int) attachment_url_to_postid($url);
            if ($existing > 0) {
                return ['id' => $existing, 'status' => 'existing', 'url' => $url];
            }
        }
        $id = $this->sideloadImage($url, $postId);
        if ($id > 0) {
            return ['id' => $id, 'status' => 'imported', 'url' => $url];
        }
        return ['id' => 0, 'status' => 'failed', 'url' => $url];
    }

    /**
     * @param array<int,string> $urls
     * @return array<string,mixed>
     */
    private function applyImages(int $productId, array $urls): array
    {
        $urls = array_values(array_filter(array_map('strval', $urls)));
        $stats = [
            'provided' => count($urls),
            'imported' => 0,
            'existing' => 0,
            'failed' => 0,
            'skipped' => 0,
            'items' => [],
        ];
        if (!$urls) return $stats;

        $attachmentIds = [];
        foreach ($urls as $url) {
            $res = $this->resolveAttachmentId($url, $productId);
            $stats['items'][] = $res;
            if (($res['id'] ?? 0) > 0) {
                $attachmentIds[] = (int) $res['id'];
                if (($res['status'] ?? '') === 'existing') $stats['existing']++; else $stats['imported']++;
            } else {
                if (($res['status'] ?? '') === 'invalid_url') $stats['skipped']++; else $stats['failed']++;
            }
        }
        if (!$attachmentIds) return $stats;

        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) return $stats;
        if (method_exists($product, 'set_image_id')) $product->set_image_id((int) array_shift($attachmentIds));
        if ($attachmentIds && method_exists($product, 'set_gallery_image_ids')) {
            $product->set_gallery_image_ids(array_map('intval', $attachmentIds));
        }
        $product->save();
        return $stats;
    }

    private function sideloadImage(string $url, int $postId): int
    {
        if (!function_exists('media_sideload_image')) return 0;
        if (!function_exists('download_url') && defined('ABSPATH')) @include_once ABSPATH . 'wp-admin/includes/file.php';
        if (!function_exists('media_sideload_image') && defined('ABSPATH')) @include_once ABSPATH . 'wp-admin/includes/media.php';
        if (!function_exists('wp_read_image_metadata') && defined('ABSPATH')) @include_once ABSPATH . 'wp-admin/includes/image.php';
        if (!function_exists('media_sideload_image')) return 0;
        $id = media_sideload_image($url, $postId, null, 'id');
        return (function_exists('is_wp_error') && is_wp_error($id)) ? 0 : (int) $id;
    }

    /**
     * Whether a product already has a featured image in the media library.
     */
    public function hasThumbnail(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }
        $product = function_exists('wc_get_product') ? wc_get_product($productId) : null;

        return $product && (int) $product->get_image_id() > 0;
    }

    private function isHierarchical(string $taxonomy): bool
    {
        if (function_exists('is_taxonomy_hierarchical')) return (bool) is_taxonomy_hierarchical($taxonomy);
        return in_array($taxonomy, ['product_cat'], true);
    }

    public function upsertTerm(
        string $taxonomy,
        string $name,
        string $description = '',
        int $parentId = 0,
        ?int $existingTermId = null,
        bool $syncNames = false
    ): int {
        $parentId = ($parentId > 0 && $this->isHierarchical($taxonomy)) ? $parentId : 0;

        // 1) A mapped term wins: keep the user's own name/slug/description and
        //    only repair the hierarchy. This is what keeps the link alive after
        //    the shop manager renames a category in WooCommerce.
        if ($existingTermId !== null && $existingTermId > 0 && function_exists('get_term')) {
            $term = get_term($existingTermId, $taxonomy);
            if ($term instanceof \WP_Term && !is_wp_error($term)) {
                $args = [];
                if ($parentId > 0 && (int) $term->parent !== $parentId) {
                    $args['parent'] = $parentId;
                }
                if ($syncNames) {
                    if (sanitize_text_field($term->name) !== sanitize_text_field($name)) {
                        $args['name'] = $name;
                    }
                    if ($description !== '' && (string) $term->description !== $description) {
                        $args['description'] = $description;
                    }
                }
                if ($args && function_exists('wp_update_term')) {
                    wp_update_term($existingTermId, $taxonomy, $args);
                }
                return (int) $existingTermId;
            }
            // The mapped term was deleted in WooCommerce: fall through and recreate.
        }

        // 2) No mapping yet: look the term up by name (legacy / first sync).
        $term = function_exists('get_term_by') ? get_term_by('name', $name, $taxonomy) : false;
        if ($term instanceof \WP_Term) {
            $termId = (int) $term->term_id;
            $args = [];
            if ($parentId > 0 && (int) $term->parent !== $parentId) {
                $args['parent'] = $parentId;
            }
            if ($syncNames && $description !== '' && (string) $term->description !== $description) {
                $args['description'] = $description;
            }
            if ($args && function_exists('wp_update_term')) {
                wp_update_term($termId, $taxonomy, $args);
            }
            return $termId;
        }

        // 3) Create it.
        $args = [
            'description' => $description,
            'slug' => function_exists('sanitize_title')
                ? sanitize_title($name)
                : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)),
        ];
        if ($parentId > 0) {
            $args['parent'] = $parentId;
        }
        $result = function_exists('wp_insert_term') ? wp_insert_term($name, $taxonomy, $args) : null;
        if ((function_exists('is_wp_error') && is_wp_error($result)) || !is_array($result)) {
            return 0;
        }
        return (int) ($result['term_id'] ?? 0);
    }
}
