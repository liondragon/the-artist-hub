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

        if (!is_admin()) {
            return;
        }

        $admin_files = [
            __DIR__ . '/class-pricing-catalog-admin.php',
            __DIR__ . '/class-quote-edit-screen.php',
            __DIR__ . '/class-quote-pricing-metabox.php',
        ];

        foreach ($admin_files as $admin_file) {
            if (file_exists($admin_file)) {
                require_once $admin_file;
            }
        }
    }
}
