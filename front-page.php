<?php get_header(); ?>
<main id="page_content" class="page_content">
	<div class="inner container">
		<section id="latest_projects" class="page_section">	
			<h3 class="section-top">Latest Projects</h3>
			<div>
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
			<div>
				<p class="project-date"><?php echo get_post_meta($post->ID, 'project_start_date', true)?></p>
				<a class="project-item section-tab flexbox flex-even clickable" href="<?php the_permalink(); ?>">
					<div class=""><?php echo get_post_meta($post->ID, 'project_customer_name', true)?></div>
					<div class="currency"><?php echo get_post_meta($post->ID, 'project_total_amount', true)?></div>
				</a>
			</div>
<?php
    }
} else {
    // Error message: sorry, no posts here
}
wp_reset_postdata();
?>
			</div>
			<div class="section-footer">
				<div><a href="<?php echo get_post_type_archive_link('projects')?>" class="button" >View All Projects</a></div>
			<div>
		</section><!-- #archive_content -->
		<section id="summary" class="page_section">
			<h2 class="section-top">Company Name</h2>
			<div class="section-tab">
				<h3>Year-to-Date Earnings</h3>
				<p class="currency">$520,000.53</p>
			</div>
			<div id="insurance_tab" class="section-tab">
				<h3>Insurance Policy</h3>
				<div class="sub-tab flexbox flex-start stack">
					<div class="inline">Active: <span class="blue">Yes</span></div>
					<div class="inline">Expires: <span class="blue">02/02/2024</span></div>
					<div class="inline"><a href="" class="button empty">Upload</a></div>
				</div>
			</div>
			<div class="section-tab">
				<h3>State Registration</h3>
				<div class="sub-tab flexbox flex-start stack">
					<div class="inline">Active: <span class="blue">Yes</span></div>
					<div class="inline">Expires: <span class="blue">02/02/2024</span></div>
					<div class="inline"><a target="_blank" href="https://coloradosos.gov/biz/FileDocSearchCriteria.do?quitButtonDestination=BusinessFunctions" class="button empty">File Periodic Report</a></div>
					<div class="inline"><a href="" class="button empty">Upload Certificate</a></div>
				</div>
			</div>
		</section>
		<section id="handbook" class="page_section">
			<h2 class="section-top">Artist Group Operating Procedures</h2>
			<div class="blocks section-tab">
			<div class="blocks-item"><a href="https://hub.flooringartists.com/project-management/" class="button empty">
                <h4 class="blocks-item-title">Project Management</h4>
                <p class="blocks-item-description">Opertional Standards, Project Forecasting & Communication</p> 
			</a></div>
			<div class="blocks-item"><a href="https://hub.flooringartists.com/floor-installation/" class="button empty">
                <h4 class="blocks-item-title">Floor Installation</h4>
                <p class="blocks-item-description">Guidelines, Standards & Expectations</p> 
			</a></div>
			<div class="blocks-item"><a href="https://hub.flooringartists.com/sand-finish/" class="button empty">
                <h4 class="blocks-item-title">Sand and Finish</h4>
                <p class="blocks-item-description">Guidelines, Standards & Expectations</p> 
			</a></div>
			<div class="blocks-item"><a href="https://hub.flooringartists.com/equipment-organization/" class="button empty">
                <h4 class="blocks-item-title">Equipment & Organization</h4>
                <p class="blocks-item-description">Vehicle Set-Up, Security, Warehouse, & Inventory</p> 
			</a></div>
			<div class="blocks-item"><a href="https://hub.flooringartists.com/equipment-organization/" class="button empty">
                <h4 class="blocks-item-title">Sales</h4>
                <p class="blocks-item-description">Estimating, Measuring, Creating Work Orders</p> 
			</a></div>
			</div>
			<div id="fin_tab" class="section-tab">
				<h3>Financial & Accounting</h3>
				<div class="sub-tab flexbox flex-start stack">

				</div>
			</div>
				<div id="fin_tab" class="section-tab">
				<h3>Marketing</h3>
				<div class="sub-tab flexbox flex-start stack">
				</div>
			</div>
		</section>
		
		<section id="legal" class="page_section">
			<h2 class="section-top">Pricing & Agreements</h2>
			<div class="section-tab">

			</div>
		</section>
	</div><!--.inner -->
</main>
<?php get_footer(); ?>