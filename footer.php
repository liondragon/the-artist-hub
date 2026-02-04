<footer id="footer" role="contentinfo" class="no-print">
	<div class="inner">
		<div class="copyright"><?php echo custom_copyright(); ?> <?php echo get_bloginfo('name'); ?></div>
		<nav class="terms" aria-label="Legal Menu">
			<?php
			wp_nav_menu([
				'theme_location' => 'footer-menu',
				'menu_class' => 'ul_reset',
				'container' => false,
				'fallback_cb' => false,
				'depth' => 1,
			]);
			?>
		</nav>
	</div><!-- inner -->
</footer><!-- #colophon -->
<?php wp_footer(); ?>
</body>

</html>