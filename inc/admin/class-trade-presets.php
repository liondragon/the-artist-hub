<?php
declare(strict_types=1);

/**
 * Trade Presets Management
 * 
 * Handles the "Recipe" logic for Trades:
 * 1. Adds a "Default Sections" field to Trade Add/Edit screens.
 * 2. Saves the list of Section Keys to term meta.
 * 
 * Note: Deletion protection is handled by policy (manual reassignment),
 * not by code hooks, to keep logic simple.
 */
class TAH_Trade_Presets
{

    const META_KEY = '_tah_trade_default_sections';
    const NONCE_ACTION = 'tah_trade_presets_save';
    const NONCE_NAME = '_tah_trade_presets_nonce';

    public function __construct()
    {
        // A. Add Fields to Taxonomy Screens
        add_action('trade_add_form_fields', [$this, 'render_field_add']);
        add_action('trade_edit_form_fields', [$this, 'render_field_edit']);

        // B. Save Fields
        add_action('created_trade', [$this, 'save_meta']);
        add_action('edited_trade', [$this, 'save_meta']);

        // C. Assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Render Field on "Add New Trade" Screen
     */
    public function render_field_add($taxonomy)
    {
        $sections = $this->get_all_global_sections();
        ?>
        <div class="form-field term-sections-wrap">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <label><?php _e('Info Sections Recipe', 'the-artist'); ?></label>
            <p class="description"><?php _e('Select sections and drag selected rows to define recipe order.', 'the-artist'); ?>
            </p>

            <?php $this->render_sortable_sections_list($sections, []); ?>
        </div>
        <?php
    }

    /**
     * Render Field on "Edit Trade" Screen
     */
    public function render_field_edit($term)
    {
        $sections = $this->get_all_global_sections();
        $saved_keys = get_term_meta($term->term_id, self::META_KEY, true);
        if (!is_array($saved_keys)) {
            $saved_keys = [];
        }
        ?>
        <tr class="form-field term-sections-wrap">
            <th scope="row"><label><?php _e('Info Sections Recipe', 'the-artist'); ?></label></th>
            <td>
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <p class="description" style="margin-bottom:10px;">
                    <?php _e('Select sections and drag selected rows to define recipe order.', 'the-artist'); ?>
                </p>
                <?php $this->render_sortable_sections_list($sections, $saved_keys); ?>
            </td>
        </tr>
        <?php
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        if (!in_array($hook_suffix, ['term.php', 'edit-tags.php'], true)) {
            return;
        }

        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
        if ($taxonomy !== 'trade') {
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
    }

    /**
     * Save Term Meta
     */
    public function save_meta($term_id)
    {
        if (!current_user_can('manage_categories')) {
            return;
        }

        if (
            !isset($_POST[self::NONCE_NAME]) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])),
                self::NONCE_ACTION
            )
        ) {
            return;
        }

        if (!isset($_POST['tah_trade_sections']) || !is_array($_POST['tah_trade_sections'])) {
            update_term_meta($term_id, self::META_KEY, []);
            return;
        }

        $submitted = (array) wp_unslash($_POST['tah_trade_sections']);

        // VALIDATION: Filter out unknown keys against the current Global Library.
        $valid_keys = wp_list_pluck($this->get_all_global_sections(), 'key');
        $valid_key_set = array_fill_keys($valid_keys, true);

        $filtered_keys = [];
        foreach ($submitted as $key => $value) {
            $section_key = sanitize_key((string) $key);
            if ($section_key !== '' && $value === '1' && isset($valid_key_set[$section_key])) {
                $filtered_keys[] = $section_key;
            }
        }

        // Save as indexed array (order preserved from DOM/sortable).
        update_term_meta($term_id, self::META_KEY, array_values(array_unique($filtered_keys)));
    }

    /**
     * Helper: Get All Info Sections with Keys
     */
    private function get_all_global_sections()
    {
        $args = [
            'post_type' => 'tah_template_part',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $posts = get_posts($args);
        $data = [];

        foreach ($posts as $p) {
            $key = get_post_meta($p->ID, '_tah_section_key', true);
            if ($key) {
                $data[] = [
                    'id' => $p->ID,
                    'title' => $p->post_title,
                    'key' => $key
                ];
            }
        }

        return $data;
    }

    /**
     * Render sortable Trade Preset rows preserving saved key order.
     *
     * @param array<int, array{id:int,title:string,key:string}> $sections
     * @param array<int, string> $saved_keys
     */
    private function render_sortable_sections_list(array $sections, array $saved_keys)
    {
        if (empty($sections)) {
            echo '<p>' . esc_html__('No Info Sections found.', 'the-artist') . '</p>';
            return;
        }

        $by_key = [];
        foreach ($sections as $section) {
            $by_key[$section['key']] = $section;
        }

        $sorted = [];
        foreach ($saved_keys as $saved_key) {
            if (isset($by_key[$saved_key])) {
                $sorted[] = $by_key[$saved_key];
                unset($by_key[$saved_key]);
            }
        }

        foreach ($by_key as $remaining) {
            $sorted[] = $remaining;
        }

        echo '<ul class="tah-trade-sections-sortable tah-quote-sections-list">';
        foreach ($sorted as $sec) {
            $checked = in_array($sec['key'], $saved_keys, true);
            $enabled_label = $checked ? __('Included in recipe', 'the-artist') : __('Not in recipe', 'the-artist');
            $enabled_icon = $checked ? 'dashicons-visibility' : 'dashicons-hidden';

            echo '<li class="tah-trade-section-row tah-quote-section-item' . ($checked ? '' : ' tah-trade-section-disabled') . '" data-key="' . esc_attr($sec['key']) . '">';
            echo '<div class="tah-quote-section-title-row">';

            // Drag handle — same SVG as Quote sections
            echo '<span class="tah-drag-handle" aria-hidden="true"><svg viewBox="0 0 32 32" class="svg-icon"><path d="M 14 5.5 a 3 3 0 1 1 -3 -3 A 3 3 0 0 1 14 5.5 Z m 7 3 a 3 3 0 1 0 -3 -3 A 3 3 0 0 0 21 8.5 Z m -10 4 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 12.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 12.5 Z m -10 10 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 22.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 22.5 Z"></path></svg></span>';

            // Title
            echo '<label class="tah-inline-enable">';
            echo '<span class="tah-quote-section-title">' . esc_html($sec['title']) . '</span>';
            echo '</label>';

            // Hidden input to carry the value when included
            echo '<input type="hidden" class="tah-trade-section-enabled" name="tah_trade_sections[' . esc_attr($sec['key']) . ']" value="' . ($checked ? '1' : '0') . '">';

            // Visibility toggle icon — always visible
            echo '<button type="button" class="button-link tah-trade-toggle-enabled tah-icon-button" aria-label="' . esc_attr($enabled_label) . '" title="' . esc_attr($enabled_label) . '">';
            echo '<span class="dashicons ' . esc_attr($enabled_icon) . '" aria-hidden="true"></span>';
            echo '</button>';

            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
}

new TAH_Trade_Presets();
