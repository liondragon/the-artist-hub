<section id="latest_projects" class="archive_content" role="main">	
					<div class="table">
				<div class="table-row list_header">
					<div class="table-cell">Name</div>
					<div class="table-cell">Brand</div>
					<div class="table-cell">Purchase Date</div>
					<div class="table-cell">Warranty</div>
				</div>
<?php
$args = array( 
    'posts_per_page' => 200, 
    'post_type' => 'projects', 
    'post_status' => 'publish' 
);
$projects_query = new WP_Query( $args );
if( $projects_query->have_posts() ){
    while( $projects_query->have_posts() ){
        $projects_query->the_post();
?>
			<a class="table-row project" href="<?php the_permalink(); ?>">
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'equipment_name', true)?></div>
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'eq_brand', true)?></div>
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'equipment_purchase_date', true)?></div>
				<div class="table-cell"><?php echo get_post_meta($post->ID, 'equipment_warranty', true)?></div>
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