<?php
declare(strict_types=1);

/**
 * Admin Security & Login Behavior
 *
 * Note: shared admin UI chrome/customization hooks now live in
 * `inc/modules/admin-ui/class-admin-ui-module.php`.
 */

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

/**
 * -----------------------------------------------------------------------------
 * 1. LOGIN CUSTOMIZATION
 * -----------------------------------------------------------------------------
 */

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
 * 2. SECURITY & REWRITES
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

add_action('wp_logout', 'auto_redirect_after_logout');
function auto_redirect_after_logout()
{
    nocache_headers();
    wp_safe_redirect(home_url());
    exit;
}
