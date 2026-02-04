<?php
declare(strict_types=1);
?>
<section id="latest_projects" class="archive_content" role="main">
	<div class="table">
		<div class="table-row list_header">
			<div class="table-cell"><?php esc_html_e('Name', 'the-artist'); ?></div>
			<div class="table-cell"><?php esc_html_e('Brand', 'the-artist'); ?></div>
			<div class="table-cell"><?php esc_html_e('Purchase Date', 'the-artist'); ?></div>
			<div class="table-cell"><?php esc_html_e('Warranty', 'the-artist'); ?></div>
		</div>
		<?php
		$args = [
			'posts_per_page' => 200,
			'post_type' => 'equipment',
			'post_status' => 'publish'
		];
		$equipment_query = new WP_Query($args);
		if ($equipment_query->have_posts()) {
			while ($equipment_query->have_posts()) {
				$equipment_query->the_post();
				?>
				<a class="table-row project" href="<?php the_permalink(); ?>">
					<div class="table-cell"><?php echo esc_html(get_post_meta(get_the_ID(), 'equipment_name', true)); ?></div>
					<div class="table-cell"><?php echo esc_html(get_post_meta(get_the_ID(), 'eq_brand', true)); ?></div>
					<div class="table-cell">
						<?php echo esc_html(get_post_meta(get_the_ID(), 'equipment_purchase_date', true)); ?>
					</div>
					<div class="table-cell"><?php echo esc_html(get_post_meta(get_the_ID(), 'equipment_warranty', true)); ?>
					</div>
				</a>
				<?php
			}
		} else {
			echo '<div class="table-row"><div class="table-cell" colspan="4">' . esc_html__('No equipment found.', 'the-artist') . '</div></div>';
		}
		wp_reset_postdata();
		?>
	</div><!--.table -->
</section><!-- #archive_content -->