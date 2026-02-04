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
    if (is_category() || is_archive() || is_post_type_archive() || is_home() || is_front_page() || is_page() || is_author() | is_tag())
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
            $content = file_get_contents(get_template_directory() . '/assets/templates/quotes/hardwood.html');
            break;

        case 'projects':
            $content = '';
            break;
    }

    return $content;
}

// Templates Button in Admin
add_action('media_buttons', function ($editor_id) {
    echo '<a href="#" id="my_template_button" class="button">Templates</a>';
});

function enqueue_custom_admin_js()
{
    wp_enqueue_script('my-custom-script', get_template_directory_uri() . '/assets/js/custom-script.js', array('jquery'), filemtime(get_template_directory() . '/assets/js/custom-script.js'), true);

    // Localize script to pass the template directory URL to JavaScript
    wp_localize_script('my-custom-script', 'templateData', array('url' => get_template_directory_uri() . '/assets/templates/quotes/'));
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_js');
