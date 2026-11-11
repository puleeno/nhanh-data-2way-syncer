<?php

namespace Puleeno\NhanhVn\Syncer\Admin\Pages;

class Data
{
    public static function get(): array
    {
        $products = [
            self::p('POLO-PIMA-01-DEN-L', 'Áo Polo Nam Vải Pima Cotton Cao Cấp - Đen / L', 'Thời Trang Nam', 'NOVA Basic', 380000, 46, 'WEB-48201', 380000, 46, 'synced', 'fast', 14, 128, 48640000),
            self::p('POLO-PIMA-01-TRANG-M', 'Áo Polo Nam Vải Pima Cotton Cao Cấp - Trắng / M', 'Thời Trang Nam', 'NOVA Basic', 350000, 12, 'WEB-48202', 380000, 12, 'price_mismatch', 'fast', 11, 164, 57400000),
            self::p('JEAN-SLIM-COGIAN-XANH-31', 'Quần Jean Nam Slim-fit Co Giãn 4 Chiều - Xanh Đậm 31', 'Thời Trang Nam', 'UrbanFit', 520000, 18, 'WEB-48350', 520000, 25, 'stock_mismatch', 'steady', 26, 72, 37440000),
            self::p('DAM-LINEN-SUONG-BEG-S', 'Đầm Linen Dáng Suông Phối Viền Cổ - Màu Be / Size S', 'Thời Trang Nữ', 'Linené', 650000, 34, 'WEB-49102', 650000, 34, 'synced', 'steady', 22, 88, 57200000),
            self::p('BALO-TECH-WATERPROOF-XAM', 'Balo Laptop Chống Nước TechPack Pro 15.6 inch - Xám Titanium', 'Phụ Kiện & Balo', 'TechPack', 790000, 68, 'WEB-50119', 790000, 68, 'synced', 'fast', 16, 142, 112180000),
            self::p('MOUSE-SILENT-DUAL-DEN', 'Chuột Không Dây Silent Click Dual-Mode - Đen Mờ', 'Thiết Bị Công Nghệ', 'LogiNova', 280000, 115, 'WEB-51004', 280000, 115, 'synced', 'fast', 13, 290, 81200000),
            self::p('SON-DUONG-COCO-ORGANIC', 'Son Dưỡng Ẩm Chiết Xuất Dừa Bến Tre Hữu Cơ 5g', 'Mỹ Phẩm & Chăm Sóc', 'CocoCare', 95000, 240, 'WEB-52110', 95000, 240, 'synced', 'fast', 18, 410, 38950000),
            self::p('NHANH-NEW-001', 'Sản phẩm mới tạo trên Nhanh.vn (chưa map)', 'Phụ Kiện & Balo', 'NoBrand', 199000, 30, null, null, null, 'unlinked', 'steady', 30, 0, 0),
        ];

        // Ánh xạ taxonomy: product_cat (danh mục sản phẩm) và product_brand (thương hiệu)
        $term_maps = [
            ['type' => 'category', 'nhanh_id' => 'NCAT-101', 'nhanh' => 'Thời Trang Nam', 'wc_taxonomy' => 'product_cat', 'web' => 'Thời Trang Nam', 'wc_term_id' => 301, 'count' => 128, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'category', 'nhanh_id' => 'NCAT-102', 'nhanh' => 'Thời Trang Nữ', 'wc_taxonomy' => 'product_cat', 'web' => 'Thời Trang Nữ', 'wc_term_id' => 302, 'count' => 96, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'category', 'nhanh_id' => 'NCAT-103', 'nhanh' => 'Phụ Kiện & Balo', 'wc_taxonomy' => 'product_cat', 'web' => 'Phụ Kiện Túi Xách', 'wc_term_id' => 303, 'count' => 54, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'category', 'nhanh_id' => 'NCAT-104', 'nhanh' => 'Thiết Bị Công Nghệ', 'wc_taxonomy' => 'product_cat', 'web' => null, 'wc_term_id' => null, 'count' => 37, 'status' => 'unmapped', 'time' => 'Chưa đồng bộ'],
            ['type' => 'category', 'nhanh_id' => 'NCAT-105', 'nhanh' => 'Mỹ Phẩm & Chăm Sóc', 'wc_taxonomy' => 'product_cat', 'web' => 'Làm Đẹp & Chăm Sóc Da', 'wc_term_id' => 305, 'count' => 62, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'brand', 'nhanh_id' => 'NBR-201', 'nhanh' => 'NOVA Basic', 'wc_taxonomy' => 'product_brand', 'web' => 'NOVA Basic', 'wc_term_id' => 401, 'count' => 84, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'brand', 'nhanh_id' => 'NBR-202', 'nhanh' => 'UrbanFit', 'wc_taxonomy' => 'product_brand', 'web' => 'UrbanFit', 'wc_term_id' => 402, 'count' => 46, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'brand', 'nhanh_id' => 'NBR-203', 'nhanh' => 'Linené', 'wc_taxonomy' => 'product_brand', 'web' => 'Linené', 'wc_term_id' => 403, 'count' => 29, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'brand', 'nhanh_id' => 'NBR-204', 'nhanh' => 'TechPack', 'wc_taxonomy' => 'product_brand', 'web' => null, 'wc_term_id' => null, 'count' => 18, 'status' => 'unmapped', 'time' => 'Chưa đồng bộ'],
            ['type' => 'brand', 'nhanh_id' => 'NBR-205', 'nhanh' => 'LogiNova', 'wc_taxonomy' => 'product_brand', 'web' => 'LogiNova', 'wc_term_id' => 405, 'count' => 33, 'status' => 'synced', 'time' => 'Hôm nay'],
            ['type' => 'brand', 'nhanh_id' => 'NBR-206', 'nhanh' => 'CocoCare', 'wc_taxonomy' => 'product_brand', 'web' => 'CocoCare', 'wc_term_id' => 406, 'count' => 21, 'status' => 'synced', 'time' => 'Hôm nay'],
        ];

        // Ánh xạ đơn hàng: Nhanh.vn ↔ WooCommerce (HPOS-safe qua wc_order_id)
        $order_maps = [
            ['wc_order_id' => 10421, 'nhanh_order_id' => '90412', 'nhanh_order_code' => 'ORD-NH-90412', 'sync_status' => 'synced', 'total_amount' => 1140000, 'source' => 'wc_to_nhanh', 'last_synced_at' => '17:45:10 hôm nay'],
            ['wc_order_id' => 10418, 'nhanh_order_id' => '93210', 'nhanh_order_code' => 'ORD-NH-93210', 'sync_status' => 'synced', 'total_amount' => 380000, 'source' => 'nhanh_to_wc', 'last_synced_at' => '17:58:41 hôm nay'],
            ['wc_order_id' => 10415, 'nhanh_order_id' => '93188', 'nhanh_order_code' => 'ORD-NH-93188', 'sync_status' => 'synced', 'total_amount' => 790000, 'source' => 'nhanh_to_wc', 'last_synced_at' => '17:20:05 hôm nay'],
            ['wc_order_id' => 10410, 'nhanh_order_id' => '93142', 'nhanh_order_code' => 'ORD-NH-93142', 'sync_status' => 'synced', 'total_amount' => 650000, 'source' => 'wc_to_nhanh', 'last_synced_at' => '16:52:30 hôm nay'],
            ['wc_order_id' => 10405, 'nhanh_order_id' => '93101', 'nhanh_order_code' => 'ORD-NH-93101', 'sync_status' => 'pending', 'total_amount' => 520000, 'source' => 'wc_to_nhanh', 'last_synced_at' => '—'],
            ['wc_order_id' => 10398, 'nhanh_order_id' => '93055', 'nhanh_order_code' => 'ORD-NH-93055', 'sync_status' => 'failed', 'total_amount' => 280000, 'source' => 'nhanh_to_wc', 'last_synced_at' => '15:10:12 hôm nay'],
        ];

        $logs = [
            ['time' => '18:02:15', 'type' => 'manual_batch', 'ref' => 'BATCH-1024', 'title' => 'Đồng bộ toàn diện thành công', 'detail' => 'Đã quét và đồng bộ giá bán & tồn kho. Toàn bộ chênh lệch đã được cập nhật.', 'status' => 'success', 'ms' => 820],
            ['time' => '17:58:41', 'type' => 'webhook_order', 'ref' => 'ORD-NH-93210', 'title' => 'Đơn hàng mới tại Nhanh.vn', 'detail' => 'Tự động hạ tồn kho website cho POLO-PIMA-01-DEN-L: 47 -> 46. Đã map WC #10418 ↔ ORD-NH-93210.', 'status' => 'success', 'ms' => 48],
            ['time' => '17:45:10', 'type' => 'webhook_order', 'ref' => 'ORD-NH-90412', 'title' => 'Đồng bộ đơn hàng từ Website về Nhanh.vn', 'detail' => 'Đơn hàng WooCommerce #10421 đã tạo thành công trên Nhanh.vn với ID #ORD-NH-90412.', 'status' => 'success', 'ms' => 145],
            ['time' => '17:45:00', 'type' => 'price_update', 'ref' => 'POLO-PIMA-01-TRANG-M', 'title' => 'Phát hiện lệch giá bán', 'detail' => 'Nhanh 350.000₫, Website 380.000₫. Chờ đồng bộ theo quy tắc.', 'status' => 'warning', 'ms' => 65],
            ['time' => '17:30:12', 'type' => 'stock_update', 'ref' => 'JEAN-SLIM-COGIAN-XANH-31', 'title' => 'Phát hiện lệch tồn kho', 'detail' => 'Nhanh 18, Website 25. Xuất kho tại cửa hàng chưa phản ánh.', 'status' => 'warning', 'ms' => 72],
            ['time' => '17:12:03', 'type' => 'category_sync', 'ref' => 'CAT-305', 'title' => 'Đồng bộ danh mục sản phẩm Mỹ Phẩm & Chăm Sóc', 'detail' => 'Ánh xạ sang term taxonomy product_cat "Làm Đẹp & Chăm Sóc Da".', 'status' => 'success', 'ms' => 120],
            ['time' => '17:05:10', 'type' => 'category_sync', 'ref' => 'NBR-204', 'title' => 'Thương hiệu TechPack chưa ánh xạ', 'detail' => 'Thương hiệu "TechPack" trên Nhanh.vn chưa có term tương ứng trong taxonomy product_brand trên WooCommerce.', 'status' => 'warning', 'ms' => 88],
        ];

        $revenue = [
            ['label' => 'T2', 'web' => 18200000, 'nhanh' => 12400000, 'orders' => 38],
            ['label' => 'T3', 'web' => 22100000, 'nhanh' => 14100000, 'orders' => 45],
            ['label' => 'T4', 'web' => 19800000, 'nhanh' => 16800000, 'orders' => 41],
            ['label' => 'T5', 'web' => 25400000, 'nhanh' => 17200000, 'orders' => 52],
            ['label' => 'T6', 'web' => 28900000, 'nhanh' => 21400000, 'orders' => 61],
            ['label' => 'T7', 'web' => 34100000, 'nhanh' => 25600000, 'orders' => 74],
            ['label' => 'CN', 'web' => 31600000, 'nhanh' => 23200000, 'orders' => 68],
        ];

        $turnover = [
            ['category' => 'Thời Trang Nam', 'ratio' => 6.2, 'dsi' => 19, 'value' => 182000000, 'sold' => 143480000, 'fast' => 55, 'steady' => 30, 'slow' => 10, 'stagnant' => 5],
            ['category' => 'Thời Trang Nữ', 'ratio' => 4.1, 'dsi' => 28, 'value' => 154000000, 'sold' => 67820000, 'fast' => 25, 'steady' => 45, 'slow' => 20, 'stagnant' => 10],
            ['category' => 'Phụ Kiện & Balo', 'ratio' => 5.4, 'dsi' => 21, 'value' => 96000000, 'sold' => 112180000, 'fast' => 45, 'steady' => 35, 'slow' => 15, 'stagnant' => 5],
            ['category' => 'Thiết Bị Công Nghệ', 'ratio' => 7.1, 'dsi' => 16, 'value' => 124000000, 'sold' => 81200000, 'fast' => 60, 'steady' => 25, 'slow' => 10, 'stagnant' => 5],
            ['category' => 'Mỹ Phẩm & Chăm Sóc', 'ratio' => 5.8, 'dsi' => 18, 'value' => 72000000, 'sold' => 38950000, 'fast' => 50, 'steady' => 30, 'slow' => 15, 'stagnant' => 5],
        ];

        return compact('products', 'term_maps', 'order_maps', 'logs', 'revenue', 'turnover');
    }

    private static function p(string $code, string $name, string $cat, string $brand, int $nPrice, int $nStock, ?string $webId, ?int $wPrice, ?int $wStock, string $status, string $velocity, int $days, int $sold, int $rev): array
    {
        return [
            'nhanh_code' => $code,
            'nhanh_name' => $name,
            'category' => $cat,
            'brand' => $brand,
            'nhanh_price' => $nPrice,
            'nhanh_stock' => $nStock,
            'web_id' => $webId,
            'web_price' => $wPrice,
            'web_stock' => $wStock,
            'sync_status' => $status,
            'sell_velocity' => $velocity,
            'turnover_days' => $days,
            'units_sold_month' => $sold,
            'revenue_month' => $rev,
            'last_synced' => 'Hôm nay',
        ];
    }
}
