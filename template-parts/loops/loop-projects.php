<?php
declare(strict_types=1);
?>
<section id="latest_projects" class="archive_content" role="main">
	<div class="table">
		<div class="table-row list_header">
			<div class="table-cell"><?php esc_html_e('Start Date', 'the-artist'); ?></div>
			<div class="table-cell"><?php esc_html_e('Client Name', 'the-artist'); ?></div>
			<div class="table-cell"><?php esc_html_e('Address', 'the-artist'); ?></div>
			<div class="table-cell"><?php esc_html_e('Amount', 'the-artist'); ?></div>
		</div>
		<?php
		$args = [
			'posts_per_page' => 200,
			'post_type' => 'projects',
			'post_status' => 'publish'
		];
		$projects_query = new WP_Query($args);
		if ($projects_query->have_posts()) {
			while ($projects_query->have_posts()) {
				$projects_query->the_post();
				?>
				<a class="table-row project" href="<?php the_permalink(); ?>">
					<div class="table-cell"><?php echo esc_html(get_post_meta(get_the_ID(), 'project_start_date', true)); ?>
					</div>
					<div class="table-cell"><?php echo esc_html(get_post_meta(get_the_ID(), 'project_customer_name', true)); ?>
					</div>
					<div class="table-cell"><?php echo esc_html(get_post_meta(get_the_ID(), 'project_address', true)); ?></div>
					<div class="table-cell"><?php echo esc_html(get_post_meta(get_the_ID(), 'project_total_amount', true)); ?>
					</div>
				</a>
				<?php
			}
		} else {
			echo '<div class="table-row"><div class="table-cell" colspan="4">' . esc_html__('No projects found.', 'the-artist') . '</div></div>';
		}
		wp_reset_postdata();
		?>
	</div><!--.table -->
</section><!-- #archive_content -->