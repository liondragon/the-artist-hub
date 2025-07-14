<?php
// Custom Logout URL
add_filter('logout_url', 'custom_logout_url', 10, 2);
function custom_logout_url($logout_url, $redirect=null){
    $logout_url = wp_nonce_url(site_url('sign-in.php')."?action=logout", 'log-out' );
    return $logout_url ;
}

// Rename wp-login.php
add_filter( 'login_url', 'new_login_page', 10, 3 );
function new_login_page( $login_url, $redirect, $force_reauth ) {
	$login_page = home_url( '/sign-in.php/' );
	return add_query_arg( 'redirect_to', $redirect, $login_page );
}
add_action('wp_logout','auto_redirect_after_logout');
function auto_redirect_after_logout() {
	nocache_headers();
	wp_safe_redirect( home_url() );
	exit;
}
?>