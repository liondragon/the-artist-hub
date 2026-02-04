<?php
declare(strict_types=1);

/**
 * User Management & Profile Customizations
 * 
 * Handles:
 * - Custom Avatar (URL based)
 * - Extra Profile Fields (Company Name)
 * - Access Control (Redirects, Menu Hiding)
 * - Profile UI Tweaks
 */

/**
 * -----------------------------------------------------------------------------
 * 1. ACCESS CONTROL & REDIRECTS
 * -----------------------------------------------------------------------------
 */

/**
 * Remove Menus for Subscribers
 */
function remove_menus()
{
    $author = wp_get_current_user();
    if (isset($author->roles[0])) {
        $current_role = $author->roles[0];
    } else {
        $current_role = 'no_role';
    }

    if ($current_role == 'subscriber') {
        //remove_menu_page( 'index.php' );                  //Dashboard
        //remove_menu_page( 'edit.php' );                   //Posts
        remove_menu_page('upload.php');                 //Media
        remove_menu_page('tools.php');                  //Tools
        remove_menu_page('edit-comments.php');               //Comments
        remove_menu_page('edit.php?post_type=my_other_custom_post_type_I_want_to_hide');
    }
}
add_action('admin_menu', 'remove_menus');

/**
 * Redirect non-logged-in users from Home to External Site
 */
function redirect_to_specific_page()
{
    if (is_home() && !is_user_logged_in()) {
        wp_redirect('https://flooringartists.com', 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_to_specific_page');

/**
 * Redirect non-logged-in users from Projects
 */
function custom_redirect()
{
    global $post;
    if ($post && $post->post_type == 'projects' && !is_user_logged_in()) {
        wp_redirect(home_url());
        exit();
    }
}
//add_action("template_redirect","custom_redirect");

/**
 * 404 access to Projects for logged-in users? (Logic seems inverse or specific)
 */
function project_404_loggedin_func()
{
    global $post;
    if ($post && $post->post_type == 'projects') {
        if (!is_user_logged_in()) {
            global $wp_query;
            $wp_query->posts = [];
            $wp_query->post = null;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
        }
    }
}
//add_action('template_redirect', 'project_404_loggedin_func');


/**
 * -----------------------------------------------------------------------------
 * 2. CUSTOM AVATAR (URL Based)
 * -----------------------------------------------------------------------------
 */

// Add Avatar Field
function the_artist_custom_avatar_url($user)
{
    ?>
    <table class="form-table">
        <tr>
            <th>
                <label for="avtr">
                    <?php _e('Custom Avatar URL', 'the-artist'); ?>
                </label>
            </th>
            <td>
                <input type="text" name="avtr" id="avtr"
                    value="<?php echo esc_attr(get_the_author_meta('avtr', $user->ID)); ?>" class="regular-text" /><br />
                <span class="description">
                    <?php _e('Please enter your url.', 'the-artist'); ?>
                </span>
            </td>
        </tr>
    </table>
<?php }
add_action('show_user_profile', 'the_artist_custom_avatar_url');
add_action('edit_user_profile', 'the_artist_custom_avatar_url');

// Save Avatar Field
function the_artist_save_custom_avatar_url($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return FALSE;
    }

    if (isset($_POST['avtr'])) {
        update_user_meta($user_id, 'avtr', esc_url_raw($_POST['avtr']));
    }
}
add_action('personal_options_update', 'the_artist_save_custom_avatar_url');
add_action('edit_user_profile_update', 'the_artist_save_custom_avatar_url');

// Display Custom Avatar
add_filter('get_avatar', 'the_artist_custom_avatar', 1, 5);
function the_artist_custom_avatar($avatar, $id_or_email, $size, $default, $alt)
{
    $user = false;
    if (is_numeric($id_or_email)) {
        $id = (int) $id_or_email;
        $user = get_user_by('id', $id);
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $id = (int) $id_or_email->user_id;
            $user = get_user_by('id', $id);
        }
    } else {
        $user = get_user_by('email', $id_or_email);
    }

    if ($user && is_object($user)) {
        $custom_user_image = get_the_author_meta('avtr', $user->ID);
        if ($custom_user_image) {
            $avatar = "<img alt='" . esc_attr($alt) . "' src='" . esc_url($custom_user_image) . "' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
        }
    }
    return $avatar;
}


/**
 * -----------------------------------------------------------------------------
 * 3. EXTRA PROFILE FIELDS (Company Name)
 * -----------------------------------------------------------------------------
 */

function the_artist_show_extra_profile_fields($user)
{
    $company_name = get_the_author_meta('company_name', $user->ID);
    ?>
    <h3>
        <?php esc_html_e('Business Information', 'the-artist'); ?>
    </h3>

    <table class="form-table">
        <tr>
            <th><label for="company_name">
                    <?php esc_html_e('Company Name', 'the-artist'); ?>
                </label></th>
            <td>
                <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($company_name); ?>"
                    class="regular-text" />
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'the_artist_show_extra_profile_fields');
add_action('edit_user_profile', 'the_artist_show_extra_profile_fields');

function the_artist_user_profile_update_errors($errors, $update, $user)
{
    if (!$update) {
        return;
    }

    if (empty($_POST['company_name'])) {
        $errors->add('company_name_error', __('<strong>ERROR</strong>: Please enter the company name.', 'the-artist'));
    }
}
add_action('user_profile_update_errors', 'the_artist_user_profile_update_errors', 10, 3);

function the_artist_update_profile_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (!empty($_POST['company_name'])) {
        // SECURITY FIX: Added sanitize_text_field
        update_user_meta($user_id, 'company_name', sanitize_text_field($_POST['company_name']));
    }
}
add_action('personal_options_update', 'the_artist_update_profile_fields');
add_action('edit_user_profile_update', 'the_artist_update_profile_fields');


/**
 * -----------------------------------------------------------------------------
 * 4. PROFILE UI CLEANUP
 * -----------------------------------------------------------------------------
 */

function remove_website_row_wpse_94963_css()
{
    echo '<style>tr.user-url-wrap{ display: none; }</style>';
}
add_action('admin_head-user-edit.php', 'remove_website_row_wpse_94963_css');
add_action('admin_head-profile.php', 'remove_website_row_wpse_94963_css');

if (!function_exists('remove_bio_box')) {
    function remove_bio_box($buffer)
    {
        $buffer = str_replace('<h3>About Yourself</h3>', '<h3>User Password</h3>', $buffer);
        $buffer = preg_replace('/<tr class=\"user-description-wrap\"[\s\S]*?<\/tr>/', '', $buffer, 1);
        return $buffer;
    }
    function user_profile_subject_start()
    {
        ob_start('remove_bio_box');
    }
    function user_profile_subject_end()
    {
        ob_end_flush();
    }
    add_action('admin_head-profile.php', 'user_profile_subject_start');
    add_action('admin_footer-profile.php', 'user_profile_subject_end');
}
