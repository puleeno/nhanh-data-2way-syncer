<?php

namespace Puleeno\NhanhVn\Syncer\Woo;

use WC_Product;
use WP_Error;

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
     * @param array<string,mixed> $data  Keys: name, sku, price, stock, description, categories (term ids), images (urls), status, type
     * @return int Product id (0 on failure)
     */
    public function upsertProduct(array $data): int
    {
        $sku = isset($data['sku']) ? (string) $data['sku'] : '';
        $existingId = $sku !== '' ? wc_get_product_id_by_sku($sku) : 0;

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
        if (isset($data['images']) && is_array($data['images'])) {
            $images = [];
            foreach ($data['images'] as $idx => $url) {
                $images[] = ['position' => $idx, 'src' => esc_url_raw($url)];
            }
            $product->set_images($images);
        }

        $productId = $product->save();
        return is_wp_error($productId) ? 0 : (int) $productId;
    }

    /**
     * Create a term in a taxonomy (if missing) and return its id.
     */
    public function upsertTerm(string $taxonomy, string $name, string $description = ''): int
    {
        $term = get_term_by('name', $name, $taxonomy);
        if ($term instanceof \WP_Term) {
            return (int) $term->term_id;
        }

        $result = wp_insert_term($name, $taxonomy, [
            'description' => $description,
            'slug' => sanitize_title($name),
        ]);

        if (is_wp_error($result) || !is_array($result)) {
            return 0;
        }
        return (int) ($result['term_id'] ?? 0);
    }
}