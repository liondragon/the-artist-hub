<?php
declare(strict_types=1);

/**
 * Custom Post Types Inclusion
 * 
 * Registers all custom post types and taxonomies by including
 * their respective definition files.
 */

require_once __DIR__ . '/cpt/equipment.php';
require_once __DIR__ . '/cpt/projects.php';
require_once __DIR__ . '/cpt/vehicles.php';
