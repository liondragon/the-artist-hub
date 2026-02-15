<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('TAH_PRICING_DB_VERSION')) {
    define('TAH_PRICING_DB_VERSION', '1.0.0');
}

/**
 * Pricing module bootstrap.
 */
final class TAH_Pricing_Module
{
    /**
     * Prevent duplicate bootstrap.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Boot pricing module includes when enabled and run schema migration.
     */
    public static function boot()
    {
        if (self::$booted || !self::is_enabled()) {
            return;
        }

        self::$booted = true;

        self::maybe_migrate_schema();
        self::load_module_classes();
        self::register_hooks();
    }

    /**
     * Runtime module capability flag.
     *
     * Default is ON; external code can disable via:
     * add_filter('tah_module_pricing_enabled', '__return_false');
     *
     * @return bool
     */
    public static function is_enabled()
    {
        return (bool) apply_filters('tah_module_pricing_enabled', true);
    }

    /**
     * Run DB schema migrations when installed version is outdated.
     */
    private static function maybe_migrate_schema()
    {
        $installed_version = (string) get_option('tah_pricing_db_version', '0');
        if (version_compare($installed_version, TAH_PRICING_DB_VERSION, '>=')) {
            return;
        }

        $migration_file = get_template_directory() . '/inc/migrations/pricing-tables.php';
        if (!file_exists($migration_file)) {
            return;
        }

        require_once $migration_file;

        if (function_exists('tah_pricing_create_tables')) {
            tah_pricing_create_tables();
            update_option('tah_pricing_db_version', TAH_PRICING_DB_VERSION);
        }
    }

    /**
     * Load module classes when files exist.
     */
    private static function load_module_classes()
    {
        $repository_file = __DIR__ . '/class-pricing-repository.php';
        if (file_exists($repository_file)) {
            require_once $repository_file;
        }

        $formula_file = __DIR__ . '/class-price-formula.php';
        if (file_exists($formula_file)) {
            require_once $formula_file;
        }

        $frontend_file = __DIR__ . '/class-quote-pricing-frontend.php';
        if (file_exists($frontend_file)) {
            require_once $frontend_file;
        }

        $view_tracking_file = __DIR__ . '/class-quote-view-tracking.php';
        if (file_exists($view_tracking_file)) {
            require_once $view_tracking_file;
        }

        if (!is_admin()) {
            return;
        }

        $admin_files = [
            __DIR__ . '/class-pricing-catalog-admin.php',
            __DIR__ . '/class-pricing-trade-presets.php',
            __DIR__ . '/class-quote-edit-screen.php',
            __DIR__ . '/class-quote-pricing-metabox.php',
        ];

        foreach ($admin_files as $admin_file) {
            if (file_exists($admin_file)) {
                require_once $admin_file;
            }
        }
    }

    /**
     * Register runtime hooks after classes are loaded.
     */
    private static function register_hooks()
    {
        add_action('before_delete_post', [__CLASS__, 'handle_before_delete_post']);
    }

    /**
     * Cascade-delete pricing rows when a quote is permanently deleted.
     */
    public static function handle_before_delete_post($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !class_exists('TAH_Pricing_Repository')) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'quotes') {
            return;
        }

        $repository = new TAH_Pricing_Repository();
        $repository->delete_quote_pricing_data($post_id);
    }
}
