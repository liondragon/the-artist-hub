<?php
declare(strict_types=1);

/**
 * Register Info Section CPT (tah_template_part)
 * AND Handle Validation/Immutability Logic
 */

// 1. Register CPT
add_action('init', 'register_cpt_tah_template_part');
function register_cpt_tah_template_part()
{
    $labels = array(
        'name' => _x('Info Sections', 'post type general name', 'the-artist'),
        'singular_name' => _x('Info Section', 'post type singular name', 'the-artist'),
        'menu_name' => _x('Info Sections', 'admin menu', 'the-artist'),
        'name_admin_bar' => _x('Info Section', 'add new on admin bar', 'the-artist'),
        'add_new' => _x('Add New', 'Info Section', 'the-artist'),
        'add_new_item' => __('Add New Info Section', 'the-artist'),
        'new_item' => __('New Info Section', 'the-artist'),
        'edit_item' => __('Edit Info Section', 'the-artist'),
        'view_item' => __('View Info Section', 'the-artist'),
        'all_items' => __('Info Sections', 'the-artist'),
        'search_items' => __('Search Info Sections', 'the-artist'),
        'not_found' => __('No info sections found.', 'the-artist'),
        'not_found_in_trash' => __('No info sections found in Trash.', 'the-artist')
    );

    $args = array(
        'labels' => $labels,
        'public' => false, // Not publicly queryable on frontend directly
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=quotes', // Submenu of Quotes
        'query_var' => false, // No frontend permalink
        'rewrite' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'edit_post' => 'manage_options',
            'read_post' => 'manage_options',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'create_posts' => 'manage_options',
            'delete_posts' => 'manage_options',
            'delete_private_posts' => 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options',
            'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options',
        ),
        'has_archive' => false,
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'editor', 'page-attributes'), // page-attributes for menu_order
    );

    register_post_type('tah_template_part', $args);
}

// 2. Enforce Classic Editor
add_filter('use_block_editor_for_post_type', 'tah_disable_block_editor_for_sections', 10, 2);
function tah_disable_block_editor_for_sections($use_block_editor, $post_type)
{
    if ($post_type === 'tah_template_part' || $post_type === 'quotes') {
        return false;
    }
    return $use_block_editor;
}

// 3. Meta Box for Canonical Key
add_action('add_meta_boxes', 'tah_register_section_key_metabox');
function tah_register_section_key_metabox()
{
    add_meta_box(
        'tah_section_key_box',
        __('Section Slug (Unique ID)', 'the-artist'),
        'tah_render_section_key_metabox',
        'tah_template_part',
        'side',
        'high'
    );
}

function tah_render_section_key_metabox($post)
{
    wp_nonce_field('tah_save_section_key', '_tah_section_key_nonce');

    $current_key = get_post_meta($post->ID, '_tah_section_key', true);

    if (!empty($current_key)) {
        // IMMUTABLE UI: Read-Only Text
        ?>
        <p><strong>
                <?php _e('Current Key:', 'the-artist'); ?>
            </strong></p>
        <code style="font-size: 1.2em; display:block; margin-bottom:10px;"><?php echo esc_html($current_key); ?></code>
        <p class="description">
            <?php _e('This key is immutable and cannot be changed.', 'the-artist'); ?>
        </p>
        <?php
    } else {
        // MUTABLE UI: Input Field (Only for new/unset keys)
        ?>
        <label for="_tah_section_key">
            <?php _e('Enter Key:', 'the-artist'); ?>
        </label>
        <input type="text" id="_tah_section_key" name="_tah_section_key" value="" class="widefat" pattern="[a-z][a-z0-9_]*">
        <p class="description">
            <?php _e('Format: Lowercase letters, numbers, and underscores only. Must start with a letter.', 'the-artist'); ?>
            <br>
            <code>^[a-z][a-z0-9_]*$</code>
        </p>
        <?php
    }
}

// 4. Save Logic (Validation & Enforcement)
add_action('save_post_tah_template_part', 'tah_save_section_key_logic', 10, 3);

function tah_generate_section_key_from_title($title)
{
    $key = sanitize_title($title);
    $key = str_replace('-', '_', $key);
    $key = preg_replace('/[^a-z0-9_]/', '', $key);
    $key = preg_replace('/_+/', '_', (string) $key);
    $key = trim((string) $key, '_');

    if ($key === '') {
        return '';
    }

    if (!preg_match('/^[a-z]/', $key)) {
        $key = 'section_' . $key;
    }

    return $key;
}

function tah_save_section_key_logic($post_id, $post, $update)
{
    // A. Security Checks
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (wp_is_post_revision($post_id))
        return;
    if (!current_user_can('edit_post', $post_id))
        return;
    if (!isset($_POST['_tah_section_key_nonce']) || !wp_verify_nonce($_POST['_tah_section_key_nonce'], 'tah_save_section_key')) {
        return;
    }

    // B. Check if Key already exists (Immutability check)
    $existing_key = get_post_meta($post_id, '_tah_section_key', true);
    if (!empty($existing_key)) {
        // If key exists, we DO NOT process any input. We strictly ignore changes.
        return;
    }

    // C. Process New Key Input (or auto-generate from title if empty)
    $submitted_key = '';
    if (isset($_POST['_tah_section_key'])) {
        $submitted_key = trim(sanitize_text_field(wp_unslash($_POST['_tah_section_key'])));
    }

    if ($submitted_key === '') {
        $submitted_key = tah_generate_section_key_from_title((string) $post->post_title);
    }

    // 1. Validate Regex (strict; lowercase + underscore format only)
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $submitted_key)) {
        // INVALID: Set Admin Notice Transient and RETURN (Do not save)
        set_transient('tah_key_error_' . get_current_user_id(), __('Error: Invalid Key Format. Uppercase or special characters not allowed.', 'the-artist'), 30);
        return;
    }

    // 2. Uniqueness Check
    global $wpdb;
    $duplicate_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta pm 
         JOIN $wpdb->posts p ON pm.post_id = p.ID 
         WHERE meta_key = '_tah_section_key' 
         AND meta_value = %s 
         AND post_type = 'tah_template_part'
         AND post_id != %d 
         AND post_status != 'trash'",
        $submitted_key,
        $post_id
    ));

    if ($duplicate_id) {
        // DUPLICATE: Set Admin Notice Transient and RETURN (Do not save)
        set_transient('tah_key_error_' . get_current_user_id(), sprintf(__('Error: Key "%s" already exists on another Info Section.', 'the-artist'), $submitted_key), 30);
        return;
    }

    // 3. Success: Update Meta
    update_post_meta($post_id, '_tah_section_key', $submitted_key);
}

// 5. Admin Notices
add_action('admin_notices', 'tah_section_key_admin_notices');
function tah_section_key_admin_notices()
{
    $error = get_transient('tah_key_error_' . get_current_user_id());
    if ($error) {
        delete_transient('tah_key_error_' . get_current_user_id());
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php echo esc_html($error); ?>
            </p>
        </div>
        <?php
    }
}

// 6. Admin Columns
add_filter('manage_tah_template_part_posts_columns', 'tah_section_columns');
function tah_section_columns($columns)
{
    $columns['tah_key'] = __('Section Key', 'the-artist');
    $columns['menu_order'] = __('Order', 'the-artist'); // Make sure standard column shows if supported
    return $columns;
}

add_action('manage_tah_template_part_posts_custom_column', 'tah_section_custom_column', 10, 2);
function tah_section_custom_column($column, $post_id)
{
    if ($column === 'tah_key') {
        $key = get_post_meta($post_id, '_tah_section_key', true);
        echo $key ? '<code>' . esc_html($key) . '</code>' : '<span style="color:red;">(No Key)</span>';
    }
}

// Make Menu Order Sortable
add_filter('manage_edit-tah_template_part_sortable_columns', 'tah_section_sortable_columns');
function tah_section_sortable_columns($columns)
{
    $columns['menu_order'] = 'menu_order';
    return $columns;
}
