<?php
// Remove Admin Menu Items
function remove_menu_items(){
	remove_menu_page( 'tools.php' );
	remove_submenu_page( 'themes.php', 'customize.php?return=' . urlencode($_SERVER['SCRIPT_NAME']));
	remove_submenu_page( 'themes.php','themes.php' );
	remove_submenu_page( 'options-general.php', 'akismet-key-config' );
	remove_submenu_page( 'admin.php', 'wp_mailjet_options_campaigns_menu' );
//	remove_submenu_page( 'options-general.php', 'options-media.php' );
}
add_action( 'admin_menu', 'remove_menu_items', 999 );

function remove_posts_menu() {
    remove_menu_page('edit.php');
}
add_action('admin_menu', 'remove_posts_menu');

//Add Avatar Field
function the_artist_custom_avatar_url( $user ) {
?>
	<table class="form-table">
		<tr>
			<th>
				<label for="avtr"><?php _e('Custom Avatar URL', 'your_textdomain'); ?>
			</label></th>
			<td>
				<input type="text" name="avtr" id="avtr" value="<?php echo esc_attr( get_the_author_meta( 'avtr', $user->ID ) ); ?>" class="regular-text" /><br />
				<span class="description"><?php _e('Please enter your url.', 'your_textdomain'); ?></span>
			</td>
		</tr>
	</table>
<?php }
function the_artist_save_custom_avatar_url( $user_id ) {
	if ( !current_user_can( 'edit_user', $user_id ) )
		return FALSE;
		update_user_meta( $user_id, 'avtr', $_POST['avtr'] );
}
add_action( 'show_user_profile', 'the_artist_custom_avatar_url' );
add_action( 'edit_user_profile', 'the_artist_custom_avatar_url' );
add_action( 'personal_options_update', 'the_artist_save_custom_avatar_url' );
add_action( 'edit_user_profile_update', 'the_artist_save_custom_avatar_url' );

// Custom Avatar Function
add_filter( 'get_avatar' , 'the_artist_custom_avatar' , 1 , 5 );
function the_artist_custom_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
	$user = false;
	if ( is_numeric( $id_or_email ) ) {
		$id = (int) $id_or_email;
		$user = get_user_by( 'id' , $id );
	} elseif ( is_object( $id_or_email ) ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			$id = (int) $id_or_email->user_id;
			$user = get_user_by( 'id' , $id );
			}
	} else {
		$user = get_user_by( 'email', $id_or_email );   
	}
		if ( $user && is_object( $user ) ) {
			$custom_user_image  = get_the_author_meta('avtr');
			if ( $custom_user_image ) {
				$avatar = $custom_user_image;
				$avatar = "<img alt='{$alt}' src='{$avatar}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
			}
		}
	return $avatar;
}

// Disable Jetpack CSS and Scripts
//add_filter( 'jetpack_implode_frontend_css', '__return_false', 99 );
//add_filter( 'jetpack_sharing_counts', '__return_false', 99 );

add_action( 'wp_enqueue_scripts', 'crunchify_enqueue_scripts_styles' );
function crunchify_enqueue_scripts_styles() {
wp_dequeue_script( 'devicepx' );
}

// Remove Emoji Loading Script
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

//Remove Gutenberg Block Library CSS from loading on the frontend
function smartwp_remove_wp_block_library_css(){
 wp_dequeue_style( 'wp-block-library' );
 wp_dequeue_style( 'wp-block-library-theme' );
 wp_dequeue_style( 'wc-block-style' ); // Remove WooCommerce block CSS
}
add_action( 'wp_enqueue_scripts', 'smartwp_remove_wp_block_library_css', 100 );

// Alternative
// Fully Disable Gutenberg editor.
add_filter('use_block_editor_for_post_type', '__return_false', 10);
// Don't load Gutenberg-related stylesheets.
add_action( 'wp_enqueue_scripts', 'remove_block_css', 100 );
function remove_block_css() {
wp_dequeue_style( 'wp-block-library' ); // WordPress core
wp_dequeue_style( 'wp-block-library-theme' ); // WordPress core
wp_dequeue_style( 'wc-block-style' ); // WooCommerce
wp_dequeue_style( 'storefront-gutenberg-blocks' ); // Storefront theme
}

// Disable Lightbox on Some Pages
function my_lbwps_enabled($enabled, $id) {
	if (is_category() || is_archive() || is_post_type_archive() || is_post_type_archive() || is_home() || is_front_page() || is_page() || is_author() | is_tag())
		return false;
		return $enabled;
}
add_filter('lbwps_enabled', 'my_lbwps_enabled', 10, 2);

// Hide Wordpress Version
function disable_version() {
	return '';
	}
add_filter('the_generator','disable_version');

//Disable Admin Bar Menu Items
function remove_admin_bar_links() {
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
//$wp_admin_bar->remove_node('site-name');
}
add_action( 'wp_before_admin_bar_render', 'remove_admin_bar_links' );
add_filter( 'update_right_now_text', '__return_false' );
//add_filter( 'privacy_on_link_text', '__return_false' );

function clear_node_title( $wp_admin_bar ) {
 
    // Get all the nodes
    $all_toolbar_nodes = $wp_admin_bar->get_nodes();
    // Create an array of node ID's we'd like to remove
    $clear_titles = array(
        'site-name',
    );
 
    foreach ( $all_toolbar_nodes as $node ) {
 
        // Run an if check to see if a node is in the array to clear_titles
        if ( in_array($node->id, $clear_titles) ) {
            // use the same node's properties
            $args = $node;
 
            // make the node title a blank string
            $args->title = 'Switch';
            $args->title = 'Switch';
 
            // update the Toolbar node
            $wp_admin_bar->add_node( $args );
        }
    }
}
add_action( 'admin_bar_menu', 'clear_node_title', 999 );

//Remove Help Tab
function remove_context_menu_help(){
    //get the current screen object
    $current_screen = get_current_screen();
	$current_screen->remove_help_tabs();
}
add_action('admin_head', 'remove_context_menu_help');

// Change Admin Bar User Profile Greeting
add_filter( 'admin_bar_menu', 'replace_wordpress_howdy', 25 );
function replace_wordpress_howdy( $wp_admin_bar ) {
    // try to grab the node
    $my_account = $wp_admin_bar->get_node( 'my-account' );
    if ( ! $my_account ) {
        // nothing to do if it's not present
        return;
    }

    // strip the “Howdy,” out of the existing title
    $newtext = str_replace( 'Howdy,', '', $my_account->title );

    // re-register it with our cleaned title
    $wp_admin_bar->add_node( array(
        'id'    => 'my-account',
        'title' => $newtext,
    ) );
}


// Remove Head Links
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rest_output_link_wp_head', 10);
remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
remove_action('template_redirect', 'rest_output_link_header', 11, 0);
remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
remove_action ('wp_head', 'rsd_link');
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'wp_generator');
remove_action( 'wp_head', 'wp_resource_hints', 2 );

//Disable jpeg compression
add_filter('jpeg_quality', function($arg){return 100;});

// Allow REST API Only for localhost
function restrict_rest_api_to_localhost() {
	$whitelist = [ '127.0.0.1', "::1" ];
	if( ! in_array($_SERVER['REMOTE_ADDR'], $whitelist ) ){
		die( 'REST API is disabled.' );
	}
}
add_action( 'rest_api_init', 'restrict_rest_api_to_localhost', 0 );


// Remove Category Base ( Remove Category URL 1.1.6)
/* hooks */
register_activation_hook( __FILE__, 'remove_category_url_refresh_rules' );
register_deactivation_hook( __FILE__, 'remove_category_url_deactivate' );

/* actions */
add_action( 'created_category', 'remove_category_url_refresh_rules' );
add_action( 'delete_category', 'remove_category_url_refresh_rules' );
add_action( 'edited_category', 'remove_category_url_refresh_rules' );
add_action( 'init', 'remove_category_url_permastruct' );

/* filters */
add_filter( 'category_rewrite_rules', 'remove_category_url_rewrite_rules' );
add_filter( 'query_vars', 'remove_category_url_query_vars' );    // Adds 'category_redirect' query variable
add_filter( 'request', 'remove_category_url_request' );       // Redirects if 'category_redirect' is set

function remove_category_url_refresh_rules() {
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

function remove_category_url_deactivate() {
	remove_filter( 'category_rewrite_rules', 'remove_category_url_rewrite_rules' ); // We don't want to insert our custom rules again
	remove_category_url_refresh_rules();
}

/**
 * Removes category base.
 *
 * @return void
 */
function remove_category_url_permastruct() {
	global $wp_rewrite, $wp_version;

	if ( 3.4 <= $wp_version ) {
		$wp_rewrite->extra_permastructs['category']['struct'] = '%category%';
	} else {
		$wp_rewrite->extra_permastructs['category'][0] = '%category%';
	}
}

/**
 * Adds our custom category rewrite rules.
 *
 * @param array $category_rewrite Category rewrite rules.
 *
 * @return array
 */
function remove_category_url_rewrite_rules( $category_rewrite ) {
	global $wp_rewrite;

	$category_rewrite = array();

	/* WPML is present: temporary disable terms_clauses filter to get all categories for rewrite */
	if ( class_exists( 'Sitepress' ) ) {
		global $sitepress;

		remove_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ), 10 );
		$categories = get_categories( array( 'hide_empty' => false, '_icl_show_all_langs' => true ) );
		add_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ), 10, 4 );
	} else {
		$categories = get_categories( array( 'hide_empty' => false ) );
	}

	foreach ( $categories as $category ) {
		$category_nicename = $category->slug;
		if ( $category->parent == $category->cat_ID ) {
			$category->parent = 0;
		} elseif ( 0 != $category->parent ) {
			$category_nicename = get_category_parents( $category->parent, false, '/', true ) . $category_nicename;
		}
		$category_rewrite[ '(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
		$category_rewrite[ '(' . $category_nicename . ')/page/?([0-9]{1,})/?$' ]                  = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$category_rewrite[ '(' . $category_nicename . ')/?$' ]                                    = 'index.php?category_name=$matches[1]';
	}

	// Redirect support from Old Category Base
	$old_category_base                                 = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
	$old_category_base                                 = trim( $old_category_base, '/' );
	$category_rewrite[ $old_category_base . '/(.*)$' ] = 'index.php?category_redirect=$matches[1]';

	return $category_rewrite;
}

function remove_category_url_query_vars( $public_query_vars ) {
	$public_query_vars[] = 'category_redirect';

	return $public_query_vars;
}

/**
 * Handles category redirects.
 *
 * @param $query_vars Current query vars.
 *
 * @return array $query_vars, or void if category_redirect is present.
 */
function remove_category_url_request( $query_vars ) {
	if ( isset( $query_vars['category_redirect'] ) ) {
		$catlink = trailingslashit( get_option( 'home' ) ) . user_trailingslashit( $query_vars['category_redirect'], 'category' );
		status_header( 301 );
		header( "Location: $catlink" );
		exit;
	}

	return $query_vars;
}

add_filter( 'default_content', 'pu_default_editor_content' );

function pu_default_editor_content( $content ) {

    global $post_type;

    switch( $post_type ) 
    {
        case 'post':
            $content = '';
        break;

        case 'quotes':
                     $content = file_get_contents(get_template_directory() . '/content/quotes/hardwood.html');
        break;

        case 'projects':
            $content = '';
        break;
    }

    return $content;
}



//Custom Pages Code
//include_once( __DIR__ . '/custom_pages/projects/projects.php');
include_once( __DIR__ . '/custom_pages/quotes/quotes.php');
//include_once( __DIR__ . '/custom_pages/equipment/equipment.php');
//include_once( __DIR__ . '/custom_pages/vehicles/vehicles.php');

//Templates Button
add_action('media_buttons', function($editor_id) {
    echo '<a href="#" id="my_template_button" class="button">Templates</a>';
});
function enqueue_custom_admin_js() {
    wp_enqueue_script('my-custom-script', get_template_directory_uri() . '/js/custom-script.js', array('jquery'), filemtime(get_template_directory() . '/js/custom-script.js'), true);

    // Localize script to pass the template directory URL to JavaScript
    wp_localize_script('my-custom-script', 'templateData', array('url' => get_template_directory_uri() . '/content/quotes/'));
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_js');



//SEARCH CUSTOM FIELDS

/**
 * Extend WordPress search to include custom fields
 *
 * https://adambalee.com
 */

/**
 * Join posts and postmeta tables
 *
 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_join
 */
function cf_search_join( $join ) {
    global $wpdb;

    if ( is_search() ) {    
        $join .=' LEFT JOIN '.$wpdb->postmeta. ' ON '. $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
    }

    return $join;
}
add_filter('posts_join', 'cf_search_join' );

/**
 * Modify the search query with posts_where
 *
 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_where
 */
function cf_search_where( $where ) {
    global $pagenow, $wpdb;

    if ( is_search() ) {
        $where = preg_replace(
            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(".$wpdb->posts.".post_title LIKE $1) OR (".$wpdb->postmeta.".meta_value LIKE $1)", $where );
    }

    return $where;
}
add_filter( 'posts_where', 'cf_search_where' );

/**
 * Prevent duplicates
 *
 * http://codex.wordpress.org/Plugin_API/Filter_Reference/posts_distinct
 */
function cf_search_distinct( $where ) {
    global $wpdb;

    if ( is_search() ) {
        return "DISTINCT";
    }

    return $where;
}
add_filter( 'posts_distinct', 'cf_search_distinct' );



?>