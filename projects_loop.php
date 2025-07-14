<section id="latest_projects" class="archive_content" role="main">	
					<div class="table">
				<div class="table-row list_header">
					<div class="table-cell">Start Date</div>
					<div class="table-cell">Client Name</div>
					<div class="table-cell">Address</div>
					<div class="table-cell">Amount</div>
				</div>
<?php
$args = array( 
    'posts_per_page' => 200, 
    'post_type' => 'equipment', 
    'post_status' => 'publish' 
);
$projects_query = new WP_Query( $args );
if( $projects_query->have_posts() ){
    while( $projects_query->have_posts() ){
        $projects_query->the_post();
?>
			<a class="table-row project" href="<?php the_permalink(); ?>">
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'project_start_date', true)?></div>
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'project_customer_name', true)?></div>
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'project_address', true)?></div>
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'project_total_amount', true)?></div>
			</a>
<?php
    }
} else {
    // Error message: sorry, no posts here
}
wp_reset_postdata();
?>
			</div><!--.table -->
		</section><!-- #archive_content -->