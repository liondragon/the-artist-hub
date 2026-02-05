<?php
declare(strict_types=1);

/**
 * Editor Configuration
 * 
 * Configures the block editor (Gutenberg), Classic Editor (TinyMCE),
 * and default content templates.
 */

// Remove Gutenberg Block Library CSS from loading on the frontend
function smartwp_remove_wp_block_library_css()
{
    wp_dequeue_style('wp-block-library'); // WordPress core
    wp_dequeue_style('wp-block-library-theme'); // WordPress core
    wp_dequeue_style('wc-block-style'); // WooCommerce
    wp_dequeue_style('storefront-gutenberg-blocks'); // Storefront theme
}
add_action('wp_enqueue_scripts', 'smartwp_remove_wp_block_library_css', 100);

/**
 * COMPLETELY DISABLE BLOCK EDITOR (GUTENBERG)
 * 
 * As per internal requirements, this theme is designed to be lightweight
 * and does not support the Block Editor. Use the Classic Editor.
 */
add_filter('use_block_editor_for_post', '__return_false', 10);
add_filter('use_widgets_block_editor', '__return_false');

// Disable Lightbox on Some Pages
function my_lbwps_enabled($enabled, $id)
{
    if (is_category() || is_archive() || is_post_type_archive() || is_home() || is_front_page() || is_page() || is_author() || is_tag())
        return false;
    return $enabled;
}
add_filter('lbwps_enabled', 'my_lbwps_enabled', 10, 2);

// Default Editor Content
add_filter('default_content', 'pu_default_editor_content');

function pu_default_editor_content($content)
{

    global $post_type;

    switch ($post_type) {
        case 'post':
            $content = '';
            break;

        case 'quotes':
            $file = get_template_directory() . '/assets/templates/quotes/hardwood.html';
            $content = file_exists($file) ? file_get_contents($file) : '';
            break;

        case 'projects':
            $content = '';
            break;
    }

    return $content;
}

// Templates Button in Admin (only for quotes post type)
add_action('media_buttons', function ($editor_id) {
    global $post_type;
    if ($post_type !== 'quotes') {
        return;
    }
    echo '<a href="#" id="my_template_button" class="button">Templates</a>';
});

function enqueue_custom_admin_js($hook)
{
    // Only load on post editor pages
    if (!in_array($hook, ['post.php', 'post-new.php'])) {
        return;
    }

    wp_enqueue_script('my-custom-script', get_template_directory_uri() . '/assets/js/custom-script.js', array('jquery'), filemtime(get_template_directory() . '/assets/js/custom-script.js'), true);

    // Get templates dynamically from the directory
    $templates = get_quote_templates();

    // Pre-escape data for safe JS interpolation
    $escaped_templates = array_map(function ($t) {
        return [
            'file' => esc_attr($t['file']),
            'title' => esc_html($t['title']),
            'description' => esc_html($t['description'])
        ];
    }, $templates);

    // Localize script to pass templates data to JavaScript
    wp_localize_script('my-custom-script', 'templateData', array(
        'url' => get_template_directory_uri() . '/assets/templates/quotes/',
        'templates' => $escaped_templates
    ));
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_js');

/**
 * Scan templates directory and return array of templates with metadata
 */
function get_quote_templates()
{
    $templates = [];
    $dir = get_template_directory() . '/assets/templates/quotes/';

    foreach (glob($dir . '*.html') as $file) {
        $content = file_exists($file) ? file_get_contents($file) : '';
        $metadata = parse_template_metadata($content);
        $filename = basename($file);

        $templates[] = [
            'file' => $filename,
            'title' => $metadata['title'] ?? ucwords(str_replace(['_', '-'], ' ', pathinfo($file, PATHINFO_FILENAME))),
            'description' => $metadata['description'] ?? ''
        ];
    }

    // Sort alphabetically by title
    usort($templates, fn($a, $b) => strcmp($a['title'], $b['title']));

    return $templates;
}

/**
 * Parse template metadata from HTML comment header
 * 
 * Expected format:
 * <!--
 * Template: Template Name
 * Description: Template description text
 * -->
 */
function parse_template_metadata($content)
{
    $metadata = [];

    if (preg_match('/<!--\s*Template:\s*(.+?)\s*Description:\s*(.+?)\s*-->/s', $content, $matches)) {
        $metadata['title'] = trim($matches[1]);
        $metadata['description'] = trim($matches[2]);
    }

    return $metadata;
}
