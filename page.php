<?php
declare(strict_types=1);
get_header(); ?>
<article id="page_content"
	class="page_content <?php if (is_active_sidebar('page-widgets')) {
		echo 'sidebar_active';
	} ?>">
	<div id="page_header" class="pageline">
		<div class="inner">
			<h1 class="page-title screen-reader-text"><?php the_title(); ?></h1>
			<span><?php echo get_post_meta($wp_query->posts[0]->ID, 'page-tagline', true); ?> </span>
		</div>
	</div>
	<div class="inner article-body-wrap">
		<div id="content" class="article-body">
			<?php while (have_posts()):
				the_post(); ?>
				<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<div class="entry-content">
						<?php the_content(); ?>
					</div><!-- .entry-content -->
				</div><!-- #post -->
			<?php endwhile; // end of the loop. ?>
		</div><!-- .page-content -->
		<?php if (is_active_sidebar('page-widgets')) { ?>
			<aside id="sidebar" class="col-right" role="complementary">
				<div class="s-wrapper">
					<?php dynamic_sidebar('page-widgets'); ?>
				<?php } ?>
			</div>
		</aside>
	</div><!-- .inner -->
</article><!-- #page_content -->
<?php get_footer(); ?>