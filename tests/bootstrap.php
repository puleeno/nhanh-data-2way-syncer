<?php

/**
 * PHPUnit bootstrap for Nhanh Data 2-Way Syncer.
 *
 * Stubs the WordPress + WooCommerce functions the plugin depends on so unit
 * tests can run without a full WP install. Each stub is backed by a simple
 * in-memory store so tests can assert on state.
 */

// ---------------------------------------------------------------------------
// WordPress function stubs
// ---------------------------------------------------------------------------

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { /* no-op */ }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { /* no-op */ }
}
if (!function_exists('do_action')) {
    function do_action($hook, ...$args) { /* no-op */ }
}
if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) { return $value; }
}
if (!function_exists('add_menu_page')) {
    function add_menu_page(...$args) { /* no-op */ }
}
if (!function_exists('add_submenu_page')) {
    function add_submenu_page(...$args) { /* no-op */ }
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
    function wp_safe_redirect($location, $status = 302) { /* no-op */ }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '') { throw new Exception($message); }
}
if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action, $query_arg = false) { /* no-op */ }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string)$str)); }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($str) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string)$str)); }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return $text; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data); }
}
if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0) {
        if ($type === 'mysql') {
            return $gmt ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        }
        return $type === 'timestamp' ? time() : time();
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
        if (is_array($args)) {
            $query = http_build_query($args);
        } else {
            $query = $args . '=' . rawurlencode($args);
        }
        return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
    }
}
if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        return substr(bin2hex(random_bytes($length)), 0, $length);
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
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) { $GLOBALS['__wp_cron_events'][$hook] = ['timestamp' => $timestamp, 'recurrence' => $recurrence, 'args' => $args]; return true; }
}
if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = []) { unset($GLOBALS['__wp_cron_events'][$hook]); return true; }
}
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = []) { $GLOBALS['__wp_cron_events'][$hook . '_once'] = ['timestamp' => $timestamp, 'hook' => $hook, 'args' => $args]; return true; }
}
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) { return $GLOBALS['__wp_cron_events'][$hook]['timestamp'] ?? false; }
}
if (!function_exists('wp_get_scheduled_hook_events')) {
    function wp_get_scheduled_hook_events($hook) { return isset($GLOBALS['__wp_cron_events'][$hook]) ? [$GLOBALS['__wp_cron_events'][$hook]] : []; }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) { return number_format((float)$number, $decimals); }
}
if (!function_exists('wpautop')) {
    function wpautop($text) { return '<p>' . $text . '</p>'; }
}
if (!function_exists('wp_filter_post_html')) {
    function wp_filter_post_html($html) { return $html; }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($title) { return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-')); }
}

// ---------------------------------------------------------------------------
// WooCommerce stubs
// ---------------------------------------------------------------------------

if (!class_exists('WC_Product')) {
    class WC_Product {
        public $id;
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
        public function save() { $this->id = $GLOBALS['__next_wc_product_id']++; $GLOBALS['__wc_products'][$this->id] = $this; return $this->id; }
        public function get_data() { return $this->data; }
    }
}
if (!class_exists('WC_Product_Variation')) {
    class WC_Product_Variation extends WC_Product {}
}
if (!function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku($sku) {
        foreach ($GLOBALS['__wc_products'] ?? [] as $id => $product) {
            if ($product->get_data()['sku'] === $sku) return $id;
        }
        return 0;
    }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return $GLOBALS['__wc_products'][$id] ?? null; }
}
if (!function_exists('wc_create_product')) {
    function wc_create_product($args) { $p = new WC_Product(); foreach ($args as $k => $v) { if (method_exists($p, 'set_' . $k)) $p->{'set_' . $k}($v); } return $p->save(); }
}

// ---------------------------------------------------------------------------
// WordPress term stubs
// ---------------------------------------------------------------------------

if (!class_exists('WP_Term')) {
    class WP_Term {
        public $term_id;
        public $name;
        public $taxonomy;
        public function __construct($id, $name, $taxonomy) { $this->term_id = $id; $this->name = $name; $this->taxonomy = $taxonomy; }
    }
}
if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy) {
        foreach ($GLOBALS['__wp_terms'] ?? [] as $term) {
            if ($term->taxonomy === $taxonomy && $term->{$field} === $value) return $term;
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
// WP_Error stub
// ---------------------------------------------------------------------------

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        public function __construct($code = '', $message = '') { if ($code) $this->errors[$code] = $message; }
        public function get_error_message() { return implode('; ', $this->errors); }
    }
}

// ---------------------------------------------------------------------------
// WordPress constants
// ---------------------------------------------------------------------------

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
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
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}
if (!defined('MONTH_IN_SECONDS')) {
    define('MONTH_IN_SECONDS', 2592000);
}
if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
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
    define('NHANH_SYNCER_BASENAME', 'nhan-data-2way-syncer/nhan-data-2way-syncer.php');
}

// ---------------------------------------------------------------------------
// WP function stubs
// ---------------------------------------------------------------------------

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return $thing instanceof WP_Error; }
}
if (!function_exists('wp_slash')) {
    function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : addslashes($value); }
}
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) $args = get_object_vars($args);
        if (!is_array($args)) $args = wp_parse_str($args, []);
        return array_merge($defaults, $args);
    }
}
if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$result) { parse_str($string, $result); }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') { return 'Test Blog'; }
}
if (!function_exists('get_locale')) {
    function get_locale() { return 'en_US'; }
}
if (!function_exists('is_multisite')) {
    function is_multisite() { return false; }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return 1; }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() { return new class { public $ID = 1; public $user_login = 'admin'; }; }
}
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) { return $single ? '' : []; }
}
if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) { return true; }
}
if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $key) { return true; }
}
if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) { return null; }
}
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) { return true; }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) { return $single ? '' : []; }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) { return true; }
}
if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $key) { return true; }
}
if (!function_exists('get_the_terms')) {
    function get_the_terms($post_id, $taxonomy) { return false; }
}
if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($object_id, $terms, $taxonomy) { return []; }
}
if (!function_exists('get_term')) {
    function get_term($term_id, $taxonomy = '') { return new WP_Term($term_id, 'Test Term', $taxonomy); }
}
if (!function_exists('get_terms')) {
    function get_terms($args = []) { return []; }
}
if (!function_exists('term_exists')) {
    function term_exists($term, $taxonomy = '', $parent = null) { return null; }
}
if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy, $args = []) {
        $id = $GLOBALS['__next_term_id']++;
        $GLOBALS['__wp_terms'][$id] = new WP_Term($id, $term, $taxonomy);
        return ['term_id' => $id, 'term_taxonomy_id' => $id];
    }
}
if (!function_exists('wp_update_term')) {
    function wp_update_term($term_id, $taxonomy, $args = []) { return ['term_id' => $term_id, 'term_taxonomy_id' => $term_id]; }
}
if (!function_exists('wp_delete_term')) {
    function wp_delete_term($term_id, $taxonomy) { unset($GLOBALS['__wp_terms'][$term_id]); return true; }
}
if (!function_exists('get_term_link')) {
    function get_term_link($term, $taxonomy = '') { return 'http://example.com/term/' . $term; }
}
if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy = '') {
        foreach ($GLOBALS['__wp_terms'] ?? [] as $term) {
            if ($term->taxonomy === $taxonomy && $term->{$field} === $value) return $term;
        }
        return false;
    }
}
if (!function_exists('term_exists')) {
    function term_exists($term, $taxonomy = '', $parent = null) {
        foreach ($GLOBALS['__wp_terms'] ?? [] as $t) {
            if ($t->name === $term && $t->taxonomy === $taxonomy) return ['term_id' => $t->term_id];
        }
        return null;
    }
}

// ---------------------------------------------------------------------------
// WP Database mock
// ---------------------------------------------------------------------------

if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $num_rows = 0;
        public $tables = [];

        public function get_charset_collate() { return ''; }
        public function prepare($query, ...$args) {
            foreach ($args as $arg) {
                if (is_string($arg)) $query = preg_replace('/%s/', "'" . addslashes($arg) . "'", $query, 1);
                elseif (is_int($arg)) $query = preg_replace('/%d/', (string)$arg, $query, 1);
            }
            return $query;
        }
        public function insert($table, $data) {
            $this->tables[$table] = $this->tables[$table] ?? [];
            $id = count($this->tables[$table]) + 1;
            $data['id'] = $id;
            $this->tables[$table][$id] = $data;
            $this->insert_id = $id;
            return true;
        }
        public function update($table, $data, $where) {
            foreach ($this->tables[$table] ?? [] as $key => &$row) {
                $match = true;
                foreach ($where as $k => $v) {
                    if (($row[$k] ?? null) != $v) { $match = false; break; }
                }
                if ($match) { $row = array_merge($row, $data); $this->tables[$table][$key] = $row; return true; }
            }
            return false;
        }
        public function get_var($query) {
            // Match SELECT id FROM table WHERE col = value
            if (preg_match('/SELECT\s+id\s+FROM\s+(\w+)\s+WHERE\s+(\w+)\s*=\s*(.+)/i', $query, $m)) {
                $table = $m[1];
                $col = $m[2];
                $val = trim($m[3], "'\"");
                foreach ($this->tables[$table] ?? [] as $row) {
                    if (($row[$col] ?? null) == $val) return $row['id'] ?? null;
                }
            }
            return null;
        }
        public function get_row($query, $output = OBJECT) {
            // Match SELECT * FROM table WHERE col = value
            if (preg_match('/FROM\s+(\w+)\s+WHERE\s+(\w+)\s*=\s*(.+?)(?:\s+AND\s+|$)/i', $query, $m)) {
                $table = $m[1];
                $col = $m[2];
                $val = trim($m[3], "'\"");
                foreach ($this->tables[$table] ?? [] as $row) {
                    if (($row[$col] ?? null) == $val) return (object)$row;
                }
            }
            return null;
        }
        public function get_results($query, $output = OBJECT) {
            // Match SELECT * FROM table [WHERE ...]
            if (preg_match('/FROM\s+(\w+)/i', $query, $m)) {
                $table = $m[1];
                return array_values($this->tables[$table] ?? []);
            }
            return [];
        }
    }
}

$GLOBALS['wpdb'] = new wpdb();
$GLOBALS['__wp_options'] = $GLOBALS['__wp_options'] ?? [];
$GLOBALS['__wp_transients'] = $GLOBALS['__wp_transients'] ?? [];
$GLOBALS['__wp_cron_events'] = $GLOBALS['__wp_cron_events'] ?? [];
$GLOBALS['__wc_products'] = $GLOBALS['__wc_products'] ?? [];
$GLOBALS['__next_wc_product_id'] = $GLOBALS['__next_wc_product_id'] ?? 1000;
$GLOBALS['__wp_terms'] = $GLOBALS['__wp_terms'] ?? [];
$GLOBALS['__next_term_id'] = $GLOBALS['__next_term_id'] ?? 100;