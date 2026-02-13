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
</head>

<body <?php body_class('single-quotes'); ?>>
	<?php wp_body_open(); ?>
	<header role="banner" id="masthead" class="site-header">
		<div id="company_info" class="inner">
			<a id="logo" href="<?php echo esc_url(home_url('/')); ?>">
				<div class="logo_img">
					<svg id="tfa_logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 134.71 32.82">
						<defs>
							<style>
								.cls-1 {
									fill: #bd2927;
								}

								.cls-1,
								.cls-2,
								.cls-3,
								.cls-4 {
									stroke-width: 0px;
								}

								.cls-2 {
									fill: #343434;
								}

								.cls-3 {
									fill: #fff;
								}

								.cls-4 {
									fill: #bd2726;
								}
							</style>
						</defs>
						<g id="Text">
							<path class="cls-2"
								d="m40.09,17.13v3.05h4.39v1.07h-4.39v3.54h-1.25v-8.75h6.17v1.09h-4.92Z" />
							<path class="cls-2" d="m46.6,15.52h1.2v9.27h-1.2v-9.27Z" />
							<path class="cls-2"
								d="m49.47,21.48c0-1.97,1.46-3.37,3.45-3.37s3.44,1.4,3.44,3.37-1.45,3.39-3.44,3.39-3.45-1.41-3.45-3.39Zm5.67,0c0-1.41-.95-2.32-2.22-2.32s-2.24.91-2.24,2.32.96,2.34,2.24,2.34,2.22-.92,2.22-2.34Z" />
							<path class="cls-2"
								d="m57.41,21.48c0-1.97,1.46-3.37,3.45-3.37s3.44,1.4,3.44,3.37-1.45,3.39-3.44,3.39-3.45-1.41-3.45-3.39Zm5.67,0c0-1.41-.95-2.32-2.22-2.32s-2.24.91-2.24,2.32.96,2.34,2.24,2.34,2.22-.92,2.22-2.34Z" />
							<path class="cls-2"
								d="m69.52,18.11v1.16c-.1-.01-.19-.01-.27-.01-1.29,0-2.09.79-2.09,2.24v3.3h-1.2v-6.62h1.15v1.11c.42-.77,1.25-1.17,2.41-1.17Z" />
							<path class="cls-2"
								d="m70.79,16.12c0-.44.35-.79.81-.79s.81.34.81.76c0,.45-.34.8-.81.8s-.81-.34-.81-.77Zm.21,2.05h1.2v6.62h-1.2v-6.62Z" />
							<path class="cls-2"
								d="m80.77,20.98v3.81h-1.2v-3.67c0-1.3-.65-1.94-1.79-1.94-1.27,0-2.1.76-2.1,2.2v3.41h-1.2v-6.62h1.15v1c.49-.67,1.34-1.06,2.39-1.06,1.61,0,2.75.92,2.75,2.87Z" />
							<path class="cls-2"
								d="m89.37,18.17v5.72c0,2.34-1.19,3.4-3.44,3.4-1.21,0-2.44-.34-3.16-.99l.57-.92c.61.52,1.57.86,2.55.86,1.56,0,2.27-.73,2.27-2.22v-.52c-.57.69-1.44,1.02-2.39,1.02-1.91,0-3.36-1.3-3.36-3.21s1.45-3.2,3.36-3.2c.99,0,1.89.36,2.45,1.09v-1.02h1.14Zm-1.17,3.14c0-1.29-.95-2.15-2.27-2.15s-2.29.86-2.29,2.15.95,2.16,2.29,2.16,2.27-.89,2.27-2.16Z" />
							<path class="cls-2"
								d="m100.77,22.61h-4.65l-.96,2.19h-1.29l3.96-8.75h1.24l3.97,8.75h-1.31l-.96-2.19Zm-.44-1l-1.89-4.29-1.89,4.29h3.77Z" />
							<path class="cls-2"
								d="m107.73,18.11v1.16c-.1-.01-.19-.01-.27-.01-1.29,0-2.09.79-2.09,2.24v3.3h-1.2v-6.62h1.15v1.11c.42-.77,1.25-1.17,2.41-1.17Z" />
							<path class="cls-2"
								d="m113.21,24.41c-.36.31-.91.46-1.45.46-1.34,0-2.1-.74-2.1-2.07v-3.64h-1.12v-.99h1.12v-1.45h1.2v1.45h1.9v.99h-1.9v3.59c0,.71.38,1.11,1.04,1.11.35,0,.69-.11.94-.31l.38.86Z" />
							<path class="cls-2"
								d="m114.45,16.12c0-.44.35-.79.81-.79s.81.34.81.76c0,.45-.34.8-.81.8s-.81-.34-.81-.77Zm.21,2.05h1.2v6.62h-1.2v-6.62Z" />
							<path class="cls-2"
								d="m117.31,24.09l.5-.95c.56.4,1.46.69,2.32.69,1.11,0,1.57-.34,1.57-.9,0-1.49-4.19-.2-4.19-2.84,0-1.19,1.06-1.99,2.76-1.99.86,0,1.84.23,2.41.6l-.51.95c-.6-.39-1.26-.52-1.91-.52-1.05,0-1.56.39-1.56.91,0,1.56,4.2.29,4.2,2.86,0,1.2-1.1,1.96-2.86,1.96-1.1,0-2.19-.34-2.74-.77Z" />
							<path class="cls-2"
								d="m128.13,24.41c-.36.31-.91.46-1.45.46-1.34,0-2.1-.74-2.1-2.07v-3.64h-1.12v-.99h1.12v-1.45h1.2v1.45h1.9v.99h-1.9v3.59c0,.71.38,1.11,1.04,1.11.35,0,.69-.11.94-.31l.38.86Z" />
							<path class="cls-2"
								d="m128.75,24.09l.5-.95c.56.4,1.46.69,2.32.69,1.11,0,1.57-.34,1.57-.9,0-1.49-4.19-.2-4.19-2.84,0-1.19,1.06-1.99,2.76-1.99.86,0,1.84.23,2.41.6l-.51.95c-.6-.39-1.26-.52-1.91-.52-1.05,0-1.56.39-1.56.91,0,1.56,4.2.29,4.2,2.86,0,1.2-1.1,1.96-2.86,1.96-1.1,0-2.19-.34-2.74-.77Z" />
							<path class="cls-4" d="m37.3,7.8h-1.8v-.65h4.35v.65h-1.8v4.6h-.74v-4.6Z" />
							<path class="cls-4"
								d="m44.33,10.11v2.29h-.72v-2.21c0-.78-.39-1.16-1.07-1.16-.77,0-1.26.46-1.26,1.32v2.05h-.72v-5.57h.72v2.15c.3-.38.8-.6,1.4-.6.97,0,1.65.56,1.65,1.73Z" />
							<path class="cls-4"
								d="m49.26,10.65h-3.23c.09.7.65,1.16,1.44,1.16.47,0,.86-.16,1.15-.48l.4.47c-.36.42-.91.65-1.57.65-1.28,0-2.14-.85-2.14-2.03s.85-2.03,2-2.03,1.97.83,1.97,2.05c0,.06,0,.15-.02.22Zm-3.23-.52h2.55c-.08-.67-.58-1.14-1.28-1.14s-1.2.47-1.28,1.14Z" />
						</g>
						<circle id="BG" class="cls-1" cx="16.41" cy="16.41" r="16.41" />
						<g id="Logo">
							<path class="cls-3"
								d="m16.6,1.54l2.01,4.59v.04l-7.68,16.81s-.01.01-.03.03c-1.95.62-3.73,1.57-5.33,2.84h0L16.58,1.55" />
							<path class="cls-3"
								d="m13.27,22.41l6.36-13.91h.01l7.62,17.47h0c-1.64-1.34-3.46-2.31-5.46-2.96-2.76-.88-5.69-1.06-8.54-.6t0,0Z" />
						</g>
					</svg>
				</div>
			</a><!-- #logo -->
		</div><!-- #company_info -->
	</header><!-- #masthead -->
	<section id="page_content" class="page_content has_sidebar">
		<header id="page_header" class="pageline">
			<div class="inner">
				<h1 class="page-title screen-reader-text">
					<span><?php $estimate_type = esc_attr(get_post_meta(get_the_ID(), 'estimate_type', true));
					if ($estimate_type == 'virtual')
						echo 'Virtual Estimate';
					else
						echo 'Work Proposal'; ?></span>
				</h1>
				<div class="tagline_separator">for</div>
				<div class="tagline">
					<?php
					$customer_name = esc_attr(get_post_meta(get_the_ID(), 'customer_name', true));
					$customer_address = esc_attr(get_post_meta(get_the_ID(), 'customer_address', true));
					if ($customer_name) {
						echo '<div>' . $customer_name . '</div>';
					}
					if ($customer_address) {
						echo '<div>' . $customer_address . '</div>';
					}
					if (empty($customer_name) && empty($customer_address)) {
						echo the_title();
					}
					?>
				</div>
			</div>
		</header>
		<section id="page_body" class="page_body">
			<div class="inner">
				<?php while (have_posts()):
					the_post(); ?>
					<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content pricing">
								<?php the_content(); ?>
								<?php if (function_exists('tah_render_quote_sections')) {
									tah_render_quote_sections(get_the_ID());
								} ?>
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
				<div class="separator">|</div>
				<div class="terms"><a href="https://hub.flooringartists.com/terms-of-service/">Proposal Terms and
						Conditions</a>
				</div><!-- inner -->
			</div><!-- footer-bottom -->
	</footer><!-- #colophon -->
	<?php wp_footer(); ?>
</body>

</html>
