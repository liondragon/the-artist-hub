<?php
declare(strict_types=1);
get_header(); ?>
<main id="page_content" class="page_content full_width">
	<header id="page_header" class="pageline">
		<div class="inner">
			<h1 class="page-title screen-reader-text">Equipment</h1>
			<div class="tagline">We are what we do. Repeatedly.</div>
		</div>
	</header>
	<section id="archive_content" class="archive_content" role="main">
		<div class="inner">
			<?php if (have_posts()): ?>
				<div class="post_container grid_3">
					<?php // Start the Loop.
						while (have_posts()):
							the_post();
							?>
						<a class="project_post col" href="<?php the_permalink(); ?>" title="">
							<?php
							if (has_post_thumbnail()) {
								echo '<figure class="thumbnail">';
								the_post_thumbnail('medium');
								echo '</figure>';
							}
							?>
							<div class="project_description">
								<span><?php echo get_post_meta($post->ID, 'equipment_name', true) ?></span><br>
								<span><?php esc_html_e('Brand:', 'the-artist'); ?>
									<?php echo strip_tags(get_the_term_list($post->ID, 'eq_brand')) ?></span><br>
								<span><?php esc_html_e('Purchase Date:', 'the-artist'); ?>
									<?php echo get_post_meta($post->ID, 'equipment_purchase_date', true) ?></span><br>
							</div>
						</a>
					<?php endwhile; ?>
				</div><!-- .post_container -->
				<?php if (function_exists("paginate")):
					paginate();
				endif;
			else:
				get_template_part('template-parts/content/content', 'none');
			endif; ?>
		</div><!-- .inner -->
	</section><!-- #archive_content -->
</main>
<?php get_footer(); ?>