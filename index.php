<?php get_header(); ?>
<section id="page_content" class="page_content full_width">
	<header id="page_header" class="pageline">
		<div class="inner">
			<?php if ( is_home() && ! is_front_page() ) : ?>
			<h1 class="page-title screen-reader-text">
				The Flooring Artists <?php single_post_title(); ?>
			</h1>
			<span>Read. Learn. Act.</span>
			<?php else : ?>
			<h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
			<?php endif; ?>
		</div>
	</header>
	<div class="col-full inner clearfix">
	<main id="archive_content" class="archive_content">
	<?php /* Start the Loop */ 
		$query = new WP_Query( 'cat=-2' );
		if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post();
		get_template_part( 'content', get_post_format() );
		endwhile;
		if (function_exists("pagination")) { pagination(isset($additional_loop)&&$additional_loop->max_num_pages); }
	?>
	<?php
		else :
	?>
			<article id="post-0" class="post no-results not-found">
	<?php
		if ( current_user_can( 'edit_posts' ) ) : // Show a different message to a logged-in user who can add posts.
	?>
			<header class="entry-header">
				<h2 class="entry-title"><?php _e( 'No posts to display', 'the-artist' ); ?></h2>
			</header>
			<div class="entry-content">
				<p><?php printf( __( 'Ready to publish your first post? <a href="%s">Get started here</a>.', 'the-artist' ), admin_url( 'post-new.php' ) ); ?></p>
			</div><!-- .entry-content -->

	<?php
		else : // Show the default message to everyone else.
	?>
			<header class="entry-header">
				<h2 class="entry-title"><?php _e( 'Nothing Found', 'the-artist' ); ?></h2>
			</header>
			<div class="entry-content">
				<p><?php _e( 'Apologies, but no results were found. Perhaps searching will help find a related post.', 'the-artist' ); ?></p>
					<?php get_search_form(); ?>
			</div><!-- .entry-content -->
	<?php
		endif; // end current_user_can() check
	?>
			</article><!-- #post-0 -->

	<?php
		endif; // end have_posts() check
	?>
	</main><!-- #main -->
</div><!-- #content .col-full .inner .clearfix -->
</section><!-- #page_content -->
<?php get_footer(); ?>