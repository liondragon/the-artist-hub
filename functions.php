<?php
if ( ! isset( $content_width ) ) {
	$content_width = 625;
}
function the_artist_content_width() {
	if ( is_page_template( 'page-templates/full-width.php' ) || is_attachment() || ! is_active_sidebar( 'sidebar-1' ) ) {
		global $content_width;
		$content_width = 960;
	}
}
add_action( 'template_redirect', 'the_artist_content_width' );

// Remove default image sizes
add_filter( 'intermediate_image_sizes_advanced', 'the_artist_remove_default_images' );
function the_artist_remove_default_images( $sizes ) {
//	unset( $sizes['medium']);
//	unset( $sizes['large']);
//	unset( $sizes['medium_large']);
//	unset( $sizes['1536x1536'] );
//	unset( $sizes['2048x2048'] );
	return $sizes;
}

function the_artist_setup() {
	add_editor_style( get_template_directory_uri() . '/css/editor-style.css' );
	register_nav_menu( 'primary', __( 'Primary Menu', 'the-artist' ) );
	register_nav_menu( 'footer-menu', __( 'Footer Menu', 'the-artist' ) );
	register_nav_menu( 'res-menu', __( 'Resources Menu', 'the-artist' ) );
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 190, 190);
	add_image_size( 'large', 300, 300);
	add_image_size( 'medium', 150, 150);
	add_image_size( 'thumbnail', 65, 65 );
	add_image_size( 'wide', 440, 270, true );
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
}
add_action( 'after_setup_theme', 'the_artist_setup' );

// Stop WordPress from modifying .htaccess permalink rules
add_filter('flush_rewrite_rules_hard','__return_false');

//Preload CSS
function preload_css() {
	$css_path = get_template_directory_uri() . '/css/main.css';
	$css_ver = filemtime(get_template_directory() . '/css/main.css');
	$css_link = $css_path . '?ver=' . $css_ver;
	echo '<link rel="preload" href="' . $css_link . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">'. "\n";
	echo '<noscript><link rel="stylesheet" href="' . $css_link . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"></noscript>'. "\n";
}
add_action('wp_head', 'preload_css', -1000);

// Add/Remove/Defer Scritps and Stylsheets
function custom_scripts() {
    global $wp_scripts;
	$css_path = get_template_directory_uri() . '/css/main.css';
	$css_ver = filemtime(get_template_directory() . '/css/main.css');
    if(is_admin()) return;
	wp_enqueue_style( 'the-artist-style', $css_path, array(), $css_ver );
	wp_deregister_script( 'wp-embed' );
//	wp_deregister_script('jquery');
	wp_enqueue_script( 'functions', get_template_directory_uri() . '/js/functions.js', array(), null, true);
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) )wp_enqueue_script( 'comment-reply' );
}
add_action('wp_enqueue_scripts', 'custom_scripts');

// Async load
function defer_scripts( $tag, $handle, $src ) {
  $defer = array( 'slider','functions', );
  $async = array( '' );
	if ( in_array( $handle, $async ) ) {
		return '<script src="' . $src . '" defer="async" type="text/javascript"></script>' . "\n";
	}
	else if ( in_array( $handle, $defer ) ) {
		return '<script src="' . $src . '" defer="defer" type="text/javascript"></script>' . "\n";
	}
	return $tag;
} 
add_filter( 'script_loader_tag', 'defer_scripts', 10, 3 );

//Favicon
function add_favicon(){ ?>
    <!-- Custom Favicons -->
    <link rel="shortcut icon" href="<?php echo get_stylesheet_directory_uri();?>/assets/favicon.ico"/>
    <link rel="apple-touch-icon" href="<?php echo get_stylesheet_directory_uri(); ?>/assets/favicon.ico">
    <?php }
add_action('wp_head','add_favicon');

function strip_table_class_attribute($content) {
    // Use a regular expression to remove 'class' attributes from tables
    $content = preg_replace('/<table(.*?)class=["\'](.*?)["\'](.*?)>/i', '<table$1$3>', $content);

    return $content;
}

// Apply the filter to the_content and widget_text_content
add_filter('the_content', 'strip_table_class_attribute', 10);
add_filter('widget_text_content', 'strip_table_class_attribute', 10);

// Apply the filter to the_content and widget_text_content
add_filter('the_content', 'strip_table_class_attribute', 10);
add_filter('widget_text_content', 'strip_table_class_attribute', 10);



// Remove WP Logo From Dashboard
add_action( 'admin_bar_menu', 'remove_wp_logo', 999 );
function remove_wp_logo( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'wp-logo' );
}

add_action('wp_dashboard_setup', 'remove_dashboard_widgets');

function remove_dashboard_widgets () {
      //Completely remove various dashboard widgets (remember they can also be HIDDEN from admin)
      remove_meta_box( 'dashboard_quick_press',   'dashboard', 'side' );      //Quick Press widget
      remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );      //Recent Drafts
      remove_meta_box( 'dashboard_primary',       'dashboard', 'side' );      //WordPress.com Blog
      remove_meta_box( 'dashboard_secondary',     'dashboard', 'side' );      //Other WordPress News
      remove_meta_box( 'dashboard_incoming_links','dashboard', 'normal' );    //Incoming Links
      remove_meta_box( 'dashboard_plugins',       'dashboard', 'normal' );    //Plugins
}

// Remove class from pasted data
add_filter('tiny_mce_before_init', 'configure_tinymce_to_remove_attributes');
function configure_tinymce_to_remove_attributes($in) {
    $in['paste_preprocess'] = "function(plugin, args) {
        var contentWrapper = jQuery('<div>' + args.content + '</div>');
        
        // Removing class and style attributes from all elements
        contentWrapper.find('*').removeAttr('class').removeAttr('style');

        // Update the pasted content
        args.content = contentWrapper.html();
    }";
    return $in;
}

add_filter('tiny_mce_before_init', 'strong_wrap_for_total_rows');

//Embolden Total
function strong_wrap_for_total_rows($in) {
    $in['paste_postprocess'] = "function(plugin, args) {
        var contentWrapper = jQuery('<div>' + args.node.innerHTML + '</div>');

        // Find table rows that have a cell with 'Total'
        contentWrapper.find('tr').each(function() {
            var row = jQuery(this);
            
            if (row.find('td:contains(\"Total\")').length > 0) {
                // For rows with a cell that contains 'Total', 
                // wrap the content of each cell in <strong> tags
                row.find('td').each(function() {
                    var cell = jQuery(this);
                    cell.html('<strong>' + cell.html() + '</strong>');
                });
            }
        });

        // Update the pasted content
        args.node.innerHTML = contentWrapper.html();
    }";
    return $in;
}

//Allow empty tables
function allow_nbsp_in_tinymce( $mceInit ) {
    $mceInit['entities'] = '160,nbsp,38,amp,60,lt,62,gt';   
    $mceInit['entity_encoding'] = 'named';
    return $mceInit;
}
add_filter( 'tiny_mce_before_init', 'allow_nbsp_in_tinymce' );

include_once( __DIR__ . '/includes/admin_dash.php');
include_once( __DIR__ . '/includes/hide_wp.php');
include_once( __DIR__ . '/includes/widgets.php');
include_once( __DIR__ . '/includes/custom_layout.php');
include_once( __DIR__ . '/includes/notes_function.php');
require_once( __DIR__ . '/includes/comments.php');
require_once( __DIR__ . '/includes/custom_post_types.php');
require_once( __DIR__ . '/includes/user_management.php');

?>