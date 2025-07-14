<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header" id="page_header">
			<h1 class="page-title screen-reader-text entry-title"><?php the_title(); ?></h1>
						<div class="post-meta">
				<span class="post-category"><?php the_category(', ') ?></span>
			</div><!-- .post-meta -->
				<div class="social-share .top">
			<?php get_template_part('template', 'sharing-box');?>
				</div>
	</header><!-- .entry-header -->
	<div class="entry-content">
	<?php 
		// Insert featured image if there is one and the post format is set to 'Image'
		$the_format = get_post_format();
		if ( has_post_thumbnail() && ($the_format == 'image') ) { 
			echo '<figure class="thumbnail alignleft">';
			the_post_thumbnail(array(190,190));
			echo '</figure>';
		} 
		the_content();
	?>
	</div><!-- .entry-content -->
	<footer class="entry-meta">
				<div class="social-share">
			<h5 class="sharing-box-name">Don't be selfish. Share the knowledge!</h5>
			<?php get_template_part('template', 'sharing-box');?>
				</div>
		<?php if ( is_singular() && get_the_author_meta( 'description' ) && is_multi_author() ) : // If a user has filled out their description and this is a multi-author blog, show a bio on their entries. ?>
			<aside class="author-info">
				<div class="author-avatar">
					<?php echo get_avatar( get_the_author_meta( 'user_email' ), apply_filters( 'the-artist_author_bio_avatar_size', 68 ) ); ?>
				</div><!-- .author-avatar -->
				<div class="author-description">
					<h2><?php printf( __( 'About %s', 'the-artist' ), get_the_author() ); ?></h2>
					<p><?php the_author_meta( 'description' ); ?></p>
					<div class="author-link">
						<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>" rel="author">
								<?php printf( __( 'View all posts by %s <span class="meta-nav">&rarr;</span>', 'the-artist' ), get_the_author() ); ?>
						</a>
					</div><!-- .author-link	-->
				</div><!-- .author-description -->
			</aside><!-- .author-info -->
			<?php endif; ?>
	</footer><!-- .entry-meta -->
</article><!-- #post -->