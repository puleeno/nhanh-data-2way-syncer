<?php

namespace Puleeno\NhanhVn\Syncer\Admin;

class Menu
{
    public const SLUG = 'nhanh-syncer';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('NhanhSync Hub', 'nhanh-data-2way-syncer'),
            __('NhanhSync Hub', 'nhanh-data-2way-syncer'),
            'manage_options',
            self::SLUG,
            [$this, 'render_page'],
            'dashicons-update-alt',
            56
        );

        $submenus = $this->submenus();
        foreach ($submenus as $slug => $item) {
            add_submenu_page(
                self::SLUG,
                $item['title'],
                $item['menu_title'],
                'manage_options',
                $slug,
                [$this, 'render_page']
            );
        }
    }

    public function submenus(): array
    {
        return [
            'nhanh-syncer' => [
                'title' => __('Tổng Quan & Theo Dõi Thời Gian Thực', 'nhanh-data-2way-syncer'),
                'menu_title' => __('Tổng quan', 'nhanh-data-2way-syncer'),
                'group' => 'dashboard',
            ],
            'nhanh-syncer-mapping' => [
                'title' => __('Liên Kết & Đối Soát Nhanh.vn ↔ Website', 'nhanh-data-2way-syncer'),
                'menu_title' => __('Liên kết Sản phẩm', 'nhanh-data-2way-syncer'),
                'group' => 'catalog',
            ],
            'nhanh-syncer-categories' => [
                'title' => __('Đồng Bộ Danh Mục & Thương Hiệu (product_cat / product_brand)', 'nhanh-data-2way-syncer'),
                'menu_title' => __('Danh mục & Thương hiệu', 'nhanh-data-2way-syncer'),
                'group' => 'catalog',
            ],
            'nhanh-syncer-logs' => [
                'title' => __('Nhật Ký Đồng Bộ & Tra Soát Giao Dịch', 'nhanh-data-2way-syncer'),
                'menu_title' => __('Nhật ký & Tra soát', 'nhanh-data-2way-syncer'),
                'group' => 'audit',
            ],
            'nhanh-syncer-reports' => [
                'title' => __('Báo Cáo Doanh Thu & Luân Chuyển Tồn Kho', 'nhanh-data-2way-syncer'),
                'menu_title' => __('Báo cáo', 'nhanh-data-2way-syncer'),
                'group' => 'dashboard',
            ],
            'nhanh-syncer-settings' => [
                'title' => __('Thiết Lập API Nhanh.vn & Webhook', 'nhanh-data-2way-syncer'),
                'menu_title' => __('Cấu hình API', 'nhanh-data-2way-syncer'),
                'group' => 'settings',
            ],
        ];
    }

    public function render_page(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : self::SLUG;
        $pages = new Pages\Renderer();
        $pages->render($page, $this->submenus());
    }
}
