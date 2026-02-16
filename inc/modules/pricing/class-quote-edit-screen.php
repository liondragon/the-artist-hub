<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom quote edit screen scaffold.
 */
final class TAH_Quote_Edit_Screen
{
    const POST_TYPE = 'quotes';
    const QUOTE_OPTIONS_METABOX_ID = 'tah_quote_options';
    const ADMIN_NOTES_METABOX_ID = 'tah_quote_admin_notes';
    const DUPLICATE_ACTION = 'tah_duplicate_quote';
    const DUPLICATE_NONCE_ACTION = 'tah_duplicate_quote';
    const NOTES_NONCE_ACTION = 'tah_quote_admin_notes_save';
    const NOTES_NONCE_NAME = '_tah_quote_admin_notes_nonce';
    const NOTES_META_KEY = '_tah_quote_admin_notes';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'configure_meta_boxes'], 100, 2);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'register_quote_options_metabox'], 100);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'register_admin_notes_metabox'], 100);
        add_action('edit_form_after_title', [$this, 'render_editor_shell']);
        add_action('edit_form_after_editor', [$this, 'render_screen_options_footer']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('admin_body_class', [$this, 'add_body_class']);
        add_action('admin_post_' . self::DUPLICATE_ACTION, [$this, 'handle_duplicate_quote']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_admin_notes'], 40, 3);
    }

    /**
     * Remove default editor chrome and non-essential boxes for the custom layout.
     */
    public function configure_meta_boxes($post_type = '', $post = null)
    {
        if ($post_type !== '' && $post_type !== self::POST_TYPE) {
            return;
        }

        remove_meta_box('slugdiv', self::POST_TYPE, 'normal');
        // remove_meta_box('postdivrich', self::POST_TYPE, 'normal');
        remove_meta_box('postexcerpt', self::POST_TYPE, 'normal');
        remove_meta_box('postcustom', self::POST_TYPE, 'normal');
        remove_meta_box('commentstatusdiv', self::POST_TYPE, 'normal');
        remove_meta_box('commentsdiv', self::POST_TYPE, 'normal');
        remove_meta_box('trackbacksdiv', self::POST_TYPE, 'normal');
        remove_meta_box('authordiv', self::POST_TYPE, 'normal');
        remove_meta_box('revisionsdiv', self::POST_TYPE, 'normal');
        remove_meta_box('formatdiv', self::POST_TYPE, 'side');
        remove_meta_box('postimagediv', self::POST_TYPE, 'side');
    }

    public function register_quote_options_metabox($post = null): void
    {
        add_meta_box(
            self::QUOTE_OPTIONS_METABOX_ID,
            __('Quote Options', 'the-artist'),
            [$this, 'render_quote_options_metabox'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    public function register_admin_notes_metabox($post = null): void
    {
        add_meta_box(
            self::ADMIN_NOTES_METABOX_ID,
            __('Admin Notes', 'the-artist'),
            [$this, 'render_admin_notes_metabox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render top-level CRM shell; postboxes are moved into slots by JS.
     *
     * @param WP_Post $post
     */
    public function render_editor_shell($post)
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        $customer_name = (string) get_post_meta((int) $post->ID, 'customer_name', true);
        $customer_address = (string) get_post_meta((int) $post->ID, 'customer_address', true);
        $trade_name = $this->get_trade_name((int) $post->ID);
        $quote_format = (string) get_post_meta((int) $post->ID, '_tah_quote_format', true);
        $quote_format = $quote_format !== '' ? $quote_format : 'standard';
        $status = get_post_status_object((string) $post->post_status);
        $status_label = $status ? (string) $status->label : (string) $post->post_status;
        $view_tracking_summary = $this->get_view_tracking_summary((int) $post->ID);
        echo '<div id="tah-quote-editor">';

        echo '<div class="tah-quote-editor-header tah-card">';
        echo '<div class="tah-quote-editor-header-main">';
        echo '<div class="tah-quote-editor-header-row">';
        echo '<strong class="tah-quote-editor-customer-name">' . esc_html($customer_name !== '' ? $customer_name : __('Quote Information', 'the-artist')) . '</strong>';
        if ($customer_address !== '') {
            echo '<span class="tah-quote-editor-customer-address">' . esc_html($customer_address) . '</span>';
        }
        echo '</div>';
        echo '<div class="tah-quote-editor-header-row">';
        echo '<span class="tah-badge tah-badge--accent">' . esc_html(sprintf(__('Format: %s', 'the-artist'), ucfirst($quote_format))) . '</span>';
        echo '<span class="tah-badge tah-badge--neutral">' . esc_html(sprintf(__('Status: %s', 'the-artist'), $status_label)) . '</span>';
        echo '<span class="tah-badge tah-badge--neutral">' . esc_html(sprintf(__('Trade: %s', 'the-artist'), $trade_name)) . '</span>';
        echo '</div>';
        if ($view_tracking_summary !== '') {
            echo '<div class="tah-quote-editor-header-row">';
            echo '<span class="tah-quote-editor-view-summary">' . esc_html($view_tracking_summary) . '</span>';
            echo '</div>';
        }
        echo '<div id="tah-quote-editor-header-panels" class="tah-quote-editor-panels tah-quote-editor-header-panels"></div>';
        echo '</div>';

        echo '<div class="tah-quote-editor-header-actions">';
        echo '<div class="tah-split-button" id="tah-quote-actions">';
        echo '<button type="button" class="button button-primary" id="tah-action-primary">' . esc_html__('Publish', 'the-artist') . '</button>';
        echo '<button type="button" class="button button-primary tah-split-toggle" id="tah-action-toggle" aria-haspopup="true" aria-expanded="false">';
        echo '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
        echo '</button>';
        echo '<div class="tah-split-menu" id="tah-action-menu" style="display:none;">';

        $duplicate_url = $this->build_duplicate_url((int) $post->ID);
        echo '<a class="tah-split-item" href="' . esc_url($duplicate_url) . '">' . esc_html__('Duplicate', 'the-artist') . '</a>';
        echo '<button type="button" class="tah-split-item" data-action="save_draft">' . esc_html__('Save Draft', 'the-artist') . '</button>';
        echo '<button type="button" class="tah-split-item tah-split-item-danger" data-action="delete">' . esc_html__('Move to Trash', 'the-artist') . '</button>';
        echo '<button type="button" class="tah-split-item" data-action="email" disabled>' . esc_html__('Email (Soon)', 'the-artist') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<section class="tah-quote-editor-main">';
        echo '<div id="tah-quote-editor-main-panels" class="tah-quote-editor-panels"></div>';
        echo '</section>';
        echo '</div>'; // #tah-quote-editor
    }

    /**
     * Render an in-flow footer slot for WP Screen Options.
     *
     * @param WP_Post $post
     */
    public function render_screen_options_footer($post): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        echo '<div id="tah-screen-options-footer" class="tah-screen-options-footer"></div>';
    }

    /**
     * Add a body class to target quote editor chrome overrides.
     */
    public function add_body_class($classes)
    {
        if ($this->is_quote_edit_screen()) {
            return trim($classes . ' tah-quote-editor-enabled');
        }

        return $classes;
    }

    /**
     * Load quote-edit-screen-specific styles and metabox relocation script.
     */
    public function enqueue_assets($hook_suffix)
    {
        if (!$this->is_quote_edit_screen($hook_suffix)) {
            return;
        }

        $css_path = get_template_directory() . '/assets/css/quote-editor.css';
        $css_version = file_exists($css_path) ? (string) filemtime($css_path) : '1.0.0';

        wp_enqueue_style(
            'tah-quote-editor',
            get_template_directory_uri() . '/assets/css/quote-editor.css',
            ['theme-admin'],
            $css_version
        );

        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-resizable');
        wp_add_inline_script('jquery', $this->relocation_script());
    }

    /**
     * @return string
     */
    private function relocation_script()
    {
        return <<<'JS'
jQuery(function ($) {
    var STORAGE_PREFIX = 'tah_quote_editor_';

    function spanClassFor(value) {
        var v = parseInt(String(value || '12'), 10) || 12;
        if (v === 4) return 'tah-span-4';
        if (v === 6) return 'tah-span-6';
        if (v === 8) return 'tah-span-8';
        return 'tah-span-12';
    }

    function applyPanelSpan($panel, span) {
        $panel.removeClass('tah-span-12 tah-span-8 tah-span-6 tah-span-4').addClass(spanClassFor(span));
    }

    function closestSpanFromWidth(widthPx, containerWidthPx) {
        var container = parseFloat(containerWidthPx || 0);
        var width = parseFloat(widthPx || 0);
        if (!Number.isFinite(container) || container <= 0 || !Number.isFinite(width) || width <= 0) {
            return 12;
        }

        if (width >= (container - 4)) {
            return 12;
        }

        var candidates = [4, 6, 8, 12];
        var best = 12;
        var bestDelta = Infinity;
        candidates.forEach(function (span) {
            var spanWidth = container * (span / 12);
            var delta = Math.abs(width - spanWidth);
            if (delta < bestDelta) {
                bestDelta = delta;
                best = span;
            }
        });
        return best;
    }

    function applySavedPanelSpan($panel) {
        var id = String($panel.attr('id') || '');
        if (!id) {
            return;
        }

        var key = STORAGE_PREFIX + 'panel_span_' + id;
        var saved = window.localStorage ? window.localStorage.getItem(key) : null;
        applyPanelSpan($panel, saved ? saved : 12);
    }

    function initPanelResizing($panel, helpers) {
        if (!$panel || !$panel.length || !$.fn.resizable) {
            return;
        }

        if ($panel.data('tahResizableAttached')) {
            return;
        }
        $panel.data('tahResizableAttached', true);

        $panel.resizable({
            handles: 'e',
            minWidth: 220,
            start: function () {
                $panel.addClass('tah-is-resizing');
                var $container = $panel.closest('.tah-quote-editor-panels');
                var containerWidth = $container.length ? $container.width() : 0;
                if (containerWidth > 0) {
                    $panel.resizable('option', 'maxWidth', containerWidth);
                    $panel.resizable('option', 'containment', $container);
                }
            },
            stop: function (event, ui) {
                $panel.removeClass('tah-is-resizing');

                var id = String($panel.attr('id') || '');
                var $container = $panel.closest('.tah-quote-editor-panels');
                var containerWidth = $container.length ? $container.width() : 0;
                var nextSpan = closestSpanFromWidth(ui.size.width, containerWidth);
                if (id === 'tah_quote_pricing' && containerWidth > 0 && ui.size.width >= (containerWidth - 8)) {
                    nextSpan = 12;
                }

                // Avoid fighting the CSS grid; snap by span classes, then clear inline width.
                applyPanelSpan($panel, nextSpan);
                $panel.css({ width: '', height: '' });

                if (id && window.localStorage) {
                    window.localStorage.setItem(STORAGE_PREFIX + 'panel_span_' + id, String(nextSpan));
                }
            }
        });
    }

    // Prep main editor (postdivrich) to look like a card
    var $editor = $('#postdivrich');
    if ($editor.length) {
        $editor.addClass('postbox tah-card');
        if (!$editor.find('.postbox-header, .hndle').length) {
             $editor.prepend('<div class="postbox-header"><h2 class="hndle ui-sortable-handle">Note to a Customer</h2></div>');
        }
        // Wrap editor content in a body container for consistent padding if needed, 
        // strictly speaking WP editor handles its own padding, but let's ensure structure.
        if (!$editor.find('.tah-card-body').length) {
             $editor.children(':not(.postbox-header)').wrapAll('<div class="tah-card-body"></div>');
        }
    }

    var map = [
        { id: 'tfa_metabox', target: '#tah-quote-editor-header-panels' },
        { id: 'tah_quote_options', target: '#tah-quote-editor-header-panels' },
        { id: 'tah_trade_single_select', target: '#tah-quote-editor-header-panels' },
        { id: 'tah_quote_pricing', target: '#tah-quote-editor-main-panels' },
        { id: 'tah_quote_sections', target: '#tah-quote-editor-main-panels' },
        { id: 'tah_quote_admin_notes', target: '#tah-quote-editor-main-panels' },
        { id: 'submitdiv', target: '#tah-quote-editor-main-panels' },
        { id: 'postdivrich', target: '#tah-quote-editor-main-panels' }
    ];

    map.forEach(function (entry) {
        var $panel = $('#' + entry.id);
        var $target = $(entry.target);

        if (!$panel.length || !$target.length) {
            return;
        }

        $panel.addClass('tah-quote-editor-panel');
        $target.append($panel);
        applySavedPanelSpan($panel);
    });

    var $sortAreas = $('#tah-quote-editor-header-panels, #tah-quote-editor-main-panels');
    $sortAreas.sortable({
        connectWith: '.tah-quote-editor-panels',
        items: '> .postbox',
        handle: '.hndle',
        placeholder: 'tah-editor-panel-placeholder',
        forcePlaceholderSize: true,
        tolerance: 'pointer'
    });

    // Bind panel resizing once metaboxes are in place.
    map.forEach(function (entry) {
        var $panel = $('#' + entry.id);
        if ($panel.length) {
            initPanelResizing($panel, null);
        }
    });

    function getCurrentPostStatus() {
        var $original = $('#original_post_status');
        if ($original.length) {
            return String($original.val() || '');
        }
        var $status = $('#post_status');
        return $status.length ? String($status.val() || '') : '';
    }

    function isAlreadyPublished(status) {
        return status === 'publish' || status === 'private' || status === 'future';
    }

    function setPrimaryLabel() {
        var status = getCurrentPostStatus();
        var $btn = $('#tah-action-primary');
        if (!$btn.length) {
            return;
        }
        $btn.text(isAlreadyPublished(status) ? 'Update' : 'Publish');
    }

    function triggerPrimaryAction() {
        var status = getCurrentPostStatus();
        if (!isAlreadyPublished(status)) {
            var $status = $('#post_status');
            if ($status.length) {
                $status.val('publish');
            }
        }
        $('#publish').trigger('click');
    }

    function triggerSaveDraft() {
        var $saveDraft = $('#save-post');
        if ($saveDraft.length) {
            $saveDraft.trigger('click');
            return;
        }

        window.alert('Save Draft is not available in this state.');
    }

    setPrimaryLabel();

    $('#tah-action-primary').on('click', function () {
        triggerPrimaryAction();
    });

    $('#tah-action-toggle').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var $menu = $('#tah-action-menu');
        var isOpen = $menu.is(':visible');
        $menu.toggle(!isOpen);
        $('#tah-action-toggle').attr('aria-expanded', (!isOpen).toString());
    });

    $('#tah-action-menu').on('click', function (event) {
        event.stopPropagation();
    });

    $(document).on('click', function () {
        $('#tah-action-menu').hide();
        $('#tah-action-toggle').attr('aria-expanded', 'false');
    });

    $(document).on('click', '#tah-action-menu [data-action]', function (event) {
        event.preventDefault();
        var action = String($(this).data('action') || '');
        $('#tah-action-menu').hide();
        $('#tah-action-toggle').attr('aria-expanded', 'false');

        if (action === 'save_draft') {
            triggerSaveDraft();
            return;
        }
        if (action === 'delete') {
            if (!window.confirm('Move this quote to the trash?')) {
                return;
            }

            var $deleteLink = $('#submitdiv a.submitdelete').first();
            if ($deleteLink.length) {
                $deleteLink[0].click();
                return;
            }

            window.alert('Delete is not available until the quote has been saved at least once.');
            return;
        }
        if (action === 'email') {
            window.alert('Email is not implemented yet.');
            return;
        }
    });

    $(document).on('postbox-toggled', function (event, postbox) {
        if (!postbox) {
            return;
        }

        var $postbox = $(postbox);
        $postbox.css({ height: '' });
    });

    // Move WP Screen Options to the bottom of the quote editor (in-flow, not floating).
    var $screenMetaLinks = $('#screen-meta-links');
    var $screenMeta = $('#screen-meta');
    var $footer = $('#tah-screen-options-footer');
    if ($footer.length && $screenMetaLinks.length) {
        $footer.append($screenMetaLinks);
        if ($screenMeta.length) {
            $footer.append($screenMeta);
        }
    }
});
JS;
    }

    /**
     * @param WP_Post $post
     */
    public function render_quote_options_metabox($post): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        $quote_id = (int) $post->ID;
        $trade_term_id = $this->get_active_trade_term_id($quote_id);
        $trade_terms = get_terms([
            'taxonomy' => 'trade',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        $estimate_type = (string) get_post_meta($quote_id, 'estimate_type', true);
        $quote_format = (string) get_post_meta($quote_id, '_tah_quote_format', true);
        $quote_format = $quote_format === 'insurance' ? 'insurance' : 'standard';

        wp_nonce_field('the_artist_save_quote', '_tfanonce');
        wp_nonce_field('tah_quote_pricing_save', '_tah_quote_pricing_nonce');
        wp_nonce_field('tah_quote_sections_save', '_tah_quote_sections_nonce');

        echo '<div class="tah-quote-options-fields">';
        echo '<p class="tah-quote-options-field">';
        echo '<label for="tah-trade-term-id"><strong>' . esc_html__('Trade', 'the-artist') . '</strong></label>';
        echo '<select id="tah-trade-term-id" name="tah_trade_term_id" class="widefat">';
        echo '<option value="0" ' . selected(0, $trade_term_id, false) . '>' . esc_html__('None', 'the-artist') . '</option>';

        if (is_array($trade_terms)) {
            foreach ($trade_terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                echo '<option value="' . esc_attr((string) $term->term_id) . '" ' . selected((int) $term->term_id, $trade_term_id, false) . '>';
                echo esc_html((string) $term->name);
                echo '</option>';
            }
        }

        echo '</select>';
        echo '</p>';

        echo '<p class="tah-quote-options-field">';
        echo '<label for="estimate_type"><strong>' . esc_html__('Estimate Type', 'the-artist') . '</strong></label>';
        echo '<select id="estimate_type" name="estimate_type" class="widefat">';
        echo '<option value="in_house" ' . selected($estimate_type, 'in_house', false) . '>' . esc_html__('On-Site Quote', 'the-artist') . '</option>';
        echo '<option value="virtual" ' . selected($estimate_type, 'virtual', false) . '>' . esc_html__('Virtual Estimate', 'the-artist') . '</option>';
        echo '</select>';
        echo '</p>';

        echo '<p class="tah-quote-options-field">';
        echo '<label for="tah-quote-format"><strong>' . esc_html__('Quote Format', 'the-artist') . '</strong></label>';
        echo '<select id="tah-quote-format" name="tah_quote_format" class="widefat">';
        echo '<option value="standard" ' . selected($quote_format, 'standard', false) . '>' . esc_html__('Standard', 'the-artist') . '</option>';
        echo '<option value="insurance" ' . selected($quote_format, 'insurance', false) . '>' . esc_html__('Insurance', 'the-artist') . '</option>';
        echo '</select>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * @param WP_Post $post
     */
    public function render_admin_notes_metabox($post): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        $admin_notes = (string) get_post_meta((int) $post->ID, self::NOTES_META_KEY, true);

        wp_nonce_field(self::NOTES_NONCE_ACTION, self::NOTES_NONCE_NAME);
        echo '<p class="description">' . esc_html__('Internal only. Never shown on customer quote pages.', 'the-artist') . '</p>';
        echo '<textarea name="' . esc_attr(self::NOTES_META_KEY) . '" class="widefat" rows="6" placeholder="' . esc_attr__('Add private notes for your team...', 'the-artist') . '">' . esc_textarea($admin_notes) . '</textarea>';
    }

    /**
     * @param WP_Post $post
     */

    /**
     * @return bool
     */
    private function is_quote_edit_screen($hook_suffix = '')
    {
        if ($hook_suffix !== '' && !in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return false;
        }

        $screen = get_current_screen();

        return $screen instanceof WP_Screen
            && $screen->base === 'post'
            && $screen->post_type === self::POST_TYPE;
    }

    /**
     * @return string
     */
    private function get_trade_name($post_id)
    {
        $terms = wp_get_post_terms($post_id, 'trade');
        if (is_wp_error($terms) || empty($terms)) {
            return (string) __('Not set', 'the-artist');
        }

        $first = reset($terms);

        return $first instanceof WP_Term
            ? (string) $first->name
            : (string) __('Not set', 'the-artist');
    }

    private function get_active_trade_term_id(int $post_id): int
    {
        $terms = wp_get_post_terms($post_id, 'trade');
        if (is_wp_error($terms) || empty($terms)) {
            return 0;
        }

        usort($terms, function ($a, $b) {
            return (int) $a->term_id <=> (int) $b->term_id;
        });

        $active_term = $terms[0] ?? null;
        if (!$active_term instanceof WP_Term) {
            return 0;
        }

        return (int) $active_term->term_id;
    }

    private function get_view_tracking_summary(int $post_id): string
    {
        $view_count = (int) get_post_meta($post_id, '_tah_quote_view_count', true);
        if ($view_count <= 0) {
            return '';
        }

        $summary = sprintf(
            /* translators: %d is the quote view count. */
            _n('Viewed %d time', 'Viewed %d times', $view_count, 'the-artist'),
            $view_count
        );

        $last_viewed = (string) get_post_meta($post_id, '_tah_quote_last_viewed_at', true);
        if ($last_viewed === '') {
            return $summary;
        }

        $timestamp = strtotime($last_viewed);
        if ($timestamp === false) {
            return $summary;
        }

        $summary .= ' • ' . sprintf(
            /* translators: %s is localized datetime string. */
            __('Last viewed %s', 'the-artist'),
            wp_date('M j \a\t g:i A', $timestamp)
        );

        return $summary;
    }

    /**
     * Duplicate quote post + pricing data and redirect to the new draft.
     */
    public function handle_duplicate_quote()
    {
        $source_quote_id = isset($_GET['quote_id']) ? (int) wp_unslash((string) $_GET['quote_id']) : 0;
        if ($source_quote_id <= 0) {
            wp_die(esc_html__('Invalid quote ID.', 'the-artist'));
        }

        check_admin_referer(self::DUPLICATE_NONCE_ACTION . '_' . $source_quote_id);

        $source_post = get_post($source_quote_id);
        if (!$source_post instanceof WP_Post || $source_post->post_type !== self::POST_TYPE) {
            wp_die(esc_html__('Quote not found.', 'the-artist'));
        }

        if (!current_user_can('edit_post', $source_quote_id)) {
            wp_die(esc_html__('You are not allowed to duplicate this quote.', 'the-artist'));
        }

        $new_quote_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'draft',
            'post_title' => (string) $source_post->post_title,
            'post_content' => (string) $source_post->post_content,
            'post_excerpt' => (string) $source_post->post_excerpt,
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($new_quote_id) || (int) $new_quote_id <= 0) {
            wp_die(esc_html__('Could not create duplicate quote.', 'the-artist'));
        }

        $new_quote_id = (int) $new_quote_id;

        $this->copy_quote_taxonomies($source_quote_id, $new_quote_id);
        $this->copy_quote_meta($source_quote_id, $new_quote_id);
        if (!$this->copy_pricing_rows($source_quote_id, $new_quote_id)) {
            wp_delete_post($new_quote_id, true);
            wp_die(esc_html__('Could not duplicate pricing rows.', 'the-artist'));
        }

        wp_safe_redirect(admin_url('post.php?post=' . $new_quote_id . '&action=edit'));
        exit;
    }

    /**
     * Save admin-only quote notes meta.
     *
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     */
    public function save_admin_notes($post_id, $post, $update): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $nonce = isset($_POST[self::NOTES_NONCE_NAME]) ? sanitize_text_field(wp_unslash((string) $_POST[self::NOTES_NONCE_NAME])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::NOTES_NONCE_ACTION)) {
            return;
        }

        $notes = isset($_POST[self::NOTES_META_KEY])
            ? sanitize_textarea_field(wp_unslash((string) $_POST[self::NOTES_META_KEY]))
            : '';

        update_post_meta((int) $post_id, self::NOTES_META_KEY, $notes);
    }

    private function build_duplicate_url(int $quote_id): string
    {
        $url = add_query_arg([
            'action' => self::DUPLICATE_ACTION,
            'quote_id' => $quote_id,
        ], admin_url('admin-post.php'));

        return wp_nonce_url($url, self::DUPLICATE_NONCE_ACTION . '_' . $quote_id);
    }

    private function copy_quote_taxonomies(int $source_quote_id, int $new_quote_id): void
    {
        $taxonomy_names = get_object_taxonomies(self::POST_TYPE, 'names');
        if (!is_array($taxonomy_names) || empty($taxonomy_names)) {
            return;
        }

        foreach ($taxonomy_names as $taxonomy_name) {
            $term_ids = wp_get_object_terms($source_quote_id, $taxonomy_name, ['fields' => 'ids']);
            if (is_wp_error($term_ids)) {
                continue;
            }

            if (is_array($term_ids)) {
                wp_set_object_terms($new_quote_id, $term_ids, $taxonomy_name, false);
            }
        }
    }

    private function copy_quote_meta(int $source_quote_id, int $new_quote_id): void
    {
        $all_meta = get_post_meta($source_quote_id);
        if (!is_array($all_meta) || empty($all_meta)) {
            return;
        }

        $skip_keys = [
            '_edit_lock' => true,
            '_edit_last' => true,
            '_wp_old_slug' => true,
            '_tah_prices_resolved_at' => true,
            '_tah_quote_view_count' => true,
            '_tah_quote_last_viewed_at' => true,
            '_tah_lock_offer_expires_at' => true,
            '_tah_price_locked_until' => true,
        ];

        foreach ($all_meta as $meta_key => $values) {
            if (!is_string($meta_key) || isset($skip_keys[$meta_key])) {
                continue;
            }

            if (!is_array($values)) {
                continue;
            }

            delete_post_meta($new_quote_id, $meta_key);
            foreach ($values as $meta_value) {
                add_post_meta($new_quote_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
    }

    private function copy_pricing_rows(int $source_quote_id, int $new_quote_id): bool
    {
        $repository = new TAH_Pricing_Repository();
        $source_groups = $repository->get_quote_groups($source_quote_id);
        if (empty($source_groups)) {
            return true;
        }

        $group_rows = [];
        $group_id_map = [];
        foreach ($source_groups as $index => $source_group) {
            if (!is_array($source_group)) {
                continue;
            }

            $old_group_id = isset($source_group['id']) ? (int) $source_group['id'] : 0;
            $group_rows[] = [
                'id' => 0,
                'name' => isset($source_group['name']) ? (string) $source_group['name'] : '',
                'description' => isset($source_group['description']) ? (string) $source_group['description'] : '',
                'selection_mode' => isset($source_group['selection_mode']) ? (string) $source_group['selection_mode'] : 'all',
                'show_subtotal' => !empty($source_group['show_subtotal']),
                'is_collapsed' => !empty($source_group['is_collapsed']),
                'sort_order' => isset($source_group['sort_order']) ? (int) $source_group['sort_order'] : $index,
            ];
            $group_id_map[] = $old_group_id;
        }

        $persisted_group_ids = $repository->save_quote_groups($new_quote_id, $group_rows);
        if (empty($persisted_group_ids)) {
            return false;
        }

        $new_group_map = [];
        foreach ($group_id_map as $index => $old_group_id) {
            $new_group_id = isset($persisted_group_ids[$index]) ? (int) $persisted_group_ids[$index] : 0;
            if ($old_group_id > 0 && $new_group_id > 0) {
                $new_group_map[$old_group_id] = $new_group_id;
            }
        }

        $source_items = $repository->get_quote_line_items($source_quote_id);
        if (empty($source_items)) {
            return true;
        }

        $item_rows = [];
        foreach ($source_items as $index => $source_item) {
            if (!is_array($source_item)) {
                continue;
            }

            $old_group_id = isset($source_item['group_id']) ? (int) $source_item['group_id'] : 0;
            $new_group_id = isset($new_group_map[$old_group_id]) ? (int) $new_group_map[$old_group_id] : 0;
            if ($new_group_id <= 0) {
                continue;
            }

            $item_rows[] = [
                'id' => 0,
                'group_id' => $new_group_id,
                'pricing_item_id' => isset($source_item['pricing_item_id']) && (int) $source_item['pricing_item_id'] > 0
                    ? (int) $source_item['pricing_item_id']
                    : null,
                'item_type' => isset($source_item['item_type']) ? (string) $source_item['item_type'] : 'standard',
                'title' => isset($source_item['title']) ? (string) $source_item['title'] : '',
                'description' => isset($source_item['description']) ? (string) $source_item['description'] : null,
                'quantity' => isset($source_item['quantity']) ? (float) $source_item['quantity'] : 0.0,
                'unit_type' => isset($source_item['unit_type']) ? (string) $source_item['unit_type'] : 'flat',
                'price_mode' => isset($source_item['price_mode']) ? (string) $source_item['price_mode'] : TAH_Price_Formula::MODE_DEFAULT,
                'price_modifier' => isset($source_item['price_modifier']) ? (float) $source_item['price_modifier'] : 0.0,
                'resolved_price' => isset($source_item['resolved_price']) ? (float) $source_item['resolved_price'] : 0.0,
                'previous_resolved_price' => isset($source_item['previous_resolved_price']) && is_numeric($source_item['previous_resolved_price'])
                    ? (float) $source_item['previous_resolved_price']
                    : null,
                'is_selected' => !array_key_exists('is_selected', $source_item) || !empty($source_item['is_selected']),
                'sort_order' => isset($source_item['sort_order']) ? (int) $source_item['sort_order'] : $index,
                'material_cost' => isset($source_item['material_cost']) && is_numeric($source_item['material_cost'])
                    ? (float) $source_item['material_cost']
                    : null,
                'labor_cost' => isset($source_item['labor_cost']) && is_numeric($source_item['labor_cost'])
                    ? (float) $source_item['labor_cost']
                    : null,
                'line_sku' => isset($source_item['line_sku']) ? (string) $source_item['line_sku'] : null,
                'tax_rate' => isset($source_item['tax_rate']) && is_numeric($source_item['tax_rate'])
                    ? (float) $source_item['tax_rate']
                    : null,
                'note' => isset($source_item['note']) ? (string) $source_item['note'] : null,
            ];
        }

        if (!empty($item_rows)) {
            $persisted_line_item_ids = $repository->save_quote_line_items($new_quote_id, $item_rows);
            if (empty($persisted_line_item_ids)) {
                return false;
            }
        }

        return true;
    }
}

$GLOBALS['tah_quote_edit_screen'] = new TAH_Quote_Edit_Screen();
