<?php
// Register Vehicle Custom Post
add_action( 'init', 'register_cpt_vehicle' );
function register_cpt_vehicle() {
	$labels = array( 
		'name' => _x( 'Vehicles', 'vehicles', 'the-artist' ),
		'singular_name' => _x( 'Vehicle', 'vehicles', 'the-artist' ),
		'add_new' => _x( 'Add New', 'vehicles', 'the-artist' ),
		'add_new_item' => _x( 'Add New Vehicle', 'vehicles', 'the-artist' ),
		'edit_item' => _x( 'Edit Vehicle', 'vehicles', 'the-artist' ),
		'new_item' => _x( 'New Vehicle', 'vehicles', 'the-artist' ),
		'view_item' => _x( 'View Vehicle', 'vehicles', 'the-artist' ),
		'search_items' => _x( 'Search vehicles', 'vehicles', 'the-artist' ),
		'not_found' => _x( 'No vehicles found', 'vehicles', 'the-artist' ),
		'not_found_in_trash' => _x( 'No vehicles found in Trash', 'vehicles', 'the-artist' ),
		'parent_item_colon' => _x( 'Parent Vehicle:', 'vehicles', 'the-artist' ),
		'menu_name' => _x( 'Vehicles', 'vehicles', 'the-artist' ),
	);
	$args = array( 
		'labels' => $labels,
		'hierarchical' => false,
		'description' => 'Vehicles Owned.',
		'supports' => array( 'title', 'editor', 'custom-fields', 'revisions' ),
		'taxonomies' => false,
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
	register_post_type( 'vehicles', $args );
}

//Metabox
add_action( 'admin_menu', 'tfa_add_vehicle_metabox' );
// or add_action( 'add_meta_boxes', 'tfa_add_vehicle_metabox' );
// or add_action( 'add_meta_boxes_{post_type}', 'tfa_add_vehicle_metabox' );

function tfa_add_vehicle_metabox() {

	add_meta_box(
		'tfa_metabox', // metabox ID
		'Customer Information', // title
		'tfa_vehicle_metabox_callback', // callback function
		'vehicles', // post type or post types in array
		'normal', // position (normal, side, advanced)
		'high' // priority (default, low, high, core)
	);

}

// CALLBACK FUNCTION

function tfa_vehicle_metabox_callback( $post ) {

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
					<label for="in_house">On-Site Vehicle</label>
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

add_action( 'save_post', 'tfa_save_vehicle_meta', 10, 2 );
// or add_action( 'save_post_{post_type}', 'tfa_save_vehicle_meta', 10, 2 );

function tfa_save_vehicle_meta( $post_id, $post ) {

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
	if( 'vehicles' !== $post->post_type ) {
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

/**Custom Taxonomies*/
add_action( 'init', 'register_taxonomy_make' );
function register_taxonomy_make() {
	$labels = array( 
		'name' => _x( 'Make', 'vh_make', 'the-artist' ),
		'singular_name' => _x( 'Make', 'vh_make', 'the-artist' ),
		'search_items' => _x( 'Search Make', 'vh_make', 'the-artist' ),
		'popular_items' => _x( 'Popular Make', 'vh_make', 'the-artist' ),
		'all_items' => _x( 'All Make', 'vh_make', 'the-artist' ),
		'parent_item' => _x( 'Parent Make', 'vh_make', 'the-artist' ),
		'parent_item_colon' => _x( 'Parent Make:', 'vh_make', 'the-artist' ),
		'edit_item' => _x( 'Edit Make', 'eq_make', 'the-artist' ),
		'update_item' => _x( 'Update Make', 'vh_make', 'the-artist' ),
		'add_new_item' => _x( 'Add New Make', 'vh_make', 'the-artist' ),
		'new_item_name' => _x( 'New Make', 'vh_make', 'the-artist' ),
		'separate_items_with_commas' => _x( 'Separate wood species with commas', 'vh_make', 'the-artist' ),
		'add_or_remove_items' => _x( 'Add or remove Make', 'vh_make', 'the-artist' ),
		'choose_from_most_used' => _x( 'Choose from most used Make', 'vh_make', 'the-artist' ),
		'menu_name' => _x( 'Make', 'vh_make', 'the-artist' ),
	);
	$args = array( 
		'labels' => $labels,
		'public' => true,
		'show_in_nav_menus' => true,
		'show_ui' => true,
		'show_tagcloud' => true,
		'show_admin_column' => false,
		'hierarchical' => true,
		'rewrite' => true,
		'query_var' => true
	);
	register_taxonomy( 'vh_make', array('vehicles'), $args );
}

?>