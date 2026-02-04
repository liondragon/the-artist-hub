<?php
// Register Equipment Custom Post
add_action('init', 'register_cpt_equipment');
function register_cpt_equipment()
{
	$labels = array(
		'name' => _x('Equipment', 'equipment', 'the-artist'),
		'singular_name' => _x('Equipment', 'equipment', 'the-artist'),
		'add_new' => _x('Add New', 'equipment', 'the-artist'),
		'add_new_item' => _x('Add New Equipment', 'equipment', 'the-artist'),
		'edit_item' => _x('Edit Equipment', 'equipment', 'the-artist'),
		'new_item' => _x('New Equipment', 'equipment', 'the-artist'),
		'view_item' => _x('View Equipment', 'equipment', 'the-artist'),
		'search_items' => _x('Search equipment', 'equipment', 'the-artist'),
		'not_found' => _x('No equipment found', 'equipment', 'the-artist'),
		'not_found_in_trash' => _x('No equipment found in Trash', 'equipment', 'the-artist'),
		'parent_item_colon' => _x('Parent Equipment:', 'equipment', 'the-artist'),
		'menu_name' => _x('Equipment', 'equipment', 'the-artist'),
	);
	$args = array(
		'labels' => $labels,
		'hierarchical' => false,
		'description' => 'Completed Equipment.',
		'supports' => array('title', 'editor', 'custom-fields'),
		// 'taxonomies' => array(), // Default is empty array
		'taxonomies' => array(),
		'public' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_position' => 5,
		'menu_icon' => 'dashicons-portfolio',
		'show_in_nav_menus' => false,
		'publicly_queryable' => true,
		'exclude_from_search' => false,
		'has_archive' => true,
		'query_var' => true,
		'can_export' => true,
		'rewrite' => true,
		'capability_type' => 'post',
	);
	register_post_type('equipment', $args);
}

//Add a Metabox
add_action('admin_menu', 'the_artist_equipment_add_metabox');
function the_artist_equipment_add_metabox()
{
	add_meta_box(
		'tfa_equipment_metabox', // metabox ID
		'Equipment Information', // title
		'the_artist_equipment_metabox_callback', // callback function
		'equipment', // post type or post types in array
		'normal', // position (normal, side, advanced)
		'high' // priority (default, low, high, core)
	);

}

// CALLBACK FUNCTION

function the_artist_equipment_metabox_callback($post)
{

	$equipment_name = get_post_meta($post->ID, 'equipment_name', true);
	$model_n = get_post_meta($post->ID, 'model_n', true);
	$equipment_warranty = get_post_meta($post->ID, 'equipment_warranty', true);
	$equipment_purchase_date = get_post_meta($post->ID, 'equipment_purchase_date', true);
	$equipment_sn = get_post_meta($post->ID, 'equipment_sn', true);
	$eq_price = get_post_meta($post->ID, 'eq_price', true);
	$eq_own = get_post_meta($post->ID, 'eq_own', true);

	// nonce, actually I think it is not necessary here
	wp_nonce_field('the_artist_save_equipment', '_tfanonce_equipment');
	$users = get_users(array('role__in' => array('author', 'subscriber')));
	foreach ($users as $user) {
		echo '<span>' . esc_html($user->ID) . '</span>';
		//echo get_user_meta( ($user->ID),  );
		//echo '<span>' . array_values ( $user ) . '</span>';
		//print_r(array_values($users));
		//echo (get_user_meta ( $user->ID));
	}
	echo '<table class="form-table">
		<tbody>
			<tr>
				<th><label for="equipment_name">Name</label></th>
				<td><input type="text" id="equipment_name" name="equipment_name" value="' . esc_attr($equipment_name) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="model_n">Model</label></th>
				<td><input type="text" id="model_n" name="model_n" value="' . esc_attr($model_n) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="equipment_warranty">Warranty</label></th>
				<td><input type="text" id="equipment_warranty" name="equipment_warranty" value="' . esc_attr($equipment_warranty) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="equipment_purchase_date">Purchase Date</label></th>
				<td><input type="text" id="equipment_purchase_date" name="equipment_purchase_date" value="' . esc_attr($equipment_purchase_date) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="equipment_sn">Serial Number</label></th>
				<td><input type="text" id="equipment_sn" name="equipment_sn" value="' . esc_attr($equipment_sn) . '" class="regular-text"></td>
			</tr>
				<th><label for="eq_price">Purchase Price</label></th>
				<td><input type="text" id="eq_price" name="eq_price" value="' . esc_attr($eq_price) . '" class="regular-text"></td>
			</tr>
			</tr>
				<th><label for="eq_own">Ownership</label></th>
				<td><input type="text" id="eq_own" name="eq_own" value="' . esc_attr($eq_own) . '" class="regular-text"></td>
			</tr>
		</tbody>
	</table>';

}

// SAVE DATA

add_action('save_post', 'the_artist_equipment_save_meta', 10, 2);
// or add_action( 'save_post_{post_type}', 'the_artist_equipment_save_meta', 10, 2 );

function the_artist_equipment_save_meta($post_id, $post)
{

	// nonce check
	if (!isset($_POST['_tfanonce_equipment']) || !wp_verify_nonce($_POST['_tfanonce_equipment'], 'the_artist_save_equipment')) {
		return $post_id;
	}
	// Check the user's permissions.
	if (!current_user_can('edit_post', $post_id)) {
		return $post_id;
	}

	// check current user permissions
	$post_type = get_post_type_object($post->post_type);

	if (!current_user_can($post_type->cap->edit_post, $post_id)) {
		return $post_id;
	}

	// Do not save the data if autosave
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return $post_id;
	}

	// define your own post type here
	if ('equipment' !== $post->post_type) {
		return $post_id;
	}

	if (isset($_POST['equipment_name'])) {
		update_post_meta($post_id, 'equipment_name', sanitize_text_field($_POST['equipment_name']));
	} else {
		delete_post_meta($post_id, 'equipment_name');
	}
	if (isset($_POST['model_n'])) {
		update_post_meta($post_id, 'model_n', sanitize_text_field($_POST['model_n']));
	} else {
		delete_post_meta($post_id, 'model_n');
	}
	if (isset($_POST['equipment_warranty'])) {
		update_post_meta($post_id, 'equipment_warranty', sanitize_text_field($_POST['equipment_warranty']));
	} else {
		delete_post_meta($post_id, 'equipment_warranty');
	}
	if (isset($_POST['equipment_purchase_date'])) {
		update_post_meta($post_id, 'equipment_purchase_date', sanitize_text_field($_POST['equipment_purchase_date']));
	} else {
		delete_post_meta($post_id, 'equipment_purchase_date');
	}
	if (isset($_POST['equipment_sn'])) {
		update_post_meta($post_id, 'equipment_sn', sanitize_text_field($_POST['equipment_sn']));
	} else {
		delete_post_meta($post_id, 'equipment_sn');
	}
	if (isset($_POST['eq_price'])) {
		update_post_meta($post_id, 'eq_price', sanitize_text_field($_POST['eq_price']));
	} else {
		delete_post_meta($post_id, 'eq_price');
	}
	if (isset($_POST['eq_own'])) {
		update_post_meta($post_id, 'eq_own', sanitize_text_field($_POST['eq_own']));
	} else {
		delete_post_meta($post_id, 'eq_own');
	}

	return $post_id;

}

//ADD CUSTOM COLUMNS

add_filter('manage_equipment_posts_columns', function ($columns) {
	$columns['title'] = __('Equipment Title', 'the-artist');
	return $columns;
});

add_filter('manage_equipment_posts_columns', function ($columns) {
	return array_merge($columns, ['equipment_purchase_date' => __('Purchase Date', 'the-artist')], ['equipment_name' => __('Name', 'the-artist')], ['model_n' => __('Model', 'the-artist')], ['eq_price' => __('Purchase Price', 'the-artist')], ['eq_own' => __('Ownership', 'the-artist')], ['equipment_warranty' => __('Warranty', 'the-artist')]);
});

add_filter('manage_edit-equipment_sortable_columns', function ($columns) {
	$columns['equipment_purchase_date'] = 'equipment_purchase_date';
	$columns['equipment_warranty'] = 'equipment_warranty';
	$columns['equipment_name'] = 'equipment_name';
	$columns['eq_price'] = 'eq_price';
	$columns['eq_own'] = 'eq_own';
	return $columns;
});

add_filter('manage_equipment_posts_columns', function ($columns) {
	$taken_out = $columns['date'];
	unset($columns['date']);
	unset($columns['title']);
	return $columns;
});

add_action('manage_equipment_posts_custom_column', function ($column_key, $post_id) {
	if ($column_key == 'equipment_purchase_date') {
		$equipment_purchase_date = get_post_meta($post_id, 'equipment_purchase_date', true);
		if ($equipment_purchase_date) {
			echo '<span>';
			_e($equipment_purchase_date, 'the-artist');
			echo '</span>';
		} else {
			echo 'Not Provided';
		}
	}

	if ($column_key == 'equipment_name') {
		$equipment_name = get_post_meta($post_id, 'equipment_name', true);
		if ($equipment_name) {
			echo '<span>';
			_e($equipment_name, 'the-artist');
			echo '</span>';
		} else {
			echo 'Not Provided';
		}
	}

	if ($column_key == 'model_n') {
		$model_n = get_post_meta($post_id, 'model_n', true);
		if ($model_n) {
			echo '<span>';
			_e($model_n, 'the-artist');
			echo '</span>';
		} else {
			echo 'Not Provided';
		}
	}

	if ($column_key == 'eq_price') {
		$eq_price = get_post_meta($post_id, 'eq_price', true);
		if ($eq_price) {
			echo '<span>';
			_e($eq_price, 'the-artist');
			echo '</span>';
		} else {
			echo 'Not Provided';
		}
	}

	if ($column_key == 'eq_own') {
		$eq_own = get_post_meta($post_id, 'eq_own', true);
		if ($eq_own) {
			echo '<span>';
			_e($eq_own, 'the-artist');
			echo '</span>';
		} else {
			echo 'Not Provided';
		}
	}

	if ($column_key == 'equipment_warranty') {
		$equipment_warranty = get_post_meta($post_id, 'equipment_warranty', true);
		if ($equipment_warranty) {
			echo '<span>';
			_e($equipment_warranty, 'the-artist');
			echo '</span>';
		} else {
			echo 'Not Provided';
		}
	}
}, 10, 2);



/**Custom Taxonomies*/
add_action('init', 'register_taxonomy_brand');
function register_taxonomy_brand()
{
	$labels = array(
		'name' => _x('Brand', 'eq_brand', 'the-artist'),
		'singular_name' => _x('Brand', 'eq_brand', 'the-artist'),
		'search_items' => _x('Search Brand', 'eq_brand', 'the-artist'),
		'popular_items' => _x('Popular Brand', 'eq_brand', 'the-artist'),
		'all_items' => _x('All Brand', 'eq_brand', 'the-artist'),
		'parent_item' => _x('Parent Brand', 'eq_brand', 'the-artist'),
		'parent_item_colon' => _x('Parent Brand:', 'eq_brand', 'the-artist'),
		'edit_item' => _x('Edit Brand', 'eq_brand', 'the-artist'),
		'update_item' => _x('Update Brand', 'eq_brand', 'the-artist'),
		'add_new_item' => _x('Add New Brand', 'eq_brand', 'the-artist'),
		'new_item_name' => _x('New Brand', 'eq_brand', 'the-artist'),
		'separate_items_with_commas' => _x('Separate wood species with commas', 'eq_brand', 'the-artist'),
		'add_or_remove_items' => _x('Add or remove Brand', 'eq_brand', 'the-artist'),
		'choose_from_most_used' => _x('Choose from most used Brand', 'eq_brand', 'the-artist'),
		'menu_name' => _x('Brand', 'eq_brand', 'the-artist'),
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
	register_taxonomy('eq_brand', array('equipment'), $args);
}


add_action('init', 'register_taxonomy_store');
function register_taxonomy_store()
{
	$labels = array(
		'name' => _x('Store', 'eq_store', 'the-artist'),
		'singular_name' => _x('Store', 'eq_store', 'the-artist'),
		'search_items' => _x('Search Store', 'eq_store', 'the-artist'),
		'popular_items' => _x('Popular Store', 'eq_store', 'the-artist'),
		'all_items' => _x('All Store', 'eq_store', 'the-artist'),
		'parent_item' => _x('Parent Store', 'eq_store', 'the-artist'),
		'parent_item_colon' => _x('Parent Store:', 'eq_store', 'the-artist'),
		'edit_item' => _x('Edit Store', 'eq_store', 'the-artist'),
		'update_item' => _x('Update Store', 'eq_store', 'the-artist'),
		'add_new_item' => _x('Add New Store', 'eq_store', 'the-artist'),
		'new_item_name' => _x('New Store', 'eq_store', 'the-artist'),
		'separate_items_with_commas' => _x('Separate wood species with commas', 'eq_store', 'the-artist'),
		'add_or_remove_items' => _x('Add or remove Store', 'eq_store', 'the-artist'),
		'choose_from_most_used' => _x('Choose from most used Store', 'eq_store', 'the-artist'),
		'menu_name' => _x('Store', 'eq_store', 'the-artist'),
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
	register_taxonomy('eq_store', array('equipment'), $args);
}
?>