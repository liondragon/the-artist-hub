<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link rel="preload"
		href="<?php echo esc_url(get_template_directory_uri()); ?>/fonts/open-sans/open-sans-v17-latin-regular.woff2"
		as="font" type="font/woff2" crossorigin>
	<link rel="preload"
		href="<?php echo esc_url(get_template_directory_uri()); ?>/fonts/museo-sans/museo-sans-300.woff2" as="font"
		type="font/woff2" crossorigin>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<header role="banner" id="masthead" class="site-header">
		<div id="company_info" class="inner">
			<a id="logo" href="<?php echo esc_url(home_url('/')); ?>">
				<div class="logo_img">
					<svg id="tfa_logo" width="350" height="91.6" role="img" aria-labelledby="tfa_logo_title"
						xmlns="http://www.w3.org/2000/svg" viewBox="0 0 125.43 32.82">
						<title id="tfa_logo_title">The Flooring Artists</title>
						<defs>
						</defs>
						<g id="Logo">
							<path id="round_bg" data-name="round bg" class="logo-main"
								d="M16.41,0A16.41,16.41,0,1,1,0,16.41,16.41,16.41,0,0,1,16.41,0" />
							<polygon class="logo-white" points="10.24 25.26 2.63 25.26 15.6 7.56 10.24 25.26" />
							<polygon class="logo-white"
								points="19.9 25.26 12.9 25.26 14.84 16.95 18.02 16.95 19.9 25.26" />
							<polygon class="logo-white"
								points="17.92 15.8 14.88 15.8 16.25 7.56 16.55 7.56 17.92 15.8" />
							<polygon class="logo-white" points="30.2 25.26 22.58 25.26 17.22 7.56 30.2 25.26" />
						</g>
						<g id="Text">
							<path class="logo-text" d="M40.12,17.19v2.74h4v1.29h-4v3.57h-1.5V15.9h5.77v1.29Z" />
							<path class="logo-text" d="M45.67,24.79V15.9H47.1v8.89Z" />
							<path class="logo-text"
								d="M54.75,21.62a3.14,3.14,0,1,1-6.26,0,3.14,3.14,0,1,1,6.26,0Zm-4.76,0c0,1.37.62,2.24,1.63,2.24s1.64-.87,1.64-2.24-.62-2.24-1.64-2.24S50,20.24,50,21.62Z" />
							<path class="logo-text"
								d="M62.09,21.62a3.14,3.14,0,1,1-6.27,0,3.14,3.14,0,1,1,6.27,0Zm-4.77,0c0,1.37.63,2.24,1.64,2.24S60.6,23,60.6,21.62,60,19.38,59,19.38,57.32,20.24,57.32,21.62Z" />
							<path class="logo-text"
								d="M67.16,18.42v1.26a2.18,2.18,0,0,0-.5-.05c-1.14,0-1.76.73-1.76,2.05v3.11H63.47V18.44H64.8v.94h0a2,2,0,0,1,1.82-1A2.31,2.31,0,0,1,67.16,18.42Z" />
							<path class="logo-text" d="M68.19,17.26V15.9h1.42v1.36Zm0,7.53V18.44h1.42v6.35Z" />
							<path class="logo-text"
								d="M77,20.91v3.88H75.6V21c0-1.14-.49-1.59-1.29-1.59s-1.45.62-1.45,1.83v3.52H71.44V18.44h1.35v.83h0a2.26,2.26,0,0,1,1.9-1C76.15,18.29,77,19.17,77,20.91Z" />
							<path class="logo-text"
								d="M82.86,19.33h0v-.89h1.34v5.95a2.56,2.56,0,0,1-2.88,2.82c-1.7,0-2.66-.77-2.73-2h1.43c0,.62.52,1,1.32,1s1.48-.49,1.48-1.82v-.62h0a2.09,2.09,0,0,1-1.83,1c-1.62,0-2.64-1.25-2.64-3.23s1-3.22,2.66-3.22A2.08,2.08,0,0,1,82.86,19.33Zm-3,2.16c0,1.25.58,2.05,1.5,2.05s1.56-.8,1.56-2.05-.6-2-1.56-2S79.84,20.24,79.84,21.49Z" />
							<path class="logo-text"
								d="M90.47,22.38l-.82,2.41H88.07l3.24-8.89h2l3.24,8.89H95l-.82-2.41Zm3-2c-.4-1.19-.78-2.35-1.15-3.54h0c-.36,1.19-.73,2.35-1.14,3.54l-.24.7h2.79Z" />
							<path class="logo-text"
								d="M101.21,18.42v1.26a2.18,2.18,0,0,0-.5-.05c-1.14,0-1.76.73-1.76,2.05v3.11H97.52V18.44h1.33v.94h0a2,2,0,0,1,1.82-1A2.31,2.31,0,0,1,101.21,18.42Z" />
							<path class="logo-text"
								d="M105.37,23.64v1.12a3.22,3.22,0,0,1-.8.11c-1.33,0-1.92-.56-1.92-1.95V19.53h-1.06V18.44h1.06V16.86h1.42v1.58h1.24v1.09h-1.24v3.18c0,.71.24,1,.87,1A1.29,1.29,0,0,0,105.37,23.64Z" />
							<path class="logo-text" d="M106.62,17.26V15.9h1.43v1.36Zm0,7.53V18.44h1.43v6.35Z" />
							<path class="logo-text"
								d="M114.63,20.42h-1.37c0-.76-.45-1.13-1.21-1.13s-1,.29-1,.75.44.67,1.44.92c1.16.3,2.39.57,2.39,2.08,0,1.15-1,1.94-2.66,1.94s-2.73-.76-2.75-2.27h1.45c0,.77.48,1.21,1.33,1.21s1.15-.31,1.15-.79c0-.65-.5-.77-1.6-1.05s-2.18-.5-2.18-1.92c0-1.13,1-1.9,2.49-1.9S114.62,19,114.63,20.42Z" />
							<path class="logo-text"
								d="M119.22,23.64v1.12a3.22,3.22,0,0,1-.8.11c-1.33,0-1.92-.56-1.92-1.95V19.53h-1.06V18.44h1.06V16.86h1.42v1.58h1.24v1.09h-1.24v3.18c0,.71.24,1,.86,1A1.31,1.31,0,0,0,119.22,23.64Z" />
							<path class="logo-text"
								d="M125.23,20.42h-1.37c0-.76-.45-1.13-1.21-1.13s-1.05.29-1.05.75.44.67,1.43.92c1.17.3,2.4.57,2.4,2.08,0,1.15-1,1.94-2.66,1.94S120,24.22,120,22.71h1.45c0,.77.48,1.21,1.33,1.21s1.15-.31,1.15-.79c0-.65-.5-.77-1.6-1.05s-2.18-.5-2.18-1.92c0-1.13,1-1.9,2.49-1.9S125.22,19,125.23,20.42Z" />
							<path class="logo-main" d="M38.32,8v4.4h-1.1V8H35.54v-1H40V8Z" />
							<path class="logo-main"
								d="M41.65,9a1.32,1.32,0,0,1,1.05-.51c.86,0,1.41.53,1.41,1.57V12.4h-1V10.16c0-.58-.26-.84-.67-.84s-.75.34-.75,1v2h-1V7.05h1V9Z" />
							<path class="logo-main"
								d="M47.49,11.24h1.07a1.73,1.73,0,0,1-1.82,1.28,1.85,1.85,0,0,1-1.9-2.06,1.85,1.85,0,0,1,1.89-2A1.77,1.77,0,0,1,48.5,9.73a3.31,3.31,0,0,1,.12,1H45.88c0,.79.41,1.06.86,1.06A.69.69,0,0,0,47.49,11.24ZM45.9,10.05h1.66a.82.82,0,0,0-.83-.84C46.3,9.21,46,9.47,45.9,10.05Z" />
						</g>
					</svg>
				</div>
			</a><!-- #logo -->
		</div><!-- #company_info -->
	</header><!-- #masthead -->
	<section id="page_content" class="page_content has_sidebar">
		<header id="page_header" class="pageline">
			<div class="inner">
				<h1 class="page-title screen-reader-text"><span>Project</span></h1>
				<div class="tagline_separator">for</div>
				<div class="tagline">
					<?php
					$project_customer_name = esc_attr(get_post_meta(get_the_ID(), 'project_customer_name', true));
					$project_address = esc_attr(get_post_meta(get_the_ID(), 'project_address', true));
					if ($project_customer_name) {
						echo '<div>' . $project_customer_name . '</div>';
					}
					if ($project_address) {
						echo '<div>' . $project_address . '</div>';
					}
					if (empty($project_customer_name) && empty($project_address)) {
						echo the_title();
					}
					?>
				</div>
			</div>
		</header>
		<section id="project_cost" class="inner">
			<h4>Original Project Scope</h4>
			<table class="pricing">
				<tr>
					<td scope="row">Placeholder</td>
					<td>$0.00</td>
				</tr>
				<tr>
					<td scope="row">Placeholder</td>
					<td>$0.00</td>
				</tr>
				<tr class="total_line">
					<th scope="row">Total</th>
					<td>$0.00</td>
				</tr>
			</table>
			<h4>Approved Change Orders</h4>
			<table class="pricing">
				<tr>
					<td scope="row">Placeholder</td>
					<td>$0.00</td>
				</tr>
				<tr>
					<td scope="row">Placeholder</td>
					<td>$0.00</td>
				</tr>
			</table>
			<div></div>
			<table class="pricing">
				<tr class="total_line">
					<th scope="row">Current Project Total</th>
					<td>$0.00</td>
				</tr>
			</table>
		</section>
		<section id="project_status" class="inner">
			<div class="project_progress project_status_row">
				<h2 class="visually_hidden">Project Progress</h2>
				<ol role="list">
					<li class="timeline_step completed_step">
						<svg width="32" height="17" xmlns="http://www.w3.org/2000/svg" class="timeline_icon"
							aria-hidden="true" focusable="false">
							<path
								d="M9.94 8.007l3.983 3.983 1.06 1.06 1.06-1.06 6.93-6.93-2.12-2.12-6.93 6.93h2.12l-3.982-3.984-2.12 2.12z">
							</path>
						</svg>
						<span class="visually_hidden">Past step: </span>
						<span class="os-timeline-step__title">Project Started</span>
						<span class="timeline_date">November 6</span>
					</li>
					<li aria-current="true" class="timeline_step completed_step">
						<svg width="32" height="17" xmlns="http://www.w3.org/2000/svg" class="timeline_icon"
							aria-hidden="true" focusable="false">
							<path
								d="M19.803 5h1.572c.226 0 .443.244.603.404l1.772 1.85c.16.16.25.453.25.68v2.832c0 .015 0 .234-1 .234-.415-.854-1.116-1.287-2.124-1.287-.426 0-1.05.173-1.403.356-.14.072-.473.086-.473-.07V5.803c0-.442.36-.803.803-.803zM9.263 3h7.83c.5 0 .907.406.907.906v6.188c0 .5-.406.906-.906.906h-2.138c-.115 0-.214.206-.26.1-.397-.9-1.297-1.387-2.338-1.387-1.04 0-1.94.418-2.338 1.32-.046.104-.145-.033-.26-.033H8.672c-.37 0-.672-.3-.672-.672V4.265C8 3.57 8.57 3 9.264 3zm11.676 7.978c.828 0 1.5.67 1.5 1.5 0 .828-.672 1.5-1.5 1.5-.83 0-1.5-.672-1.5-1.5 0-.83.67-1.5 1.5-1.5zm-8.582-.07c.828 0 1.5.67 1.5 1.5 0 .828-.672 1.5-1.5 1.5s-1.5-.672-1.5-1.5c0-.83.672-1.5 1.5-1.5z">
							</path>
						</svg>
						<span class="visually_hidden">Current step: </span>
						<span class="os-timeline-step__title">Stain</span>
						<span class="timeline_date">November 8</span>
					</li>
					<li class="timeline_step">
						<svg width="32" height="17" xmlns="http://www.w3.org/2000/svg" class="timeline_icon"
							aria-hidden="true" focusable="false">
							<path
								d="M23.45 10.99c-.162-.307-.54-.42-.84-.257l-4.582 2.45-6.557-11.93H8.62c-.347 0-.62.283-.62.628 0 .346.273.628.62.628h2.118l4.825 8.783c-.037-.006-.074-.006-.112-.006-1.37 0-2.482 1.123-2.482 2.508s1.11 2.508 2.483 2.508c1.038 0 1.92-.64 2.293-1.543l5.445-2.92c.304-.164.422-.54.26-.847zm-8 3.874c-.59 0-1.06-.476-1.06-1.072 0-.596.47-1.072 1.06-1.072.59 0 1.063.476 1.063 1.072 0 .596-.472 1.072-1.062 1.072zm8.994-6.698l-5.848 3.288-2.718-4.93 5.847-3.287 2.72 4.93zm-4.288-5.482l-4.882 2.744-1.48-2.683L18.675 0l1.48 2.684z">
							</path>
						</svg>
						<span class="visually_hidden">Upcoming step: </span>
						<span class="os-timeline-step__title">First Coat</span>
					</li>
					<li class="timeline_step">
						<svg width="32" height="17" xmlns="http://www.w3.org/2000/svg" class="timeline_icon"
							aria-hidden="true" focusable="false">
							<path
								d="M15.623 5.014l-4.29 3.577c-.196.168-.327.362-.327.62v6.206c0 .322.335.584.656.584h2.004c.32 0 .584-.262.584-.584l-.033-3.115c0-.16.13-.29.29-.29h2.918c.16 0 .292.13.292.29l.033 3.116c0 .322.263.584.584.584h2.09c.322 0 .585-.262.585-.584V9.48c0-.257-.172-.626-.37-.792l-4.263-3.674c-.218-.184-.536-.184-.754 0zm7.17 2.374l-5.967-5.046C16.606 2.122 16.312 2 16 2c-.312 0-.606.123-.79.31L9.207 7.388c-.245.208-.276.576-.068.822.115.136.28.206.446.206.133 0 .266-.044.376-.137l5.69-4.847c.208-.155.49-.157.697-.002 1.286.962 5.693 4.85 5.693 4.85.246.206.614.177.822-.07.208-.246.177-.614-.068-.822z">
							</path>
						</svg>
						<span class="visually_hidden">Upcoming step: </span>
						<span class="os-timeline-step__title">Final Coat</span>
					</li>
				</ol>
			</div>
			<div class="project_status_message project_status_row">
				<h4 class="">We are getting ready to apply first coat of finish</h4>
				<p class="">
					Please don't walk on the floor until further notice.
				</p>
				<p class="">Current Time Estimate: <strong>&nbsp;5 - 6 pm</strong></p>
			</div>
		</section>
		<section id="page_body" class="page_body">
			<div class="inner">
				<?php while (have_posts()):
					the_post(); ?>
					<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
						<div class="entry-content">
							<?php the_content(); ?>
						</div><!-- .entry-content -->
					</article><!-- #post -->
				<?php endwhile; // end of the loop. ?>
			</div><!-- .inner -->
		</section><!-- #page_body -->
	</section>
	<footer id="footer" role="contentinfo" class="no-print">
		<div class="footer-bottom">
			<div class="inner" role="menu" aria-label="Legal Links and Information">
				<div class="copyright"><?php echo custom_copyright(); ?> <?php echo get_bloginfo('name'); ?></div>
			</div><!-- inner -->
		</div><!-- footer-bottom -->
	</footer><!-- #colophon -->
	<?php wp_footer(); ?>
</body>

</html>