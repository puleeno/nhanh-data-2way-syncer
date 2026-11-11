<?php

namespace Puleeno\NhanhVn\Syncer\Admin;

class Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, Menu::SLUG) !== 0) {
            return;
        }

        wp_enqueue_style(
            'nhanh-syncer-admin',
            NHANH_SYNCER_URL . 'assets/css/admin.css',
            [],
            NHANH_SYNCER_VERSION
        );

        wp_enqueue_script(
            'nhanh-syncer-admin',
            NHANH_SYNCER_URL . 'assets/js/admin.js',
            [],
            NHANH_SYNCER_VERSION,
            true
        );
    }
}
