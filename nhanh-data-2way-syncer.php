<?php
/**
 * Plugin Name: Nhanh Data 2-Way Syncer
 * Plugin URI: https://nghesachxua.com/
 * Description: Đồng bộ dữ liệu 2 chiều giữa Nhanh.vn và Website WordPress/WooCommerce.
 * Version: 0.1.0
 * Author: Puleeno Nguyen
 * Author URI: mailto:puleeno@gmail.com
 * Text Domain: nhanh-data-2way-syncer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NHANH_SYNCER_VERSION', '0.1.0');
define('NHANH_SYNCER_FILE', __FILE__);
define('NHANH_SYNCER_PATH', plugin_dir_path(__FILE__));
define('NHANH_SYNCER_URL', plugin_dir_url(__FILE__));
define('NHANH_SYNCER_BASENAME', plugin_basename(__FILE__));

$autoload = NHANH_SYNCER_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

spl_autoload_register(static function ($class) {
    $prefix = 'Puleeno\\NhanhVn\\Syncer\\';
    $base_dir = NHANH_SYNCER_PATH . 'src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

add_action('plugins_loaded', static function () {
    if (class_exists(\Puleeno\NhanhVn\Syncer\Plugin::class)) {
        \Puleeno\NhanhVn\Syncer\Plugin::instance()->boot();
    }
});

register_activation_hook(__FILE__, static function () {
    if (class_exists(\Puleeno\NhanhVn\Syncer\Plugin::class)) {
        \Puleeno\NhanhVn\Syncer\Plugin::activate();
    }
});

register_deactivation_hook(__FILE__, static function () {
    if (class_exists(\Puleeno\NhanhVn\Syncer\Plugin::class)) {
        \Puleeno\NhanhVn\Syncer\Plugin::deactivate();
    }
});
