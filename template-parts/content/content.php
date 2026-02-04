<article id="post-<?php the_ID(); ?>" <?php post_class(''); ?>>
	<?php
	if (is_sticky() && is_home() && !is_paged()): ?>
		<div class="featured-post">
			<?php esc_html_e('Featured', 'the-artist'); ?>
		</div>
	<?php endif; ?>
	<header class="entry-header">
		<?php if (is_category() && !is_tax('projects')): ?>
			<h2 class="entry-title"><a href="<?php the_permalink(); ?>"
					title="<?php echo esc_attr(sprintf(__('Permalink to %s', 'the-artist'), the_title_attribute('echo=0'))); ?>"
					rel="bookmark"><?php the_title(); ?></a></h2>
			<div class="post-meta">
				<span class="post-date"><?php the_time('jS F, Y') ?></span>
			</div><!-- .post-meta -->
		<?php else: ?>
			<h2 class="entry-title">
				<a href="<?php the_permalink(); ?>"
					title="<?php echo esc_attr(sprintf(__('Permalink to %s', 'the-artist'), the_title_attribute('echo=0'))); ?>"
					rel="bookmark"><?php the_title(); ?></a>
			</h2>
			<div class="post-meta">
				<span class="post-category"><?php the_category(', ') ?></span>
			</div><!-- .post-meta -->
		<?php endif; // is_category() 
		?>
	</header><!-- .entry-header -->

	<?php if (is_search()):  // Search Template
			?>
		<div class="entry-summary">
			<?php the_excerpt(); ?>
		</div><!-- .entry-summary -->
	<?php else: ?>
		<div class="entry-content">
			<?php if (has_post_thumbnail()): ?>
				<div class="thumbnail">
					<a href="<?php echo esc_url(get_permalink()); ?>" title="<?php the_title_attribute(); ?>">
						<?php the_post_thumbnail('wide', ['class' => ''], ['title' => the_title_attribute('echo=0'), 'alt' => the_title_attribute('echo=0')]); ?>
					</a>
				</div>
			<?php endif; ?>
			<div class="blog_excerpt">
				<?php the_excerpt(); ?>
			</div>
			<?php wp_link_pages(['before' => '<div class="page-links">' . __('Pages:', 'the-artist'), 'after' => '</div>']); ?>
		</div><!-- .entry-content -->
	<?php endif; ?>
</article><!-- #post -->