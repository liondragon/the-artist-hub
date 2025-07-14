<?php
// Admin Bar Area Stylesheet
function override_admin_bar_css() { 
	if ( is_admin_bar_showing() ) { 
		wp_enqueue_style( 'admin_css', get_template_directory_uri() . '/css/admin-bar-style.css', false, '1.0.0' );
	}
}
add_theme_support( 'admin-bar', array( 'callback' => '__return_false' ) );
add_action( 'admin_head', 'override_admin_bar_css' );
add_action( 'wp_head', 'override_admin_bar_css' );

// Admin Area Stylesheet
add_action( 'admin_enqueue_scripts', 'load_admin_style' );
function load_admin_style() {
	wp_enqueue_style( 'admin_css', get_template_directory_uri() . '/css/admin-style.css', false, '1.0.0' );
}

//Change Logo Image Title
function my_login_logo_url_title() {
    return 'The Flooring Artists';
}
add_filter( 'login_headertext', 'my_login_logo_url_title' );

//Add a Stylesheet
function my_custom_login() {
echo '<link rel="stylesheet" type="text/css" href="' . get_stylesheet_directory_uri() . '/css/ui.css" />';
}
add_action('login_head', 'my_custom_login');

//Custom Error Message
function login_error_override()
{
	return 'Incorrect login details.';
}
add_filter('login_errors', 'login_error_override');

//Autoselect Remember Me
function login_checked_remember_me() {
add_filter( 'login_footer', 'rememberme_checked' );
}
add_action( 'init', 'login_checked_remember_me' );
function rememberme_checked() {
echo "<script>document.getElementById('rememberme').checked = true;</script>";
}

function my_login_head() {
remove_action('login_head', 'wp_shake_js', 12);
}
add_action('login_head', 'my_login_head');

//Remove Footer Text
function wpse_remove_footer()
{
    add_filter( 'admin_footer_text',    '__return_false', 11 );
    add_filter( 'update_footer',        '__return_false', 11 );
}
add_action( 'admin_init', 'wpse_remove_footer' );
?>