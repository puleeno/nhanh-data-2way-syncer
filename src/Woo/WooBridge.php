<?php

namespace Puleeno\NhanhVn\Syncer\Woo;

use WC_Product;

/**
 * Gateway/Adapter over WooCommerce product & term APIs.
 * Accepts a normalized data array (mapped from canonical Nhanh product).
 */
class WooBridge
{
    /**
     * @param array<string,mixed> $data
     * @return int Product id (0 on failure)
     */
    public function upsertProduct(array $data): int
    {
        $sku = isset($data['sku']) ? (string) $data['sku'] : '';
        $existingId = 0;
        if (!empty($data['wc_product_id'])) {
            $existingId = (int) $data['wc_product_id'];
        } elseif ($sku !== '' && function_exists('wc_get_product_id_by_sku')) {
            $existingId = (int) wc_get_product_id_by_sku($sku);
        }

        $product = ($existingId > 0 && function_exists('wc_get_product')) ? wc_get_product($existingId) : null;
        if (!$product instanceof WC_Product) {
            $product = new WC_Product();
        }

        if (isset($data['name'])) {
            $product->set_name(sanitize_text_field((string) $data['name']));
        }
        if ($sku !== '') {
            $product->set_sku(sanitize_text_field($sku));
        }

        // Pricing: support regular/sale/active (fix wp-admin showing "–")
        if (array_key_exists('regular_price', $data) || array_key_exists('price', $data)) {
            $regular = array_key_exists('regular_price', $data) ? (string) $data['regular_price'] : (string) ($data['price'] ?? '');
            $sale = array_key_exists('sale_price', $data) ? (string) $data['sale_price'] : '';
            if (method_exists($product, 'set_regular_price')) $product->set_regular_price($regular);
            if (method_exists($product, 'set_sale_price')) $product->set_sale_price($sale);
            $active = $sale !== '' ? $sale : $regular;
            if (method_exists($product, 'set_price')) $product->set_price($active);
        }

        // Stock
        $manage = array_key_exists('manage_stock', $data) ? (bool) $data['manage_stock'] : true;
        if (method_exists($product, 'set_manage_stock')) $product->set_manage_stock($manage);
        if (isset($data['stock'])) {
            $qty = (int) $data['stock'];
            if (method_exists($product, 'set_stock_quantity')) $product->set_stock_quantity($qty);
            if (method_exists($product, 'set_stock_status')) {
                $product->set_stock_status($qty > 0 ? 'instock' : 'outofstock');
            }
        }

        // Content
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

        // Status
        if (isset($data['status'])) {
            $st = function_exists('sanitize_key') ? sanitize_key((string) $data['status']) : strtolower((string) $data['status']);
            $product->set_status($st);
        }

        // Categories
        if (isset($data['categories']) && is_array($data['categories'])) {
            $product->set_category_ids(array_values(array_unique(array_map('intval', $data['categories']))));
        }

        // Shipping dimensions/weight
        foreach (['weight', 'length', 'width', 'height'] as $dim) {
            if (array_key_exists($dim, $data) && $data[$dim] !== null && $data[$dim] !== '') {
                $method = 'set_' . $dim;
                if (method_exists($product, $method)) {
                    $product->$method((string) $data[$dim]);
                }
            }
        }

        // Meta (barcode, nhanh ids, extended fields snapshot refs)
        if (method_exists($product, 'update_meta_data') && isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $k => $v) {
                $product->update_meta_data((string) $k, $v);
            }
        }

        $productId = $product->save();
        if (function_exists('is_wp_error') && is_wp_error($productId)) return 0;
        $productId = (int) $productId;
        if ($productId <= 0) return 0;

        // Taxonomies beyond product_cat (e.g. brand, tags)
        if (isset($data['taxonomy_terms']) && is_array($data['taxonomy_terms']) && function_exists('wp_set_object_terms')) {
            foreach ($data['taxonomy_terms'] as $tax => $termIds) {
                if (!is_array($termIds)) continue;
                $ids = array_values(array_unique(array_map('intval', $termIds)));
                wp_set_object_terms($productId, $ids, (string) $tax, false);
            }
        }

        // Images
        $forceImages = !empty($data['force_images']);
        $hasImage = method_exists($product, 'get_image_id') ? ((int) $product->get_image_id() > 0) : false;
        if (!empty($data['images']) && is_array($data['images']) && ($forceImages || !$hasImage)) {
            $this->applyImages($productId, $data['images'], $forceImages);
        }

        return $productId;
    }

    /** @param array<int,string> $urls */
    private function applyImages(int $productId, array $urls, bool $force = false): void
    {
        $urls = array_values(array_filter(array_map('strval', $urls)));
        if (!$urls) return;

        $attachmentIds = [];
        foreach ($urls as $url) {
            $url = function_exists('esc_url_raw') ? esc_url_raw($url) : $url;
            if ($url === '' || !preg_match('#^https?://#i', $url)) continue;

            if (function_exists('attachment_url_to_postid')) {
                $existing = (int) attachment_url_to_postid($url);
                if ($existing > 0) { $attachmentIds[] = $existing; continue; }
            }
            $id = $this->sideloadImage($url, $productId);
            if ($id > 0) $attachmentIds[] = $id;
        }
        if (!$attachmentIds) return;

        if (!function_exists('wc_get_product')) return;
        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) return;

        if (method_exists($product, 'set_image_id')) {
            $product->set_image_id((int) array_shift($attachmentIds));
        }
        if ($attachmentIds && method_exists($product, 'set_gallery_image_ids')) {
            $product->set_gallery_image_ids(array_map('intval', $attachmentIds));
        } elseif ($attachmentIds && method_exists($product, 'set_images')) {
            // backward compat with test stubs
            $all = array_merge($product->get_data()['images'] ?? [], $attachmentIds);
            $product->set_images($all);
        }
        $product->save();
    }

    private function sideloadImage(string $url, int $postId): int
    {
        if (!function_exists('media_sideload_image')) return 0;
        if (!function_exists('download_url') && defined('ABSPATH')) {
            @include_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('media_sideload_image') && defined('ABSPATH')) {
            @include_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (!function_exists('wp_read_image_metadata') && defined('ABSPATH')) {
            @include_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!function_exists('media_sideload_image')) return 0;
        $id = media_sideload_image($url, $postId, null, 'id');
        return (function_exists('is_wp_error') && is_wp_error($id)) ? 0 : (int) $id;
    }

    private function isHierarchical(string $taxonomy): bool
    {
        if (function_exists('is_taxonomy_hierarchical')) {
            return (bool) is_taxonomy_hierarchical($taxonomy);
        }
        return in_array($taxonomy, ['product_cat'], true);
    }

    public function upsertTerm(string $taxonomy, string $name, string $description = '', int $parentId = 0): int
    {
        $parentId = ($parentId > 0 && $this->isHierarchical($taxonomy)) ? $parentId : 0;

        $term = function_exists('get_term_by') ? get_term_by('name', $name, $taxonomy) : false;
        if ($term instanceof \WP_Term) {
            $termId = (int) $term->term_id;
            $args = [];
            if ($parentId > 0 && (int) $term->parent !== $parentId) $args['parent'] = $parentId;
            if ($description !== '') $args['description'] = $description;
            if ($args && function_exists('wp_update_term')) wp_update_term($termId, $taxonomy, $args);
            return $termId;
        }

        $args = [
            'description' => $description,
            'slug' => function_exists('sanitize_title') ? sanitize_title($name) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)),
        ];
        if ($parentId > 0) $args['parent'] = $parentId;

        $result = function_exists('wp_insert_term') ? wp_insert_term($name, $taxonomy, $args) : null;
        if ((function_exists('is_wp_error') && is_wp_error($result)) || !is_array($result)) return 0;
        return (int) ($result['term_id'] ?? 0);
    }
}
