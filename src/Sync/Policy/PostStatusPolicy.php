<?php

namespace Puleeno\NhanhVn\Syncer\Sync\Policy;

use Puleeno\NhanhVn\Syncer\Domain\Product\NhanhProduct;

class PostStatusPolicy
{
    /**
     * Decide the WooCommerce post status for an imported product.
     *
     * When `import_post_status = publish` and image import is enabled, a product
     * is only published once its images are ready — otherwise it stays `draft`
     * and gets published on a later run. This prevents publishing products with
     * no images.
     *
     * @param array<string,mixed> $settings
     */
    public function resolve(NhanhProduct $p, array $settings, bool $imagesReady = true): string
    {
        $configured = isset($settings['import_post_status']) ? (string) $settings['import_post_status'] : 'draft';
        if (!in_array($configured, ['publish', 'draft'], true)) {
            $configured = 'draft';
        }

        if ($configured === 'draft') {
            return 'draft';
        }

        $imagesEnabled = !empty($settings['import_images']);

        return ($imagesEnabled && !$imagesReady) ? 'draft' : 'publish';
    }
}
