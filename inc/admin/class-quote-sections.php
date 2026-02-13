<?php
declare(strict_types=1);

/**
 * Quote Sections Controller
 *
 * Owns quote section meta persistence for Quotes.
 */
class TAH_Quote_Sections
{
    const NONCE_ACTION = 'tah_quote_sections_save';
    const NONCE_NAME = '_tah_quote_sections_nonce';
    const FIELD_ORDER = 'tah_quote_sections_order';
    const FIELD_ENABLED = 'tah_quote_section_enabled';
    const FIELD_TITLE = 'tah_quote_section_title';
    const FIELD_MODE = 'tah_quote_section_mode';
    const FIELD_CONTENT = 'tah_quote_section_content';
    const FIELD_DELETED = 'tah_quote_sections_deleted';
    const FIELD_ACTION = 'tah_quote_sections_action';
    const META_ORDER = '_tah_quote_sections_order';
    const META_TRADE_PRESET = '_tah_trade_default_sections';
    const SECTION_KEY_META = '_tah_section_key';
    const META_SECTION_PREFIX = '_tah_section_';
    const META_TITLE_SUFFIX = '_title';
    const META_ENABLED_SUFFIX = '_enabled';
    const META_MODE_SUFFIX = '_mode';
    const META_CONTENT_SUFFIX = '_content';
    const MODE_DEFAULT = 'default';
    const MODE_CUSTOM = 'custom';
    const ACTION_SYNC = 'sync';
    const ACTION_RESET_ORDER = 'reset_order';
    const ACTION_RESET_TRADE_DEFAULT = 'reset_trade_default';
    const LOCAL_SECTION_PREFIX = 'local_';

    public function __construct()
    {
        add_action('add_meta_boxes_quotes', [$this, 'register_quote_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('save_post_quotes', [$this, 'save_quote_sections'], 10, 3);
    }

    public function register_quote_meta_boxes()
    {
        remove_meta_box('tradediv', 'quotes', 'side');
        remove_meta_box('tagsdiv-trade', 'quotes', 'side');

        add_meta_box(
            'tah_trade_single_select',
            __('Trade', 'the-artist'),
            [$this, 'render_trade_single_select_metabox'],
            'quotes',
            'side',
            'high'
        );

        add_meta_box(
            'tah_quote_sections',
            __('Info Sections', 'the-artist'),
            [$this, 'render_quote_sections_metabox'],
            'quotes',
            'normal',
            'high'
        );
    }

    public function render_trade_single_select_metabox($post)
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $active_trade = $this->get_active_trade_term((int) $post->ID);
        $active_term_id = $active_trade instanceof WP_Term ? (int) $active_trade->term_id : 0;

        $trade_terms = get_terms([
            'taxonomy' => 'trade',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($trade_terms) || empty($trade_terms)) {
            echo '<p>' . esc_html__('No Trades available.', 'the-artist') . '</p>';
            return;
        }

        echo '<p><label>';
        echo '<input type="radio" name="tah_trade_term_id" value="0" ' . checked(0, $active_term_id, false) . '> ';
        echo esc_html__('None', 'the-artist');
        echo '</label></p>';

        foreach ($trade_terms as $term) {
            echo '<p><label>';
            echo '<input type="radio" name="tah_trade_term_id" value="' . esc_attr((string) $term->term_id) . '" ' . checked((int) $term->term_id, $active_term_id, false) . '> ';
            echo esc_html($term->name);
            echo '</label>';
        }
    }

    public function render_quote_sections_metabox($post)
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $active_trade = $this->get_active_trade_term((int) $post->ID);
        $has_active_trade = $active_trade instanceof WP_Term;
        $order = $this->get_render_order((int) $post->ID);
        $sections_map = $this->get_global_sections_map();
        $all_trade_terms = wp_get_post_terms((int) $post->ID, 'trade');
        $multi_trade_warning = !is_wp_error($all_trade_terms) && count($all_trade_terms) > 1;

        if ($multi_trade_warning) {
            echo '<p class="notice-inline notice-warning">';
            echo esc_html__('Multiple trade terms are assigned. A deterministic trade is shown here; next save enforces single-select.', 'the-artist');
            echo '</p>';
        }

        echo '<div class="tah-quote-sections-tools">';
        echo '<div class="tah-actions-dropdown">';
        echo '<button type="button" class="tah-actions-toggle" aria-label="' . esc_attr__('Actions', 'the-artist') . '">';
        echo '<span class="tah-actions-indicator" aria-hidden="true"></span>';
        echo '<span class="screen-reader-text">' . esc_html__('Actions', 'the-artist') . '</span>';
        echo '</button>';
        echo '<div class="tah-actions-menu" style="display:none;">';
        echo '<button type="submit" class="tah-menu-item" name="' . esc_attr(self::FIELD_ACTION) . '" value="' . esc_attr(self::ACTION_SYNC) . '" ' . disabled(!$has_active_trade, true, false) . '>';
        echo esc_html__('Sync from Trade', 'the-artist');
        echo '</button>';
        echo '<button type="submit" class="tah-menu-item" name="' . esc_attr(self::FIELD_ACTION) . '" value="' . esc_attr(self::ACTION_RESET_ORDER) . '" ' . disabled(!$has_active_trade, true, false) . '>';
        echo esc_html__('Reset Order to Trade', 'the-artist');
        echo '</button>';
        echo '<div class="tah-menu-separator"></div>';
        echo '<button type="submit" class="tah-menu-item tah-menu-item-danger" name="' . esc_attr(self::FIELD_ACTION) . '" value="' . esc_attr(self::ACTION_RESET_TRADE_DEFAULT) . '" ' . disabled(!$has_active_trade, true, false) . '>';
        echo esc_html__('Reset to Trade Default', 'the-artist');
        echo '</button>';
        echo '</div>'; // .tah-actions-menu
        echo '</div>'; // .tah-actions-dropdown
        echo '</div>';

        $empty_message_style = empty($order) ? '' : ' style="display:none"';
        echo '<p id="tah-quote-sections-empty-message" class="description"' . $empty_message_style . '>';
        echo esc_html__('No sections configured. Select a Trade above and click "Sync" to populate.', 'the-artist');
        echo '</p>';

        echo '<input type="hidden" id="tah-quote-sections-order" name="' . esc_attr(self::FIELD_ORDER) . '" value="' . esc_attr(implode(',', $order)) . '">';
        echo '<input type="hidden" id="tah-quote-sections-deleted" name="' . esc_attr(self::FIELD_DELETED) . '" value="">';

        echo '<ul id="tah-quote-sections-list">';
        foreach ($order as $key) {
            $state = $this->get_section_state((int) $post->ID, $key);
            $is_local = $this->is_local_section_key($key);
            $title = isset($sections_map[$key]) ? $sections_map[$key]['title'] : $this->read_section_title((int) $post->ID, $key);

            $item_classes = 'tah-quote-section-item';
            if (!$is_local && $state['mode'] === self::MODE_CUSTOM) {
                $item_classes .= ' tah-section-modified';
            }
            echo '<li class="' . esc_attr($item_classes) . '" data-key="' . esc_attr($key) . '">';
            echo '<div class="tah-quote-section-title-row">';
            echo '<span class="tah-drag-handle" aria-hidden="true"><svg viewBox="0 0 32 32" class="svg-icon"><path d="M 14 5.5 a 3 3 0 1 1 -3 -3 A 3 3 0 0 1 14 5.5 Z m 7 3 a 3 3 0 1 0 -3 -3 A 3 3 0 0 0 21 8.5 Z m -10 4 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 12.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 12.5 Z m -10 10 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 22.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 22.5 Z"></path></svg></span>';
            echo '<label class="tah-inline-enable">';
            if ($is_local) {
                echo '<input type="text" class="tah-local-title-input" name="' . esc_attr(self::FIELD_TITLE) . '[' . esc_attr($key) . ']" value="' . esc_attr($title) . '" placeholder="' . esc_attr__('Custom Section Title', 'the-artist') . '">';
            } else {
                echo '<span class="tah-quote-section-title">' . esc_html($title) . '</span>';
            }
            echo '</label>';
            echo '<input type="hidden" class="tah-section-enabled-input" name="' . esc_attr(self::FIELD_ENABLED) . '[' . esc_attr($key) . ']" value="' . esc_attr($state['enabled'] ? '1' : '0') . '">';
            echo '<input type="hidden" class="tah-section-mode-input" name="' . esc_attr(self::FIELD_MODE) . '[' . esc_attr($key) . ']" value="' . esc_attr($state['mode']) . '">';
            $enabled_label = $state['enabled'] ? __('Hide section', 'the-artist') : __('Show section', 'the-artist');
            $enabled_icon = $state['enabled'] ? 'dashicons-visibility' : 'dashicons-hidden';
            echo '<button type="button" class="button-link tah-reset-section tah-icon-button" aria-label="' . esc_attr__('Revert to Default', 'the-artist') . '" title="' . esc_attr__('Revert to Default', 'the-artist') . '">';
            echo '<span class="dashicons dashicons-undo" aria-hidden="true"></span>';
            echo '</button>';
            echo '<button type="button" class="button-link tah-toggle-enabled tah-icon-button" aria-label="' . esc_attr($enabled_label) . '" title="' . esc_attr($enabled_label) . '">';
            echo '<span class="dashicons ' . esc_attr($enabled_icon) . '" aria-hidden="true"></span>';
            echo '</button>';
            echo '<button type="button" class="button-link tah-delete-section" aria-label="' . esc_attr__('Delete section', 'the-artist') . '" title="' . esc_attr__('Delete section', 'the-artist') . '">';
            echo '<span class="lp-btn-icon dashicons dashicons-trash" aria-hidden="true"></span>';
            echo '</button>';
            echo '<button type="button" class="button-link tah-edit-section tah-icon-button" aria-label="' . esc_attr__('Expand', 'the-artist') . '" title="' . esc_attr__('Expand', 'the-artist') . '">';
            echo '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
            echo '</button>';
            if ($is_local) {
                $mode_badge = __('CUSTOM', 'the-artist');
            } else {
                $mode_badge = $state['mode'] === self::MODE_CUSTOM
                    ? __('MODIFIED', 'the-artist')
                    : __('DEFAULT', 'the-artist');
            }
            echo '<span class="tah-mode-badge">' . esc_html($mode_badge) . '</span>';
            echo '</div>';

            $custom_style = 'style="display:none"';
            echo '<div class="tah-section-custom-content" ' . $custom_style . '>';
            echo '<textarea class="widefat" rows="5" aria-label="' . esc_attr__('Section HTML', 'the-artist') . '" name="' . esc_attr(self::FIELD_CONTENT) . '[' . esc_attr($key) . ']">' . esc_textarea($state['content']) . '</textarea>';
            echo '<span class="tah-custom-html-hint" aria-hidden="true">' . esc_html__('HTML', 'the-artist') . '</span>';
            echo '</div>';

            echo '</li>';
        }

        echo '<li class="tah-quote-section-item tah-create-section-item">';
        echo '<div class="tah-quote-section-title-row">';
        echo '<span class="dashicons dashicons-plus-alt2 tah-create-section-icon" aria-hidden="true"></span>';
        echo '<input type="text" id="tah-create-section-input" class="tah-create-section-input" placeholder="' . esc_attr__('Create a new info section for this quote', 'the-artist') . '" autocomplete="off">';

        echo '<div class="tah-create-section-actions">';
        echo '<button type="button" class="button-link tah-create-section-save" id="tah-create-section-save" aria-label="' . esc_attr__('Save new info section', 'the-artist') . '" disabled>';
        echo '<span class="dashicons dashicons-yes" aria-hidden="true"></span>';
        echo '</button>';
        echo '<button type="button" class="button-link tah-create-section-discard" id="tah-create-section-discard" aria-label="' . esc_attr__('Discard new info section', 'the-artist') . '" style="display:none">';
        echo '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
        echo '</button>';
        echo '</div>'; // .tah-create-section-actions

        echo '</div>'; // .tah-quote-section-title-row
        echo '</li>';

        echo '</ul>';
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'quotes') {
            return;
        }

        $file_path = get_template_directory() . '/assets/js/quote-sections.js';
        $version = file_exists($file_path) ? (string) filemtime($file_path) : '1.0.0';

        wp_enqueue_script(
            'tah-quote-sections',
            get_template_directory_uri() . '/assets/js/quote-sections.js',
            ['jquery', 'jquery-ui-sortable'],
            $version,
            true
        );

        $preset_map = [];
        $trade_terms = get_terms([
            'taxonomy' => 'trade',
            'hide_empty' => false,
            'fields' => 'ids',
        ]);
        if (!is_wp_error($trade_terms)) {
            foreach ($trade_terms as $trade_term_id) {
                $preset_map[(string) (int) $trade_term_id] = $this->get_filtered_trade_preset_keys((int) $trade_term_id);
            }
        }

        $sections_map = $this->get_global_sections_map();
        $section_titles = [];
        $section_contents = [];
        foreach ($sections_map as $key => $row) {
            $section_titles[$key] = (string) $row['title'];
            $section_contents[$key] = (string) $row['content'];
        }

        wp_localize_script('tah-quote-sections', 'tahQuoteSectionsConfig', [
            'tradePresets' => $preset_map,
            'sectionTitles' => $section_titles,
            'sectionContents' => $section_contents,
            'labels' => [
                'enabled' => __('Enabled', 'the-artist'),
                'showSection' => __('Show section', 'the-artist'),
                'hideSection' => __('Hide section', 'the-artist'),
                'default' => __('DEFAULT', 'the-artist'),
                'modified' => __('MODIFIED', 'the-artist'),
                'customLocal' => __('CUSTOM', 'the-artist'),
                'expand' => __('Expand', 'the-artist'),
                'collapse' => __('Collapse', 'the-artist'),
                'deleteSection' => __('Delete section', 'the-artist'),
                'resetToDefault' => __('Revert to Default', 'the-artist'),
                'sectionHtmlAria' => __('Section HTML', 'the-artist'),
                'sectionHtmlHint' => __('HTML', 'the-artist'),
                'customSectionTitlePlaceholder' => __('Custom Section Title', 'the-artist'),
                'newSectionDefaultTitle' => __('Custom Section', 'the-artist'),
                'emptyState' => __('No sections configured. Select a Trade above to populate from a recipe.', 'the-artist'),
            ],
        ]);
    }

    public function render_sections_frontend($post_id)
    {
        $order = $this->get_render_order((int) $post_id);
        if (empty($order)) {
            return;
        }

        $sections_map = $this->get_global_sections_map();

        foreach ($order as $key) {
            $state = $this->get_section_state((int) $post_id, $key);
            if (!$state['enabled']) {
                continue;
            }

            $section_title = isset($sections_map[$key]) && $sections_map[$key]['title'] !== ''
                ? (string) $sections_map[$key]['title']
                : (string) $this->read_section_title((int) $post_id, $key);

            if ($state['mode'] === self::MODE_CUSTOM) {
                if ($state['content'] !== '') {
                    $this->render_frontend_section_block($section_title, (string) $state['content']);
                }
                continue;
            }

            if (!isset($sections_map[$key])) {
                continue;
            }

            $global_content = (string) $sections_map[$key]['content'];
            if ($global_content !== '') {
                $this->render_frontend_section_block($section_title, $global_content);
            }
        }
    }

    private function render_frontend_section_block($title, $content_html)
    {
        // If content already ships with collapsible structure, keep it raw.
        if (stripos($content_html, 'class="collapsible') !== false || stripos($content_html, "class='collapsible") !== false) {
            echo $content_html;
            return;
        }

        echo '<div class="collapsible">';
        echo '<h3 class="collapsible-trigger" role="button" tabindex="0" aria-expanded="false">';
        echo esc_html((string) $title);
        echo '</h3>';
        echo '<div class="collapsible-content"><div class="collapsible-inner">';
        // Intentionally render raw stored HTML fragments.
        echo $content_html;
        echo '</div></div></div>';
    }

    /**
     * Persist Quote Sections data when section fields are submitted.
     */
    public function save_quote_sections($post_id, $post, $update)
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (
            !isset($_POST[self::NONCE_NAME]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        $this->persist_single_trade_selection((int) $post_id);
        $this->maybe_initialize_quote_sections_order((int) $post_id);
        if ($this->handle_sync_reset_action((int) $post_id)) {
            return;
        }

        $deleted_keys = [];
        if (isset($_POST[self::FIELD_DELETED])) {
            $deleted_keys = $this->normalize_order($_POST[self::FIELD_DELETED]);
            if (!empty($deleted_keys)) {
                $this->clear_section_overrides($post_id, $deleted_keys);
            }
        }

        if (isset($_POST[self::FIELD_ORDER])) {
            $order = $this->normalize_order($_POST[self::FIELD_ORDER]);
            if (!empty($deleted_keys)) {
                $deleted_set = array_fill_keys($deleted_keys, true);
                $order = array_values(array_filter($order, function ($key) use ($deleted_set) {
                    return !isset($deleted_set[$key]);
                }));
            }
            update_post_meta($post_id, self::META_ORDER, $order);
        }

        if (isset($_POST[self::FIELD_ENABLED]) && is_array($_POST[self::FIELD_ENABLED])) {
            $enabled_rows = wp_unslash($_POST[self::FIELD_ENABLED]);
            foreach ($enabled_rows as $key => $enabled) {
                $section_key = sanitize_key((string) $key);
                if ($section_key === '') {
                    continue;
                }

                $normalized_enabled = !in_array((string) $enabled, ['0', 'false', 'off'], true);
                update_post_meta($post_id, $this->meta_key_enabled($section_key), $normalized_enabled ? '1' : '0');
            }
        }

        if (isset($_POST[self::FIELD_TITLE]) && is_array($_POST[self::FIELD_TITLE])) {
            $title_rows = wp_unslash($_POST[self::FIELD_TITLE]);
            foreach ($title_rows as $key => $title) {
                $section_key = sanitize_key((string) $key);
                if ($section_key === '' || !$this->is_local_section_key($section_key)) {
                    continue;
                }

                $sanitized_title = sanitize_text_field((string) $title);
                if ($sanitized_title === '') {
                    delete_post_meta($post_id, $this->meta_key_title($section_key));
                    continue;
                }

                update_post_meta($post_id, $this->meta_key_title($section_key), $sanitized_title);
            }
        }

        $content_items = [];
        if (isset($_POST[self::FIELD_CONTENT]) && is_array($_POST[self::FIELD_CONTENT])) {
            $content_items = wp_unslash($_POST[self::FIELD_CONTENT]);
        }

        if (isset($_POST[self::FIELD_MODE]) && is_array($_POST[self::FIELD_MODE])) {
            $modes = wp_unslash($_POST[self::FIELD_MODE]);
            $sections_map = $this->get_global_sections_map();
            foreach ($modes as $key => $mode) {
                $section_key = sanitize_key((string) $key);
                if ($section_key === '') {
                    continue;
                }

                $normalized_mode = in_array($mode, [self::MODE_DEFAULT, self::MODE_CUSTOM], true)
                    ? $mode
                    : self::MODE_DEFAULT;

                $content = '';
                if (array_key_exists($key, $content_items)) {
                    $content = wp_kses_post((string) $content_items[$key]);
                }

                // Empty custom content is treated as reverting to default.
                if ($normalized_mode === self::MODE_CUSTOM && trim(wp_strip_all_tags($content)) === '') {
                    $normalized_mode = self::MODE_DEFAULT;
                    $content = '';
                }

                // If override content resolves to the same global default, keep section in default mode.
                if (
                    $normalized_mode === self::MODE_CUSTOM &&
                    isset($sections_map[$section_key]) &&
                    $this->normalize_content_for_compare($content) === $this->normalize_content_for_compare((string) $sections_map[$section_key]['content'])
                ) {
                    $normalized_mode = self::MODE_DEFAULT;
                    $content = '';
                }

                update_post_meta($post_id, $this->meta_key_mode($section_key), $normalized_mode);
                if ($normalized_mode === self::MODE_DEFAULT) {
                    delete_post_meta($post_id, $this->meta_key_content($section_key));
                } elseif (array_key_exists($key, $content_items)) {
                    update_post_meta($post_id, $this->meta_key_content($section_key), $content);
                }
            }
            return;
        }

        if (!empty($content_items)) {
            foreach ($content_items as $key => $content) {
                $section_key = sanitize_key((string) $key);
                if ($section_key === '') {
                    continue;
                }

                update_post_meta($post_id, $this->meta_key_content($section_key), wp_kses_post((string) $content));
            }
        }
    }

    private function maybe_initialize_quote_sections_order($post_id)
    {
        // Initialize once: if meta exists (even as empty array), do not auto-reinitialize.
        if (metadata_exists('post', $post_id, self::META_ORDER)) {
            return;
        }

        $initial_order = $this->get_initial_order_from_trade_or_general($post_id);
        update_post_meta($post_id, self::META_ORDER, $initial_order);
    }

    private function get_initial_order_from_trade_or_general($post_id)
    {
        $active_trade = $this->get_active_trade_term($post_id);
        if ($active_trade instanceof WP_Term) {
            $active_preset = $this->get_filtered_trade_preset_keys((int) $active_trade->term_id);
            if (!empty($active_preset)) {
                return $active_preset;
            }
        }

        $general_trade = $this->get_general_trade_term();
        if ($general_trade instanceof WP_Term) {
            return $this->get_filtered_trade_preset_keys((int) $general_trade->term_id);
        }

        return [];
    }

    private function get_general_trade_term()
    {
        $general_by_slug = get_term_by('slug', 'general', 'trade');
        if ($general_by_slug instanceof WP_Term) {
            return $general_by_slug;
        }

        $general_by_name = get_term_by('name', 'General', 'trade');
        if ($general_by_name instanceof WP_Term) {
            return $general_by_name;
        }

        return null;
    }

    private function persist_single_trade_selection($post_id)
    {
        if (isset($_POST['tah_trade_term_id'])) {
            $requested_term_id = absint(wp_unslash($_POST['tah_trade_term_id']));
            if ($requested_term_id > 0) {
                wp_set_post_terms($post_id, [$requested_term_id], 'trade', false);
                return;
            }

            wp_set_post_terms($post_id, [], 'trade', false);
            return;
        }

        if (isset($_POST['tax_input']['trade'])) {
            $raw_terms = (array) wp_unslash($_POST['tax_input']['trade']);
            $ids = array_values(array_filter(array_map('absint', $raw_terms)));
            if (empty($ids)) {
                wp_set_post_terms($post_id, [], 'trade', false);
                return;
            }

            sort($ids, SORT_NUMERIC);
            wp_set_post_terms($post_id, [(int) $ids[0]], 'trade', false);
        }
    }

    private function handle_sync_reset_action($post_id)
    {
        if (!isset($_POST[self::FIELD_ACTION])) {
            return false;
        }

        $action = sanitize_key((string) wp_unslash($_POST[self::FIELD_ACTION]));
        if ($action === 'reset') {
            // Backward compatibility for older UI posts.
            $action = self::ACTION_RESET_ORDER;
        }

        if (!in_array($action, [self::ACTION_SYNC, self::ACTION_RESET_ORDER, self::ACTION_RESET_TRADE_DEFAULT], true)) {
            return false;
        }

        $active_trade = $this->get_active_trade_term($post_id);
        if (!($active_trade instanceof WP_Term)) {
            return false;
        }

        $preset_keys = $this->get_filtered_trade_preset_keys((int) $active_trade->term_id);
        $current_order = get_post_meta($post_id, self::META_ORDER, true);
        if (!is_array($current_order)) {
            $current_order = [];
        }

        if ($action === self::ACTION_SYNC) {
            $next_order = $this->compute_sync_order($current_order, $preset_keys);
            update_post_meta($post_id, self::META_ORDER, $next_order);
            return true;
        }

        $next_order = $this->compute_reset_order($preset_keys);
        update_post_meta($post_id, self::META_ORDER, $next_order);

        if ($action === self::ACTION_RESET_TRADE_DEFAULT) {
            $keys_to_clear = array_values(array_unique(array_merge($this->normalize_order($current_order), $next_order)));
            $this->clear_section_overrides($post_id, $keys_to_clear);
        }
        return true;
    }

    /**
     * Clear per-section override meta so sections fall back to defaults.
     *
     * @param string[] $section_keys
     */
    private function clear_section_overrides($post_id, array $section_keys)
    {
        foreach ($section_keys as $section_key) {
            $section_key = sanitize_key((string) $section_key);
            if ($section_key === '') {
                continue;
            }

            delete_post_meta($post_id, $this->meta_key_enabled($section_key));
            delete_post_meta($post_id, $this->meta_key_mode($section_key));
            delete_post_meta($post_id, $this->meta_key_content($section_key));
            delete_post_meta($post_id, $this->meta_key_title($section_key));
        }
    }

    /**
     * Resolve the active Trade term deterministically for a Quote.
     *
     * Returns null when lookup errors or when no Trade terms are assigned.
     */
    private function get_active_trade_term($post_id)
    {
        $terms = wp_get_post_terms((int) $post_id, 'trade');
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }

        usort($terms, function ($a, $b) {
            return (int) $a->term_id <=> (int) $b->term_id;
        });

        return $terms[0] ?? null;
    }

    /**
     * Return a trade preset filtered against the current Global Library.
     *
     * Preserves stored order when provided as an ordered array; otherwise
     * returns a deterministic, sorted fallback.
     *
     * @return string[]
     */
    private function get_filtered_trade_preset_keys($term_id)
    {
        $global_keys = $this->get_global_section_keys();
        if (empty($global_keys)) {
            return [];
        }

        $raw_preset = get_term_meta((int) $term_id, self::META_TRADE_PRESET, true);
        if (empty($raw_preset)) {
            return [];
        }

        $preset_keys = is_array($raw_preset) ? $raw_preset : [(string) $raw_preset];
        $filtered = $this->filter_keys_against_library($preset_keys, $global_keys);

        if (is_array($raw_preset)) {
            return $filtered;
        }

        sort($filtered, SORT_STRING);
        return $filtered;
    }

    /**
     * @param mixed[] $candidate_keys
     * @param string[] $global_keys
     * @return string[]
     */
    private function filter_keys_against_library(array $candidate_keys, array $global_keys)
    {
        $global_key_set = array_fill_keys($global_keys, true);
        $filtered = [];

        foreach ($candidate_keys as $candidate) {
            $key = sanitize_key((string) $candidate);
            if ($key !== '' && isset($global_key_set[$key])) {
                $filtered[] = $key;
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * @return string[]
     */
    private function get_global_section_keys()
    {
        $sections = get_posts([
            'post_type' => 'tah_template_part',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if (empty($sections)) {
            return [];
        }

        $keys = [];
        foreach ($sections as $section_id) {
            $key = sanitize_key((string) get_post_meta((int) $section_id, self::SECTION_KEY_META, true));
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Sync order is additive: keep current order and append missing preset keys.
     *
     * @param string[] $current_order
     * @param string[] $preset_order
     * @return string[]
     */
    private function compute_sync_order(array $current_order, array $preset_order)
    {
        $normalized_current = $this->normalize_order($current_order);
        $normalized_preset = $this->normalize_order($preset_order);

        $existing = array_fill_keys($normalized_current, true);
        $result = $normalized_current;

        foreach ($normalized_preset as $key) {
            if (!isset($existing[$key])) {
                $result[] = $key;
                $existing[$key] = true;
            }
        }

        return $result;
    }

    /**
     * Reset order mirrors the preset exactly (after normalization).
     *
     * @param string[] $preset_order
     * @return string[]
     */
    private function compute_reset_order(array $preset_order)
    {
        return $this->normalize_order($preset_order);
    }

    /**
     * Read per-section state with deterministic defaults.
     *
     * @return array{enabled: bool, mode: string, content: string}
     */
    private function get_section_state($post_id, $section_key)
    {
        return [
            'enabled' => $this->read_section_enabled($post_id, $section_key),
            'mode' => $this->read_section_mode($post_id, $section_key),
            'content' => $this->read_section_content($post_id, $section_key),
        ];
    }

    private function read_section_enabled($post_id, $section_key)
    {
        $value = get_post_meta((int) $post_id, $this->meta_key_enabled($section_key), true);
        if ($value === '' || $value === null) {
            return true;
        }

        return !in_array((string) $value, ['0', 'false', 'off'], true);
    }

    private function read_section_mode($post_id, $section_key)
    {
        $value = get_post_meta((int) $post_id, $this->meta_key_mode($section_key), true);
        return in_array($value, [self::MODE_DEFAULT, self::MODE_CUSTOM], true)
            ? $value
            : self::MODE_DEFAULT;
    }

    private function read_section_content($post_id, $section_key)
    {
        $value = get_post_meta((int) $post_id, $this->meta_key_content($section_key), true);
        return is_string($value) ? $value : '';
    }

    private function read_section_title($post_id, $section_key)
    {
        if (!$this->is_local_section_key($section_key)) {
            return (string) $section_key;
        }

        $value = get_post_meta((int) $post_id, $this->meta_key_title($section_key), true);
        return is_string($value) && $value !== '' ? $value : __('Custom Section', 'the-artist');
    }

    /**
     * Legacy-safe render order reader.
     *
     * Missing order meta must render nothing and must not auto-initialize.
     *
     * @return string[]
     */
    private function get_render_order($post_id)
    {
        if (!metadata_exists('post', (int) $post_id, self::META_ORDER)) {
            return [];
        }

        $stored = get_post_meta((int) $post_id, self::META_ORDER, true);
        return is_array($stored) ? $this->normalize_order($stored) : [];
    }

    /**
     * @return array<string, array{title: string, content: string}>
     */
    private function get_global_sections_map()
    {
        $section_ids = get_posts([
            'post_type' => 'tah_template_part',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if (empty($section_ids)) {
            return [];
        }

        $map = [];
        foreach ($section_ids as $section_id) {
            $section_id = (int) $section_id;
            $key = sanitize_key((string) get_post_meta($section_id, self::SECTION_KEY_META, true));
            if ($key === '') {
                continue;
            }

            $section = get_post($section_id);
            if (!$section) {
                continue;
            }

            $map[$key] = [
                'title' => (string) $section->post_title,
                'content' => (string) $section->post_content,
            ];
        }

        return $map;
    }

    /**
     * @param mixed $raw_order
     * @return string[]
     */
    private function normalize_order($raw_order)
    {
        if (is_string($raw_order)) {
            $raw_order = explode(',', $raw_order);
        }

        if (!is_array($raw_order)) {
            return [];
        }

        $normalized = [];
        foreach ($raw_order as $value) {
            $key = sanitize_key((string) $value);
            if ($key !== '') {
                $normalized[] = $key;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function meta_key_mode($section_key)
    {
        return $this->meta_key($section_key, self::META_MODE_SUFFIX);
    }

    private function meta_key_content($section_key)
    {
        return $this->meta_key($section_key, self::META_CONTENT_SUFFIX);
    }

    private function meta_key_enabled($section_key)
    {
        return $this->meta_key($section_key, self::META_ENABLED_SUFFIX);
    }

    private function meta_key_title($section_key)
    {
        return $this->meta_key($section_key, self::META_TITLE_SUFFIX);
    }

    private function meta_key($section_key, $suffix)
    {
        return self::META_SECTION_PREFIX . $section_key . $suffix;
    }

    private function is_local_section_key($section_key)
    {
        return strpos((string) $section_key, self::LOCAL_SECTION_PREFIX) === 0;
    }

    private function normalize_content_for_compare($value)
    {
        $content = (string) $value;
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        return trim($content);
    }
}

$GLOBALS['tah_quote_sections_controller'] = new TAH_Quote_Sections();

function tah_render_quote_sections($post_id)
{
    $controller = $GLOBALS['tah_quote_sections_controller'] ?? null;
    if (!$controller instanceof TAH_Quote_Sections) {
        return;
    }

    $controller->render_sections_frontend((int) $post_id);
}
