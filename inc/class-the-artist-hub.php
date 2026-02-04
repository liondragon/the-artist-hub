<?php
declare(strict_types=1);
/**
 * The Artist Theme - Main Class
 *
 * Encapsulates theme setup, asset enqueuing, and core functionality.
 *
 * @package The_Artist
 * @since 3.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class The_Artist_Hub
{
    /**
     * Theme version for cache busting
     */
    private $version = '3.1';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register all hooks
     */
    private function __construct()
    {
        // Theme setup
        add_action('after_setup_theme', [$this, 'setup']);
        add_action('template_redirect', [$this, 'set_content_width']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_head', [$this, 'preload_css'], -1000);
        add_action('wp_head', [$this, 'add_favicon']);
        add_filter('script_loader_tag', [$this, 'defer_scripts'], 10, 3);

        // Image handling
        add_filter('intermediate_image_sizes_advanced', [$this, 'filter_image_sizes']);

        // Rewrite rules
        add_filter('flush_rewrite_rules_hard', '__return_false');
    }

    /**
     * Theme setup - nav menus, image sizes, theme supports
     */
    public function setup()
    {
        // Load shared content styles + editor-specific styles
        // Load shared content styles + editor-specific styles
        $styles = [
            'assets/css/variables.css',
            'assets/css/_content.css'
        ];

        $versioned_styles = [];
        foreach ($styles as $style) {
            $path = get_stylesheet_directory() . '/' . $style;
            if (file_exists($path)) {
                $versioned_styles[] = get_stylesheet_directory_uri() . '/' . $style . '?ver=' . filemtime($path);
            } else {
                $versioned_styles[] = $style;
            }
        }

        add_editor_style($versioned_styles);

        // Navigation menus
        register_nav_menu('primary', __('Primary Menu', 'the-artist'));
        register_nav_menu('footer-menu', __('Footer Menu', 'the-artist'));
        register_nav_menu('res-menu', __('Resources Menu', 'the-artist'));

        // Thumbnails and image sizes
        add_theme_support('post-thumbnails');
        set_post_thumbnail_size(190, 190);
        add_image_size('large', 300, 300);
        add_image_size('medium', 150, 150);
        add_image_size('thumbnail', 65, 65);
        add_image_size('wide', 440, 270, true);

        // Theme supports
        add_theme_support('title-tag');
        add_theme_support('html5', [
            'comment-list',
            'comment-form',
            'search-form',
            'gallery',
            'caption',
            'style',
            'script',
            'navigation-widgets'
        ]);
    }

    /**
     * Set content width based on template
     */
    public function set_content_width()
    {
        global $content_width;

        if (!isset($content_width)) {
            $content_width = 625;
        }

        if (is_page_template('page-templates/full-width.php') || is_attachment() || !is_active_sidebar('sidebar-1')) {
            $content_width = 960;
        }
    }

    /**
     * Enqueue frontend CSS and JS
     */
    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        // Load the main style.css (now merged with main.css)
        $css_path = get_stylesheet_directory_uri() . '/style.css';
        $css_ver = filemtime(get_stylesheet_directory() . '/style.css');

        wp_enqueue_style('the-artist-style', $css_path, [], $css_ver);
        wp_deregister_script('wp-embed');
        wp_enqueue_script('functions', get_template_directory_uri() . '/assets/js/functions.js', [], null, true);

        if (is_singular() && comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
    }

    /**
     * Preload main CSS for performance
     */
    public function preload_css()
    {
        // Load the main style.css (now merged with main.css)
        $css_path = get_stylesheet_directory_uri() . '/style.css';
        $css_ver = filemtime(get_stylesheet_directory() . '/style.css');
        $css_link = $css_path . '?ver=' . $css_ver;

        echo '<link rel="preload" href="' . esc_url($css_link) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . esc_url($css_link) . '"></noscript>' . "\n";
    }

    /**
     * Add favicon to head
     */
    public function add_favicon()
    {
        $favicon_url = get_stylesheet_directory_uri() . '/assets/favicon.ico';
        ?>
        <!-- Custom Favicons -->
        <link rel="shortcut icon" href="<?php echo esc_url($favicon_url); ?>" />
        <link rel="apple-touch-icon" href="<?php echo esc_url($favicon_url); ?>">
        <?php
    }

    /**
     * Defer/async scripts for performance
     */
    public function defer_scripts($tag, $handle, $src)
    {
        $defer = ['slider', 'functions'];
        $async = [];

        if (in_array($handle, $async, true)) {
            return '<script src="' . esc_url($src) . '" async type="text/javascript"></script>' . "\n";
        }

        if (in_array($handle, $defer, true)) {
            return '<script src="' . esc_url($src) . '" defer type="text/javascript"></script>' . "\n";
        }

        return $tag;
    }

    /**
     * Filter default image sizes (currently returns all sizes unchanged)
     */
    public function filter_image_sizes($sizes)
    {
        // Uncomment to remove specific sizes:
        // unset($sizes['medium']);
        // unset($sizes['large']);
        // unset($sizes['medium_large']);
        // unset($sizes['1536x1536']);
        // unset($sizes['2048x2048']);
        return $sizes;
    }
}
