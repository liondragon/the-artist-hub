<?php
// Register Project Custom Post
add_action('init', 'register_cpt_project');
function register_cpt_project()
{
	$labels = array(
		'name' => _x('Projects', 'projects', 'the-artist'),
		'singular_name' => _x('Project', 'projects', 'the-artist'),
		'add_new' => _x('Add New', 'projects', 'the-artist'),
		'add_new_item' => _x('Add New Project', 'projects', 'the-artist'),
		'edit_item' => _x('Edit Project', 'projects', 'the-artist'),
		'new_item' => _x('New Project', 'projects', 'the-artist'),
		'view_item' => _x('View Project', 'projects', 'the-artist'),
		'search_items' => _x('Search projects', 'projects', 'the-artist'),
		'not_found' => _x('No projects found', 'projects', 'the-artist'),
		'not_found_in_trash' => _x('No projects found in Trash', 'projects', 'the-artist'),
		'parent_item_colon' => _x('Parent Project:', 'projects', 'the-artist'),
		'menu_name' => _x('Projects', 'projects', 'the-artist'),
	);
	$args = array(
		'labels' => $labels,
		'hierarchical' => false,
		'description' => 'Completed Projects.',
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
	register_post_type('projects', $args);
}

//Add a Metabox
add_action('admin_menu', 'the_artist_project_add_metabox');
function the_artist_project_add_metabox()
{
	add_meta_box(
		'tfa_project_metabox', // metabox ID
		'Project Information', // title
		'the_artist_project_metabox_callback', // callback function
		'projects', // post type or post types in array
		'normal', // position (normal, side, advanced)
		'high' // priority (default, low, high, core)
	);

}

// CALLBACK FUNCTION

function the_artist_project_metabox_callback($post)
{

	$project_customer_name = get_post_meta($post->ID, 'project_customer_name', true);
	$project_address = get_post_meta($post->ID, 'project_address', true);
	$project_total_amount = get_post_meta($post->ID, 'project_total_amount', true);
	$project_start_date = get_post_meta($post->ID, 'project_start_date', true);
	$project_end_date = get_post_meta($post->ID, 'project_end_date', true);
	$subcontractor = get_post_meta($post->ID, 'subcontractor', true);

	// nonce, actually I think it is not necessary here
	wp_nonce_field('the_artist_save_project', '_tfanonce_project');
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
				<th><label for="project_customer_name">Customer Name</label></th>
				<td><input type="text" id="project_customer_name" name="project_customer_name" value="' . esc_attr($project_customer_name) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="project_address">Project Address</label></th>
				<td><input type="text" id="project_address" name="project_address" value="' . esc_attr($project_address) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="project_total_amount">Total Amount</label></th>
				<td><input type="text" id="project_total_amount" name="project_total_amount" value="' . esc_attr($project_total_amount) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="project_start_date">Project Start Date</label></th>
				<td><input type="text" id="project_start_date" name="project_start_date" value="' . esc_attr($project_start_date) . '" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="project_end_date">Project End Date</label></th>
				<td><input type="text" id="project_end_date" name="project_end_date" value="' . esc_attr($project_end_date) . '" class="regular-text"></td>
			</tr>
				<th><label for="subcontractor">Subcontractor</label></th>
				<td>
					<select id="subcontractor" name="subcontractor">';
	foreach ($users as $user)
		echo '<option value="' . esc_html($user->ID) . '"' . selected($user->ID, $subcontractor, false) . '>' . $user->display_name . '</option>';
	echo '
					</select>
				</td>
			</tr>
		</tbody>
	</table>';

}

// SAVE DATA

add_action('save_post', 'the_artist_project_save_meta', 10, 2);
// or add_action( 'save_post_{post_type}', 'the_artist_project_save_meta', 10, 2 );

function the_artist_project_save_meta($post_id, $post)
{

	// nonce check
	if (!isset($_POST['_tfanonce_project']) || !wp_verify_nonce($_POST['_tfanonce_project'], 'the_artist_save_project')) {
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
	if ('projects' !== $post->post_type) {
		return $post_id;
	}

	if (isset($_POST['project_customer_name'])) {
		update_post_meta($post_id, 'project_customer_name', sanitize_text_field($_POST['project_customer_name']));
	} else {
		delete_post_meta($post_id, 'project_customer_name');
	}
	if (isset($_POST['project_address'])) {
		update_post_meta($post_id, 'project_address', sanitize_text_field($_POST['project_address']));
	} else {
		delete_post_meta($post_id, 'project_address');
	}
	if (isset($_POST['project_total_amount'])) {
		update_post_meta($post_id, 'project_total_amount', sanitize_text_field($_POST['project_total_amount']));
	} else {
		delete_post_meta($post_id, 'project_total_amount');
	}
	if (isset($_POST['project_start_date'])) {
		update_post_meta($post_id, 'project_start_date', sanitize_text_field($_POST['project_start_date']));
	} else {
		delete_post_meta($post_id, 'project_start_date');
	}
	if (isset($_POST['project_end_date'])) {
		update_post_meta($post_id, 'project_end_date', sanitize_text_field($_POST['project_end_date']));
	} else {
		delete_post_meta($post_id, 'project_end_date');
	}
	if (isset($_POST['subcontractor'])) {
		update_post_meta($post_id, 'subcontractor', sanitize_text_field($_POST['subcontractor']));
	} else {
		delete_post_meta($post_id, 'subcontractor');
	}

	return $post_id;

}

//ADD CUSTOM COLUMNS

add_filter('manage_projects_posts_columns', function ($columns) {
	$columns['title'] = __('Project Title', 'the-artist');
	return $columns;
});

add_filter('manage_projects_posts_columns', function ($columns) {
	return array_merge($columns, ['project_start_date' => __('Start Date', 'the-artist')], ['project_customer_name' => __('Client Name', 'the-artist')], ['project_address' => __('Address', 'the-artist')], ['subcontractor' => __('Team', 'the-artist')], ['project_total_amount' => __('Labor', 'the-artist')]);
});

add_filter('manage_edit-projects_sortable_columns', function ($columns) {
	$columns['project_start_date'] = 'project_start_date';
	$columns['project_total_amount'] = 'project_total_amount';
	$columns['project_customer_name'] = 'project_customer_name';
	$columns['subcontractor'] = 'subcontractor';
	return $columns;
});

add_filter('manage_projects_posts_columns', function ($columns) {
	$taken_out = $columns['date'];
	unset($columns['date']);
	unset($columns['title']);
	return $columns;
});

add_action('manage_projects_posts_custom_column', function ($column_key, $post_id) {
	if ($column_key == 'project_start_date') {
		$project_start_date = get_post_meta($post_id, 'project_start_date', true);
		if ($project_start_date) {
			echo '<span>';
			_e($project_start_date, 'the-artist');
			echo '</span>';
		} else {
			_e('Not Provided', 'the-artist');
		}
	}

	if ($column_key == 'project_customer_name') {
		$project_customer_name = get_post_meta($post_id, 'project_customer_name', true);
		if ($project_customer_name) {
			echo '<span>';
			_e($project_customer_name, 'the-artist');
			echo '</span>';
		} else {
			_e('Not Provided', 'the-artist');
		}
	}

	if ($column_key == 'project_address') {
		$project_address = get_post_meta($post_id, 'project_address', true);
		if ($project_address) {
			echo '<span>';
			_e($project_address, 'the-artist');
			echo '</span>';
		} else {
			_e('Not Provided', 'the-artist');
		}
	}

	if ($column_key == 'subcontractor') {
		$subcontractor = get_post_meta($post_id, 'subcontractor', true);
		if ($subcontractor) {
			echo '<span>';
			_e($subcontractor, 'the-artist');
			echo '</span>';
		} else {
			_e('Not Provided', 'the-artist');
		}
	}

	if ($column_key == 'project_total_amount') {
		$project_total_amount = get_post_meta($post_id, 'project_total_amount', true);
		if ($project_total_amount) {
			echo '<span>';
			_e($project_total_amount, 'the-artist');
			echo '</span>';
		} else {
			_e('Not Provided', 'the-artist');
		}
	}
}, 10, 2);

?>