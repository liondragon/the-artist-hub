<?php
declare(strict_types=1);

/**
 * Theme module registry.
 *
 * Keep module loading explicit and deterministic.
 */
final class TAH_Module_Registry
{
    /**
     * Prevent duplicate registry boot.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Boot all registered theme modules in fixed order.
     */
    public static function boot()
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        require_once __DIR__ . '/info-sections/class-info-sections-module.php';
        TAH_Info_Sections_Module::boot();

        require_once __DIR__ . '/pricing/class-pricing-module.php';
        TAH_Pricing_Module::boot();
    }
}
