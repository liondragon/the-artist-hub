<?php get_header('blog'); ?>
<section id="page_content" class="page_content <?php if ( is_active_sidebar( 'blog-widget-area' ) ) {echo 'sidebar_active';}?>">
	<div class="inner">
		<main id="content" role="main" class="post-content col-left">
			<?php
				while ( have_posts() ) : the_post();
				get_template_part( 'content', 'single' );
			?>
			<?php	if ( get_the_author_meta( 'description' ) ) :
			?>
					<aside id="author-bio">
						<div class="profile-image">
						<?php if (function_exists('get_avatar')) { echo get_avatar( get_the_author_meta('email'), '90' ); }?>
						</div>
						<div class="authorinfo">
							<h3 class="title"><?php the_author_posts_link(); ?></h3>
							<p><?php the_author_meta('description'); ?></p>
							<div class="profile-info">
							<?php if (get_the_author_meta('user_url') ) : ?>
							<a href="<?php the_author_meta('user_url'); ?>"><?php the_author_meta('first_name'); ?>'s website</a> | 
							<?php endif; ?>
							<?php if (get_the_author_meta('twitter') ) : ?>
							Follow <?php the_author_meta('first_name'); ?> on <a href="http://www.twitter.com/<?php the_author_meta('twitter'); ?>">Twitter</a> | 
							<?php endif; ?>
							</div> <!-- END .profile-info -->
						</div>
				</aside>	
			<?php endif; ?>
			<section id="comments_area"><?php comments_template( '', true );?></section>
			<?php endwhile; // end of the loop. ?>
		</main><!-- #content -->
		<?php if ( is_active_sidebar( 'blog-widget-area' ) ) { ?>
			<aside id="sidebar" class="col-right" role="complementary">
				<?php dynamic_sidebar( 'blog-widget-area' ); ?><?php } ?>
			</aside><!-- #secondary -->
	</div><!-- .inner -->
</section><!-- #page_content -->
<?php get_footer(); ?>