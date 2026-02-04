<?php
declare(strict_types=1);

/**
 * Admin Customization & Security
 * 
 * Handles:
 * - Admin Cleanup (Menus, Widgets, Nodes)
 * - Login Page Customization
 * - Admin Styles
 * - Security Tweaks (Version Hiding, Login URL)
 */

/**
 * -----------------------------------------------------------------------------
 * 1. ADMIN UI CLEANUP
 * -----------------------------------------------------------------------------
 */

// Remove Admin Menu Items
function the_artist_remove_menu_items()
{
    remove_menu_page('tools.php');
    remove_submenu_page('themes.php', 'customize.php?return=' . urlencode($_SERVER['SCRIPT_NAME']));
    remove_submenu_page('themes.php', 'themes.php');
    remove_submenu_page('options-general.php', 'akismet-key-config');
    remove_submenu_page('admin.php', 'wp_mailjet_options_campaigns_menu');
}
add_action('admin_menu', 'the_artist_remove_menu_items', 999);

function the_artist_remove_posts_menu()
{
    remove_menu_page('edit.php');
}
add_action('admin_menu', 'the_artist_remove_posts_menu');

// Disable Admin Bar Menu Items
function remove_admin_bar_links()
{
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('new-content');      // Remove the 'add new' button
    $wp_admin_bar->remove_menu('comments');         // Remove the comments bubble
    $wp_admin_bar->remove_menu('about');            // Remove the about WordPress link
    $wp_admin_bar->remove_menu('wporg');            // Remove the WordPress.org link
    $wp_admin_bar->remove_menu('documentation');    // Remove the WordPress documentation link
    $wp_admin_bar->remove_menu('support-forums');   // Remove the support forums link
    $wp_admin_bar->remove_menu('feedback');         // Remove the feedback link
    $wp_admin_bar->remove_menu('view-site'); // 'Visit Site'
    $wp_admin_bar->remove_menu('dashboard'); // 'Dashboard'
    $wp_admin_bar->remove_menu('themes'); // 'Themes'
    $wp_admin_bar->remove_menu('widgets'); // 'Widgets'
    $wp_admin_bar->remove_menu('menus'); // 'Menus'
    $wp_admin_bar->remove_menu('customize'); //Customize
    $wp_admin_bar->remove_node('search');
}
add_action('wp_before_admin_bar_render', 'remove_admin_bar_links');
add_filter('update_right_now_text', '__return_false');

function clear_node_title($wp_admin_bar)
{
    // Get all the nodes
    $all_toolbar_nodes = $wp_admin_bar->get_nodes();
    // Create an array of node ID's we'd like to remove
    $clear_titles = array(
        'site-name',
    );

    foreach ($all_toolbar_nodes as $node) {
        // Run an if check to see if a node is in the array to clear_titles
        if (in_array($node->id, $clear_titles)) {
            // use the same node's properties
            $args = $node;

            // make the node title a blank string
            $args->title = 'Switch';

            // update the Toolbar node
            $wp_admin_bar->add_node($args);
        }
    }
}
add_action('admin_bar_menu', 'clear_node_title', 999);

// Remove Help Tab
function remove_context_menu_help()
{
    $current_screen = get_current_screen();
    $current_screen->remove_help_tabs();
}
add_action('admin_head', 'remove_context_menu_help');

// Change Admin Bar User Profile Greeting
add_filter('admin_bar_menu', 'replace_wordpress_howdy', 25);
function replace_wordpress_howdy($wp_admin_bar)
{
    $my_account = $wp_admin_bar->get_node('my-account');
    if (!$my_account) {
        return;
    }

    $newtext = str_replace('Howdy,', '', $my_account->title);

    $wp_admin_bar->add_node(array(
        'id' => 'my-account',
        'title' => $newtext,
    ));
}

// Remove Head Links
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rest_output_link_wp_head', 10);
remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
remove_action('template_redirect', 'rest_output_link_header', 11, 0);
remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wp_resource_hints', 2);

// Hide Wordpress Version
function disable_version()
{
    return '';
}
add_filter('the_generator', 'disable_version');

// Remove WP Logo from Admin Bar
add_action('admin_bar_menu', 'the_artist_remove_wp_logo', 999);
function the_artist_remove_wp_logo($wp_admin_bar)
{
    $wp_admin_bar->remove_node('wp-logo');
}

// Remove Dashboard Widgets
add_action('wp_dashboard_setup', 'the_artist_remove_dashboard_widgets');
function the_artist_remove_dashboard_widgets()
{
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
    remove_meta_box('dashboard_secondary', 'dashboard', 'side');
    remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
    remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
}

//Remove Footer Text
function wpse_remove_footer()
{
    add_filter('admin_footer_text', '__return_false', 11);
    add_filter('update_footer', '__return_false', 11);
}
add_action('admin_init', 'wpse_remove_footer');


/**
 * -----------------------------------------------------------------------------
 * 2. ADMIN STYLES & LOGIN CUSTOMIZATION
 * -----------------------------------------------------------------------------
 */

// Admin Styles - covers admin bar, admin area, and login page
function load_theme_admin_styles()
{
    $css_path = get_template_directory() . '/assets/css/admin.css';
    $version = file_exists($css_path) ? filemtime($css_path) : '1.0.0';
    wp_enqueue_style('theme-admin', get_template_directory_uri() . '/assets/css/admin.css', false, $version);
}
add_action('admin_enqueue_scripts', 'load_theme_admin_styles');
add_action('login_enqueue_scripts', 'load_theme_admin_styles');

// Also load on frontend for admin bar styling
function load_admin_bar_styles()
{
    if (is_admin_bar_showing()) {
        $css_path = get_template_directory() . '/assets/css/admin.css';
        $version = file_exists($css_path) ? filemtime($css_path) : '1.0.0';
        wp_enqueue_style('theme-admin', get_template_directory_uri() . '/assets/css/admin.css', false, $version);
    }
}
add_action('wp_enqueue_scripts', 'load_admin_bar_styles');
add_theme_support('admin-bar', array('callback' => '__return_false'));

// Change Login Logo Title
function my_login_logo_url_title()
{
    return 'The Flooring Artists';
}
add_filter('login_headertext', 'my_login_logo_url_title');


//Custom Error Message
function login_error_override()
{
    return 'Incorrect login details.';
}
add_filter('login_errors', 'login_error_override');

//Autoselect Remember Me
function login_checked_remember_me()
{
    add_filter('login_footer', 'rememberme_checked');
}
add_action('init', 'login_checked_remember_me');
function rememberme_checked()
{
    echo "<script>document.getElementById('rememberme').checked = true;</script>";
}

function my_login_head()
{
    remove_action('login_head', 'wp_shake_js', 12);
}
add_action('login_head', 'my_login_head');


/**
 * -----------------------------------------------------------------------------
 * 3. SECURITY & REWRITES
 * -----------------------------------------------------------------------------
 */

/**
 * Disable JPEG compression.
 */
add_filter('jpeg_quality', function ($arg) {
    return 100;
});

/**
 * Restrict REST API to localhost and logged-in users.
 */
function restrict_rest_api_to_localhost()
{
    if (is_user_logged_in()) {
        return;
    }

    $whitelist = ['127.0.0.1', "::1"];
    if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
        die('REST API is disabled for non-authenticated users.');
    }
}
add_action('rest_api_init', 'restrict_rest_api_to_localhost', 0);

// Custom Logout URL
add_filter('logout_url', 'custom_logout_url', 10, 2);
function custom_logout_url($logout_url, $redirect = null)
{
    $logout_url = wp_nonce_url(site_url('sign-in.php') . "?action=logout", 'log-out');
    return $logout_url;
}

// Rename wp-login.php
add_filter('login_url', 'new_login_page', 10, 3);
function new_login_page($login_url, $redirect, $force_reauth)
{
    $login_page = home_url('/sign-in.php/');
    return add_query_arg('redirect_to', $redirect, $login_page);
}
add_action('wp_logout', 'auto_redirect_after_logout');
function auto_redirect_after_logout()
{
    nocache_headers();
    wp_safe_redirect(home_url());
    exit;
}
