<?php

namespace Puleeno\NhanhVn\Syncer\Sync\Policy;

use Puleeno\NhanhVn\Syncer\Domain\Product\NhanhProduct;

class PostStatusPolicy
{
    /** @param array<string,mixed> $settings */
    public function resolve(NhanhProduct $p, array $settings): string
    {
        $configured = isset($settings['import_post_status']) ? (string) $settings['import_post_status'] : 'draft';
        if (!in_array($configured, ['publish', 'draft'], true)) $configured = 'draft';
        // preserve existing plugin behavior/tests: configured status is authoritative
        return $configured;
    }
}
