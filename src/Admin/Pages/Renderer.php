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

        echo '<div class="wrap nhanh-syncer-wrap">';
        $this->render_header($meta, $settings, $page);

        echo '<div class="nhanh-syncer-shell">';
        $this->render_sidebar($page, $submenus, $data, $settings);
        echo '<main class="nhanh-syncer-main">';

        switch ($page) {
            case 'nhanh-syncer-mapping':
                $this->page_mapping($data);
                break;
            case 'nhanh-syncer-categories':
                $this->page_categories($data);
                break;
            case 'nhanh-syncer-logs':
                $this->page_logs($data);
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

    private function render_header(array $meta, array $settings, string $page): void
    {
        $title = esc_html($meta['title'] ?? 'NhanhSync Hub');
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

        echo '<aside class="nhanh-syncer-sidebar">';
        echo '<div class="nhanh-syncer-side-brand"><span class="dashicons dashicons-update-alt"></span><div><strong>NhanhSync Hub</strong><small>Nhanh.vn ↔ WordPress</small></div></div>';

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
        $this->kv('Taxonomy danh mục/thương hiệu', 'product_cat / ' . $brandTax, true);
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

    private function page_mapping(array $data): void
    {
        $products = $data['products'];
        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $status = isset($_GET['sync_status']) ? sanitize_key(wp_unslash($_GET['sync_status'])) : 'all';
        $filtered = array_filter($products, function ($p) use ($q, $status) {
            if ($status !== 'all' && $p['sync_status'] !== $status) return false;
            if ($q !== '') {
                $hay = strtolower($p['nhanh_code'] . ' ' . $p['nhanh_name'] . ' ' . ($p['web_id'] ?? '') . ' ' . ($p['brand'] ?? ''));
                if (strpos($hay, strtolower($q)) === false) return false;
            }
            return true;
        });

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-admin-links"></span> Bảng Quản Lý Liên Kết Đối Tượng (Nhanh.vn ↔ Website)</h3><span class="muted">' . count($filtered) . ' / ' . count($products) . ' sản phẩm</span></div>';
        echo '<form method="get" class="nhanh-filters"><input type="hidden" name="page" value="nhanh-syncer-mapping" />';
        echo '<input type="search" name="q" value="' . esc_attr($q) . '" class="regular-text" placeholder="Tìm SKU, tên, thương hiệu, ID Nhanh/Web..." /> ';
        echo '<select name="sync_status"><option value="all">Tất cả trạng thái</option><option value="synced"' . selected($status,'synced',false) . '>Đã khớp</option><option value="price_mismatch"' . selected($status,'price_mismatch',false) . '>Lệch giá</option><option value="stock_mismatch"' . selected($status,'stock_mismatch',false) . '>Lệch tồn</option><option value="unlinked"' . selected($status,'unlinked',false) . '>Chưa liên kết</option></select> ';
        echo '<button class="button">Lọc</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-mapping')) . '">Xóa lọc</a></form>';

        echo '<div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Sản phẩm trên Nhanh.vn</th><th>Trạng thái ánh xạ</th><th>Đối tượng trên Website</th><th>Tồn kho</th><th>Giá bán</th><th>Thao tác</th></tr></thead><tbody>';
        foreach ($filtered as $p) {
            $priceDiff = ($p['web_price'] !== null && (int)$p['web_price'] !== (int)$p['nhanh_price']);
            $stockDiff = ($p['web_stock'] !== null && (int)$p['web_stock'] !== (int)$p['nhanh_stock']);
            echo '<tr>';
            echo '<td><strong>' . esc_html($p['nhanh_name']) . '</strong><div class="muted mono">' . esc_html($p['nhanh_code']) . '</div><div class="muted">' . esc_html($p['category']) . ' · <span class="mono">Brand:</span> ' . esc_html($p['brand'] ?? '—') . ' · ' . $this->velocity_badge($p['sell_velocity']) . '</div></td>';
            echo '<td>' . $this->status_badge($p['sync_status']) . '<div class="muted small">Cập nhật: ' . esc_html($p['last_synced']) . '</div></td>';
            if (!empty($p['web_id'])) {
                echo '<td><strong>Đã liên kết: ' . esc_html($p['web_id']) . '</strong><div class="muted">SKU Web: ' . esc_html($p['nhanh_code']) . '</div></td>';
            } else {
                echo '<td><span class="pill bad">Chưa liên kết Website</span><div class="muted">Chưa có đối tượng tương ứng</div></td>';
            }
            $nStock = number_format_i18n((int)$p['nhanh_stock']);
            $wStock = $p['web_stock'] === null ? '—' : number_format_i18n((int)$p['web_stock']);
            echo '<td><div class="mono">Nhanh: <strong>' . $nStock . '</strong></div><div class="mono ' . ($stockDiff ? 'diff' : '') . '">Web: <strong>' . $wStock . '</strong></div></td>';
            echo '<td><div class="mono">Nhanh: <strong>' . esc_html($this->vnd($p['nhanh_price'])) . '</strong></div><div class="mono ' . ($priceDiff ? 'diff' : '') . '">Web: <strong>' . ($p['web_price'] === null ? '—' : esc_html($this->vnd($p['web_price']))) . '</strong></div></td>';
            echo '<td class="nhanh-actions">';
            echo '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-mapping&q=' . rawurlencode($p['nhanh_code']))) . '">Đối soát</a> ';
            if (empty($p['web_id'])) {
                echo '<a class="button button-small button-primary" href="#">Liên kết</a>';
            } else {
                echo '<a class="button button-small" href="#">Đồng bộ</a>';
            }
            echo '</td></tr>';
        }
        if (empty($filtered)) {
            echo '<tr><td colspan="6" class="muted">Không tìm thấy sản phẩm phù hợp bộ lọc.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function page_categories(array $data): void
    {
        $terms = isset($data['term_maps']) ? $data['term_maps'] : ($data['categories'] ?? []);
        $type = isset($_GET['term_type']) ? sanitize_key(wp_unslash($_GET['term_type'])) : 'all';
        $filtered = array_filter($terms, function ($t) use ($type) {
            if ($type === 'all') return true;
            return ($t['type'] ?? 'category') === $type;
        });

        $total = count($terms); $mapped = 0; $unmapped = 0; $catCount = 0; $brandCount = 0;
        foreach ($terms as $t) {
            if (($t['status'] ?? '') === 'synced') $mapped++; else $unmapped++;
            if (($t['type'] ?? '') === 'brand') $brandCount++; else $catCount++;
        }
        echo '<div class="nhanh-grid kpis">';
        $this->kpi('Tổng term Nhanh.vn', (string)$total, $catCount . ' danh mục · ' . $brandCount . ' thương hiệu', 'dashicons-networking');
        $this->kpi('Đã liên kết WC', (string)$mapped, 'Khớp product_cat / product_brand', 'dashicons-yes-alt', 'ok');
        $this->kpi('Chưa ánh xạ', (string)$unmapped, 'Cần map vào taxonomy WooCommerce', 'dashicons-warning', $unmapped ? 'warn' : 'ok');
        $brandTax = $this->taxonomyResolver ? $this->taxonomyResolver->brandTaxonomy() : 'product_brand';
        $this->kpi('Taxonomy tham chiếu', '2 loại', 'product_cat (categories) · ' . $brandTax . ' (brands)', 'dashicons-tag', 'accent');
        echo '</div>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-networking"></span> Đồng Bộ Danh Mục & Thương Hiệu (Nhanh.vn ↔ WooCommerce)</h3><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=nhanh-syncer-categories')) . '">Đồng bộ toàn bộ</a></div>';
        echo '<form method="get" class="nhanh-filters"><input type="hidden" name="page" value="nhanh-syncer-categories" />';
        echo '<select name="term_type"><option value="all"' . selected($type,'all',false) . '>Tất cả (Danh mục & Thương hiệu)</option><option value="category"' . selected($type,'category',false) . '>Danh mục (product_cat)</option><option value="brand"' . selected($type,'brand',false) . '>Thương hiệu (' . esc_attr($brandTax) . ')</option></select> ';
        echo '<button class="button">Lọc</button></form>';
        echo '<div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Loại</th><th>Term trên Nhanh.vn</th><th>WC Taxonomy</th><th>Số SP</th><th>Term WooCommerce tương ứng</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>';
        foreach ($filtered as $t) {
            $isBrand = ($t['type'] ?? '') === 'brand';
            $typeLabel = $isBrand ? 'Thương hiệu' : 'Danh mục';
            $typePill = $isBrand ? 'neutral' : 'ok';
            echo '<tr>';
            echo '<td><span class="pill ' . $typePill . '">' . esc_html($typeLabel) . '</span></td>';
            echo '<td><strong>' . esc_html($t['nhanh']) . '</strong><div class="muted">ID: ' . esc_html($t['nhanh_id']) . '</div></td>';
            echo '<td class="mono">' . esc_html($t['wc_taxonomy']) . '</td>';
            echo '<td class="mono">' . number_format_i18n((int)($t['count'] ?? 0)) . '</td>';
            echo '<td>' . (!empty($t['web']) ? '<strong>' . esc_html($t['web']) . '</strong><div class="muted mono">term_id: ' . esc_html((string)$t['wc_term_id']) . '</div>' : '<span class="pill bad">Chưa ánh xạ</span><div class="muted">Chưa có term WC tương ứng</div>') . '</td>';
            echo '<td>' . (($t['status'] ?? '') === 'synced' ? '<span class="pill ok">Đã khớp</span>' : '<span class="pill warn">Chờ xử lý</span>') . '<div class="muted small">' . esc_html($t['time'] ?? '') . '</div></td>';
            echo '<td><a class="button button-small" href="#">' . (!empty($t['web']) ? 'Thay đổi' : 'Ánh xạ') . '</a></td>';
            echo '</tr>';
        }
        if (empty($filtered)) {
            echo '<tr><td colspan="7" class="muted">Không có term phù hợp.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private function page_logs(array $data): void
    {
        $logs = $data['logs'];
        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $st = isset($_GET['log_status']) ? sanitize_key(wp_unslash($_GET['log_status'])) : 'all';
        $filtered = array_filter($logs, function ($l) use ($q, $st) {
            if ($st !== 'all' && $l['status'] !== $st) return false;
            if ($q !== '') {
                $hay = strtolower($l['title'] . ' ' . $l['detail'] . ' ' . $l['ref']);
                if (strpos($hay, strtolower($q)) === false) return false;
            }
            return true;
        });
        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-backup"></span> Nhật Ký Đồng Bộ & Tra Soát Giao Dịch</h3><span class="muted">' . count($filtered) . ' sự kiện</span></div>';
        echo '<form method="get" class="nhanh-filters"><input type="hidden" name="page" value="nhanh-syncer-logs" />';
        echo '<input type="search" name="q" value="' . esc_attr($q) . '" class="regular-text" placeholder="Tìm theo tiêu đề, mã, chi tiết..." /> ';
        echo '<select name="log_status"><option value="all">Tất cả</option><option value="success"' . selected($st,'success',false) . '>Thành công</option><option value="warning"' . selected($st,'warning',false) . '>Cảnh báo</option><option value="error"' . selected($st,'error',false) . '>Lỗi</option></select> ';
        echo '<button class="button">Lọc</button></form>';
        echo '<div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Thời gian</th><th>Loại</th><th>Mã tham chiếu</th><th>Sự kiện</th><th>Trạng thái</th><th>Xử lý</th></tr></thead><tbody>';
        foreach ($filtered as $l) {
            $pill = $l['status'] === 'success' ? 'ok' : ($l['status'] === 'warning' ? 'warn' : 'bad');
            $label = $l['status'] === 'success' ? 'Thành công' : ($l['status'] === 'warning' ? 'Cảnh báo' : 'Lỗi');
            echo '<tr><td class="mono">' . esc_html($l['time']) . '</td><td><span class="pill neutral">' . esc_html($this->log_type_label($l['type'])) . '</span></td><td class="mono">' . esc_html($l['ref']) . '</td><td><strong>' . esc_html($l['title']) . '</strong><div class="muted">' . esc_html($l['detail']) . '</div></td><td><span class="pill ' . $pill . '">' . $label . '</span></td><td class="mono">' . (int)$l['ms'] . ' ms</td></tr>';
        }
        if (empty($filtered)) echo '<tr><td colspan="6" class="muted">Không có log phù hợp.</td></tr>';
        echo '</tbody></table></div></section>';
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
        if ($saved) echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cấu hình NhanhSync Hub.</p></div>';
        if ($test === 'ok') echo '<div class="notice notice-success is-dismissible"><p>Kết nối API Nhanh.vn thành công (mô phỏng kiểm tra cấu hình).</p></div>';
        if ($test === 'fail') echo '<div class="notice notice-error is-dismissible"><p>Chưa thể kết nối: vui lòng kiểm tra App ID và Access Token.</p></div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="nhanh-settings-form">';
        echo '<input type="hidden" name="action" value="nhanh_syncer_save_settings" />';
        wp_nonce_field('nhanh_syncer_save_settings');

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-admin-network"></span> Thông Tin Xác Thực Nhanh.vn Open API</h3><span class="muted">open.nhanh.vn</span></div><div class="nhanh-form-grid">';
        $this->field_text('app_id', 'App ID (Mã Ứng Dụng)', $settings['app_id'] ?? '');
        $this->field_text('business_id', 'Business ID (Doanh Nghiệp)', $settings['business_id'] ?? '');
        $this->field_text('business_name', 'Tên doanh nghiệp / cửa hàng', $settings['business_name'] ?? '', 'text', false);
        $this->field_text('secret_key', 'Secret Key (Khóa Bí Mật)', $settings['secret_key'] ?? '', 'password');
        $this->field_text('access_token', 'Access Token', $settings['access_token'] ?? '', 'password', false, 'full');
        echo '</div></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-rss"></span> Webhook & Đồng Bộ Tự Động</h3></div><div class="nhanh-form-grid">';
        $this->field_text('webhook_url', 'Webhook URL (endpoint nhận sự kiện)', $settings['webhook_url'] ?? '', 'text', false, 'full');
        $this->field_text('webhook_secret', 'Webhook Secret', $settings['webhook_secret'] ?? '', 'text');
        $this->field_text('sync_interval_minutes', 'Chu kỳ đồng bộ (phút)', (string)($settings['sync_interval_minutes'] ?? 15), 'number');
        $this->field_text('safety_stock_buffer', 'Safety stock buffer', (string)($settings['safety_stock_buffer'] ?? 2), 'number');
        $brandTaxDefault = $this->taxonomyResolver ? $this->taxonomyResolver->brandTaxonomy() : 'product_brand';
        $this->field_text('brand_taxonomy', 'WooCommerce brand taxonomy', $settings['brand_taxonomy'] ?? $brandTaxDefault, 'text', true, 'full');
        echo '</div><div class="nhanh-checks">';
        $this->field_check('webhook_active', 'Kích hoạt webhook thời gian thực', !empty($settings['webhook_active']));
        $this->field_check('auto_sync_enabled', 'Bật đồng bộ tự động theo chu kỳ', !empty($settings['auto_sync_enabled']));
        $this->field_check('auto_price_update', 'Tự động cập nhật giá (Nhanh → Web)', !empty($settings['auto_price_update']));
        $this->field_check('auto_stock_update', 'Tự động cập nhật tồn kho (Nhanh → Web)', !empty($settings['auto_stock_update']));
        $this->field_check('auto_create_missing_web_product', 'Tự tạo sản phẩm web nếu chưa tồn tại', !empty($settings['auto_create_missing_web_product']));
        echo '</div></section>';

        echo '<section class="nhanh-card"><div class="nhanh-card-head"><h3><span class="dashicons dashicons-database"></span> Kiến trúc DB & Tham chiếu WooCommerce</h3><span class="muted">dbDelta · schema v' . esc_html(defined('NHANH_SYNCER_DB_VERSION') ? NHANH_SYNCER_DB_VERSION : 'n/a') . '</span></div>';
        echo '<div class="nhanh-table-wrap"><table class="widefat striped nhanh-table"><thead><tr><th>Bảng (prefix)</th><th>Mục đích</th><th>Tham chiếu WooCommerce</th></tr></thead><tbody>';
        $rows = [
            ['nhanh_sync_product_map', 'Ánh xạ & snapshot sản phẩm Nhanh ↔ WC, trạng thái đối soát, metrics', 'wc_product_id/wc_variation_id → product/product_variation, _sku/_price/_stock (lookup/meta)'],
            ['nhanh_sync_product_warehouses', 'Tồn kho theo từng kho Nhanh (đa kho)', 'Gộp theo rule + buffer → tồn WC'],
            ['nhanh_sync_term_map', 'Ánh xạ term Nhanh ↔ WC cho Danh mục & Thương hiệu', 'wc_taxonomy=product_cat, wc_term_id → terms/term_taxonomy (categories); wc_taxonomy=product_brand, wc_term_id → terms/term_taxonomy (brands)'],
            ['nhanh_sync_product_terms', 'Quan hệ N-N sản phẩm ↔ term map (nhiều category/brand)', 'wp_set_object_terms(..., product_cat/product_brand)'],
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
