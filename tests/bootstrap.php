<?php

/**
 * PHPUnit bootstrap for Nhanh Data 2-Way Syncer.
 *
 * Stubs the WordPress + WooCommerce functions the plugin depends on so unit
 * tests can run without a full WP install. State is kept in $GLOBALS so tests
 * can seed and assert on it.
 */

// ---------------------------------------------------------------------------
// WordPress constants
// ---------------------------------------------------------------------------

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/fixtures/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('NHANH_SYNCER_VERSION')) {
    define('NHANH_SYNCER_VERSION', '0.1.0');
}
if (!defined('NHANH_SYNCER_DB_VERSION')) {
    define('NHANH_SYNCER_DB_VERSION', '1.0.0');
}
if (!defined('NHANH_SYNCER_FILE')) {
    define('NHANH_SYNCER_FILE', __FILE__);
}
if (!defined('NHANH_SYNCER_PATH')) {
    define('NHANH_SYNCER_PATH', __DIR__ . '/../');
}
if (!defined('NHANH_SYNCER_URL')) {
    define('NHANH_SYNCER_URL', 'http://example.com/');
}
if (!defined('NHANH_SYNCER_BASENAME')) {
    define('NHANH_SYNCER_BASENAME', 'nhanh-data-2way-syncer/nhanh-data-2way-syncer.php');
}

// ---------------------------------------------------------------------------
// Generic WordPress functions
// ---------------------------------------------------------------------------

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {}
}
if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) { return $value; }
}
if (!function_exists('add_menu_page')) {
    function add_menu_page(...$args) {}
}
if (!function_exists('add_submenu_page')) {
    function add_submenu_page(...$args) {}
}
if (!function_exists('is_admin')) {
    function is_admin() { return false; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://example.com/wp-admin/' . ltrim($path, '/'); }
}
if (!function_exists('home_url')) {
    function home_url($path = '') { return 'http://example.com/' . ltrim($path, '/'); }
}
if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302) {}
}
if (!function_exists('wp_die')) {
    function wp_die($message = '') { throw new \RuntimeException((string) $message); }
}
if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action, $query_arg = false) {}
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($str) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $str)); }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return $text; }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default') { return $text; }
}
if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default') { return $number === 1 ? $single : $plural; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data); }
}
if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0) {
        if ($type === 'mysql' || $type === 'mysql2') {
            return $gmt ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        }
        return time();
    }
}
if (!function_exists('get_option')) {
    function get_option($option, $default = false) { return $GLOBALS['__wp_options'][$option] ?? $default; }
}
if (!function_exists('update_option')) {
    function update_option($option, $value) { $GLOBALS['__wp_options'][$option] = $value; return true; }
}
if (!function_exists('delete_option')) {
    function delete_option($option) { unset($GLOBALS['__wp_options'][$option]); return true; }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        $query = is_array($args) ? http_build_query($args) : ($args . '=' . rawurlencode($args));
        return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
    }
}
if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) { $GLOBALS['__wp_transients'][$key] = $value; return true; }
}
if (!function_exists('get_transient')) {
    function get_transient($key) { return $GLOBALS['__wp_transients'][$key] ?? false; }
}
if (!function_exists('delete_transient')) {
    function delete_transient($key) { unset($GLOBALS['__wp_transients'][$key]); return true; }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) { return number_format((float) $number, $decimals); }
}
if (!function_exists('wpautop')) {
    function wpautop($text) { return '<p>' . $text . '</p>'; }
}
if (!function_exists('wp_filter_post_html')) {
    function wp_filter_post_html($html) { return $html; }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        $title = strtolower(trim((string) $title));
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        return trim($title, '-');
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return $thing instanceof \WP_Error; }
}
if (!function_exists('wp_slash')) {
    function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : addslashes((string) $value); }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return 1; }
}

// ---------------------------------------------------------------------------
// Cron stubs
// ---------------------------------------------------------------------------

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        $GLOBALS['__wp_cron_events'][$hook] = ['timestamp' => $timestamp, 'recurrence' => $recurrence, 'args' => $args];
        return true;
    }
}
if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = []) { unset($GLOBALS['__wp_cron_events'][$hook]); return true; }
}
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = []) {
        $GLOBALS['__wp_cron_events'][$hook . '_once'] = ['timestamp' => $timestamp, 'hook' => $hook, 'args' => $args];
        return true;
    }
}
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) { return $GLOBALS['__wp_cron_events'][$hook]['timestamp'] ?? false; }
}

// ---------------------------------------------------------------------------
// WP_Error stub
// ---------------------------------------------------------------------------

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $errors = [];
        public function __construct($code = '', $message = '')
        {
            if ($code !== '') {
                $this->errors[$code] = $message;
            }
        }
        public function get_error_message()
        {
            return implode('; ', $this->errors);
        }
    }
}

// ---------------------------------------------------------------------------
// WooCommerce stubs
// ---------------------------------------------------------------------------

if (!class_exists('WooCommerce')) {
    class WooCommerce
    {
        public static $version = '9.0.0';
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product
    {
        public $id = 0;
        private $data = [];

        public function set_name($name) { $this->data['name'] = $name; return $this; }
        public function set_sku($sku) { $this->data['sku'] = $sku; return $this; }
        public function set_price($price) { $this->data['price'] = $price; return $this; }
        public function set_stock_quantity($qty) { $this->data['stock_qty'] = $qty; return $this; }
        public function set_stock_status($status) { $this->data['stock_status'] = $status; return $this; }
        public function set_description($desc) { $this->data['description'] = $desc; return $this; }
        public function set_status($status) { $this->data['status'] = $status; return $this; }
        public function set_category_ids($ids) { $this->data['category_ids'] = $ids; return $this; }
        public function set_images($images) { $this->data['images'] = $images; return $this; }

        public function save()
        {
            if ($this->id <= 0) {
                $this->id = $GLOBALS['__next_wc_product_id']++;
            }
            $GLOBALS['__wc_products'][$this->id] = $this;
            return $this->id;
        }

        public function get_data() { return $this->data; }
        public function get_id() { return $this->id; }
    }
}

if (!function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku($sku) {
        foreach ($GLOBALS['__wc_products'] ?? [] as $id => $product) {
            $data = method_exists($product, 'get_data') ? $product->get_data() : [];
            if (isset($data['sku']) && $data['sku'] === $sku) {
                return (int) $id;
            }
        }
        return 0;
    }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return $GLOBALS['__wc_products'][$id] ?? null; }
}

// ---------------------------------------------------------------------------
// Term stubs
// ---------------------------------------------------------------------------

if (!class_exists('WP_Term')) {
    class WP_Term
    {
        public $term_id;
        public $name;
        public $taxonomy;
        public function __construct($id, $name, $taxonomy)
        {
            $this->term_id = $id;
            $this->name = $name;
            $this->taxonomy = $taxonomy;
        }
    }
}
if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy = '') {
        foreach ($GLOBALS['__wp_terms'] ?? [] as $term) {
            if ($term->taxonomy === $taxonomy && isset($term->{$field}) && $term->{$field} === $value) {
                return $term;
            }
        }
        return false;
    }
}
if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy, $args = []) {
        $id = $GLOBALS['__next_term_id']++;
        $GLOBALS['__wp_terms'][$id] = new WP_Term($id, $term, $taxonomy);
        return ['term_id' => $id, 'term_taxonomy_id' => $id];
    }
}

// ---------------------------------------------------------------------------
// wpdb: small in-memory SQL-ish mock
// ---------------------------------------------------------------------------

if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $num_rows = 0;
        /** @var array<string,array<int,array<string,mixed>>> */
        public $tables = [];

        public function get_charset_collate() { return ''; }

        public function prepare($query, ...$args)
        {
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }
            foreach ($args as $arg) {
                if ($arg === null) {
                    $query = preg_replace('/%s/', 'NULL', $query, 1);
                } elseif (is_int($arg) || is_float($arg)) {
                    $query = preg_replace('/%d/', (string) $arg, $query, 1);
                } elseif (is_array($arg)) {
                    $query = preg_replace('/%s/', "'" . addslashes(json_encode($arg)) . "'", $query, 1);
                } else {
                    $query = preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $query, 1);
                }
            }
            return $query;
        }

        private function conditions(string $where): array
        {
            $conds = [];
            foreach (preg_split('/\s+AND\s+/i', $where) as $part) {
                if (preg_match('/(\w+)\s*=\s*(.+)$/', trim($part), $m)) {
                    $conds[$m[1]] = trim($m[2], "'\"");
                }
            }
            return $conds;
        }

        private function matches(array $row, array $conds): bool
        {
            foreach ($conds as $key => $value) {
                if (!array_key_exists($key, $row) || (string) $row[$key] !== (string) $value) {
                    return false;
                }
            }
            return true;
        }

        private function select(string $query): array
        {
            if (!preg_match('/FROM\s+(\w+)(.*)$/is', $query, $m)) {
                return [];
            }
            $table = $m[1];
            $rest = $m[2];
            $rows = array_values($this->tables[$table] ?? []);

            if (preg_match('/WHERE\s+(.*?)(?:\s+ORDER\s+BY|\s+LIMIT|$)/is', $rest, $wm)) {
                $conds = $this->conditions($wm[1]);
                $rows = array_values(array_filter($rows, function ($row) use ($conds) {
                    return $this->matches($row, $conds);
                }));
            }

            if (preg_match('/ORDER\s+BY\s+(\w+)(\s+ASC|\s+DESC)?/i', $rest, $om)) {
                $col = $om[1];
                $dir = strtoupper(trim($om[2] ?? 'ASC')) === 'DESC' ? -1 : 1;
                usort($rows, function ($a, $b) use ($col, $dir) {
                    return strcmp((string) ($a[$col] ?? ''), (string) ($b[$col] ?? '')) * $dir;
                });
            }

            if (preg_match('/LIMIT\s+(\d+)/i', $rest, $lm)) {
                $rows = array_slice($rows, 0, (int) $lm[1]);
            }

            return $rows;
        }

        public function insert($table, $data)
        {
            $this->tables[$table] = $this->tables[$table] ?? [];
            $id = count($this->tables[$table]) + 1;
            $data['id'] = $id;
            $this->tables[$table][$id] = $data;
            $this->insert_id = $id;
            $this->num_rows = 1;
            return 1;
        }

        public function update($table, $data, $where)
        {
            $count = 0;
            foreach ($this->tables[$table] ?? [] as $key => $row) {
                if ($this->matches($row, $where)) {
                    $this->tables[$table][$key] = array_merge($row, $data);
                    $count++;
                }
            }
            $this->num_rows = $count;
            return $count;
        }

        public function get_var($query)
        {
            $rows = $this->select($query);
            if (!$rows) {
                return null;
            }
            // Honour "SELECT id FROM ..." by returning the id column.
            if (preg_match('/SELECT\s+id\s+FROM/i', $query) && array_key_exists('id', $rows[0])) {
                return $rows[0]['id'];
            }
            $values = array_values($rows[0]);
            return $values[0] ?? null;
        }

        public function get_row($query, $output = OBJECT)
        {
            $rows = $this->select($query);
            if (!$rows) {
                return null;
            }
            return $output === ARRAY_A ? $rows[0] : (object) $rows[0];
        }

        public function get_results($query, $output = OBJECT)
        {
            $rows = $this->select($query);
            $this->num_rows = count($rows);
            $out = [];
            foreach ($rows as $row) {
                $out[] = $output === ARRAY_A ? $row : (object) $row;
            }
            return $out;
        }
    }
}

// ---------------------------------------------------------------------------
// Global state
// ---------------------------------------------------------------------------

$GLOBALS['wpdb'] = new wpdb();
$GLOBALS['__wp_options'] = $GLOBALS['__wp_options'] ?? [];
$GLOBALS['__wp_transients'] = $GLOBALS['__wp_transients'] ?? [];
$GLOBALS['__wp_cron_events'] = $GLOBALS['__wp_cron_events'] ?? [];
$GLOBALS['__wc_products'] = $GLOBALS['__wc_products'] ?? [];
$GLOBALS['__next_wc_product_id'] = $GLOBALS['__next_wc_product_id'] ?? 1000;
$GLOBALS['__wp_terms'] = $GLOBALS['__wp_terms'] ?? [];
$GLOBALS['__next_term_id'] = $GLOBALS['__next_term_id'] ?? 100;