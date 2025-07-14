<?php get_header(); ?>
<section id="page_content" class="page_content">
	<header id="page_header" class="pageline">
		<div class="inner">
			<h1 class="page-title screen-reader-text"><?php the_title(); ?></h1>
			<span><?php echo get_post_meta($wp_query->posts[0]->ID, 'page-tagline', true); ?> </span>
		</div>
	</header>
	<section class="page_body">
		<div class="inner">
			<main id="content" role="main" class="page-content">
				<?php while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<div class="entry-content">
					<?php the_content(); ?>
					</div><!-- .entry-content -->
				</article><!-- #post -->
				<?php endwhile; // end of the loop. ?>
			</main><!-- #content -->
			<?php get_sidebar(); ?>
		</div>
	</section>
</section><!-- #page_content -->
<?php get_footer(); ?>