<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="preload" href="<?php echo esc_url(get_template_directory_uri()); ?>/fonts/open-sans/open-sans-v17-latin-regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?php echo esc_url(get_template_directory_uri()); ?>/fonts/museo-sans/museo-sans-300.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="https://use.typekit.net/af/442215/000000000000000000010b5a/27/l?primer=7cdcb44be4a7db8877ffa5c0007b8dd865b3bbc383831fe2ea177f62257a9191&fvd=n4&v=3" as="font" type="font/woff2" crossorigin>
<?php wp_head(); ?>
<style>
#logo {
	margin: 0 auto;
}
.inner {
	max-width: 60em; font-family: 'museo-sans', sans-serif; font-size: 0.9rem;
}
.tagline {color: #333; margin-top: 1em; text-align: center;}
h3 {margin: 0.75rem 0 0.3125rem; font-size: 1rem; font-weight: bold;}
h4 {margin: 0.75rem 0 0.3125rem; font-size: 0.9rem; font-weight: bold;}
table {border-collapse: collapse; width: 100%;}
#footer .footer-bottom .inner { justify-content: center;}
</style>
<style media="print">
	.pageline { padding: 0;}
	.pageline .page-title {font-size: 2rem;}
</style>
</head><?php get_header(); ?>
<article id="page_content" class="page_content <?php if ( is_active_sidebar( 'page-widgets' ) ) {echo 'sidebar_active';}?>">
	<div id="page_header" class="pageline">
		<div class="inner">
			<h1 class="page-title screen-reader-text"><?php the_title(); ?></h1>
			<span><?php echo get_post_meta($wp_query->posts[0]->ID, 'page-tagline', true); ?> </span>
		</div>	
	</div>
	<div class="inner article-body-wrap">
		<div id="content" class="article-body">
		<?php while ( have_posts() ) : the_post(); ?>
			<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<div class="entry-content">
					<?php the_content(); ?>
				</div><!-- .entry-content -->
			</div><!-- #post -->
		<?php endwhile; // end of the loop. ?>
		</div><!-- .page-content -->
		<?php if ( is_active_sidebar( 'page-widgets' ) ) { ?>
		<aside id="sidebar" class="col-right" role="complementary">
			<div class="s-wrapper">
				<?php dynamic_sidebar( 'page-widgets' ); ?>
				<?php } ?>
			</div>
		</aside>
		</div><!-- .inner -->
</article><!-- #page_content -->
	<footer id="footer" role="contentinfo" class="no-print">
		<div class="footer-bottom">
			<div class="inner" role="menu" aria-label="Legal Links and Information">
				<div class="copyright"><?php echo custom_copyright(); ?> <?php echo get_bloginfo( 'name' ); ?></div>
			</div><!-- inner -->
		</div><!-- footer-bottom -->
	</footer><!-- #colophon -->
<?php wp_footer(); ?>
</body>
</html>