<?php

namespace Puleeno\NhanhVn\Syncer\Woo;

use WC_Product;

/**
 * Thin adapter over the WooCommerce product/term APIs.
 *
 * The import flow calls this instead of touching `wc_insert_product` /
 * `wp_insert_term` directly, so the bridge stays easy to mock/test and the
 * plugin keeps a single place to handle HPOS + variation quirks.
 */
class WooBridge
{
    /**
     * Create or update a product from a normalized Nhanh row.
     *
     * @param array<string,mixed> $data Keys: name, sku, price, stock, description, categories (term ids), images (urls), status
     * @return int Product id (0 on failure)
     */
    public function upsertProduct(array $data): int
    {
        $sku = isset($data['sku']) ? (string) $data['sku'] : '';
        $existingId = $sku !== '' && function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($sku) : 0;

        if ($existingId > 0) {
            $product = wc_get_product($existingId);
        } else {
            $product = new WC_Product();
        }

        if (!$product instanceof WC_Product) {
            return 0;
        }

        if (isset($data['name'])) {
            $product->set_name(sanitize_text_field($data['name']));
        }
        if (isset($data['sku'])) {
            $product->set_sku(sanitize_text_field($data['sku']));
        }
        if (isset($data['price'])) {
            $product->set_price((string) $data['price']);
        }
        if (isset($data['stock'])) {
            $product->set_stock_quantity((int) $data['stock']);
            $product->set_stock_status((int) $data['stock'] > 0 ? 'instock' : 'outofstock');
        }
        if (isset($data['description'])) {
            $product->set_description(wpautop(wp_filter_post_html($data['description'])));
        }
        if (isset($data['status'])) {
            $product->set_status(sanitize_key($data['status']));
        }
        if (isset($data['categories']) && is_array($data['categories'])) {
            $product->set_category_ids(array_values(array_map('intval', $data['categories'])));
        }

        $productId = $product->save();
        if (is_wp_error($productId) || (int) $productId <= 0) {
            return 0;
        }
        $productId = (int) $productId;

        // Images must be sideloaded into the media library: WC_Product has no
        // set_images() and only accepts attachment IDs. Skip when the product
        // already has an image, so re-scans of an unchanged catalog stay cheap.
        if (!empty($data['images']) && is_array($data['images']) && (int) $product->get_image_id() <= 0) {
            $this->applyImages($productId, $data['images']);
        }

        return $productId;
    }

    /**
     * Sideload external image URLs into the media library and attach them.
     *
     * @param array<int,string> $urls
     */
    private function applyImages(int $productId, array $urls): void
    {
        $urls = array_values(array_filter(array_map('strval', $urls)));
        if (!$urls) {
            return;
        }

        $attachmentIds = [];
        foreach ($urls as $url) {
            $url = esc_url_raw($url);
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }

            // Reuse an existing attachment when the URL is already in the library.
            if (function_exists('attachment_url_to_postid')) {
                $existing = (int) attachment_url_to_postid($url);
                if ($existing > 0) {
                    $attachmentIds[] = $existing;
                    continue;
                }
            }

            $id = $this->sideloadImage($url, $productId);
            if ($id > 0) {
                $attachmentIds[] = $id;
            }
        }

        if (!$attachmentIds) {
            return;
        }

        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) {
            return;
        }

        $product->set_image_id((int) array_shift($attachmentIds));
        if ($attachmentIds) {
            $product->set_gallery_image_ids(array_map('intval', $attachmentIds));
        }
        $product->save();
    }

    /**
     * Download one image into the media library. Returns attachment id (0 on failure).
     */
    private function sideloadImage(string $url, int $postId): int
    {
        if (!function_exists('media_sideload_image')) {
            return 0;
        }

        // media_sideload_image() pulls these in.
        if (!function_exists('download_url')) {
            if (defined('ABSPATH')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
        }
        if (!function_exists('media_sideload_image') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (!function_exists('wp_read_image_metadata') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        if (!function_exists('media_sideload_image')) {
            return 0;
        }

        $id = media_sideload_image($url, $postId, null, 'id');

        return is_wp_error($id) ? 0 : (int) $id;
    }

    private function isHierarchical(string $taxonomy): bool
    {
        if (function_exists('is_taxonomy_hierarchical')) {
            return (bool) is_taxonomy_hierarchical($taxonomy);
        }
        return in_array($taxonomy, ['product_cat'], true);
    }

    /**
     * Create a term in a taxonomy (if missing) and return its id.
     *
     * @param int $parentId Parent term id (0 = root). Only meaningful for
     *                      hierarchical taxonomies such as `product_cat`.
     */
    public function upsertTerm(string $taxonomy, string $name, string $description = '', int $parentId = 0): int
    {
        // Only hierarchical taxonomies (e.g. product_cat) support parents.
        $parentId = ($parentId > 0 && $this->isHierarchical($taxonomy)) ? $parentId : 0;

        $term = get_term_by('name', $name, $taxonomy);

        if ($term instanceof \WP_Term) {
            $termId = (int) $term->term_id;
            if ($parentId > 0 && (int) $term->parent !== $parentId) {
                wp_update_term($termId, $taxonomy, ['parent' => $parentId]);
            }
            return $termId;
        }

        $args = [
            'description' => $description,
            'slug' => sanitize_title($name),
        ];
        if ($parentId > 0) {
            $args['parent'] = $parentId;
        }

        $result = wp_insert_term($name, $taxonomy, $args);

        if (is_wp_error($result) || !is_array($result)) {
            return 0;
        }
        return (int) ($result['term_id'] ?? 0);
    }
}