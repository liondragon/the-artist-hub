<?php
/** Excerpt Lenght */
if ( ! function_exists( 'custom_excerpt_length' ) ) :
   function custom_excerpt_length( $length ) {
	   return 40;
   }
endif;
add_filter( 'excerpt_length', 'custom_excerpt_length' );

/** Excerpt Lenght */
function get_excerpt($limit, $source = null){
	global $post;
    if($source == "content" ? ($excerpt = get_the_content()) : ($excerpt = get_the_excerpt()));
    $excerpt = preg_replace(" (\[.*?\])",'',$excerpt);
    $excerpt = strip_shortcodes($excerpt);
    $excerpt = strip_tags($excerpt);
    $excerpt = substr($excerpt, 0, $limit);
    $excerpt = substr($excerpt, 0, strripos($excerpt, " "));
    $excerpt = trim($preg);
	$preg = preg_replace( '/\s+/', ' ', $excerpt);
    $excerpt = $excerpt.'... <div class="readmorec"><a class="readmore" href="'.get_permalink($post->ID).'">View</a></div>';
    return $excerpt;
}

//Change Category Title
add_filter('get_the_archive_title', function ($title) {
    return preg_replace('/^\w+: /', '', $title);
});
// wordpress.stackexchange.com/questions/175884/how-to-customize-the-archive-title

/** Custom Read More Link */
if ( ! function_exists( 'read_more_link' ) ) :
	function read_more_link() {
		return '<div class="read_more_wrap"><a class="readmore" href="'. get_permalink() . '">' . __( 'Read More <span class="color">&raquo;</span>' , 'the-artist' ) . '</a></div>';
	}
endif;
if ( ! function_exists( 'auto_excerpt_more' ) ) :
	function  auto_excerpt_more($more) {
		return ' &hellip;' .read_more_link();
	}
endif;
add_filter( 'excerpt_more', 'auto_excerpt_more' );
if ( ! function_exists( 'custom_excerpt_more' ) ) :
	function custom_excerpt_more( $output ) {
		if ( has_excerpt() && ! is_attachment() ) {
			$output .= read_more_link();
		}
		return $output;
	}
endif;
add_filter( 'get_the_excerpt', 'custom_excerpt_more' );

add_action( 'pre_get_posts', 'be_change_event_posts_per_page' );
function be_change_event_posts_per_page( $query ) {
	
	if( $query->is_main_query() && !is_admin() && is_post_type_archive( 'projects' ) ) {
		$query->set( 'posts_per_page', '12' );
	}
}

/**Copyright Function*/
function custom_copyright() {
	global $wpdb;
	$copyright_dates = $wpdb->get_results("
			SELECT
			YEAR(min(post_date_gmt)) AS firstdate,
			YEAR(max(post_date_gmt)) AS lastdate
			FROM
			$wpdb->posts
			WHERE
			post_status = 'publish'
	");
	$output = '';
	if($copyright_dates) {
	$copyright = "&copy; " . $copyright_dates[0]->firstdate;
	if($copyright_dates[0]->firstdate != $copyright_dates[0]->lastdate) {
	$copyright .= '-' . $copyright_dates[0]->lastdate;
	}
	$output = $copyright;
}
return $output;
}

// Remove rel attribute from the category list
function remove_category_list_rel( $output ) {
    return str_replace( ' rel="category tag"', ' rel="category"', $output );
}
add_filter( 'wp_list_categories', 'remove_category_list_rel' );
add_filter( 'the_category', 'remove_category_list_rel' );

//Shows link to home in the list of pages
function the_artist_page_menu_args( $args ) {
	if ( ! isset( $args['show_home'] ) )
		$args['show_home'] = true;
	return $args;
}
add_filter( 'wp_page_menu_args', 'the_artist_page_menu_args' );

//Stop Image Linking
function wpb_imagelink_setup() {
	$image_set = get_option( 'image_default_link_type' );
	if ($image_set !== 'none') {
		update_option('image_default_link_type', 'none');
	}
}
add_action('admin_init', 'wpb_imagelink_setup', 10);

//Custom Field for Guest Author Name
add_filter( 'the_author', 'guest_author_name' );
function guest_author_name( $name ) {
	global $post;
	$author = get_post_meta( $post->ID, 'guest-author', true );
	if ( $author )
		$name = $author;
	return $name;
}


//Posts per Page
function custom_posts_per_page($query) {
    if (is_admin()) {
        return; // Exit early if in admin dashboard
    }

    global $wp;
    $uri = preg_replace('(\/page\/\d+)', '', $wp->request);
    $url = basename($uri);
    $pages = ['projects' => 12];

    // Check if the URL corresponds to a custom posts per page setting
    if (isset($pages[$url])) {
        $ppg = $pages[$url];
        $query->set('posts_per_page', $ppg);
    }
}
add_action('pre_get_posts', 'custom_posts_per_page');

function paginate($ajax = false) {
	global $wp_query;
	$max_pages = (int) $wp_query->max_num_pages;
	$max       = $max_pages > 7;
	$range     = ($max ? 5 : 7);
	if (get_query_var('paged')) {
		$current_page = get_query_var('paged');
	} elseif (get_query_var('page')) {
		$current_page = get_query_var('page');
	} else {
		$current_page = 1;
	}
	if (1 === $max_pages) {
		$pagination = '';
	} else {
		$mid_range   = (int) floor($range / 2);
		$start_range = range(1, $mid_range);
		$end_range   = range(($max_pages - $mid_range + 1), $max_pages);
		$exclude     = array_merge($start_range, $end_range);
		$check_range = ($range > $max_pages) ? true : false;
		if (true === $check_range) {
			$range_numbers = range(1, $max_pages);
		} elseif (false === $check_range) {
			if (!in_array($current_page, $exclude)) {
				$range_numbers = range(($current_page - $mid_range), ($current_page + $mid_range));
			} elseif (in_array($current_page, $start_range) && ($current_page - $mid_range) <= 0) {
				$range_numbers = range(1, $range);
			} elseif (in_array($current_page, $end_range) && ($current_page + $mid_range) >= $max_pages) {
				$range_numbers = range(($max_pages - $range + 1), $max_pages);
			}
		}
		foreach ($range_numbers as $v) {
			if ($v == $current_page) {
				$pages[] = '<span class="current">' . $v . '</span>';
			} else {
				$pages[] = '<a href="' . get_pagenum_link($v) . '" class="inactive">' . $v . '</a>';
			}
		}
		if ($ajax) {
			$next_class = 'load-more';
			$next_text  = 'Load More';
			$spinner    = '<div class="spinner" style="display:none;"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="margin:auto;display:block;" width="84px" height="84px" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid"><path d="M10 50A40 40 0 0 0 90 50A40 41.1 0 0 1 10 50" fill="#b1100c" stroke="none"><animateTransform attributeName="transform" type="rotate" dur="1s" repeatCount="indefinite" keyTimes="0;1" values="0 50 50.55;360 50 50.55"></animateTransform></path></svg></div>';
		} else {
			$next_class = 'next arrow';
			$next_text  = 'Next »';
			$spinner    = '';
		}
		$plast      = $max_pages - 1;
		$first      = (!in_array(1, $range_numbers)) ? '<a href="' . get_pagenum_link(1) . '">1</a>' : '';
		$last       = (!in_array($max_pages, $range_numbers)) ? '<a href="' . get_pagenum_link($max_pages) . '">' . $max_pages . '</a>' : '';
		$prev       = ($current_page !== 1) ? '<a class="prev arrow" href="' . get_pagenum_link($current_page - 1) . '">« Previous</a>' : '<a href="#" class="prev disabled">« Previous</a>';
		$next       = ($current_page !== $max_pages) ? '<a class="' . $next_class . '" href="' . get_pagenum_link($current_page + 1) . '" data-pages="' . $max_pages . '">' . $next_text . '</a>' : '<a href="#" class="' . $next_class . ' disabled">' . $next_text . '</a>';
		$fdots      = ($current_page > 5 && $max) ? '<span class="dots">…</span>' : (($current_page == 5 && $max) ? '<a href="' . get_pagenum_link(2) . '">2</a>' : '');
		$ldots      = ($current_page < $max_pages - 4 && $max) ? '<span class="dots">…</span>' : (($current_page == $max_pages - 4 && $max) ? '<a href="' . get_pagenum_link($max_pages - 1) . '">' . $plast . '</a>' : '');
		$links      = implode('', $pages);
		$pagination = '<div class="pagination">' . ($ajax ? '' : $prev . $first . $fdots . $links . $ldots . $last) . ($ajax && $current_page == $max_pages ? '' : $next) . '</div>' . $spinner;
	}
	echo $pagination;
}

//WCAG 2.0 Attributes for Dropdown Menus
function wcag_nav_menu_link_attributes( $atts, $item, $args, $depth ) {

    // Add [aria-haspopup] and [aria-expanded] to menu items that have children
    $item_has_children = in_array( 'menu-item-has-children', $item->classes );
    if ( $item_has_children ) {
        $atts['aria-haspopup'] = "true";
        $atts['aria-expanded'] = "false";
    }

    return $atts;
}
add_filter( 'nav_menu_link_attributes', 'wcag_nav_menu_link_attributes', 10, 4 );

class WPSE_78121_Sublevel_Walker extends Walker_Nav_Menu
{
    function start_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<div class='sub-menu-wrap'><ul class='sub-menu'>\n";
    }
    function end_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "$indent</ul></div>\n";
    }
}

class submenu_wrap extends Walker_Nav_Menu {
    function start_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<div class='sub-menu-wrap'><ul class='sub-menu'>\n";
    }
    function end_lvl( &$output, $depth = 0, $args = array() ) {
        $indent = str_repeat("\t", $depth);
        $output .= "$indent</ul></div>\n";
    }
}

?>