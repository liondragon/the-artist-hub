<?php
/**
 * Template Name: ACH Payment Form
 *
 * @package WordPress
 */
 get_header(''); ?>
	<div id="motto_section">
		<div class="tagline_inner inner">
		<span>Submit Your Payment Information</span>
		</div>
	</div>
	<div id="main" role="main">
		<div class="inner">	
							<div class="cfwrap">
								<div id="maincontactform" class="contactform">
									<?php echo do_shortcode('[ach_payment_form]'); ?>
								</div>
							</div>
					
		</div>
	</div><!-- #main -->
</div><!-- #content .col-full .inner .clearfix -->
<?php get_footer(); ?>