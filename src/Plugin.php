<?php

namespace Puleeno\NhanhVn\Syncer;

use Puleeno\NhanhVn\Syncer\Admin\Assets;
use Puleeno\NhanhVn\Syncer\Admin\Menu;
use Puleeno\NhanhVn\Syncer\Admin\Pages\Renderer;
use Puleeno\NhanhVn\Syncer\Admin\Settings;
use Puleeno\NhanhVn\Syncer\Contracts\SettingsRepositoryInterface;
use Puleeno\NhanhVn\Syncer\Contracts\SyncStatusResolverInterface;
use Puleeno\NhanhVn\Syncer\Database\Schema;
use Puleeno\NhanhVn\Syncer\Repository\SettingsRepository;
use Puleeno\NhanhVn\Syncer\Service\SettingsService;
use Puleeno\NhanhVn\Syncer\Service\SyncStatusResolver;
use Puleeno\NhanhVn\Syncer\Service\TermTaxonomyResolver;

class Plugin
{
    /** @var Plugin|null */
    private static $instance = null;

    /** @var array<string,mixed> */
    private $services = [];

    /** @var bool */
    private $booted = false;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bootstrap plugin runtime (hooks). Safe to call once.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Keep DB schema in sync when plugin version/db version changes.
        add_action('admin_init', [Schema::class, 'maybe_upgrade']);

        if (!self::is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'notice_woocommerce_required']);
            // Still allow settings page registration in future if desired; for now limit admin UI.
            return;
        }

        if (is_admin()) {
            $this->menu()->register();
            $this->assets()->register();
            $this->settingsController()->register();
        }
    }

    /**
     * Simple service locator with lazy singletons for core services.
     * @param string $id
     * @param callable $factory
     * @return mixed
     */
    public function make(string $id, callable $factory)
    {
        if (!array_key_exists($id, $this->services)) {
            $this->services[$id] = $factory($this);
        }
        return $this->services[$id];
    }

    public function settingsRepository(): SettingsRepositoryInterface
    {
        return $this->make('settings_repository', function () {
            return new SettingsRepository();
        });
    }

    public function settingsService(): SettingsService
    {
        return $this->make('settings_service', function (Plugin $plugin) {
            return new SettingsService($plugin->settingsRepository());
        });
    }

    public function syncStatusResolver(): SyncStatusResolverInterface
    {
        return $this->make('sync_status_resolver', function () {
            return new SyncStatusResolver();
        });
    }

    public function termTaxonomyResolver(): TermTaxonomyResolver
    {
        return $this->make('term_taxonomy_resolver', function (Plugin $plugin) {
            $settings = $plugin->settingsService()->all();
            $brand = isset($settings['brand_taxonomy']) ? (string) $settings['brand_taxonomy'] : 'product_brand';
            return new TermTaxonomyResolver($brand);
        });
    }

    public function renderer(): Renderer
    {
        return $this->make('renderer', function (Plugin $plugin) {
            return new Renderer($plugin->syncStatusResolver(), $plugin->termTaxonomyResolver());
        });
    }

    public function menu(): Menu
    {
        return $this->make('menu', function (Plugin $plugin) {
            return new Menu($plugin->renderer());
        });
    }

    public function assets(): Assets
    {
        return $this->make('assets', function () {
            return new Assets();
        });
    }

    public function settingsController(): Settings
    {
        return $this->make('settings_controller', function (Plugin $plugin) {
            return new Settings($plugin->settingsService());
        });
    }

    public function notice_woocommerce_required(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Nhanh Data 2-Way Syncer yêu cầu WooCommerce đang hoạt động để đồng bộ sản phẩm, danh mục, thương hiệu và đơn hàng.', 'nhanh-data-2way-syncer');
        echo '</p></div>';
    }

    public static function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function activate(): void
    {
        // Ensure settings defaults and schema exist.
        $plugin = self::instance();
        $plugin->settingsService()->ensureDefaults();
        Schema::install();
    }

    public static function deactivate(): void
    {
        // Keep settings/data for safety.
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_settings(): array
    {
        return self::instance()->settingsService()->all();
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function update_settings(array $settings): void
    {
        self::instance()->settingsService()->save($settings);
    }
}
