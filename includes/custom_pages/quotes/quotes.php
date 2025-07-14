<?php
// Register Quote Custom Post
add_action( 'init', 'register_cpt_quote' );
function register_cpt_quote() {
	$labels = array( 
		'name' => _x( 'Quotes', 'quotes', 'the-artist' ),
		'singular_name' => _x( 'Quote', 'quotes', 'the-artist' ),
		'add_new' => _x( 'Add New', 'quotes', 'the-artist' ),
		'add_new_item' => _x( 'Add New Quote', 'quotes', 'the-artist' ),
		'edit_item' => _x( 'Edit Quote', 'quotes', 'the-artist' ),
		'new_item' => _x( 'New Quote', 'quotes', 'the-artist' ),
		'view_item' => _x( 'View Quote', 'quotes', 'the-artist' ),
		'search_items' => _x( 'Search quotes', 'quotes', 'the-artist' ),
		'not_found' => _x( 'No quotes found', 'quotes', 'the-artist' ),
		'not_found_in_trash' => _x( 'No quotes found in Trash', 'quotes', 'the-artist' ),
		'parent_item_colon' => _x( 'Parent Quote:', 'quotes', 'the-artist' ),
		'menu_name' => _x( 'Quotes', 'quotes', 'the-artist' ),
	);
	$args = array( 
		'labels' => $labels,
		'hierarchical' => false,
		'description' => 'Customer Proposals.',
		'supports' => array( 'title', 'editor', 'custom-fields', 'revisions' ),
		'taxonomies' => [ 'trade' ],
		'public' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_position' => 5,
		'menu_icon' => 'dashicons-portfolio',
		'show_in_nav_menus' => false,
		'publicly_queryable' => true,
		'exclude_from_search' => false,
		'has_archive' => false,
		'query_var' => true,
		'can_export' => true,
		'rewrite' => true,
		'capability_type' => 'post'
	);
	register_post_type( 'quotes', $args );
}
function register_trade_taxonomy() {
    $labels = [
    'name'                       => _x('Trades', 'taxonomy general name', 'textdomain'),
    'singular_name'              => _x('Trade', 'taxonomy singular name', 'textdomain'),
    'search_items'               => __('Search Trades', 'textdomain'),
    'popular_items'              => __('Popular Trades', 'textdomain'),
    'all_items'                  => __('All Trades', 'textdomain'),
    'parent_item'                => null,
    'parent_item_colon'          => null,
    'edit_item'                  => __('Edit Trade', 'textdomain'),
    'update_item'                => __('Update Trade', 'textdomain'),
    'add_new_item'               => __('Add New Trade', 'textdomain'),
    'new_item_name'              => __('New Trade Name', 'textdomain'),
    'separate_items_with_commas' => __('Separate trades with commas', 'textdomain'),
    'add_or_remove_items'        => __('Add or remove trades', 'textdomain'),
    'choose_from_most_used'      => __('Choose from the most used trades', 'textdomain'),
    'not_found'                  => __('No trades found.', 'textdomain'),
    'menu_name'                  => __('Trades', 'textdomain'),
];
    $args = [
        'labels' => $labels,
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'trade'],
    ];
    register_taxonomy('trade', ['quotes'], $args);
}
add_action('init', 'register_trade_taxonomy');

//Metabox
add_action( 'admin_menu', 'tfa_add_metabox' );
// or add_action( 'add_meta_boxes', 'tfa_add_metabox' );
// or add_action( 'add_meta_boxes_{post_type}', 'tfa_add_metabox' );

function tfa_add_metabox() {

	add_meta_box(
		'tfa_metabox', // metabox ID
		'Customer Information', // title
		'tfa_metabox_callback', // callback function
		'quotes', // post type or post types in array
		'normal', // position (normal, side, advanced)
		'high' // priority (default, low, high, core)
	);

}

// CALLBACK FUNCTION

function tfa_metabox_callback( $post ) {

	$customer_name = get_post_meta( $post->ID, 'customer_name', true );
	$customer_address = get_post_meta( $post->ID, 'customer_address', true );
	$estimate_type = get_post_meta( $post->ID, 'estimate_type', true );

	// nonce, actually I think it is not necessary here
	wp_nonce_field( 'somerandomstr', '_tfanonce' );

	echo '<table class="form-table">
		<tbody>
			<tr>
				<th><label for="customer_name">Customer Name</label></th>
				<td><input type="text" id="customer_name" name="customer_name" value="' . esc_attr( $customer_name ) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="customer_address">Customer Address</label></th>
				<td><input type="text" id="customer_address" name="customer_address" value="' . esc_attr( $customer_address ) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="seo_tobots">Estimate Type</label></th>
				<td>
					<input type="radio" id="in_house" name="estimate_type" value="in_house" ' . checked( $estimate_type, 'in_house', false ) . ' required>
					<label for="in_house">On-Site Quote</label>
					<br>
					<input type="radio" id="virtual" name="estimate_type" value="virtual" ' . checked( $estimate_type, 'virtual', false ) . ' required>
					<label for="virtual">Virtual Estimate</label>
				</td>
			</tr>
		</tbody>
	</table>';
//checked( 'in_house', get_option( 'estimate_type' ) );
//' . checked( $value, 'in_house' ); . '
}

// SAVE DATA

add_action( 'save_post', 'tfa_save_meta', 10, 2 );
// or add_action( 'save_post_{post_type}', 'tfa_save_meta', 10, 2 );

function tfa_save_meta( $post_id, $post ) {

	// nonce check
	if ( ! isset( $_POST[ '_tfanonce' ] ) || ! wp_verify_nonce( $_POST[ '_tfanonce' ], 'somerandomstr' ) ) {
		return $post_id;
	}
	// Check the user's permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ){
		return $post_id;
	}

	// check current user permissions
	$post_type = get_post_type_object( $post->post_type );

	if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
		return $post_id;
	}

	// Do not save the data if autosave
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
		return $post_id;
	}

	// define your own post type here
	if( 'quotes' !== $post->post_type ) {
		return $post_id;
	}

	if( isset( $_POST[ 'customer_name' ] ) ) {
		update_post_meta( $post_id, 'customer_name', sanitize_text_field( $_POST[ 'customer_name' ] ) );
	} else {
		delete_post_meta( $post_id, 'customer_name' );
	}
	if( isset( $_POST[ 'customer_address' ] ) ) {
		update_post_meta( $post_id, 'customer_address', sanitize_text_field( $_POST[ 'customer_address' ] ) );
	} else {
		delete_post_meta( $post_id, 'customer_address' );
	}
	if( isset( $_POST[ 'estimate_type' ] ) ) {
		update_post_meta( $post_id, 'estimate_type', sanitize_text_field( $_POST[ 'estimate_type' ] ) );
	} else {
		delete_post_meta( $post_id, 'estimate_type' );
	}

	return $post_id;

}

//ADD CUSTOM COLUMNS

	add_filter('manage_quotes_posts_columns', function($columns) {
    	$columns['title'] = __('Quote Title', 'textdomain');
    	return $columns;
    });

    add_filter('manage_quotes_posts_columns', function($columns) {
    	return array_merge($columns, ['customer_address' => __('Address', 'textdomain')], ['customer_name' => __('Client Name', 'textdomain')]);
    });
	
	add_filter('manage_edit-quotes_sortable_columns', function($columns) {
    	$columns['customer_address'] = 'customer_address';
    	$columns['customer_name'] = 'customer_name';
    	return $columns;
    });
	
	add_filter('manage_quotes_posts_columns', function($columns) {
    	$taken_out = $columns['date'];
    	unset($columns['date']);
    	$columns['date'] = $taken_out;
    	return $columns;
    });
     
    add_action('manage_quotes_posts_custom_column', function($column_key, $post_id) {
    	if ($column_key == 'customer_address') {
    		$customer_address = get_post_meta($post_id, 'customer_address', true);
    		if ($customer_address) {
    			echo '<span>'; _e($customer_address, 'textdomain'); echo '</span>';
    		} else {
    			echo 'Not Provided';
    		}
    	}
		if ($column_key == 'customer_name') {
    		$customer_name = get_post_meta($post_id, 'customer_name', true);
    		if ($customer_name) {
    			echo '<span>'; _e($customer_name, 'textdomain'); echo '</span>';
    		} else {
    			echo 'Not Provided';
    		}
    	}
    }, 10, 2);
?>