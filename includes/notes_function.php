<?php
add_action( 'add_meta_boxes', 'notes_ideas_metabox' );
function notes_ideas_metabox() {
	add_meta_box(
		'notes-and-ideas',
		'Notes and Ideas',
		'notes_and_ideas_callback',
		array('post', 'page'),
		'normal',
		'default'
	);
}
function notes_and_ideas_callback( $post ) {
	$notes_and_ideas = get_post_meta( $post->ID, 'notes_and_ideas', true );
	wp_nonce_field( 'somerandomstr', '_ni_metabox_nonce' );
 
	echo '<div class="wp-editor-container" style="border: 0;"><textarea cols="180" rows="10" type="text" name="notes_and_ideas" class="wp-editor-area" style="width: 100%; border: 0; padding: 0;">' . $notes_and_ideas . '</textarea></div>';
}

add_action( 'save_post', 'ni_save_meta', 10, 2 );
 function ni_save_meta( $post_id, $post ) {
	$posttypes = array('post', 'page');
	if ( ! isset( $_POST[ '_ni_metabox_nonce' ] ) || ! wp_verify_nonce( $_POST[ '_ni_metabox_nonce' ], 'somerandomstr' ) ) {
		return $post_id;
	}
	// check current use permissions
	$post_type = get_post_type_object( $post->post_type );
	if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
		return $post_id;
	}
	// Do not save the data if autosave
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
		return $post_id;
	}
	// define your own post type here
	if (!in_array($post->post_type, $posttypes)) {
		return $post_id;
	}
	if( isset( $_POST[ 'notes_and_ideas' ] ) ) {
		update_post_meta( $post_id, 'notes_and_ideas', sanitize_text_field( $_POST[ 'notes_and_ideas' ] ) );
	} else {
		delete_post_meta( $post_id, 'notes_and_ideas' );
	}
	return $post_id;
}