<?php
declare(strict_types=1);
add_filter('comment_form_logged_in', '__return_empty_string');

if (!function_exists('the_artist_comment_nav')):
	function the_artist_comment_nav()
	{
		// Are there comments to navigate through?
		if (get_comment_pages_count() > 1 && get_option('page_comments')):
			?>
			<nav class="navigation comment-navigation" role="navigation">
				<div class="nav-links">
					<?php
					if ($prev_link = get_previous_comments_link(__('Older Comments', 'the-artist'))):
						printf('<div class="nav-previous">%s</div>', $prev_link);
					endif;

					if ($next_link = get_next_comments_link(__('Newer Comments', 'the-artist'))):
						printf('<div class="nav-next">%s</div>', $next_link);
					endif;
					?>
				</div><!-- .nav-links -->
			</nav><!-- .comment-navigation -->
			<?php
		endif;
	}
endif;

function comment_reform($arg)
{
	$arg['title_reply'] = __('Join the Conversation', 'the-artist');
	return $arg;
}
add_filter('comment_form_defaults', 'comment_reform');

//Comment Spam Check
function preprocess_new_comment($commentdata)
{
	if (!empty($_POST['url'])) {
		die('You appear to be a spammer. If you believe this message is a mistake, please contact us.');
	}
	return $commentdata;
}
if (function_exists('add_action')) {
	add_action('preprocess_comment', 'preprocess_new_comment');
}

//Better Comments
function better_comments($comment, $args, $depth)
{
	global $post;
	$author_id = $post->post_author;
	$GLOBALS['comment'] = $comment;
	switch ($comment->comment_type):
		case 'pingback':
		case 'trackback':
			// Display trackbacks differently than normal comments. ?>
			<li id="comment-<?php comment_ID(); ?>" <?php comment_class(); ?>>
				<div class="pingback-entry"><span class="pingback-heading"><?php esc_html_e('Pingback:', 'the-artist'); ?></span>
					<?php comment_author_link(); ?></div>
				<?php
				break;
		default:
			// Proceed with normal comments. ?>
			<li id="li-comment-<?php comment_ID(); ?>" <?php comment_class('clr'); ?>>
				<article id="comment-<?php comment_ID(); ?>" class="comment-body">
					<div class="comment-author vcard">
						<?php echo get_avatar($comment, 45); ?>
						<span class="fn"><?php comment_author_link(); ?></span>
						<span class="comment-date">
							<?php printf(_x('%s ago', '%s = human-readable time difference', 'the-artist'), human_time_diff(get_comment_time('U'), current_time('timestamp'))); ?>
						</span><!-- .comment-date -->
					</div><!-- .comment-author -->
					<div class="comment-details clr">
						<?php if ('0' == $comment->comment_approved): ?>
							<p class="comment-awaiting-moderation">
								<?php esc_html_e('Your comment is awaiting moderation.', 'the-artist'); ?></p>
						<?php endif; ?>
						<div class="comment-content entry clr">
							<?php comment_text(); ?>
						</div><!-- .comment-content -->
						<div class="reply comment-reply-link">
							<?php comment_reply_link(array_merge(
								$args,
								array(
									'reply_text' => esc_html__('Reply', 'the-artist'),
									'depth' => $depth,
									'max_depth' => $args['max_depth']
								)
							)); ?>
						</div><!-- .reply -->
					</div><!-- .comment-details -->
				</article><!-- #comment-## -->
				<?php
				break;
	endswitch; // End comment_type check.
}

?>