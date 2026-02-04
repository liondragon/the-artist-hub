<?php /* Template Name: Full-width Page
  * @package WordPress */ ?>
<?php get_header(); ?>
<article id="page_content" class="page_content full-width">
	<div id="page_header" class="pageline">
		<div class="inner">
			<h1 class="page-title screen-reader-text"><?php the_title(); ?></h1>
			<span><?php echo esc_html(get_post_meta(get_the_ID(), 'page-tagline', true)); ?> </span>
		</div>
	</div>
	<div class="inner article-body-wrap">
		<div id="content" class="article-body-full">
			<?php while (have_posts()):
				the_post(); ?>
				<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<div class="entry-content">
						<?php the_content(); ?>
					</div><!-- .entry-content -->
				</div><!-- #post -->
			<?php endwhile; // end of the loop. ?>
		</div><!-- .page-content -->
	</div><!-- .inner -->
</article><!-- #page_content -->
<?php get_footer(); ?>