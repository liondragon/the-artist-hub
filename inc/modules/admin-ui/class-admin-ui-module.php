<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared WordPress admin UI customizations.
 */
final class TAH_Admin_UI_Module
{
    /**
     * Prevent duplicate module boot.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * Boot module.
     */
    public static function boot()
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        if (!self::is_enabled()) {
            return;
        }

        self::$instance = new self();
        self::$instance->register_hooks();
    }

    /**
     * Capability gate (default: enabled).
     */
    public static function is_enabled(): bool
    {
        return (bool) apply_filters('tah_module_admin_ui_enabled', true);
    }

    private function register_hooks(): void
    {
        $this->register_menu_cleanup_hooks();
        $this->register_admin_bar_hooks();
        $this->register_admin_chrome_hooks();
        $this->register_screen_options_hooks();
        $this->register_profile_ui_hooks();
    }

    private function register_menu_cleanup_hooks(): void
    {
        add_action('admin_menu', [$this, 'remove_menu_items'], 999);
        add_action('admin_menu', [$this, 'remove_posts_menu']);
        add_action('admin_menu', [$this, 'remove_subscriber_menus'], 1000);
    }

    private function register_admin_bar_hooks(): void
    {
        add_action('wp_before_admin_bar_render', [$this, 'remove_admin_bar_links']);
        add_action('admin_bar_menu', [$this, 'clear_site_name_title'], 999);
        add_action('admin_bar_menu', [$this, 'replace_wordpress_howdy'], 25);
        add_action('admin_bar_menu', [$this, 'remove_wp_logo'], 999);
    }

    private function register_admin_chrome_hooks(): void
    {
        add_action('admin_head', [$this, 'remove_context_menu_help']);
        add_filter('update_right_now_text', '__return_false');
        add_action('wp_dashboard_setup', [$this, 'remove_dashboard_widgets']);
        add_action('admin_init', [$this, 'remove_admin_footer_text']);
        add_action('admin_footer', [$this, 'render_admin_sidebar_footer']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('after_setup_theme', [$this, 'disable_frontend_admin_bar']);
    }

    private function register_screen_options_hooks(): void
    {
        add_action('edit_form_after_editor', [$this, 'render_screen_options_footer'], 1000);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_post_screen_options_script']);
    }

    private function register_profile_ui_hooks(): void
    {
        add_action('admin_head-user-edit.php', [$this, 'remove_profile_website_row_css']);
        add_action('admin_head-profile.php', [$this, 'remove_profile_website_row_css']);
        add_action('admin_footer-profile.php', [$this, 'customize_profile_ui']);
    }

    public function remove_menu_items(): void
    {
        remove_menu_page('tools.php');
        remove_submenu_page('themes.php', 'themes.php');
        remove_submenu_page('options-general.php', 'akismet-key-config');
        remove_submenu_page('admin.php', 'wp_mailjet_options_campaigns_menu');
        $this->remove_customize_submenu();
    }

    public function remove_posts_menu(): void
    {
        remove_menu_page('edit.php');
    }

    public function remove_subscriber_menus(): void
    {
        $current_user = wp_get_current_user();
        $current_role = isset($current_user->roles[0]) ? (string) $current_user->roles[0] : 'no_role';

        if ($current_role !== 'subscriber') {
            return;
        }

        remove_menu_page('upload.php');
        remove_menu_page('tools.php');
        remove_menu_page('edit-comments.php');
        remove_menu_page('edit.php?post_type=my_other_custom_post_type_I_want_to_hide');
    }

    public function remove_admin_bar_links(): void
    {
        global $wp_admin_bar;
        if (!$wp_admin_bar instanceof WP_Admin_Bar) {
            return;
        }

        $wp_admin_bar->remove_menu('new-content');
        $wp_admin_bar->remove_menu('comments');
        $wp_admin_bar->remove_menu('about');
        $wp_admin_bar->remove_menu('wporg');
        $wp_admin_bar->remove_menu('documentation');
        $wp_admin_bar->remove_menu('support-forums');
        $wp_admin_bar->remove_menu('feedback');
        $wp_admin_bar->remove_menu('dashboard');
        $wp_admin_bar->remove_menu('themes');
        $wp_admin_bar->remove_menu('widgets');
        $wp_admin_bar->remove_menu('menus');
        $wp_admin_bar->remove_menu('customize');
        $wp_admin_bar->remove_node('search');
    }

    public function clear_site_name_title(WP_Admin_Bar $wp_admin_bar): void
    {
        $site_name_node = $wp_admin_bar->get_node('site-name');
        if (!$site_name_node) {
            return;
        }

        $site_name_node->title = 'Switch';
        $wp_admin_bar->add_node($site_name_node);
    }

    public function remove_context_menu_help(): void
    {
        $current_screen = get_current_screen();
        if ($current_screen instanceof WP_Screen) {
            $current_screen->remove_help_tabs();
        }
    }

    public function replace_wordpress_howdy(WP_Admin_Bar $wp_admin_bar): void
    {
        $my_account = $wp_admin_bar->get_node('my-account');
        if (!$my_account) {
            return;
        }

        $my_account->title = str_replace('Howdy,', '', (string) $my_account->title);
        $wp_admin_bar->add_node($my_account);
    }

    public function remove_wp_logo(WP_Admin_Bar $wp_admin_bar): void
    {
        $wp_admin_bar->remove_node('wp-logo');
    }

    public function remove_dashboard_widgets(): void
    {
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
    }

    public function remove_admin_footer_text(): void
    {
        add_filter('admin_footer_text', '__return_false', 11);
        add_filter('update_footer', '__return_false', 11);
    }

    public function render_admin_sidebar_footer(): void
    {
        $profile_url = get_edit_profile_url();
        $logout_url = wp_logout_url();
        $home_url = home_url();

        echo '<div id="tah-admin-sidebar-footer" class="tah-admin-sidebar-footer">';
        echo '<a href="' . esc_url($profile_url) . '" title="Profile" class="tah-footer-icon"><span class="dashicons dashicons-admin-users"></span></a>';
        echo '<a href="' . esc_url($logout_url) . '" title="Log Out" class="tah-footer-icon"><span class="dashicons dashicons-migrate"></span></a>';
        echo '<a href="' . esc_url($home_url) . '" title="Switch Site" class="tah-footer-icon"><span class="dashicons dashicons-admin-home"></span></a>';
        echo '</div>';
    }

    public function enqueue_admin_styles(): void
    {
        $css_path = get_template_directory() . '/assets/css/admin.css';
        $version = file_exists($css_path) ? (string) filemtime($css_path) : '1.0.0';
        wp_enqueue_style('theme-admin', get_template_directory_uri() . '/assets/css/admin.css', false, $version);
    }

    public function disable_frontend_admin_bar(): void
    {
        add_theme_support('admin-bar', ['callback' => '__return_false']);
    }

    /**
     * Render in-flow footer slot for Screen Options on post edit screens.
     *
     * @param WP_Post|mixed $post
     */
    public function render_screen_options_footer($post): void
    {
        if (!$post instanceof WP_Post) {
            return;
        }

        echo '<div id="tah-screen-options-footer" class="tah-screen-options-footer"></div>';
    }

    public function enqueue_post_screen_options_script($hook_suffix): void
    {
        if (!in_array((string) $hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->screen_options_relocation_script());
    }

    private function screen_options_relocation_script(): string
    {
        return <<<'JS'
jQuery(function ($) {
    var $screenMetaLinks = $('#screen-meta-links');
    var $screenMeta = $('#screen-meta');
    var $footer = $('#tah-screen-options-footer');
    var isQuoteEditor = $('body').hasClass('tah-quote-editor-enabled');

    if (!$screenMetaLinks.length) {
        return;
    }

    if (!isQuoteEditor) {
        var $globalFooter = $('#tah-global-screen-options-footer');
        if (!$globalFooter.length) {
            $globalFooter = $('<div id="tah-global-screen-options-footer" class="tah-screen-options-footer"></div>');
        }

        var $anchor = $('#poststuff');
        if (!$anchor.length) {
            $anchor = $('#wpbody-content');
        }

        if ($anchor.length) {
            $anchor.css('position', 'relative');
            $anchor.append($globalFooter);
            $globalFooter.css({
                position: 'absolute',
                left: '0',
                width: '100%',
                bottom: '0',
                right: 'auto',
                margin: '0',
                zIndex: '10001'
            });
            $footer = $globalFooter;
        }
    }

    if (!$footer.length) {
        return;
    }

    if (!isQuoteEditor && $footer.attr('id') === 'tah-global-screen-options-footer') {
        $footer.append($screenMetaLinks);
        if ($screenMeta.length) {
            $footer.append($screenMeta);
        }
        return;
    }

    $footer.append($screenMetaLinks);
    if ($screenMeta.length) {
        $footer.append($screenMeta);
    }
});
JS;
    }

    public function remove_profile_website_row_css(): void
    {
        echo '<style>tr.user-url-wrap{ display: none; }</style>';
    }

    public function customize_profile_ui(): void
    {
        echo '<script>';
        echo 'document.addEventListener("DOMContentLoaded",function(){';
        echo 'var headings=document.querySelectorAll("h2, h3");';
        echo 'headings.forEach(function(node){if(node.textContent&&node.textContent.trim()==="About Yourself"){node.textContent="User Password";}});';
        echo 'var aboutRow=document.querySelector("tr.user-description-wrap");';
        echo 'if(aboutRow&&aboutRow.parentNode){aboutRow.parentNode.removeChild(aboutRow);}';
        echo '});';
        echo '</script>';
    }

    private function remove_customize_submenu(): void
    {
        global $submenu;
        if (!isset($submenu['themes.php']) || !is_array($submenu['themes.php'])) {
            return;
        }

        foreach ($submenu['themes.php'] as $index => $menu_item) {
            $slug = isset($menu_item[2]) ? (string) $menu_item[2] : '';
            if ($slug !== '' && strpos($slug, 'customize.php') === 0) {
                remove_submenu_page('themes.php', $slug);
                unset($submenu['themes.php'][$index]);
            }
        }
    }
}
