<?php
declare(strict_types=1);

/**
 * Info Sections module bootstrap.
 *
 * Scope:
 * - Global Info Sections CPT registration
 * - Quote Info Sections metabox/rendering
 * - Trade preset editor integration
 */
final class TAH_Info_Sections_Module
{
    /**
     * Prevent duplicate bootstrap when loaded from multiple entry points.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Boot module includes when enabled.
     */
    public static function boot()
    {
        if (self::$booted || !self::is_enabled()) {
            return;
        }

        self::$booted = true;

        require_once __DIR__ . '/../../cpt/template-parts.php';
        require_once __DIR__ . '/../../admin/class-quote-sections.php';

        if (is_admin()) {
            require_once __DIR__ . '/../../admin/class-trade-presets.php';
        }
    }

    /**
     * Runtime module capability flag.
     *
     * Default is ON; external code can disable via:
     * add_filter('tah_module_info_sections_enabled', '__return_false');
     *
     * @return bool
     */
    public static function is_enabled()
    {
        return (bool) apply_filters('tah_module_info_sections_enabled', true);
    }
}
