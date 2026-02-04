<?php
declare(strict_types=1);
function the_artist_widgets_init()
{
	register_sidebar(array(
		'name' => __('Page Widgets', 'the-artist'),
		'id' => 'page-widgets',
		'description' => __('Appears on all pages except the Front Page and custom teplates', 'the-artist'),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => '</aside>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	));

	register_sidebar(array(
		'name' => __('Blog Widgets', 'the-artist'),
		'id' => 'blog-widget-area',
		'description' => __('Blog widget area', 'the-artist'),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => '</aside>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	));
}
add_action('widgets_init', 'the_artist_widgets_init');
?>