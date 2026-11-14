# Setup — Nhanh Data 2-Way Syncer

Hướng dẫn cài đặt, cấu hình và thiết lập cron để import dữ liệu Nhanh.vn → WooCommerce.

## 1. Yêu cầu

- WordPress 6.0+, WooCommerce 7.0+
- PHP 7.4+
- App Nhanh.vn (`appId`, `secretKey`) có bật service `pos`
- Một URL HTTPS công khai cho `returnLink` (phải khớp tuyệt đối với app trên Nhanh)

## 2. Cài plugin

```bash
cd wp-content/plugins
git clone <repo> nhanh-data-2way-syncer
cd nhanh-data-2way-syncer
composer install
```

Active trong **Plugins** hoặc:

```bash
wp plugin activate nhanh-data-2way-syncer
```

## 3. Cấu hình API

**Nhanh Syncer → Cấu hình API**:

| Trường | Giá trị |
| --- | --- |
| App ID | `78822` |
| Secret Key | lấy ở app Nhanh |
| OAuth Redirect URL | `https://<domain>/wp-admin/admin.php?page=nhanh-syncer-settings` |
| Brand Taxonomy | `product_brand` |
| Import Post Status | `draft` (an toàn cho lần đầu) hoặc `publish` |

Bấm **Authorize** → dán `accessCode` → plugin tự đổi lấy `access_token` + `business_id`.

## 4. Thiết lập cron

Plugin dùng **WP-Cron**: `SyncCronService` đăng ký hook `nhanh_syncer_run_import`
với recurrence theo `sync_interval_minutes` (mặc định 15 phút).

WP-Cron chỉ chạy khi có request, nên cần một OS-level cron bắn vào `wp-cron.php`.

### macOS — launchd + PHP/WP-CLI (khuyến nghị)

Dùng **WP-CLI** để cron chạy trong process CLI, **không phụ thuộc web server**.

`~/Library/LaunchAgents/com.nghesachxua.nhan-syncer-cron.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.nghesachxua.nhanh-syncer-cron</string>

    <key>ProgramArguments</key>
    <array>
        <string>/opt/homebrew/bin/php</string>
        <string>/usr/local/bin/wp</string>
        <string>cron</string>
        <string>event</string>
        <string>run</string>
        <string>--due-now</string>
        <string>--path=/Users/puleeno/Projects/nghesachxua.com</string>
    </array>

    <key>EnvironmentVariables</key>
    <dict>
        <key>PATH</key>
        <string>/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin</string>
    </dict>

    <key>StartInterval</key>
    <integer>300</integer>

    <key>RunAtLoad</key>
    <true/>

    <key>StandardOutPath</key>
    <string>/tmp/nhanh-syncer-cron.out.log</string>

    <key>StandardErrorPath</key>
    <string>/tmp/nhanh-syncer-cron.err.log</string>

    <key>ProcessType</key>
    <string>Background</string>

    <key>LowPriorityIO</key>
    <true/>
</dict>
</plist>
```

Cài và bật:

```bash
UID_=$(id -u)
plutil -lint ~/Library/LaunchAgents/com.nghesachxua.nhanh-syncer-cron.plist
launchctl bootout   gui/$UID_/com.nghesachxua.nhanh-syncer-cron 2>/dev/null
launchctl bootstrap gui/$UID_  ~/Library/LaunchAgents/com.nghesachxua.nhanh-syncer-cron.plist
launchctl enable    gui/$UID_/com.nghesachxua.nhanh-syncer-cron

launchctl print gui/$UID_/com.nghesachxua.nhanh-syncer-cron | grep -E "state|runs|last exit"
```

Gỡ bỏ:

```bash
launchctl bootout gui/$(id -u)/com.nghesachxua.nhan-syncer-cron
rm ~/Library/LaunchAgents/com.nghesachxua.nhanh-syncer-cron.plist
```

**Vì sao không dùng `curl`?** Cả ba cách đều chạy được, nhưng khác nhau ở chỗ *ai* thực thi job (đo thực tế trên máy này):

| Cách | Job chạy ở đâu | Cần web server? |
| --- | --- | --- |
| `curl https://<domain>/wp-cron.php` | worker của web server | Có |
| `php wp-cron.php` | worker của web server (WP tự spawn HTTP self-request; đo được `php=litespeed`) | Có |
| `wp cron event run --due-now` | **process CLI** (`php=cli`) | Không |

`curl` là cách phổ biến nhất trên shared hosting, nhưng ở môi trường local
(`.localhost`, self-signed TLS, OLS) thì phụ thuộc web server + chứng chỉ + hosts.
`wp cron event run --due-now` là cách duy nhất không cần web server và có
`--due-now` để chạy đúng các event đã đến hạn.

Lưu ý: `php` phải là **PHP CLI** (`/opt/homebrew/bin/php`), không phải `lsphp`.
Nếu `wp` báo không tìm thấy PHP, đặt `EnvironmentVariables.PATH` như trên.

### macOS — crontab (thay thế)

```bash
crontab -e
```

```cron
# HTTP (cần web server)
*/5 * * * * /usr/bin/curl -sk --max-time 120 https://nghesachxua.localhost/wp-cron.php >/dev/null 2>&1

# PHP/WP-CLI (không cần web server) — khuyến nghị
*/5 * * * * /opt/homebrew/bin/php /usr/local/bin/wp cron event run --due-now --path=/Users/puleeno/Projects/nghesachxua.com >/dev/null 2>&1
```

### Linux — crontab

```bash
crontab -e
```

```cron
*/5 * * * * /usr/bin/curl -sk --max-time 120 https://<domain>/wp-cron.php >/dev/null 2>&1
```

Hoặc dùng PHP/WP-CLI (không cần web server):

```cron
*/5 * * * * /usr/local/bin/wp cron event run --due-now --path=/var/www/site >/dev/null 2>&1
```

### Linux — systemd timer

`/etc/systemd/system/nhanh-syncer.service`:

```ini
[Unit]
Description=Nhanh Syncer wp-cron tick
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/wp cron event run --due-now --path=/var/www/site
```

`/etc/systemd/system/nhanh-syncer.timer`:

```ini
[Unit]
Description=Nhanh Syncer wp-cron timer

[Timer]
OnCalendar=*:0/5

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nhanh-syncer.timer
```

## 5. Kiểm tra hoạt động

```bash
# 1. Event đã đăng ký chưa
wp cron event list | grep nhanh_syncer_run_import

# 2. Chạy ngay một nhịp
wp cron event run nhanh_syncer_run_import

# 3. Xem job gần nhất
wp db query "SELECT id,status,scanned,updated_count,failed_count,started_at,finished_at
             FROM $(wp db prefix)nhanh_sync_jobs ORDER BY id DESC LIMIT 3" --skip-column-names
```

Kỳ vọng: `status=success`, `failed_count=0`.

## 6. Các thiết lập import

| Setting | Mặc định | Ý nghĩa |
| --- | --- | --- |
| `auto_sync_enabled` | `1` | Bật/tắt cron import |
| `sync_interval_minutes` | `15` | Chu kỳ import (tối thiểu 5 phút) |
| `import_post_status` | `draft` | `publish` hoặc `draft` cho sản phẩm tạo mới |
| `import_images` | `1` | Tải ảnh về media library (chậm nhất) |
| `import_max_per_run` | `100` | Số sản phẩm tối đa mỗi nhịp |
| `import_max_seconds` | `600` | Ngân sách thời gian mỗi nhịp |

Mỗi nhịp có ngân sách riêng: hết giờ thì dừng gọn và nhịp sau chạy tiếp
(upsert idempotent). Sản phẩm đã có ảnh sẽ **không tải lại ảnh**.

## 7. Màn hình quản trị (nguồn dữ liệu)

Các màn hình dưới đây đọc trực tiếp từ bảng của plugin, không còn dữ liệu mẫu:

| Màn hình | Nguồn dữ liệu | Ghi chú |
| --- | --- | --- |
| **Liên kết Sản phẩm** (`nhanh-syncer-mapping`) | `nhanh_sync_product_map` | Giá/tồn phía Web lấy từ sản phẩm WooCommerce (`wc_get_product`); trạng thái khớp/lệch tính lại bằng `SyncStatusResolver`. Tên danh mục/thương hiệu resolve từ `nhanh_sync_term_map`. |
| **Danh mục & Thương hiệu** (`nhanh-syncer-categories`) | `nhanh_sync_term_map` | `nhanh_term_type` = `category` \| `brand`; tên term WC lấy từ `wc_term_name`, fallback `get_term()`. |
| **Liên kết Đơn hàng** (`nhan-syncer-orders`) | `nhanh_sync_order_map` | `source` (`wc_to_nhanh` \| `nhanh_to_wc`), `total_amount`, link tới đơn WC qua `wc_order_id` (HPOS-safe). |
| **Nhật ký & Tra soát** (`nhan-syncer-logs`) | `nhanh_sync_logs` | `event_time`, `type`, `reference_code`, `status`, `duration_ms`. |
| **Tổng quan / Báo cáo** | `nhanh_sync_stats_daily` + tổng hợp từ `product_map` | Doanh thu theo ngày; luân chuyển theo danh mục. |

Ghi chú:

- Lọc và phân trang hiện thực hiện ở tầng PHP trên tập dữ liệu đã tải.
  Với catalog rất lớn, nên chuyển sang lọc/phân trang bằng SQL (các bảng đã có
  index `sync_status`, `updated_at`, `nhanh_code`).
- `nhanh_sync_order_map` có cột `source` (thêm ở DB version `1.1.0`); nếu site
  cũ chưa có, chạy:
  ```bash
  wp eval 'Puleeno\NhanhVn\Syncer\Database\Schema::maybe_upgrade();'
  ```

## 8. Sự cố thường gặp

| Hiện tượng | Nguyên nhân / cách xử lý |
| --- | --- |
| `already_running` kéo dài | Job `running` bị treo > 15 phút sẽ tự động bị thu hồi (`STALE_JOB_SECONDS`). |
| Job `failed`, `error: missing_credentials` | Chưa OAuth xong — vào Cấu hình API bấm Authorize. |
| `section_errors` có `brands` | SDK không cache brand; xem ghi chú bên dưới. |
| Import chậm | Giảm `import_max_per_run` hoặc đặt `import_images=0`. |
| Cron không chạy | Kiểm tra launchd (`launchctl print ...`) và `curl -I https://<domain>/wp-cron.php`. |

## 9. Ghi chú kỹ thuật

- `nhanh_syncer_run_import` chỉ tồn tại sau khi plugin được activate **hoặc** settings được lưu
  (hook `nhanh_syncer_settings_saved` → `SyncCronService::onSettingsSave`).
- Nếu event không có trong `wp cron event list`, chạy:
  ```bash
  wp eval '$s = new Puleeno\NhanhVn\Syncer\Service\SyncCronService(new Puleeno\NhanhVn\Syncer\Database\JobRepository()); $s->activate();'
  ```
- WooCommerce products/tags: category Nhanh → `product_cat`, brand Nhanh → `brand_taxonomy`.
- Ảnh được sideload vào media library rồi gán `_thumbnail_id` + gallery (không set URL trực tiếp).
- SDK đi kèm (`vendor/puleeno/nhan-vn-sdk`) đã được vá:
  - `AbstractEntity::$guarded` thêm `attributes`, `errors` (payload v3 có key `attributes`
    làm entity bị reset rỗng).
  - `ProductModule::getBrands()` trả `[]` khi cache rỗng thay vì ném `TypeError`.