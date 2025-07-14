<?php


//Remove Menus for Subsriber Role
function remove_menus(){

  $author = wp_get_current_user();
  if(isset($author->roles[0])){ 
     $current_role = $author->roles[0];
  }else{
     $current_role = 'no_role';
  }

  if($current_role == 'subscriber'){  
     //remove_menu_page( 'index.php' );                  //Dashboard
     //remove_menu_page( 'edit.php' );                   //Posts
     remove_menu_page( 'upload.php' );                 //Media
     remove_menu_page( 'tools.php' );                  //Tools
     remove_menu_page( 'edit-comments.php' );               //Comments
     remove_menu_page( 'edit.php?post_type=my_other_custom_post_type_I_want_to_hide' );
	   }

}
add_action( 'admin_menu', 'remove_menus' );




function redirect_to_specific_page() {
if ( is_home() && ! is_user_logged_in() ) {

wp_redirect( 'https://flooringartists.com', 301 ); 
  exit;
    }
}
add_action( 'template_redirect', 'redirect_to_specific_page' );


function custom_redirect() {        
    global $post;

    if ( $post->post_type == 'projects' && ! is_user_logged_in() ) {
      wp_redirect( home_url() ); 
      exit();
    }    
  }

//add_action("template_redirect","custom_redirect");

function project_404_loggedin_func() {
	global $post;
		if ($post->post_type == 'projects') {
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


// New Field

add_action( 'show_user_profile', 'crf_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'crf_show_extra_profile_fields' );

function crf_show_extra_profile_fields( $user ) {
	$year = get_the_author_meta( 'company_name', $user->ID );
	?>
	<h3><?php esc_html_e( 'Business Information', 'crf' ); ?></h3>

	<table class="form-table">
		<tr>
			<th><label for="company_name"><?php esc_html_e( 'Company Name', 'crf' ); ?></label></th>
			<td>
				<input type="text"
			       id="company_name"
			       name="company_name"
			       value="<?php echo esc_attr( $year ); ?>"
			       class="regular-text"
				/>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'user_profile_update_errors', 'crf_user_profile_update_errors', 10, 3 );
function crf_user_profile_update_errors( $errors, $update, $user ) {
	if ( ! $update ) {
		return;
	}

	if ( empty( $_POST['company_name'] ) ) {
		$errors->add( 'company_name_error', __( '<strong>ERROR</strong>: Please the company name.', 'crf' ) );
	}
}


add_action( 'personal_options_update', 'crf_update_profile_fields' );
add_action( 'edit_user_profile_update', 'crf_update_profile_fields' );

function crf_update_profile_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return false;
	}

	if ( ! empty( $_POST['company_name'] ) ) {
		update_user_meta( $user_id, 'company_name',  $_POST['company_name'] );
	}
}

function remove_website_row_wpse_94963_css()
{
    echo '<style>tr.user-url-wrap{ display: none; }</style>';
}
add_action( 'admin_head-user-edit.php', 'remove_website_row_wpse_94963_css' );
add_action( 'admin_head-profile.php',   'remove_website_row_wpse_94963_css' );


if(!function_exists('remove_plain_bio')){
	function remove_bio_box($buffer){
		$buffer = str_replace('<h3>About Yourself</h3>','<h3>User Password</h3>',$buffer);
		$buffer = preg_replace('/<tr class=\"user-description-wrap\"[\s\S]*?<\/tr>/','',$buffer,1);
		return $buffer;
	}
	function user_profile_subject_start(){ ob_start('remove_bio_box'); }
	function user_profile_subject_end(){ ob_end_flush(); }
}
add_action('admin_head-profile.php','user_profile_subject_start');
add_action('admin_footer-profile.php','user_profile_subject_end');
?>