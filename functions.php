<?php
declare(strict_types=1);
/**
 * The Artist Theme - Functions
 *
 * This file handles the core functionality of the theme, including:
 * - Theme setup and initialization
 * - Asset enqueueing (CSS/JS)
 * - Custom post types and taxonomies
 * - Security enhancements
 * - Admin customization
 *
 * @package The_Artist
 * @since 3.0
 */

// Load and initialize the main theme class
require_once __DIR__ . '/inc/class-the-artist-hub.php';
The_Artist_Hub::get_instance();

include_once __DIR__ . '/inc/widgets.php';
include_once __DIR__ . '/inc/template-tags.php';
include_once __DIR__ . '/inc/notes_function.php';
include_once __DIR__ . '/inc/editor-config.php';
include_once __DIR__ . '/inc/editor-filters.php';
include_once __DIR__ . '/inc/search-filters.php';
require_once __DIR__ . '/inc/modules/class-module-registry.php';
TAH_Module_Registry::boot();
include_once __DIR__ . '/inc/admin.php';
include_once __DIR__ . '/inc/users.php';

require_once __DIR__ . '/inc/comments.php';
require_once __DIR__ . '/inc/custom_post_types.php';
