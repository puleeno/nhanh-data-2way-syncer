<?php

namespace Puleeno\NhanhVn\Syncer\Admin\Pages;

use Puleeno\NhanhVn\Syncer\Contracts\SyncStatusResolverInterface;
use Puleeno\NhanhVn\Syncer\Plugin;
use Puleeno\NhanhVn\Syncer\Service\TermTaxonomyResolver;
use Puleeno\NhanhVn\Syncer\Support\Formatter;

class Renderer
{
    /** @var SyncStatusResolverInterface|null */
    private $statusResolver;

    /** @var TermTaxonomyResolver|null */
    private $taxonomyResolver;

    public function __construct(?SyncStatusResolverInterface $statusResolver = null, ?TermTaxonomyResolver $taxonomyResolver = null)
    {
        $this->statusResolver = $statusResolver;
        $this->taxonomyResolver = $taxonomyResolver;
    }

    public function render(string $page, array $submenus): void
    {
        $settings = Plugin::get_settings();
        $meta = $submenus[$page] ?? reset($submenus);
        $data = Data::get();

        $uiMode = $this->resolveUiMode($settings);
        echo '<div class="wrap nhanh-syncer-wrap ui-mode-' . esc_attr($uiMode) . '">';
        $this->render_header($meta, $settings, $page);

        echo '<div class="nhanh-syncer-shell">';
        $this->render_sidebar($page, $submenus, $data, $settings);
        echo '<main class="nhanh-syncer-main">';

        switch ($page) {
            case 'nhanh-syncer-mapping':
                $this->page_mapping($data, $settings);
                break;
            case 'nhanh-syncer-categories':
                $this->page_taxonomies($data, $settings);
                break;
            case 'nhanh-syncer-orders':
                $this->page_orders($data, $settings);
                break;
            case 'nhanh-syncer-logs':
                $this->page_logs($data, $settings);
                break;
            case 'nhanh-syncer-reports':
                $this->page_reports($data);
                break;
            case 'nhanh-syncer-settings':
                $this->page_settings($settings);
                break;
            case 'nhanh-syncer':
            default:
                $this->page_overview($data, $settings);
                break;
        }

        echo '</main>';
        echo '</div>';
        echo '</div>';
    }

    private function resolveUiMode(array $settings): string
    {
        $mode = isset($settings['ui_mode']) ? sanitize_key((string) $settings['ui_mode']) : 'compact';
        return in_array($mode, ['compact', 'advanced'], true) ? $mode : 'compact';
    }

    private function perPageFor(string $context, array $settings): int
    {
        $mode = $this->resolveUiMode($settings);
        $key = $context . '_per_page_' . $mode;
        $default = $mode === 'advanced' ? 100 : 20;
        $max = $mode === 'advanced' ? 500 : 100;
        $val = isset($settings[$key]) ? (int) $settings[$key] : $default;
        if ($val < 5) $val = 5;
        if ($val > $max) $val = $max;
        $override = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 0;
        if ($override >= 5 && $override <= $max) {
            $val = $override;
        }
        return $val;
    }

    private function paginate(array $items, int $perPage, int $paged): array
    {
        $total = count($items);
        $perPage = max(1, $perPage);
        $totalPages = (int) max(1, (int) ceil($total / $perPage));
        if ($paged < 1) $paged = 1;
        if ($paged > $totalPages) $paged = $totalPages;
        $offset = ($paged - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);
        return [
            'items' => $slice,
            'total' => $total,
            'per_page' => $perPage,
            'paged' => $paged,
            'total_pages' => $totalPages,
            'offset' => $offset,
        ];
    }

    private function renderPagination(string $page, array $pagination, array $extraParams = []): void
    {
        $paged = (int) $pagination['paged'];
        $totalPages = (int) $pagination['total_pages'];
        $total = (int) $pagination['total'];
        $offset = (int) $pagination['offset'];
        $perPage = (int) $pagination['per_page'];
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);
        $base = ['page' => $page, 'per_page' => $perPage];
        foreach ($extraParams as $k => $v) {
            $base[$k] = $v;
        }
        echo '<div class="nhanh-pagination">';
        echo '<div class="nhanh-pagination-info">Hiển thị <strong>' . esc_html((string)$from) . '–' . esc_html((string)$to) . '</strong> / <strong>' . esc_html(number_format_i18n($total)) . '</strong> bản ghi · Trang <strong>' . esc_html((string)$paged) . '</strong>/<strong>' . esc_html((string)$totalPages) . '</strong></div>';
        echo '<div class="nhanh-pagination-links">';
        $first = add_query_arg(array_merge($base, ['paged' => 1]), admin_url('admin.php'));
        $prev = add_query_arg(array_merge($base, ['paged' => max(1, $paged - 1)]), admin_url('admin.php'));
        $next = add_query_arg(array_merge($base, ['paged' => min($totalPages, $paged + 1)]), admin_url('admin.php'));
        $last = add_query_arg(array_merge($base, ['paged' => $totalPages]), admin_url('admin.php'));
        $disFirst = $paged <= 1 ? ' disabled' : '';
        $disLast = $paged >= $totalPages ? ' disabled' : '';
        if ($paged <= 1) {
            echo '<span class="button disabled">« Đầu</span> <span class="button disabled">‹ Trước</span> ';
        } else {
            echo '<a class="button" href="' . esc_url($first) . '">« Đầu</a> <a class="button" href="' . esc_url($prev) . '">‹ Trước</a> ';
        }
        if ($paged >= $totalPages) {
            echo '<span class="button disabled">Sau ›</span> <span class="button disabled">Cuối »</span>';
        } else {
            echo '<a class="button" href="' . esc_url($next) . '">Sau ›</a> <a class="button" href="' . esc_url($last) . '">Cuối »</a>';
        }
        echo '</div></div>';
    }

    private function render_header(array $meta, array $settings, string $page): void
    {
        $title = esc_html($meta['title'] ?? 'Nhanh Data 2-Way Syncer');
        $group_label = esc_html($this->group_label($meta['group'] ?? 'dashboard'));
        $last = !empty($settings['last_heartbeat']) ? esc_html($settings['last_heartbeat']) : 'Chưa có';
        $connected = !empty($settings['is_connected']);
        echo '<div class="nhanh-syncer-header">';
        echo '<div class="nhanh-syncer-breadcrumb"><span>' . $group_label . '</span><span class="sep">/</span><strong>' . $title . '</strong></div>';
        echo '<div class="nhanh-syncer-header-meta">';
        echo '<span>Tự động: ' . esc_html((string)($settings['sync_interval_minutes'] ?? 15)) . ' phút</span>';
        echo '<span class="dot">·</span>';
        echo '<span>Webhook: ' . ($connected ? 'Live' : 'Chưa kết nối') . '</span>';
        echo '<span class="dot">·</span>';
        echo '<span>Cập nhật: <code>' . $last . '</code></span>';
        echo '</div>';
        echo '<div class="nhanh-syncer-header-actions">';
        echo '<button type="button" class="button nhanh-sidebar-toggle" aria-expanded="true" aria-controls="nhanh-syncer-sidebar" title="Thu gọn/mở rộng menu"><span class="dashicons dashicons-menu-alt2" aria-hidden="true"></span> <span class="nhanh-sidebar-toggle-text">Thu gọn menu</span></button>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . $page)) . '">Làm mới</a>';
        if ($page !== 'nhanh-syncer-settings') {
            echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-settings')) . '">Cấu hình API</a>';
        }
        echo '</div>';
        echo '</div>';
    }

    private function group_label(string $group): string
    {
        switch ($group) {
            case 'catalog': return 'Hàng hóa';
            case 'audit': return 'Kiểm toán';
            case 'settings': return 'Cấu hình';
            case 'dashboard':
            default: return 'Hệ thống';
        }
    }

    private function render_sidebar(string $current_page, array $submenus, array $data, array $settings): void
    {
        $groups = [
            'dashboard' => ['title' => 'Tổng Quan & Báo Cáo', 'icon' => 'dashicons-chart-area'],
            'catalog' => ['title' => 'Quản Lý Hàng Hóa', 'icon' => 'dashicons-products'],
            'audit' => ['title' => 'Kiểm Toán & Tra Soát', 'icon' => 'dashicons-backup'],
            'settings' => ['title' => 'Cấu Hình & Tự Động Hóa', 'icon' => 'dashicons-admin-generic'],
        ];
        $current_group = $submenus[$current_page]['group'] ?? 'dashboard';
        $mismatch = 0; $unlinked = 0;
        foreach ($data['products'] as $p) {
            if (in_array($p['sync_status'], ['price_mismatch', 'stock_mismatch'], true)) $mismatch++;
            if ($p['sync_status'] === 'unlinked') $unlinked++;
        }
        $alerts = $mismatch + $unlinked;

        echo '<aside id="nhanh-syncer-sidebar" class="nhanh-syncer-sidebar">';
        echo '<div class="nhanh-syncer-side-brand"><span class="dashicons dashicons-update-alt"></span><div><strong>Nhanh Data 2-Way Syncer</strong><small>Nhanh.vn ↔ WooCommerce</small></div></div>';

        foreach ($groups as $gid => $gmeta) {
            $is_active_group = $gid === $current_group;
            echo '<div class="nhanh-syncer-group ' . ($is_active_group ? 'is-active' : '') . '">';
            echo '<div class="nhanh-syncer-group-title"><span class="dashicons ' . esc_attr($gmeta['icon']) . '"></span><span>' . esc_html($gmeta['title']) . '</span>';
            if ($gid === 'catalog' && $alerts > 0) {
                echo '<em class="nhanh-badge-warn">' . esc_html((string)$alerts) . '</em>';
            }
            echo '</div>';
            echo '<ul class="nhanh-syncer-group-items">';
            foreach ($submenus as $slug => $item) {
                if (($item['group'] ?? '') !== $gid) continue;
                $active = $slug === $current_page ? ' class="is-current"' : '';
                $label = esc_html($item['menu_title']);
                echo '<li' . $active . '><a href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . $label;
                if ($slug === 'nhanh-syncer-mapping' && $alerts > 0) {
                    echo ' <span class="nhanh-badge-inline">' . esc_html((string)$alerts) . '</span>';
                }
                echo '</a></li>';
            }
            echo '</ul></div>';
        }

        $connected = !empty($settings['is_connected']);
        echo '<div class="nhanh-syncer-side-status">';
        echo '<div class="label">Nhanh.vn API</div>';
        echo '<div class="value ' . ($connected ? 'ok' : 'bad') . '">' . ($connected ? 'Kết nối OK' : 'Chưa kết nối') . '</div>';
        echo '<div class="meta">' . esc_html($settings['business_name'] ?: 'Chưa cấu hình doanh nghiệp') . '</div>';
        echo '<div class="meta">Độ trễ: <code>' . esc_html((string)($settings['ping_latency_ms'] ?? 0)) . 'ms</code></div>';
        echo '</div>';
        echo '</aside>';
    }

    private function page_overview(array $data, array $settings): void
    {
        $products = $data['products'];
        $revenue = $data['revenue'];
        $total = count($products);
        $linked = 0; $mismatch = 0; $unlinked = 0; $stock = 0; $inv = 0;
        foreach ($products as $p) {
            if (!empty($p['web_id'])) $linked++;
            if (in_array($p['sync_status'], ['price_mismatch', 'stock_mismatch'], true)) $mismatch++;
            if ($p['sync_status'] === 'unlinked') $unlinked++;
            $stock += (int)$p['nhanh_stock'];
            $inv += (int)$p['nhanh_stock'] * (int)$p['nhanh_price'];
        }
        $mapRate = $total ? ($linked / $total * 100) : 0;
        $today = end($revenue);
        $todayTotal = ($today['web'] ?? 0) + ($today['nhanh'] ?? 0);

        echo '<div class="nhanh-grid kpis">';
        $this->kpi('Tỉ lệ liên kết SKU', number_format($mapRate, 1) . '%', $linked . '/' . $total . ' sản phẩm đã gắn · ' . $unlinked . ' chưa map', 'dashicons-admin-links');
        $this->kpi('Tổng tồn kho Nhanh.vn', number_format_i18n($stock) . ' cái', 'Giá trị kho: ' . $this->vnd_compact($inv), 'dashicons-archive');
        $this->kpi('Lệch giá & tồn kho', (string)$mismatch . ' phát hiện', $mismatch ? 'Cần đối soát trong bảng liên kết' : 'Dữ liệu đang khớp', 'dashicons-warning', $mismatch ? 'warn' : 'ok');
        $this->kpi('Doanh thu gần nhất', $this->vnd_compact($todayTotal), 'Web: ' . $this->vnd_compact($today['web'] ?? 0) . ' · ' . (int)($today['orders'] ?? 0) . ' đơn', 'dashicons-chart-line', 'accent');
        echo '</div>';

        if ($mismatch > 0 || $unlinked > 0) {
            echo '<div class="nhanh-notice warn"><span class="dashicons dashicons-warning"></span><div><strong>Phát hiện ' . esc_html((string)($mismatch + $unlinked)) . ' sản phẩm cần xử lý</strong><p>Có chênh lệch giá/tồn hoặc sản phẩm chưa liên kết giữa Nhanh.vn và Website. Mở "Liên kết Sản phẩm" để đối soát.</p></div><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-mapping')) . '">Mở Bảng Đối Soát</a></div>';
        }

        echo '<div class="nhanh-grid two">';
        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-chart-bar"></span> Doanh thu 7 ngày (Web vs Nhanh.vn)</h3><span class="muted">Đối soát kênh bán</span></div><div class="nhanh-bars">';
        $max = 1;
        foreach ($revenue as $r) { $max = max($max, $r['web'] + $r['nhanh']); }
        foreach ($revenue as $r) {
            $totalDay = $r['web'] + $r['nhanh'];
            $h = max(6, (int)round($totalDay / $max * 160));
            $hw = $totalDay ? (int)round($r['web'] / $totalDay * 100) : 50;
            echo '<div class="nhanh-bar-col"><div class="nhanh-bar" style="height:' . $h . 'px"><span class="web" style="height:' . $hw . '%" title="Web: ' . esc_attr($this->vnd($r['web'])) . '"></span><span class="nhanh" style="height:' . (100-$hw) . '%" title="Nhanh: ' . esc_attr($this->vnd($r['nhanh'])) . '"></span></div><div class="lbl">' . esc_html($r['label']) . '</div><div class="val">' . esc_html($this->vnd_compact($totalDay)) . '</div></div>';
        }
        echo '</div><div class="nhanh-legend"><span><i class="web"></i>Website</span><span><i class="nhanh"></i>Nhanh.vn (POS/Omni)</span></div></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-performance"></span> Tốc độ luân chuyển theo ngành hàng</h3><span class="muted">DSI & vòng quay</span></div><div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Ngành hàng</th><th>Vòng quay</th><th>DSI</th><th>Giá trị tồn</th></tr></thead><tbody>';
        foreach ($data['turnover'] as $t) {
            echo '<tr><td><strong>' . esc_html($t['category']) . '</strong></td><td><span class="pill ok">' . esc_html(number_format($t['ratio'], 1)) . ' vòng</span></td><td>' . esc_html((string)$t['dsi']) . ' ngày</td><td>' . esc_html($this->vnd_compact($t['value'])) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
        echo '</div>';

        echo '<div class="nhanh-grid two">';
        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-shield-alt"></span> Cơ chế đồng bộ tự động</h3></div><div class="nhanh-kv">';
        $this->kv('Tự động cập nhật Giá', !empty($settings['auto_price_update']) ? 'Đang bật (Nhanh → Web)' : 'Tắt', !empty($settings['auto_price_update']));
        $this->kv('Tự động cập nhật Tồn', !empty($settings['auto_stock_update']) ? 'Đang bật (Buffer: ' . (int)($settings['safety_stock_buffer'] ?? 0) . ')' : 'Tắt', !empty($settings['auto_stock_update']));
        $this->kv('Webhook sự kiện tức thì', !empty($settings['webhook_active']) ? 'Kích hoạt' : 'Tắt', !empty($settings['webhook_active']));
        $this->kv('Kết nối API', !empty($settings['is_connected']) ? 'OK · ' . (int)($settings['ping_latency_ms'] ?? 0) . 'ms' : 'Chưa kết nối', !empty($settings['is_connected']));
        $brandTax = $this->taxonomyResolver ? $this->taxonomyResolver->brandTaxonomy() : ($settings['brand_taxonomy'] ?? 'product_brand');
        $this->kv('Taxonomy danh mục & thương hiệu', 'product_cat (Danh mục) / ' . $brandTax . ' (Thương hiệu)', true);
        echo '</div></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-randomize"></span> Sản phẩm cần đối soát</h3><a href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-mapping')) . '">Xem tất cả →</a></div><div class="nhanh-list">';
        $shown = 0;
        foreach ($products as $p) {
            if ($p['sync_status'] === 'synced') continue;
            $badge = $this->status_badge($p['sync_status']);
            echo '<div class="nhanh-list-item"><div><strong>' . esc_html($p['nhanh_code']) . '</strong><div class="muted">' . esc_html($p['nhanh_name']) . '</div></div><div>' . $badge . '</div></div>';
            $shown++; if ($shown >= 5) break;
        }
        if ($shown === 0) echo '<div class="muted">Không có chênh lệch.</div>';
        echo '</div></section>';
        echo '</div>';
    }

    private function page_mapping(array $data, array $settings = []): void
    {
        $products = $data['products'];
        $mode = $this->resolveUiMode($settings);
        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $status = isset($_GET['sync_status']) ? sanitize_key(wp_unslash($_GET['sync_status'])) : 'all';
        $category = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';
        $brand = isset($_GET['brand']) ? sanitize_text_field(wp_unslash($_GET['brand'])) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = $this->perPageFor('products', $settings);

        $categories = [];
        $brands = [];
        foreach ($products as $p) {
            if (!empty($p['category'])) $categories[$p['category']] = true;
            if (!empty($p['brand'])) $brands[$p['brand']] = true;
        }
        $categories = array_keys($categories); sort($categories);
        $brands = array_keys($brands); sort($brands);

        $filtered = array_values(array_filter($products, function ($p) use ($q, $status, $category, $brand) {
            if ($status !== 'all' && $p['sync_status'] !== $status) return false;
            if ($category !== '' && ($p['category'] ?? '') !== $category) return false;
            if ($brand !== '' && ($p['brand'] ?? '') !== $brand) return false;
            if ($q !== '') {
                $hay = strtolower($p['nhanh_code'] . ' ' . $p['nhanh_name'] . ' ' . ($p['web_id'] ?? '') . ' ' . ($p['brand'] ?? '') . ' ' . ($p['category'] ?? ''));
                if (strpos($hay, strtolower($q)) === false) return false;
            }
            return true;
        }));
        $pageData = $this->paginate($filtered, $perPage, $paged);
        $rows = $pageData['items'];
        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-admin-links"></span> Liên Kết Sản Phẩm (Nhanh.vn ↔ WooCommerce)</h3><span class="muted">' . esc_html(number_format_i18n(count($filtered))) . ' / ' . esc_html(number_format_i18n(count($products))) . ' sản phẩm (lọc) · hỗ trợ phân trang cho catalog lớn</span></div>';
        echo '<form method="get" class="nhanh-filters nhanh-filters-advanced"><input type="hidden" name="page" value="nhanh-syncer-mapping" />';
        echo '<input type="hidden" name="per_page" value="' . esc_attr((string)$perPage) . '" />';
        echo '<input type="search" name="q" value="' . esc_attr($q) . '" class="regular-text" placeholder="Tìm SKU, tên, danh mục, thương hiệu, ID..." /> ';
        echo '<select name="sync_status"><option value="all">Tất cả trạng thái</option><option value="synced"' . selected($status,'synced',false) . '>Đã khớp</option><option value="price_mismatch"' . selected($status,'price_mismatch',false) . '>Lệch giá</option><option value="stock_mismatch"' . selected($status,'stock_mismatch',false) . '>Lệch tồn</option><option value="unlinked"' . selected($status,'unlinked',false) . '>Chưa liên kết</option></select> ';
        echo '<select name="category"><option value="">Tất cả danh mục</option>';
        foreach ($categories as $c) echo '<option value="' . esc_attr($c) . '"' . selected($category,$c,false) . '>' . esc_html($c) . '</option>';
        echo '</select> ';
        echo '<select name="brand"><option value="">Tất cả thương hiệu</option>';
        foreach ($brands as $b) echo '<option value="' . esc_attr($b) . '"' . selected($brand,$b,false) . '>' . esc_html($b) . '</option>';
        echo '</select> ';
        echo '<select name="per_page">';
        foreach ([20,50,100,200,500] as $opt) {
            if ($mode === 'compact' && $opt > 100) continue;
            echo '<option value="' . $opt . '"' . selected($perPage,$opt,false) . '>' . $opt . '/trang</option>';
        }
        echo '</select> ';
        echo '<button class="button button-primary">Lọc</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-mapping')) . '">Xóa lọc</a></form>';

        $dense = $mode === 'advanced' ? ' nhanh-table-dense' : '';
        echo '<div class="nhanh-table-wrap' . ($mode === 'advanced' ? ' nhanh-table-sticky' : '') . '"><table class="widefat striped nhanh-table' . $dense . '"><thead><tr>';
        if ($mode === 'advanced') {
            echo '<th>SKU / Mã Nhanh</th><th>Tên sản phẩm</th><th>Danh mục</th><th>Thương hiệu</th><th>Trạng thái</th><th>Web ID</th><th>Tồn Nhanh</th><th>Tồn Web</th><th>Giá Nhanh</th><th>Giá Web</th><th>Velocity</th><th>Cập nhật</th><th>Thao tác</th>';
        } else {
            echo '<th>Sản phẩm trên Nhanh.vn</th><th>Trạng thái</th><th>Đối tượng Website</th><th>Tồn kho</th><th>Giá bán</th><th>Thao tác</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $p) {
            $priceDiff = ($p['web_price'] !== null && (int)$p['web_price'] !== (int)$p['nhanh_price']);
            $stockDiff = ($p['web_stock'] !== null && (int)$p['web_stock'] !== (int)$p['nhanh_stock']);
            $nStock = number_format_i18n((int)$p['nhanh_stock']);
            $wStock = $p['web_stock'] === null ? '—' : number_format_i18n((int)$p['web_stock']);
            echo '<tr>';
            if ($mode === 'advanced') {
                echo '<td class="mono"><strong>' . esc_html($p['nhanh_code']) . '</strong></td>';
                echo '<td>' . esc_html($p['nhanh_name']) . '</td>';
                echo '<td>' . esc_html($p['category'] ?? '—') . '</td>';
                echo '<td>' . esc_html($p['brand'] ?? '—') . '</td>';
                echo '<td>' . $this->status_badge($p['sync_status']) . '</td>';
                echo '<td class="mono">' . (!empty($p['web_id']) ? esc_html($p['web_id']) : '<span class="pill bad">Chưa map</span>') . '</td>';
                echo '<td class="mono">' . $nStock . '</td>';
                echo '<td class="mono ' . ($stockDiff ? 'diff' : '') . '">' . $wStock . '</td>';
                echo '<td class="mono">' . esc_html($this->vnd($p['nhanh_price'])) . '</td>';
                echo '<td class="mono ' . ($priceDiff ? 'diff' : '') . '">' . ($p['web_price'] === null ? '—' : esc_html($this->vnd($p['web_price']))) . '</td>';
                echo '<td>' . $this->velocity_badge($p['sell_velocity']) . '</td>';
                echo '<td class="muted small">' . esc_html($p['last_synced']) . '</td>';
            } else {
                echo '<td><strong>' . esc_html($p['nhanh_name']) . '</strong><div class="muted mono">' . esc_html($p['nhanh_code']) . '</div><div class="muted">' . esc_html($p['category']) . ' · <span class="mono">Thương hiệu:</span> ' . esc_html($p['brand'] ?? '—') . ' · ' . $this->velocity_badge($p['sell_velocity']) . '</div></td>';
                echo '<td>' . $this->status_badge($p['sync_status']) . '<div class="muted small">Cập nhật: ' . esc_html($p['last_synced']) . '</div></td>';
                if (!empty($p['web_id'])) {
                    echo '<td><strong>Đã liên kết: ' . esc_html($p['web_id']) . '</strong><div class="muted">SKU Web: ' . esc_html($p['nhanh_code']) . '</div></td>';
                } else {
                    echo '<td><span class="pill bad">Chưa liên kết Website</span><div class="muted">Chưa có đối tượng tương ứng</div></td>';
                }
                echo '<td><div class="mono">Nhanh: <strong>' . $nStock . '</strong></div><div class="mono ' . ($stockDiff ? 'diff' : '') . '">Web: <strong>' . $wStock . '</strong></div></td>';
                echo '<td><div class="mono">Nhanh: <strong>' . esc_html($this->vnd($p['nhanh_price'])) . '</strong></div><div class="mono ' . ($priceDiff ? 'diff' : '') . '">Web: <strong>' . ($p['web_price'] === null ? '—' : esc_html($this->vnd($p['web_price']))) . '</strong></div></td>';
            }
            echo '<td class="nhanh-actions">';
            echo '<a class="button button-small" href="' . esc_url(add_query_arg(['page'=>'nhanh-syncer-mapping','q'=>$p['nhanh_code']], admin_url('admin.php'))) . '">Đối soát</a> ';
            if (empty($p['web_id'])) echo '<a class="button button-small button-primary" href="#">Liên kết</a>';
            else echo '<a class="button button-small" href="#">Đồng bộ</a>';
            echo '</td></tr>';
        }
        $colspan = $mode === 'advanced' ? 13 : 6;
        if (empty($rows)) {
            echo '<tr><td colspan="' . $colspan . '" class="muted">Không tìm thấy sản phẩm phù hợp bộ lọc.</td></tr>';
        }
        echo '</tbody></table></div>';
        $this->renderPagination('nhanh-syncer-mapping', $pageData, ['q' => $q, 'sync_status' => $status, 'category' => $category, 'brand' => $brand]);
        echo '<p class="description">Gợi ý: với hàng triệu sản phẩm/biến thể, cấu hình chế độ <strong>Advanced</strong> trong <a href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-settings')) . '">Cấu hình &amp; Tự Động Hóa</a> và dùng lọc + phân trang server-side (bảng <code>nhanh_sync_product_map</code>, index theo <code>nhanh_code/sync_status/updated_at</code>) để tránh tải toàn bộ dữ liệu.</p>';
        echo '</section>';
    }

    private function page_taxonomies(array $data, array $settings = []): void
    {
        $terms = isset($data['term_maps']) ? $data['term_maps'] : [];
        $mode = $this->resolveUiMode($settings);
        $type = isset($_GET['term_type']) ? sanitize_key(wp_unslash($_GET['term_type'])) : 'all';
        $status = isset($_GET['map_status']) ? sanitize_key(wp_unslash($_GET['map_status'])) : 'all';
        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = $this->perPageFor('terms', $settings);
        $filtered = array_values(array_filter($terms, function ($t) use ($type, $status, $q) {
            if ($type !== 'all' && ($t['type'] ?? 'category') !== $type) return false;
            if ($status !== 'all' && ($t['status'] ?? '') !== $status) return false;
            if ($q !== '') {
                $hay = strtolower(($t['nhanh'] ?? '') . ' ' . ($t['nhanh_id'] ?? '') . ' ' . ($t['web'] ?? ''));
                if (strpos($hay, strtolower($q)) === false) return false;
            }
            return true;
        }));

        $total = count($terms); $mapped = 0; $unmapped = 0; $catCount = 0; $brandCount = 0;
        foreach ($terms as $t) {
            if (($t['status'] ?? '') === 'synced') $mapped++; else $unmapped++;
            if (($t['type'] ?? '') === 'brand') $brandCount++; else $catCount++;
        }
        $pageData = $this->paginate($filtered, $perPage, $paged);
        $rows = $pageData['items'];
        $brandTax = $this->taxonomyResolver ? $this->taxonomyResolver->brandTaxonomy() : 'product_brand';

        if ($mode !== 'advanced') {
            echo '<div class="nhanh-grid kpis">';
            $this->kpi('Tổng danh mục & thương hiệu Nhanh.vn', (string)$total, $catCount . ' danh mục sản phẩm · ' . $brandCount . ' thương hiệu', 'dashicons-networking');
            $this->kpi('Đã liên kết WooCommerce', (string)$mapped, 'Khớp taxonomy product_cat / ' . $brandTax, 'dashicons-yes-alt', 'ok');
            $this->kpi('Chưa ánh xạ', (string)$unmapped, 'Cần ánh xạ vào taxonomy WooCommerce', 'dashicons-warning', $unmapped ? 'warn' : 'ok');
            $this->kpi('Taxonomy WooCommerce', '2 loại', 'product_cat (Danh mục) · ' . $brandTax . ' (Thương hiệu)', 'dashicons-tag', 'accent');
            echo '</div>';
        }

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-networking"></span> Đồng Bộ Danh Mục & Thương Hiệu Sản Phẩm (Nhanh.vn ↔ WooCommerce)</h3><span class="muted">' . esc_html(number_format_i18n(count($filtered))) . ' / ' . esc_html(number_format_i18n($total)) . ' term</span></div>';
        echo '<form method="get" class="nhanh-filters nhanh-filters-advanced"><input type="hidden" name="page" value="nhanh-syncer-categories" />';
        echo '<input type="hidden" name="per_page" value="' . esc_attr((string)$perPage) . '" />';
        echo '<input type="search" name="q" value="' . esc_attr($q) . '" class="regular-text" placeholder="Tìm tên/ID danh mục, thương hiệu..." /> ';
        echo '<select name="term_type"><option value="all"' . selected($type,'all',false) . '>Tất cả (Danh mục & Thương hiệu)</option><option value="category"' . selected($type,'category',false) . '>Danh mục sản phẩm (product_cat)</option><option value="brand"' . selected($type,'brand',false) . '>Thương hiệu (' . esc_attr($brandTax) . ')</option></select> ';
        echo '<select name="map_status"><option value="all"' . selected($status,'all',false) . '>Tất cả trạng thái</option><option value="synced"' . selected($status,'synced',false) . '>Đã khớp</option><option value="pending"' . selected($status,'pending',false) . '>Chờ xử lý</option><option value="unmapped"' . selected($status,'unmapped',false) . '>Chưa ánh xạ</option></select> ';
        echo '<select name="per_page">';
        foreach ([20,50,100,200,500] as $opt) { if ($mode === 'compact' && $opt > 100) continue; echo '<option value="' . $opt . '"' . selected($perPage,$opt,false) . '>' . $opt . '/trang</option>'; }
        echo '</select> ';
        echo '<button class="button button-primary">Lọc</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-categories')) . '">Xóa lọc</a></form>';

        $dense = $mode === 'advanced' ? ' nhanh-table-dense' : '';
        echo '<div class="nhanh-table-wrap' . ($mode === 'advanced' ? ' nhanh-table-sticky' : '') . '"><table class="widefat striped nhanh-table' . $dense . '"><thead><tr>';
        if ($mode === 'advanced') {
            echo '<th>Loại</th><th>ID Nhanh</th><th>Tên Nhanh</th><th>WC Taxonomy</th><th>Số SP</th><th>Term WC (ID)</th><th>Tên term WC</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th>';
        } else {
            echo '<th>Loại</th><th>Tên trên Nhanh.vn</th><th>Taxonomy WooCommerce</th><th>Số SP</th><th>Term WooCommerce tương ứng</th><th>Trạng thái</th><th>Thao tác</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $t) {
            $isBrand = ($t['type'] ?? '') === 'brand';
            $typeLabel = $isBrand ? 'Thương hiệu' : 'Danh mục sản phẩm';
            $typePill = $isBrand ? 'neutral' : 'ok';
            echo '<tr>';
            if ($mode === 'advanced') {
                echo '<td><span class="pill ' . $typePill . '">' . esc_html($typeLabel) . '</span></td>';
                echo '<td class="mono">' . esc_html($t['nhanh_id'] ?? '') . '</td>';
                echo '<td><strong>' . esc_html($t['nhanh'] ?? '') . '</strong></td>';
                echo '<td class="mono">' . esc_html($t['wc_taxonomy'] ?? '') . '</td>';
                echo '<td class="mono">' . number_format_i18n((int)($t['count'] ?? 0)) . '</td>';
                echo '<td class="mono">' . (!empty($t['wc_term_id']) ? esc_html((string)$t['wc_term_id']) : '—') . '</td>';
                echo '<td>' . (!empty($t['web']) ? esc_html($t['web']) : '<span class="pill bad">Chưa ánh xạ</span>') . '</td>';
                echo '<td>' . (($t['status'] ?? '') === 'synced' ? '<span class="pill ok">Đã khớp</span>' : '<span class="pill warn">Chờ xử lý</span>') . '</td>';
                echo '<td class="muted small">' . esc_html($t['time'] ?? '') . '</td>';
            } else {
                echo '<td><span class="pill ' . $typePill . '">' . esc_html($typeLabel) . '</span></td>';
                echo '<td><strong>' . esc_html($t['nhanh']) . '</strong><div class="muted">ID: ' . esc_html($t['nhanh_id']) . '</div></td>';
                echo '<td class="mono">' . esc_html($t['wc_taxonomy']) . '</td>';
                echo '<td class="mono">' . number_format_i18n((int)($t['count'] ?? 0)) . '</td>';
                echo '<td>' . (!empty($t['web']) ? '<strong>' . esc_html($t['web']) . '</strong><div class="muted mono">term_id: ' . esc_html((string)$t['wc_term_id']) . '</div>' : '<span class="pill bad">Chưa ánh xạ</span><div class="muted">Chưa có term WC tương ứng</div>') . '</td>';
                echo '<td>' . (($t['status'] ?? '') === 'synced' ? '<span class="pill ok">Đã khớp</span>' : '<span class="pill warn">Chờ xử lý</span>') . '<div class="muted small">' . esc_html($t['time'] ?? '') . '</div></td>';
            }
            echo '<td><a class="button button-small" href="#">' . (!empty($t['web']) ? 'Thay đổi' : 'Ánh xạ') . '</a></td>';
            echo '</tr>';
        }
        $colspan = $mode === 'advanced' ? 10 : 7;
        if (empty($rows)) echo '<tr><td colspan="' . $colspan . '" class="muted">Không có danh mục hoặc thương hiệu phù hợp.</td></tr>';
        echo '</tbody></table></div>';
        $this->renderPagination('nhanh-syncer-categories', $pageData, ['term_type' => $type, 'map_status' => $status, 'q' => $q]);
        echo '<p class="description">Danh mục (product_cat) và thương hiệu (' . esc_html($brandTax) . ') được quản lý chung qua bảng ánh xạ taxonomy. Chế độ hiển thị (Compact/Advanced) được cấu hình trong <a href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-settings')) . '">Cấu hình &amp; Tự Động Hóa</a>.</p>';
        echo '</section>';
    }

    private function page_logs(array $data, array $settings = []): void
    {
        $logs = $data['logs'];
        $mode = $this->resolveUiMode($settings);
        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $st = isset($_GET['log_status']) ? sanitize_key(wp_unslash($_GET['log_status'])) : 'all';
        $typeFilter = isset($_GET['log_type']) ? sanitize_key(wp_unslash($_GET['log_type'])) : 'all';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = $this->perPageFor('logs', $settings);
        $filtered = array_values(array_filter($logs, function ($l) use ($q, $st, $typeFilter) {
            if ($st !== 'all' && $l['status'] !== $st) return false;
            if ($typeFilter !== 'all' && ($l['type'] ?? '') !== $typeFilter) return false;
            if ($q !== '') {
                $hay = strtolower($l['title'] . ' ' . $l['detail'] . ' ' . $l['ref']);
                if (strpos($hay, strtolower($q)) === false) return false;
            }
            return true;
        }));
        $pageData = $this->paginate($filtered, $perPage, $paged);
        $rows = $pageData['items'];

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-backup"></span> Nhật Ký Đồng Bộ & Tra Soát Giao Dịch</h3><span class="muted">' . esc_html(number_format_i18n(count($filtered))) . ' sự kiện</span></div>';
        echo '<form method="get" class="nhanh-filters nhanh-filters-advanced"><input type="hidden" name="page" value="nhanh-syncer-logs" />';
        echo '<input type="hidden" name="per_page" value="' . esc_attr((string)$perPage) . '" />';
        echo '<input type="search" name="q" value="' . esc_attr($q) . '" class="regular-text" placeholder="Tìm theo tiêu đề, mã, chi tiết..." /> ';
        echo '<select name="log_status"><option value="all">Tất cả trạng thái</option><option value="success"' . selected($st,'success',false) . '>Thành công</option><option value="warning"' . selected($st,'warning',false) . '>Cảnh báo</option><option value="error"' . selected($st,'error',false) . '>Lỗi</option></select> ';
        echo '<select name="log_type"><option value="all"' . selected($typeFilter,'all',false) . '>Tất cả loại</option>';
        foreach (['stock_update'=>'Tồn kho','price_update'=>'Giá','product_sync'=>'Sản phẩm','category_sync'=>'Danh mục/Thương hiệu','webhook_order'=>'Đơn hàng','manual_batch'=>'Batch'] as $k=>$v) {
            echo '<option value="' . esc_attr($k) . '"' . selected($typeFilter,$k,false) . '>' . esc_html($v) . '</option>';
        }
        echo '</select> ';
        echo '<select name="per_page">';
        foreach ([20,50,100,200,500] as $opt) { if ($mode === 'compact' && $opt > 100) continue; echo '<option value="' . $opt . '"' . selected($perPage,$opt,false) . '>' . $opt . '/trang</option>'; }
        echo '</select> ';
        echo '<button class="button button-primary">Lọc</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-logs')) . '">Xóa lọc</a></form>';

        $dense = $mode === 'advanced' ? ' nhanh-table-dense' : '';
        echo '<div class="nhanh-table-wrap' . ($mode === 'advanced' ? ' nhanh-table-sticky' : '') . '"><table class="widefat striped nhanh-table' . $dense . '"><thead><tr>';
        if ($mode === 'advanced') {
            echo '<th>Thời gian</th><th>Loại</th><th>Mã tham chiếu</th><th>Tiêu đề</th><th>Chi tiết</th><th>Trạng thái</th><th>Xử lý</th>';
        } else {
            echo '<th>Thời gian</th><th>Loại</th><th>Mã tham chiếu</th><th>Sự kiện</th><th>Trạng thái</th><th>Xử lý</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $l) {
            $pill = $l['status'] === 'success' ? 'ok' : ($l['status'] === 'warning' ? 'warn' : 'bad');
            $label = $l['status'] === 'success' ? 'Thành công' : ($l['status'] === 'warning' ? 'Cảnh báo' : 'Lỗi');
            echo '<tr><td class="mono">' . esc_html($l['time']) . '</td><td><span class="pill neutral">' . esc_html($this->log_type_label($l['type'])) . '</span></td><td class="mono">' . esc_html($l['ref']) . '</td>';
            if ($mode === 'advanced') {
                echo '<td><strong>' . esc_html($l['title']) . '</strong></td><td class="muted">' . esc_html($l['detail']) . '</td>';
            } else {
                echo '<td><strong>' . esc_html($l['title']) . '</strong><div class="muted">' . esc_html($l['detail']) . '</div></td>';
            }
            echo '<td><span class="pill ' . $pill . '">' . $label . '</span></td><td class="mono">' . (int)$l['ms'] . ' ms</td></tr>';
        }
        $colspan = $mode === 'advanced' ? 7 : 6;
        if (empty($rows)) echo '<tr><td colspan="' . $colspan . '" class="muted">Không có log phù hợp.</td></tr>';
        echo '</tbody></table></div>';
        $this->renderPagination('nhanh-syncer-logs', $pageData, ['q'=>$q,'log_status'=>$st,'log_type'=>$typeFilter]);
        echo '<p class="description">Lưu ý: tra soát trên hệ thống lớn nên truy vấn theo trang từ bảng <code>nhanh_sync_logs</code> (index <code>event_time/type/status/reference_code/dedupe_key</code>). Chế độ hiển thị được cấu hình trong <a href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-settings')) . '">Cấu hình &amp; Tự Động Hóa</a>.</p>';
        echo '</section>';
    }

    private function page_orders(array $data, array $settings = []): void
    {
        $orders = isset($data['order_maps']) ? $data['order_maps'] : [];
        $mode = $this->resolveUiMode($settings);
        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $status = isset($_GET['sync_status']) ? sanitize_key(wp_unslash($_GET['sync_status'])) : 'all';
        $source = isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : 'all';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = $this->perPageFor('orders', $settings);

        $filtered = array_values(array_filter($orders, function ($o) use ($q, $status, $source) {
            if ($status !== 'all' && ($o['sync_status'] ?? '') !== $status) return false;
            if ($source !== 'all' && ($o['source'] ?? '') !== $source) return false;
            if ($q !== '') {
                $hay = strtolower((string)($o['wc_order_id'] ?? '') . ' ' . (string)($o['nhanh_order_id'] ?? '') . ' ' . (string)($o['nhanh_order_code'] ?? ''));
                if (strpos($hay, strtolower($q)) === false) return false;
            }
            return true;
        }));
        $pageData = $this->paginate($filtered, $perPage, $paged);
        $rows = $pageData['items'];

        $totalMapped = count($orders);
        $synced = count(array_filter($orders, function ($o) { return ($o['sync_status'] ?? '') === 'synced'; }));
        $pending = count(array_filter($orders, function ($o) { return ($o['sync_status'] ?? '') === 'pending'; }));
        $failed = count(array_filter($orders, function ($o) { return in_array(($o['sync_status'] ?? ''), ['failed','error'], true); }));

        echo '<div class="nhanh-grid kpis">';
        $this->kpi('Tổng bản ghi map', number_format_i18n($totalMapped), 'Bảng nhanh_sync_order_map', 'dashicons-database');
        $this->kpi('Đã đồng bộ', number_format_i18n($synced), 'WC ↔ Nhanh.vn đã khớp', 'dashicons-yes-alt', 'ok');
        $this->kpi('Đang chờ', number_format_i18n($pending), 'Chờ xử lý / retry', 'dashicons-clock', $pending > 0 ? 'warn' : '');
        $this->kpi('Lỗi / Thất bại', number_format_i18n($failed), 'Cần tra soát', 'dashicons-warning', $failed > 0 ? 'warn' : 'ok');
        echo '</div>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-cart"></span> Liên Kết Đơn Hàng (Nhanh.vn ↔ WooCommerce)</h3><span class="muted">' . esc_html(number_format_i18n(count($filtered))) . ' / ' . esc_html(number_format_i18n($totalMapped)) . ' bản ghi · HPOS-safe (<code>wc_order_id</code>)</span></div>';
        echo '<form method="get" class="nhanh-filters nhanh-filters-advanced"><input type="hidden" name="page" value="nhanh-syncer-orders" />';
        echo '<input type="hidden" name="per_page" value="' . esc_attr((string)$perPage) . '" />';
        echo '<input type="search" name="q" value="' . esc_attr($q) . '" class="regular-text" placeholder="Tìm theo WC Order ID, Nhanh ID, mã đơn..." /> ';
        echo '<select name="sync_status"><option value="all">Tất cả trạng thái</option><option value="synced"' . selected($status,'synced',false) . '>Đã đồng bộ</option><option value="pending"' . selected($status,'pending',false) . '>Đang chờ</option><option value="failed"' . selected($status,'failed',false) . '>Lỗi</option><option value="unlinked"' . selected($status,'unlinked',false) . '>Chưa liên kết</option></select> ';
        echo '<select name="source"><option value="all">Tất cả chiều</option><option value="wc_to_nhanh"' . selected($source,'wc_to_nhanh',false) . '>Website → Nhanh.vn</option><option value="nhanh_to_wc"' . selected($source,'nhanh_to_wc',false) . '>Nhanh.vn → Website</option></select> ';
        echo '<select name="per_page">';
        foreach ([20,50,100,200,500] as $opt) {
            if ($mode === 'compact' && $opt > 100) continue;
            echo '<option value="' . $opt . '"' . selected($perPage,$opt,false) . '>' . $opt . '/trang</option>';
        }
        echo '</select> ';
        echo '<button class="button button-primary">Lọc</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-orders')) . '">Xóa lọc</a></form>';

        $dense = $mode === 'advanced' ? ' nhanh-table-dense' : '';
        echo '<div class="nhanh-table-wrap' . ($mode === 'advanced' ? ' nhanh-table-sticky' : '') . '"><table class="widefat striped nhanh-table' . $dense . '"><thead><tr>';
        if ($mode === 'advanced') {
            echo '<th>WC Order ID</th><th>Đơn hàng WooCommerce</th><th>Nhanh Order ID</th><th>Mã đơn Nhanh.vn</th><th>Chiều đồng bộ</th><th>Giá trị</th><th>Trạng thái</th><th>Đồng bộ lần cuối</th><th>Thao tác</th>';
        } else {
            echo '<th>Đơn hàng WooCommerce</th><th>Đơn hàng Nhanh.vn</th><th>Chiều / Giá trị</th><th>Trạng thái</th><th>Đồng bộ lần cuối</th><th>Thao tác</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $o) {
            $wcId = $o['wc_order_id'] ?? null;
            $nhanhId = $o['nhanh_order_id'] ?? '';
            $nhanhCode = $o['nhanh_order_code'] ?? ($nhanhId ? ('ORD-NH-' . $nhanhId) : '');
            $statusVal = $o['sync_status'] ?? 'unlinked';
            $sourceVal = $o['source'] ?? '';
            $sourceLabel = $sourceVal === 'wc_to_nhanh' ? 'Website → Nhanh.vn' : ($sourceVal === 'nhanh_to_wc' ? 'Nhanh.vn → Website' : '—');
            $total = isset($o['total_amount']) ? $this->vnd($o['total_amount']) : '—';
            $last = $o['last_synced_at'] ?? '—';
            $wcEdit = $wcId ? admin_url('post.php?post=' . intval($wcId) . '&action=edit') : '';
            echo '<tr>';
            if ($mode === 'advanced') {
                echo '<td class="mono">' . ($wcId ? esc_html('#' . $wcId) : '<span class="pill bad">Chưa map</span>') . '</td>';
                echo '<td>' . ($wcId ? '<a href="' . esc_url($wcEdit) . '"><strong>Đơn hàng #' . esc_html((string)$wcId) . '</strong></a>' : '<span class="muted">Chưa liên kết WooCommerce</span>') . '</td>';
                echo '<td class="mono">' . ($nhanhId !== '' ? esc_html((string)$nhanhId) : '—') . '</td>';
                echo '<td class="mono">' . ($nhanhCode !== '' ? esc_html($nhanhCode) : '<span class="pill bad">Chưa map</span>') . '</td>';
                echo '<td>' . esc_html($sourceLabel) . '</td>';
                echo '<td class="mono">' . esc_html($total) . '</td>';
                echo '<td>' . $this->status_badge($statusVal) . '</td>';
                echo '<td class="muted small">' . esc_html($last) . '</td>';
            } else {
                echo '<td>' . ($wcId ? '<a href="' . esc_url($wcEdit) . '"><strong>Đơn hàng #' . esc_html((string)$wcId) . '</strong></a>' : '<span class="pill bad">Chưa liên kết Website</span>') . '<div class="muted mono">wc_order_id: ' . esc_html($wcId ? (string)$wcId : '—') . '</div></td>';
                echo '<td><strong class="mono">' . esc_html($nhanhCode !== '' ? $nhanhCode : '—') . '</strong><div class="muted mono">nhanh_order_id: ' . esc_html($nhanhId !== '' ? (string)$nhanhId : '—') . '</div></td>';
                echo '<td>' . esc_html($sourceLabel) . '<div class="mono"><strong>' . esc_html($total) . '</strong></div></td>';
                echo '<td>' . $this->status_badge($statusVal) . '</td>';
                echo '<td class="muted small">' . esc_html($last) . '</td>';
            }
            echo '<td class="nhanh-actions">';
            if ($wcId) echo '<a class="button button-small" href="' . esc_url($wcEdit) . '">Xem đơn WC</a> ';
            echo '<a class="button button-small" href="' . esc_url(add_query_arg(['page'=>'nhanh-syncer-orders','q'=>$nhanhCode ?: (string)$wcId], admin_url('admin.php'))) . '">Đối soát</a> ';
            if ($statusVal === 'failed' || $statusVal === 'unlinked' || empty($nhanhId)) echo '<a class="button button-small button-primary" href="#">Liên kết / Đồng bộ</a>';
            else echo '<a class="button button-small" href="#">Đồng bộ lại</a>';
            echo '</td></tr>';
        }
        $colspan = $mode === 'advanced' ? 9 : 6;
        if (empty($rows)) {
            echo '<tr><td colspan="' . $colspan . '" class="muted">Không tìm thấy bản ghi liên kết đơn hàng phù hợp bộ lọc.</td></tr>';
        }
        echo '</tbody></table></div>';
        $this->renderPagination('nhanh-syncer-orders', $pageData, ['q' => $q, 'sync_status' => $status, 'source' => $source]);
        echo '<p class="description">Gợi ý: tra cứu và đối soát đơn hàng trên hệ thống lớn nên truy vấn theo trang từ bảng <code>nhanh_sync_order_map</code> (index <code>wc_order_id/nhanh_order_id/sync_status/updated_at</code>). Hỗ trợ HPOS thông qua <code>wc_order_id</code>.</p>';
        echo '</section>';
    }

    private function page_reports(array $data): void
    {
        $products = $data['products'];
        $revenue = $data['revenue'];
        $turnover = $data['turnover'];
        $totalSoldUnits = 0; $totalSoldRevenue = 0; $totalStockValue = 0; $dsi = 0;
        foreach ($products as $p) { $totalSoldUnits += (int)$p['units_sold_month']; $totalSoldRevenue += (int)$p['revenue_month']; $totalStockValue += (int)$p['nhanh_stock']*(int)$p['nhanh_price']; $dsi += (int)$p['turnover_days']; }
        $avgDsi = $products ? (int)round($dsi / count($products)) : 0;
        $weekTotal = 0; foreach ($revenue as $r) $weekTotal += $r['web'] + $r['nhanh'];

        echo '<div class="nhanh-grid kpis">';
        $this->kpi('Doanh thu 7 ngày', $this->vnd_compact($weekTotal), 'Tổng Web + Nhanh.vn', 'dashicons-money-alt', 'accent');
        $this->kpi('Doanh thu xuất kho tháng', $this->vnd_compact($totalSoldRevenue), number_format_i18n($totalSoldUnits) . ' sản phẩm đã bán', 'dashicons-chart-line');
        $this->kpi('Giá trị tồn kho', $this->vnd_compact($totalStockValue), 'Vốn đang lưu chuyển tại kho', 'dashicons-archive');
        $this->kpi('DSI trung bình', $avgDsi . ' ngày', 'Số ngày bán hết tồn bình quân', 'dashicons-clock', $avgDsi <= 25 ? 'ok' : 'warn');
        echo '</div>';

        echo '<div class="nhanh-grid two">';
        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3>Doanh thu đối soát Web vs Nhanh.vn</h3></div><div class="nhanh-bars">';
        $max = 1; foreach ($revenue as $r) { $max = max($max, $r['web'] + $r['nhanh']); }
        foreach ($revenue as $r) {
            $totalDay = $r['web'] + $r['nhanh']; $h = max(6, (int)round($totalDay / $max * 150)); $hw = $totalDay ? (int)round($r['web']/$totalDay*100) : 50;
            echo '<div class="nhanh-bar-col"><div class="nhanh-bar" style="height:' . $h . 'px"><span class="web" style="height:' . $hw . '%"></span><span class="nhanh" style="height:' . (100-$hw) . '%"></span></div><div class="lbl">' . esc_html($r['label']) . '</div><div class="val">' . esc_html($this->vnd_compact($totalDay)) . '</div></div>';
        }
        echo '</div></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3>Luân chuyển theo ngành hàng</h3></div><div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Ngành hàng</th><th>Vòng quay</th><th>DSI</th><th>Doanh thu bán</th></tr></thead><tbody>';
        foreach ($turnover as $t) {
            echo '<tr><td><strong>' . esc_html($t['category']) . '</strong></td><td>' . esc_html(number_format($t['ratio'],1)) . ' vòng/năm</td><td>' . esc_html((string)$t['dsi']) . ' ngày</td><td>' . esc_html($this->vnd_compact($t['sold'])) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
        echo '</div>';

        $fast = $products; usort($fast, function($a,$b){return $b['units_sold_month'] <=> $a['units_sold_month'];}); $fast = array_slice($fast,0,5);
        $slow = $products; usort($slow, function($a,$b){return $b['turnover_days'] <=> $a['turnover_days'];}); $slow = array_slice($slow,0,5);
        echo '<div class="nhanh-grid two">';
        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-thumbs-up"></span> Top sản phẩm bán chạy</h3></div><div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Sản phẩm</th><th>Đã bán/tháng</th><th>Doanh thu</th><th>Tốc độ</th></tr></thead><tbody>';
        foreach ($fast as $p) echo '<tr><td><strong>' . esc_html($p['nhanh_code']) . '</strong><div class="muted">' . esc_html($p['nhanh_name']) . '</div></td><td class="mono">' . number_format_i18n((int)$p['units_sold_month']) . '</td><td>' . esc_html($this->vnd_compact($p['revenue_month'])) . '</td><td>' . $this->velocity_badge($p['sell_velocity']) . '</td></tr>';
        echo '</tbody></table></div></section>';
        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-warning"></span> Nguy cơ tồn đọng / chậm luân chuyển</h3></div><div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Sản phẩm</th><th>Tồn Nhanh</th><th>DSI</th><th>Tốc độ</th></tr></thead><tbody>';
        foreach ($slow as $p) echo '<tr><td><strong>' . esc_html($p['nhanh_code']) . '</strong><div class="muted">' . esc_html($p['nhanh_name']) . '</div></td><td class="mono">' . number_format_i18n((int)$p['nhanh_stock']) . '</td><td>' . esc_html((string)$p['turnover_days']) . ' ngày</td><td>' . $this->velocity_badge($p['sell_velocity']) . '</td></tr>';
        echo '</tbody></table></div></section>';
        echo '</div>';
    }

    private function page_settings(array $settings): void
    {
        $saved = isset($_GET['nhanh_saved']);
        $test = isset($_GET['nhanh_test']) ? sanitize_key(wp_unslash($_GET['nhanh_test'])) : '';
        $oauth = isset($_GET['nhanh_oauth']) ? sanitize_key(wp_unslash($_GET['nhanh_oauth'])) : '';
        if ($saved) echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cấu hình Nhanh Data 2-Way Syncer.</p></div>';
        if ($test === 'ok') echo '<div class="notice notice-success is-dismissible"><p>Kết nối API Nhanh.vn thành công (mô phỏng kiểm tra cấu hình).</p></div>';
        if ($test === 'fail') echo '<div class="notice notice-error is-dismissible"><p>Chưa thể kết nối: vui lòng kiểm tra App ID và Access Token.</p></div>';
        if ($oauth === 'ok') echo '<div class="notice notice-success is-dismissible"><p>Authorize thành công: đã nhận Access Token từ Nhanh.vn. Hãy lưu/kiểm tra kết nối nếu cần.</p></div>';
        if ($oauth === 'denied') echo '<div class="notice notice-error is-dismissible"><p>Authorize bị hủy hoặc bị từ chối trên Nhanh.vn.</p></div>';
        if ($oauth === 'invalid_verify') echo '<div class="notice notice-error is-dismissible"><p>Authorize thất bại: <code>verifyCode</code> không khớp (bảo vệ CSRF theo spec v3). Vui lòng thử lại.</p></div>';
        if ($oauth === 'missing_app_id') echo '<div class="notice notice-error is-dismissible"><p>Chưa thể authorize: vui lòng nhập App ID và lưu cấu hình trước.</p></div>';
        if ($oauth === 'missing_credentials') echo '<div class="notice notice-error is-dismissible"><p>Chưa thể authorize: cần App ID và Secret Key để đổi access code lấy access token.</p></div>';
        if ($oauth === 'no_token') echo '<div class="notice notice-error is-dismissible"><p>Authorize không thành công: không nhận được Access Token từ Nhanh.vn.</p></div>';
        if ($oauth === 'error') echo '<div class="notice notice-error is-dismissible"><p>Có lỗi trong quá trình authorize/trao đổi token với Nhanh.vn. Vui lòng kiểm tra App ID, Secret Key và thử lại.</p></div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="nhanh-settings-form">';
        echo '<input type="hidden" name="action" value="nhanh_syncer_save_settings" />';
        wp_nonce_field('nhanh_syncer_save_settings');

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-admin-network"></span> Thông Tin Xác Thực Nhanh.vn Open API</h3><span class="muted">open.nhanh.vn</span></div><div class="nhanh-form-grid">';
        $this->field_text('app_id', 'App ID (Mã Ứng Dụng)', $settings['app_id'] ?? '');
        $this->field_text('business_id', 'Business ID (Doanh Nghiệp)', $settings['business_id'] ?? '');
        $this->field_text('business_name', 'Tên doanh nghiệp / cửa hàng', $settings['business_name'] ?? '', 'text', false);
        $this->field_text('secret_key', 'Secret Key (Khóa Bí Mật)', $settings['secret_key'] ?? '', 'password');
        $returnLinkDefault = function_exists('admin_url') ? admin_url('admin.php?page=nhanh-syncer-settings') : '';
        $returnLink = isset($settings['return_link']) && trim((string)$settings['return_link']) !== '' ? (string)$settings['return_link'] : $returnLinkDefault;
        $appIdPreview = isset($settings['app_id']) ? trim((string)$settings['app_id']) : '';
        echo '<div class="nhanh-field full"><label for="nhanh_return_link">Redirect URL (OAuth Callback) — phải khớp CHÍNH XÁC URL đã đăng ký trên Nhanh.vn</label>';
        echo '<input type="text" id="nhanh_return_link" name="return_link" value="' . esc_attr($returnLink) . '" class="mono regular-text" onclick="this.select();" />';
        echo '<p class="description">URL này sẽ được gửi làm <code>returnLink</code> khi authorize và phải trùng khớp tuyệt đối với Return Link/Redirect URL đã cấu hình trong ứng dụng trên Nhanh.vn (kể cả http/https, domain, path, query, dấu <code>/</code>). Sao chép/dán đúng URL đã đăng ký trên Nhanh.vn rồi bấm <strong>Lưu cấu hình</strong>. Sai lệch sẽ gây lỗi <code>ERR_INVALID_DATA (Invalid appId or returnLink...)</code>.</p>';
        if ($appIdPreview !== '') {
            $previewAuth = 'https://nhanh.vn/oauth?appId=' . rawurlencode($appIdPreview) . '&service=pos&verifyCode={VERIFY_CODE}&returnLink=' . rawurlencode($returnLink);
            echo '<p class="description">Authorize URL (xem trước, theo spec v3): <code style="word-break:break-all;">' . esc_html($previewAuth) . '</code></p>';
            echo '<p class="description">Luồng v3 yêu cầu <code>service</code> (pos/ship) và <code>verifyCode</code> (tạo ngẫu nhiên 12–64 ký tự, lưu tạm và đối chiếu khi callback) trong URL authorize; khi đổi token cũng phải gửi kèm <code>verifyCode</code>. Nhanh.vn trả <code>ERR_INVALID_DATA</code> nếu <code>appId</code>/<code>returnLink</code>/<code>service</code>/<code>verifyCode</code> không hợp lệ hoặc không khớp cấu hình app.</p>';
        }
        echo '</div>';
        $accessToken = isset($settings['access_token']) ? trim((string) $settings['access_token']) : '';
        if ($accessToken === '') {
            $authUrl = wp_nonce_url(admin_url('admin-post.php?action=nhanh_syncer_oauth_authorize'), 'nhanh_syncer_oauth_authorize');
            $appId = isset($settings['app_id']) ? trim((string) $settings['app_id']) : '';
            $secretKey = isset($settings['secret_key']) ? trim((string) $settings['secret_key']) : '';
            echo '<div class="nhanh-field full"><label>Access Token</label>';
            if ($appId === '' || $secretKey === '') {
                echo '<p class="description" style="margin:4px 0 8px;color:#b91c1c;">Chưa có Access Token. Vui lòng nhập <strong>App ID</strong> và <strong>Secret Key</strong>, bấm <strong>Lưu cấu hình</strong>, sau đó quay lại để Authorize với Nhanh.vn.</p>';
                echo '<button type="button" class="button button-primary" disabled>Authorize với Nhanh.vn</button>';
            } else {
                echo '<p class="description" style="margin:4px 0 8px;">Chưa có Access Token. Bấm Authorize để kết nối Nhanh.vn và cấp token tự động (App ID, Secret Key và Redirect URL phải khớp cấu hình app trên Nhanh.vn).</p>';
                echo '<a class="button button-primary" href="' . esc_url($authUrl) . '">Authorize với Nhanh.vn</a>';
            }
            echo '<input type="hidden" name="access_token" value="" />';
            echo '</div>';
        } else {
            $this->field_text('access_token', 'Access Token', $accessToken, 'password', false, 'full');
            echo '<div class="nhanh-field full"><p class="description">Đã có Access Token. Nếu cần cấp lại, xóa token và lưu cấu hình để hiện nút Authorize. Redirect URL phải khớp app Nhanh.vn: <code>' . esc_html($returnLink) . '</code></p></div>';
        }
        echo '</div></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-rss"></span> Webhook & Đồng Bộ Tự Động</h3></div><div class="nhanh-form-grid">';
        $this->field_text('webhook_url', 'Webhook URL (endpoint nhận sự kiện)', $settings['webhook_url'] ?? '', 'text', false, 'full');
        $this->field_text('webhook_secret', 'Webhook Secret', $settings['webhook_secret'] ?? '', 'text');
        $this->field_text('sync_interval_minutes', 'Chu kỳ đồng bộ (phút)', (string)($settings['sync_interval_minutes'] ?? 15), 'number');
        $this->field_text('safety_stock_buffer', 'Safety stock buffer', (string)($settings['safety_stock_buffer'] ?? 2), 'number');
        $brandTaxDefault = $this->taxonomyResolver ? $this->taxonomyResolver->brandTaxonomy() : 'product_brand';
        $this->field_text('brand_taxonomy', 'Taxonomy thương hiệu WooCommerce (mặc định: product_brand)', $settings['brand_taxonomy'] ?? $brandTaxDefault, 'text', true, 'full');
        echo '</div><div class="nhanh-checks">';
        $this->field_check('webhook_active', 'Kích hoạt webhook thời gian thực', !empty($settings['webhook_active']));
        $this->field_check('auto_sync_enabled', 'Bật đồng bộ tự động theo chu kỳ', !empty($settings['auto_sync_enabled']));
        $this->field_check('auto_price_update', 'Tự động cập nhật giá (Nhanh → Web)', !empty($settings['auto_price_update']));
        $this->field_check('auto_stock_update', 'Tự động cập nhật tồn kho (Nhanh → Web)', !empty($settings['auto_stock_update']));
        $this->field_check('auto_create_missing_web_product', 'Tự tạo sản phẩm web nếu chưa tồn tại', !empty($settings['auto_create_missing_web_product']));
        echo '</div></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-admin-appearance"></span> Chế độ Giao Diện & Hiệu Năng (Catalog Lớn)</h3><span class="muted">Compact / Advanced</span></div><div class="nhanh-form-grid">';
        $uiMode = isset($settings['ui_mode']) ? $settings['ui_mode'] : 'compact';
        echo '<div class="nhanh-field"><label for="nhanh_ui_mode">Chế độ giao diện</label><select id="nhanh_ui_mode" name="ui_mode"><option value="compact"' . selected($uiMode,'compact',false) . '>Compact (ít sản phẩm, hiển thị gọn)</option><option value="advanced"' . selected($uiMode,'advanced',false) . '>Advanced (hàng triệu sản phẩm/biến thể, bảng dày + nhiều bộ lọc/phân trang)</option></select><p class="description">Advanced tối ưu cho tra soát dữ liệu lớn: phân trang, lọc mở rộng, bảng dạng lưới dày, header cố định.</p></div>';
        $this->field_text('products_per_page_compact', 'Sản phẩm/trang (Compact)', (string)($settings['products_per_page_compact'] ?? 20), 'number');
        $this->field_text('products_per_page_advanced', 'Sản phẩm/trang (Advanced)', (string)($settings['products_per_page_advanced'] ?? 100), 'number');
        $this->field_text('terms_per_page_compact', 'Danh mục & Thương hiệu/trang (Compact)', (string)($settings['terms_per_page_compact'] ?? 20), 'number');
        $this->field_text('terms_per_page_advanced', 'Danh mục & Thương hiệu/trang (Advanced)', (string)($settings['terms_per_page_advanced'] ?? 100), 'number');
        $this->field_text('orders_per_page_compact', 'Đơn hàng/trang (Compact)', (string)($settings['orders_per_page_compact'] ?? 20), 'number');
        $this->field_text('orders_per_page_advanced', 'Đơn hàng/trang (Advanced)', (string)($settings['orders_per_page_advanced'] ?? 100), 'number');
        $this->field_text('logs_per_page_compact', 'Log/trang (Compact)', (string)($settings['logs_per_page_compact'] ?? 20), 'number');
        $this->field_text('logs_per_page_advanced', 'Log/trang (Advanced)', (string)($settings['logs_per_page_advanced'] ?? 100), 'number');
        echo '</div><p class="description">Khuyến nghị Advanced cho hệ thống hàng triệu products/variations/orders: giữ per-page 100–200, luôn lọc trước, dùng truy vấn phân trang (LIMIT/OFFSET hoặc keyset) trên bảng <code>nhanh_sync_product_map</code>, <code>nhanh_sync_term_map</code>, <code>nhanh_sync_order_map</code>, <code>nhanh_sync_logs</code>.</p></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-database"></span> Kiến trúc DB & Tham chiếu WooCommerce</h3><span class="muted">dbDelta · schema v' . esc_html(defined('NHANH_SYNCER_DB_VERSION') ? NHANH_SYNCER_DB_VERSION : 'n/a') . '</span></div>';
        echo '<div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Bảng (prefix)</th><th>Mục đích</th><th>Tham chiếu WooCommerce</th></tr></thead><tbody>';
        $rows = [
            ['nhanh_sync_product_map', 'Ánh xạ & snapshot sản phẩm Nhanh ↔ WC, trạng thái đối soát, metrics', 'wc_product_id/wc_variation_id → product/product_variation, _sku/_price/_stock (lookup/meta)'],
            ['nhanh_sync_product_warehouses', 'Tồn kho theo từng kho Nhanh (đa kho)', 'Gộp theo rule + buffer → tồn WC'],
            ['nhanh_sync_term_map', 'Ánh xạ danh mục & thương hiệu Nhanh ↔ WooCommerce', 'wc_taxonomy=product_cat + wc_term_id → terms/term_taxonomy (Danh mục); wc_taxonomy=product_brand + wc_term_id → terms/term_taxonomy (Thương hiệu)'],
            ['nhanh_sync_product_terms', 'Quan hệ N-N sản phẩm ↔ danh mục/thương hiệu', 'Gán term qua wp_set_object_terms(..., product_cat) và wp_set_object_terms(..., product_brand)'],
            ['nhanh_sync_logs', 'Nhật ký sync/audit, payload/response, idempotency (dedupe_key)', 'Liên kết product_map_id, wc_order_id'],
            ['nhanh_sync_order_map', 'Ánh xạ đơn Nhanh ↔ WooCommerce', 'wc_order_id → wc_orders (HPOS) / shop_order (legacy)'],
            ['nhanh_sync_jobs', 'Theo dõi batch/cron/manual jobs', '—'],
            ['nhanh_sync_stats_daily', 'Số liệu báo cáo theo ngày/kênh (revenue/orders/units)', 'Tổng hợp từ đơn/analytics WC'],
        ];
        foreach ($rows as $r) {
            echo '<tr><td class="mono"><strong>' . esc_html($r[0]) . '</strong></td><td>' . esc_html($r[1]) . '</td><td class="muted">' . esc_html($r[2]) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';

        echo '<div class="nhanh-form-actions">';
        echo '<button type="submit" class="button button-primary button-large">Lưu cấu hình</button> ';
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-left:8px">';
        echo '<input type="hidden" name="action" value="nhanh_syncer_test_connection" />';
        wp_nonce_field('nhanh_syncer_test_connection');
        echo '<button type="submit" class="button button-large">Kiểm tra kết nối API</button>';
        echo '</form>';
        echo '</div>';
    }

    private function kpi(string $label, string $value, string $sub, string $icon, string $tone = ''): void
    {
        echo '<div class="nhanh-card kpi ' . esc_attr($tone) . '"><div class="kpi-top"><span>' . esc_html($label) . '</span><span class="dashicons ' . esc_attr($icon) . '"></span></div><div class="kpi-value">' . esc_html($value) . '</div><div class="kpi-sub">' . esc_html($sub) . '</div></div>';
    }

    private function kv(string $k, string $v, bool $ok): void
    {
        echo '<div class="kv"><span>' . esc_html($k) . '</span><strong class="' . ($ok ? 'ok' : 'bad') . '">' . esc_html($v) . '</strong></div>';
    }

    private function status_badge(string $status): string
    {
        $m = Formatter::statusBadge($status);
        return '<span class="pill ' . esc_attr($m[0]) . '">' . esc_html($m[1]) . '</span>';
    }

    private function velocity_badge(string $v): string
    {
        $m = Formatter::velocityBadge($v);
        return '<span class="pill ' . esc_attr($m[0]) . '">' . esc_html($m[1]) . '</span>';
    }

    private function log_type_label(string $t): string
    {
        return Formatter::logTypeLabel($t);
    }

    private function vnd($n): string
    {
        if (function_exists('number_format_i18n') && $n !== null && $n !== '') {
            return number_format_i18n((float) $n, 0) . ' ₫';
        }
        return Formatter::vnd($n);
    }

    private function vnd_compact($n): string
    {
        // Prefer WP i18n formatting when available, but keep compact logic consistent.
        $val = (float) $n;
        $abs = abs($val);
        if (function_exists('number_format_i18n')) {
            if ($abs >= 1000000000) return number_format_i18n($val / 1000000000, 2) . ' tỷ ₫';
            if ($abs >= 1000000) return number_format_i18n($val / 1000000, 1) . ' tr ₫';
            if ($abs >= 1000) return number_format_i18n($val / 1000, 0) . ' k ₫';
            return $this->vnd($val);
        }
        return Formatter::vndCompact($val);
    }

    private function field_text(string $name, string $label, string $value, string $type = 'text', bool $mono = true, string $extra = ''): void
    {
        $cls = 'nhanh-field' . ($extra ? ' ' . $extra : '');
        echo '<div class="' . esc_attr($cls) . '"><label for="nhanh_' . esc_attr($name) . '">' . esc_html($label) . '</label>';
        echo '<input type="' . esc_attr($type) . '" id="nhanh_' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="' . ($mono ? 'mono' : '') . '" /></div>';
    }

    private function field_check(string $name, string $label, bool $checked): void
    {
        echo '<label class="nhanh-check"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' /> <span>' . esc_html($label) . '</span></label>';
    }
}
