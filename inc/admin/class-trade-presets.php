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

        if (!isset($_POST['tah_trade_sections'])) {
            // If field missing (unchecked all), save empty array
            update_term_meta($term_id, self::META_KEY, []);
            return;
        }

        $submitted_keys = array_map(
            'sanitize_key',
            (array) wp_unslash($_POST['tah_trade_sections'])
        );

        // VALIDATION: Filter out unknown keys against the current Global Library.
        $valid_keys = wp_list_pluck($this->get_all_global_sections(), 'key');
        $valid_key_set = array_fill_keys($valid_keys, true);

        $filtered_keys = [];
        foreach ($submitted_keys as $key) {
            if ($key !== '' && isset($valid_key_set[$key])) {
                $filtered_keys[] = $key;
            }
        }

        // Save as indexed array.
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
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
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

        echo '<ul class="tah-trade-sections-sortable" style="max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px; background:#fff; margin:0;">';
        foreach ($sorted as $sec) {
            $checked = in_array($sec['key'], $saved_keys, true);
            echo '<li class="tah-trade-section-row" style="list-style:none; margin:0 0 6px; padding:6px; border:1px solid #eee; background:#fff;" data-key="' . esc_attr($sec['key']) . '">';
            echo '<label style="display:flex; align-items:center; gap:8px; margin:0;">';
            echo '<span class="dashicons dashicons-move tah-drag-handle" aria-hidden="true"></span>';
            echo '<input type="checkbox" name="tah_trade_sections[]" value="' . esc_attr($sec['key']) . '" ' . checked($checked, true, false) . '>';
            echo '<span>' . esc_html($sec['title']) . '</span> ';
            echo '<code style="color:#666; font-size:0.85em;">(' . esc_html($sec['key']) . ')</code>';
            echo '</label>';
            echo '</li>';
        }
        echo '</ul>';
    }
}

new TAH_Trade_Presets();
