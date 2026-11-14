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

### macOS — launchd (khuyến nghị)

`~/Library/LaunchAgents/com.nghesachxua.nhanh-syncer-cron.plist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.nghesachxua.nhanh-syncer-cron</string>
    <key>ProgramArguments</key>
    <array>
        <string>/usr/bin/curl</string>
        <string>--silent</string>
        <string>--show-error</string>
        <string>--fail</string>
        <string>--insecure</string>
        <string>--max-time</string>
        <string>120</string>
        <string>https://nghesachxua.localhost/wp-cron.php</string>
    </array>
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
launchctl bootout  gui/$UID_/com.nghesachxua.nhanh-syncer-cron 2>/dev/null
launchctl bootstrap gui/$UID_ ~/Library/LaunchAgents/com.nghesachxua.nhanh-syncer-cron.plist
launchctl enable   gui/$UID_/com.nghesachxua.nhanh-syncer-cron

# kiểm tra
launchctl print gui/$UID_/com.nghesachxua.nhanh-syncer-cron | grep -E "state|runs|last exit"
```

`StartInterval = 300` (5 phút): cron bắn mỗi 5 phút, còn tần suất import thật
do `sync_interval_minutes` quyết định (15 phút) — WP-Cron chỉ chạy event đến hạn.

Gỡ bỏ:

```bash
launchctl bootout gui/$(id -u)/com.nghesachxua.nhanh-syncer-cron
rm ~/Library/LaunchAgents/com.nghesachxua.nhanh-syncer-cron.plist
```

### macOS — crontab (thay thế)

```bash
crontab -e
```

```cron
*/5 * * * * /usr/bin/curl -sk --max-time 120 https://nghesachxua.localhost/wp-cron.php >/dev/null 2>&1
```

### Linux — crontab

```bash
crontab -e
```

```cron
*/5 * * * * /usr/bin/curl -sk --max-time 120 https://<domain>/wp-cron.php >/dev/null 2>&1
```

### Linux — systemd timer

`/etc/systemd/system/nhanh-syncer.service`:

```ini
[Unit]
Description=Nhanh Syncer wp-cron tick
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/bin/curl -sk --max-time 120 https://<domain>/wp-cron.php
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

## 7. Sự cố thường gặp

| Hiện tượng | Nguyên nhân / cách xử lý |
| --- | --- |
| `already_running` kéo dài | Job `running` bị treo > 15 phút sẽ tự động bị thu hồi (`STALE_JOB_SECONDS`). |
| Job `failed`, `error: missing_credentials` | Chưa OAuth xong — vào Cấu hình API bấm Authorize. |
| `section_errors` có `brands` | SDK không cache brand; xem ghi chú bên dưới. |
| Import chậm | Giảm `import_max_per_run` hoặc đặt `import_images=0`. |
| Cron không chạy | Kiểm tra launchd (`launchctl print ...`) và `curl -I https://<domain>/wp-cron.php`. |

## 8. Ghi chú kỹ thuật

- `nhan_syncer_run_import` chỉ tồn tại sau khi plugin được activate **hoặc** settings được lưu
  (hook `nhan_syncer_settings_saved` → `SyncCronService::onSettingsSave`).
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