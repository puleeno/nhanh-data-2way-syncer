# Tính năng — Nhanh Data 2-Way Syncer

Tài liệu mô tả các tính năng hiện có của plugin `nhanh-data-2way-syncer` (Nhanh.vn POS v3 ↔ WooCommerce), dựa trên mã nguồn hiện tại.

## 1. Phạm vi & kiến trúc

- **Nền tảng**: WordPress + WooCommerce (HPOS/cart-checkout compatibility được khai báo), PHP 7.4+, SDK `puleeno/nhanh-vn-sdk`.
- **Hướng đồng bộ hiện tại**:
  - **Nhanh → WooCommerce**: import/sync sản phẩm, danh mục, thương hiệu; cập nhật tồn kho theo webhook.
  - **Woo → Nhanh / đơn hàng 2 chiều đầy đủ**: schema/bảng ánh xạ đơn (`nhanh_sync_order_map`) và một số cờ cấu hình đã có, nhưng luồng đồng bộ đơn/ngược chiều chưa được triển khai hoàn chỉnh trong runtime hiện tại.
- **Mô hình xử lý (pipeline)**: dữ liệu Nhanh (entity/array, shape list/detail/webhook) → `Domain` canonical model → `Normalizer/Policy/Mapper` → `WooBridge` (gateway Woo) + repositories/DB map. Tách lớp: `Nhanh\Normalizer`, `Sync\ProductSyncOrchestrator`, `Sync\Policy`, `Sync\Mapper`, `Sync\InventorySyncService`, `Webhook\Handler\*`, `Nhanh\Gateway\NhanhProductGateway`, `Rest\WebhookController`.

## 2. Đồng bộ sản phẩm Nhanh → WooCommerce (import)

### 2.1 Nguồn & điều phối
- Chạy qua `ImportService` (`job_type=import`, có `JobRepository` + log) do **WP-Cron** hoặc dispatch thủ công (`SyncCronService`).
- Hook cron: `nhanh_syncer_run_import` (và `_once`), recurrence đăng ký động `nhanh_syncer_{N}min` (5/15/30/60). Lập lịch theo `auto_sync_enabled` và `sync_interval_minutes` (≥ 5 phút); reschedule khi lưu settings.
- Bảo vệ chạy chồng: job `running` quá cũ (stale) được thu hồi trước khi chạy mới.
- Ngân sách mỗi lần chạy: `import_max_per_run` (số sản phẩm xử lý) và `import_max_seconds` (deadline). Phần còn lại `deferred` để lần chạy sau tiếp tục (upsert idempotent).
- Cô lập lỗi theo section: `categories`, `brands`, `products` xử lý độc lập; lỗi section không hủy toàn bộ job (kết quả `success|partial|failed`).

### 2.2 Danh mục & thương hiệu
- **Danh mục (`product_cat`)**: lấy từ Nhanh, import dạng cây (xử lý cha/con, retry pass, fallback orphan về root), map qua `nhanh_sync_term_map` (`nhanh_term_type=category`).
- **Thương hiệu**: lấy từ Nhanh, import vào taxonomy cấu hình `brand_taxonomy` (mặc định `product_brand`, chấp nhận `product_cat|product_brand`), map `nhanh_term_type=brand`. Thương hiệu được import dạng phẳng.
- Cập nhật term hiện có (tên/mô tả/parent khi phù hợp) qua `WooBridge::upsertTerm`.

### 2.3 Chuẩn hóa dữ liệu sản phẩm (Nhanh v3)
`ProductNormalizer` chuẩn hóa entity/array từ nhiều shape (list/detail/webhook) về canonical `NhanhProduct`:

| Nhóm | Trường Nhanh (ví dụ/khả dụng) | Ghi chú |
| --- | --- | --- |
| Định danh | `id/idNhanh`, `parentId`, `code`, `barcode`, `type`, `status`, `updatedAt/createdAt` | Map khóa linh hoạt theo shape. |
| Nội dung | `name`, `otherName`, `description`, `content`, `videos` | `content` ưu tiên mô tả dài, fallback `description`. |
| Phân loại/tham chiếu | `categoryId` ↔ `category{}`, `internalCategory{}`, `brandId` ↔ `brand{}`, `tagIds` | Hỗ trợ list phẳng và object lồng. |
| Giá | `price` / `prices.retail, old, wholesale, import, avgCost, retailVat, importVat, vat, priceVatMode, importPriceVatMode`, `branchPrices` | Snapshot đầy đủ; tính giá hiệu lực qua policy. |
| Tồn kho | `available/remain` (flat hoặc `inventory.*`), `shipping/damaged/holding/transfering`, `warranty`, `depots[]` | Tổng + chi tiết kho; hỗ trợ depot-level. |
| Ảnh | `images.avatar`, `images.others[]`, `images[].src`, `image` | Gộp, khử trùng, xác định avatar. |
| Vận chuyển/BH | `shipping.{weight,length,width,height}`/`shippingWeight`, `warranty.{month,phone,address}` | Chuẩn hóa kiểu số/chuỗi. |
| Mở rộng | `attributes[]`, `units` (`list`), `combos[]`, `suppliers[]`, `childs[]/children[]` | `childs` normalize đệ quy. |
| Audit | `raw` | Giữ snapshot payload gốc. |

### 2.4 Mapping sang WooCommerce & lưu trữ
- **Identity/upsert**: resolve map theo `code` → `nhanh_id` → SKU; tạo/cập nhật `WC_Product`/variation, ghi `nhanh_sync_product_map` (id/code/barcode/name/category/brand/price/original_price/stock, `wc_product_id/wc_variation_id/wc_sku`, `sync_status`, `image_url`, `tags_json`, timestamps). `nhanh_barcode` và `nhanh_original_price` (từ `old`, fallback retail) được lưu đúng nguồn chuẩn hóa.
- **Giá (PricePolicy)**: tính `regular/sale/active` (nếu `old > retail` coi retail là sale). Woo set `regular_price`, `sale_price`, `price` (active) — khắc phục hiển thị “–” khi chỉ set active price; snapshot giá mở rộng/VAT/branchPrices lưu meta.
- **Trạng thái (PostStatusPolicy)**: post status Woo theo cấu hình `import_post_status` (`draft|publish`, mặc định `draft`) và áp dụng khi upsert (giữ hành vi tương thích; có thể mở rộng map theo trạng thái Nhanh).
- **Tồn kho**: tổng sync về Woo (`manage_stock`, `stock_quantity`, `stock_status` instock/outofstock theo tổng); chi tiết depot sync vào `nhanh_sync_product_warehouses`.
- **Nội dung & thuộc tính cơ bản**: name, SKU, mô tả dài/ngắn, trọng lượng/kích thước, meta mở rộng (`_nhanh_*`: ids/type/status, prices/attributes/units/combos/inventory/shipping/warranty/raw snapshot, other name...).
- **Taxonomy & quan hệ**: gán `product_cat` theo map danh mục; gán brand taxonomy theo map brand (`wp_set_object_terms`); ghi quan hệ `nhanh_sync_product_terms`.
- **Ảnh**: khi `import_images` bật, sideload/reuse attachment; mặc định không ép refresh nếu sản phẩm đã có ảnh (`force_images` tắt trong luồng hiện tại).

### 2.5 Sản phẩm biến thể / composite (childs-attributes-units)
- **Phát hiện**: `VariationMapper` coi sản phẩm có `children` là variable/composite.
- **Parent (variable)**: dựng attribute options từ `attributes` parent và giá trị thuộc tính con (fallback “Phân loại” theo tên con khi thiếu thuộc tính), set `WC_Product_Variable` attributes, `manage_stock=false` ở parent.
- **Variations**: tạo/cập nhật từng variation từ child (SKU/nhanh id-code-barcode, giá/tồn/status theo policy, map thuộc tính, shipping, mô tả, ảnh, meta `_nhanh_*` gồm units/combos/inventory/raw, depots). Lưu map variation (`wc_variation_id`) và depot per-variation.
- **Đồng bộ tập variation**: `syncVariations` loại bỏ (trash) variation không còn trong tập đã sync để giữ cấu trúc nhất quán.
- **Units/combos/suppliers/attributes/child raw**: được chuẩn hóa và lưu trong meta/map snapshot để phục vụ tra soát và mở rộng mapper chuyên sâu.

## 3. Webhook realtime (Nhanh → Plugin)

- **Endpoint REST**: `POST /wp-json/nhanh-syncer/v1/webhook` (`Rest\WebhookController`, đăng ký khi plugin boot và WooCommerce active).
- **Điều kiện**: yêu cầu `webhook_active` bật; trả lỗi nếu tắt/không hợp lệ chữ ký.
- **Xác thực**: nếu cấu hình `webhook_secret` — chấp nhận secret header (`x-nhanh-secret`/`x-webhook-secret`, hoặc HMAC `x-nhanh-signature`/`x-hub-signature-256` trên body). Không cấu hình secret thì chấp nhận request (theo thiết lập hiện tại).
- **Dispatch (`WebhookDispatcher`)**:
  - `productAdd`, `productUpdate`: normalize payload (single/list) → upsert qua `ProductSyncOrchestrator`.
  - `productDelete`: nhận danh sách id → tìm map, trash/delete sản phẩm Woo liên kết, cập nhật `sync_status=deleted`.
  - `inventoryChange`: `InventoryNormalizer` tách từng item tồn kho → `InventorySyncService` cập nhật stock/status/meta Woo, tổng `nhanh_stock_total`, và depot `nhanh_sync_product_warehouses` (kể cả unmapped được báo cáo).
  - Event không hỗ trợ: acknowledge/ignored để tránh retry storm.

## 4. Gateway Nhanh API

- `Nhanh\Gateway\NhanhProductGateway`: adapter/gateway tập trung gọi SDK (`NhanhClientFactory`, v3 POS) cho `listCategories`, `listBrands`, `searchProducts`, `getProductDetail` (tương thích tên method SDK), chuẩn hóa kết quả về mảng/list.

## 5. Lưu trữ dữ liệu & nhật ký

- **Bảng chính (`Schema`, dbDelta)**:
  - `nhanh_sync_product_map`: ánh xạ Nhanh↔Woo (kể cả variation), snapshot giá/tồn, trạng thái sync, ảnh, tags, lỗi/thời gian.
  - `nhanh_sync_product_warehouses`: tồn theo kho/điểm (per product_map).
  - `nhanh_sync_term_map`: ánh xạ category/brand Nhanh↔WC taxonomy/term.
  - `nhanh_sync_product_terms`: quan hệ N-N sản phẩm↔term.
  - `nhanh_sync_logs`: audit log sự kiện sync.
  - `nhanh_sync_jobs`: theo dõi job batch/cron/manual.
  - `nhanh_sync_order_map`: ánh xạ đơn WC↔Nhanh (nền tảng cho sync đơn).
  - `nhanh_sync_stats_daily`: thống kê ngày (nền tảng báo cáo).
- **Versioning**: `NHANH_SYNCER_DB_VERSION` / `nhanh_syncer_db_version` tự nâng cấp schema qua `admin_init`.
- **Repositories/Services hỗ trợ**: `ProductMapRepository`, `TermMapRepository`, `WarehouseStockRepository`, `ProductTermRepository`, `JobRepository`, `LogRepository`, `OrderMapRepository`, `SettingsRepository/SettingsService`, `SyncStatusResolver`.

## 6. Cấu hình (settings tiêu biểu)

- **Kết nối/OAuth**: `app_id`, `secret_key`, `access_token`, `business_id`, `business_name`, `return_link`, `is_connected`, heartbeat/latency.
- **Webhook**: `webhook_url` (mặc định `/wp-json/nhanh-syncer/v1/webhook`), `webhook_secret`, `webhook_active`.
- **Sync/import**: `auto_sync_enabled`, `sync_interval_minutes`, `import_post_status`, `import_images`, `import_max_per_run`, `import_max_seconds`, `brand_taxonomy`.
- **Hành vi/cờ (nền tảng)**: `auto_price_update`, `auto_stock_update`, `auto_create_missing_web_product`, `safety_stock_buffer` (một số cờ là nền tảng/mở rộng; luồng import hiện ghi nhận dữ liệu theo pipeline chuẩn hóa).
- **UI/hiệu năng tra cứu**: `ui_mode`, phân trang compact/advanced cho products/terms/orders/logs.

## 7. Hành vi & lưu ý hiện tại

- `import_post_status` là nguồn quyết định post status khi import/upsert (mặc định `draft`), không tự suy luận từ trạng thái Nhanh trong policy hiện tại.
- `import_images` điều khiển nạp ảnh khi sync sản phẩm; ảnh không bị ép làm mới khi đã tồn tại (trừ khi bật cơ chế force trong mapper tương lai).
- Dữ liệu mở rộng (giá/VAT/branch, inventory depot, attributes/units/combos/suppliers/videos/warranty/raw, child/variation) được chuẩn hóa và lưu/meta/map để mở rộng độ phủ; mức tạo object Woo chuyên biệt phụ thuộc mapper hiện có (simple/variable theo `childs`).
- Chạy webhook endpoint cần WooCommerce active (plugin hạn chế boot tính năng khi thiếu Woo).

## 8. Phụ lục — thành phần mã chính

- Import: `Service\ImportService`, `Service\SyncCronService`, `Sync\ProductSyncOrchestrator`
- Normalization/Domain: `Domain\Product\*`, `Nhanh\Normalizer\ProductNormalizer`, `Nhanh\Normalizer\InventoryNormalizer`
- Policies/Mappers: `Sync\Policy\PricePolicy`, `Sync\Policy\PostStatusPolicy`, `Sync\Mapper\VariationMapper`
- Inventory: `Sync\InventorySyncService`, `Database\WarehouseStockRepository`
- Woo/Gateway: `Woo\WooBridge`, `Nhanh\Gateway\NhanhProductGateway`
- Webhook/REST: `Rest\WebhookController`, `Webhook\WebhookDispatcher`, `Webhook\Handler\*`
- DB: `Database\Schema`, `Database\*Repository`
