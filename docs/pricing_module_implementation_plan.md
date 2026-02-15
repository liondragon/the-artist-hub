# Pricing Module — Implementation Plan

**Spec Reference:** [`docs/pricing_module.md`](file:///home/zhenya/dev/the-artist-hub/docs/pricing_module.md) — the authoritative specification for this feature. The generic `Canonical_Spec_Template.md` format was not used; `pricing_module.md` serves as the single source of truth.

**Verification Baseline:** Manual-only Checklist. No existing test harness or test files. Test debt is logged under Known Debt.

> **Scope:** This plan implements the pricing module as defined in `pricing_module.md`. No new user-facing features beyond what the spec describes.

---

## Repo Map (Current State)

| File | Purpose |
|---|---|
| [`functions.php`](file:///home/zhenya/dev/the-artist-hub/functions.php) | Theme entry point. Loads `TAH_Module_Registry::boot()` |
| [`inc/modules/class-module-registry.php`](file:///home/zhenya/dev/the-artist-hub/inc/modules/class-module-registry.php) | Boots theme modules. Currently loads `info-sections` only |
| [`inc/admin/class-quote-sections.php`](file:///home/zhenya/dev/the-artist-hub/inc/admin/class-quote-sections.php) | **Architectural model.** 926-line class: metabox rendering, save hooks, trade presets, frontend rendering. This module is the pattern we follow. |
| [`inc/admin/class-trade-presets.php`](file:///home/zhenya/dev/the-artist-hub/inc/admin/class-trade-presets.php) | Trade taxonomy term meta fields for section presets |
| [`inc/cpt/quotes.php`](file:///home/zhenya/dev/the-artist-hub/inc/cpt/quotes.php) | Quote CPT registration, customer info metabox, admin columns |
| [`single-quotes.php`](file:///home/zhenya/dev/the-artist-hub/single-quotes.php) | Frontend quote template. Renders `the_content()` then `tah_render_quote_sections()` |
| [`assets/js/quote-sections.js`](file:///home/zhenya/dev/the-artist-hub/assets/js/quote-sections.js) | Admin JS for quote sections metabox (drag-and-drop, toggling, etc.) |
| [`assets/css/admin.css`](file:///home/zhenya/dev/the-artist-hub/assets/css/admin.css) | Admin styles for metaboxes |
| [`inc/migrations/`](file:///home/zhenya/dev/the-artist-hub/inc/migrations/) | Empty directory — ready for schema migrations |

**Key patterns to follow:**
- Module loading via `TAH_Module_Registry::boot()` → `require_once` + `::boot()`
- Admin metabox class with `__construct()` hooking into `add_meta_boxes`, `save_post`, `admin_enqueue_scripts`
- Frontend rendering via a global function called from `single-quotes.php`
- Trade presets stored as term meta with sortable UI on taxonomy screens

---

## Phase 0 — Database Schema & Module Scaffold

**Goals:** Create the three custom tables, the schema versioning system, and the module file structure so subsequent phases have a working foundation.

**Non-Goals:** No admin UI, no frontend rendering, no cron. Just tables + wiring.

**Acceptance:** Tables exist after theme activation. `tah_pricing_db_version` option is set. Module loads without errors.

### Tasks

- [x] **Create schema migration file** · Reasoning: `low` — well-patterned DDL with provided `dbDelta()` template · (Spec: §Database Schema, §Quote Formats)
  - Artifacts: `inc/migrations/pricing-tables.php`
  - Interfaces: None — internal only
  - Done When: File contains `CREATE TABLE` for `wp_tah_pricing_items` (including `catalog_type` partition column), `wp_tah_quote_groups`, `wp_tah_quote_line_items` (including insurance-specific nullable columns: `material_cost`, `labor_cost`, `line_sku`, `tax_rate`, `note`), indexes, and charset matching spec schema. Uses `dbDelta()`.
  - Pattern:
    ```php
    function tah_pricing_create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$wpdb->prefix}tah_pricing_items ( ... ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    ```
    > `dbDelta()` is finicky — each column on its own line, no trailing commas, two spaces between column name and type.

- [x] **Create pricing module bootstrap** · Reasoning: `low` — follows existing module registry pattern with provided code template · (Spec: §Database Schema — Schema Versioning, §Development Conventions)
  - Artifacts: `inc/modules/pricing/class-pricing-module.php`
  - Interfaces: `TAH_Module_Registry::boot()` hook. `::boot()` idempotent with static `$booted` guard. `::is_enabled()` via `apply_filters('tah_module_pricing_enabled', true)`. Admin-only classes loaded behind `is_admin()` guard.
  - Done When: `TAH_Pricing_Module::boot()` runs schema migration when `tah_pricing_db_version` is outdated. Module registered in `class-module-registry.php` in explicit load order. Enable filter works.
  - Pattern:
    ```php
    class TAH_Pricing_Module {
        private static $booted = false;
        public static function boot() {
            if (self::$booted) return;
            self::$booted = true;
            if (!self::is_enabled()) return;
            // Schema migration
            $installed = get_option('tah_pricing_db_version', '0');
            if (version_compare($installed, TAH_PRICING_DB_VERSION, '<')) {
                require_once get_template_directory() . '/inc/migrations/pricing-tables.php';
                tah_pricing_create_tables();
                update_option('tah_pricing_db_version', TAH_PRICING_DB_VERSION);
            }
            // Load classes
            require_once __DIR__ . '/class-pricing-repository.php';
            if (is_admin()) {
                require_once __DIR__ . '/class-quote-edit-screen.php';
                require_once __DIR__ . '/class-quote-pricing-metabox.php';
            }
        }
        public static function is_enabled() {
            return apply_filters('tah_module_pricing_enabled', true);
        }
    }
    ```

- [x] **Create pricing repository class** · Reasoning: `medium` — 10+ CRUD methods across 3 tables; needs consistent `$wpdb` usage and proper sanitization · (Spec: §Database Schema, §Portability)
  - Artifacts: `inc/modules/pricing/class-pricing-repository.php`
  - Interfaces: `TAH_Pricing_Repository` wrapping `$wpdb` with typed methods
  - Done When: Repository has CRUD methods for all three tables: `get_catalog_items()`, `get_item_by_sku()`, `insert_item()`, `update_item()`, `deactivate_item()`, `get_quote_groups()`, `get_quote_line_items()`, `save_quote_groups()`, `save_quote_line_items()`, `delete_quote_pricing_data()`.

- [x] Verify Phase 0: activate theme, confirm tables created and `tah_pricing_db_version` set (Manual step #1)

---

## Phase 1 — Admin Catalog & Price Formula Engine

**Goals:** Build the pricing catalog admin page and the price formula parser/resolver. These are the foundational pieces everything else depends on.

**Non-Goals:** No quote-level line items metabox yet. No frontend rendering. No cron.

**Acceptance:** Admin can add/edit/deactivate catalog items. Formula strings parse correctly into `price_mode` + `price_modifier`. Price history appends automatically on updates.

### Tasks

- [x] **Implement pricing catalog admin page** · Reasoning: `medium` — standard WP admin page but needs catalog partitioning, trade filtering, and price history append logic · (Spec: §Admin UX — Pricing Catalog Admin, §Quote Formats — Catalog Partitioning)
  - Artifacts: `inc/modules/pricing/class-pricing-catalog-admin.php`
  - Interfaces: WP admin menu page under "Quotes" menu. Tabs or filter to switch between standard and insurance catalog views. Add/edit/deactivate catalog items. Trade assignment dropdown (filtered by `_tah_trade_context` when set; defaults to showing all trades until Phase 6 adds the trade context UI). Price history display.
  - Depends On: Phase 0 (repository + tables)
  - Done When: Admin can CRUD pricing items in both catalogs. Catalog view filters by `catalog_type`. Trade dropdown populated from `trade` taxonomy (trade context filtering wired but all trades default to visible until context meta is set in Phase 6). `is_active` toggle works. Editing `unit_price` appends to `price_history` JSON.

- [x] **Implement price formula parser** · Reasoning: `high` — 7 formula syntax variants, bidirectional parse/resolve, MROUND rounding with direction; correctness is critical for all downstream pricing · (Spec: §Price Modification Model)
  - Artifacts: `inc/modules/pricing/class-price-formula.php`
  - Interfaces: `TAH_Price_Formula::parse($input) → {mode, modifier}` and `TAH_Price_Formula::resolve($mode, $modifier, $catalog_price, $rounding, $direction) → $resolved_price`
  - Done When: All formula syntaxes parse correctly (`$`, `$+150`, `$-100`, `$*1.1`, `$*0.9`, `1850`, `-200`). Resolver computes correct effective prices and applies MROUND rounding.

- [x] **Implement impact preview count** · Reasoning: `low` — single COUNT query against line items table · (Spec: §Observability — Catalog Edit)
  - Artifacts: Same catalog admin file
  - Done When: When editing a pricing item, shows count of affected active quotes below the price field.

- [x] Verify Phase 1: add several catalog items across trades, modify prices and confirm history appends, test all formula parsing variants (Manual step #2)

---

## Phase 2 — Custom Quote Edit Screen & Pricing Table

**Goals:** Replace the default WP post editor with a CRM-style custom edit screen for quotes. Build the pricing table for managing groups and line items. This is the core editing experience.

**Non-Goals:** No frontend rendering. No cron. No trade presets for pricing (info section presets unaffected).

**Acceptance:** Quote edit screen shows a clean, full-width CRM layout with header bar, pricing table, and sidebar. Default WP editor chrome removed. Admin can add groups with selection modes, add line items (via auto-suggest and custom), edit quantities and formulas, reorder items/groups, and save all data to the custom tables.

### Tasks

- [x] **Scaffold custom quote edit screen layout** · Reasoning: `medium` — replaces default WP editor chrome; must remove/rearrange metaboxes without breaking save flow · (Spec: §Admin UX — Quote Edit Screen)
  - Artifacts: `inc/modules/pricing/class-quote-edit-screen.php`, `assets/css/admin.css` (reusable card/badge/icon-button styles), `assets/css/quote-editor.css` (quote-specific layout)
  - Interfaces: `remove_meta_box()` to clear default WP boxes on `quotes` CPT. `edit_form_after_title` hook injects custom layout. All markup wrapped in `<div id="tah-quote-editor">`. CSS namespaced under `#tah-quote-editor`, all classes use `tah-` prefix. Reusable styles (cards, badges, drag handles, hover-reveal buttons) in `admin.css`. Quote-specific layout in `quote-editor.css` (loaded conditionally on quote edit screen).
  - Depends On: Phase 0
  - Done When: Quote edit screen shows custom layout with header bar (customer info, trade selector, format badge, status), full-width content area for pricing table, and sidebar zone for info sections + publish box. Default WP editor, slug box, and other irrelevant metaboxes removed. Standard WP save/publish flow still works.
  - Pattern — conditional asset loading:
    ```php
    add_action('admin_enqueue_scripts', function($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
        if (get_post_type() !== 'quotes') return;
        wp_enqueue_style('tah-quote-editor',
            get_template_directory_uri() . '/assets/css/quote-editor.css');
        wp_enqueue_script('tah-quote-pricing',
            get_template_directory_uri() . '/assets/js/quote-pricing.js',
            ['jquery', 'jquery-ui-sortable'], null, true);
        wp_localize_script('tah-quote-pricing', 'tahPricingConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tah_pricing_nonce'),
            'labels'  => [ /* ... */ ],
        ]);
    });
    ```

- [x] **Implement pricing table UI** · Reasoning: `high` — largest task in the plan; inline-editable table with formula inputs, drag-and-drop, keyboard nav, badges, and margin column across PHP + JS · (Spec: §Admin UX — Quote Edit Screen, §Development Conventions)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-metabox.php`, `assets/js/quote-pricing.js`, `assets/css/admin.css` (additions), `assets/css/quote-editor.css` (additions)
  - Interfaces: CRM-style line item table within `#tah-quote-editor`. Groups as collapsible sections. Line items with inline-editable fields: drag handle, #, Item, Description, Qty, Rate (formula input), Amount (calculated). JS uses delegated events, jQuery UI Sortable, `wp_localize_script()` for config/nonces.
  - Depends On: Phase 0 (repository), Phase 1 (formula parser)
  - Done When: Groups can be added/edited/removed with name, description, selection_mode, show_subtotal, is_collapsed. Line items rendered as clean table rows with inline fields. Qty field accepts arithmetic formulas (`12*15` → 180) — shows resolved number unfocused, formula focused. Rate field shows resolved price unfocused, formula input focused, with badges (DEFAULT/MODIFIED/CUSTOM). Amount auto-calculates. Admin-only margin column shows profit % when cost data is available. Drag-and-drop reorder for both groups and items. Keyboard navigation: Tab between cells, Enter adds new row. "+Add Group" button works. All data persists on WP save.

- [x] **Implement AJAX draft save** · Reasoning: `medium` — AJAX endpoint + nonce verification + serializing nested group/item data; price recomputation on save · (Spec: §Admin UX — Quote Edit Screen)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-metabox.php`, `assets/js/quote-pricing.js`, `assets/css/quote-editor.css`
  - Interfaces: AJAX endpoint saves quote data (groups, items, formulas) without full page reload. Visual "Saved" flash confirmation.
  - Done When: Clicking save (or Ctrl+S) persists all pricing data via AJAX. Page does not reload. Resolved prices recomputed on save. Visual feedback shown.
  - Pattern — AJAX endpoint:
    ```php
    // Registration (in __construct):
    add_action('wp_ajax_tah_save_pricing', [$this, 'ajax_save_pricing']);
    // No wp_ajax_nopriv_ — admin-only

    // Handler:
    public function ajax_save_pricing() {
        check_ajax_referer('tah_pricing_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized');
        // ... save logic ...
        wp_send_json_success(['resolved_prices' => $prices]);
    }
    ```

- [x] **Implement Duplicate Quote action** · Reasoning: `low` — straightforward data copy across custom tables + redirect · (Spec: §Admin UX — Quote Edit Screen)
  - Artifacts: `inc/modules/pricing/class-quote-edit-screen.php`
  - Interfaces: "Duplicate Quote" button in header bar. Creates a new draft quote with all groups, line items, and formula settings copied from the current quote.
  - Done When: Clicking "Duplicate Quote" creates a new draft and redirects to it. All pricing groups, items, quantities, and formulas are copied. New quote has fresh `_tah_prices_resolved_at` (prices recomputed on first save).

- [x] **Implement auto-suggest endpoint** · Reasoning: `medium` — AJAX search with catalog-type filtering + trade scoping + universal items fallback · (Spec: §Admin UX — Auto-Suggest, §Quote Formats — Catalog Partitioning)
  - Artifacts: `inc/modules/pricing/class-pricing-repository.php`, `inc/modules/pricing/class-quote-pricing-metabox.php`, `assets/js/quote-pricing.js`, `assets/css/quote-editor.css`
  - Interfaces: AJAX/REST endpoint querying `wp_tah_pricing_items` by title, filtered by `catalog_type` matching quote format AND quote's trade, with universal items always shown
  - Done When: Typing in the "add item" field shows matching catalog items from the correct catalog type only. No cross-contamination between standard and insurance items. Selecting one populates title, description, unit_type, unit_price.

- [x] **Implement resolved price computation on save** · Reasoning: `medium` — wires formula engine + rounding settings into save handler; must handle all price_mode variants · (Spec: §Price Modification Model)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-metabox.php`
  - Interfaces: `_tah_prices_resolved_at` post meta
  - Done When: On save, `resolved_price` is computed for each line item using formula engine + rounding settings. `_tah_prices_resolved_at` is set. Totals are computed on-the-fly at render time (`SUM(qty × resolved_price)` per group) — not stored.

- [x] **Wire cascade deletes** · Reasoning: `low` — standard `before_delete_post` hook with DELETE queries · (Spec: §Maintenance — Cascade Deletes)
  - Artifacts: Module bootstrap file
  - Interfaces: `before_delete_post` hook
  - Done When: Permanently deleting a quote removes all its groups and line items from custom tables. Deleting a group removes its line items.
  - Handoff Required: yes — document the delete order (line items before groups before quote data) to avoid orphaned rows if a future module adds foreign-key-like constraints.

- [x] **Implement view tracking** · Reasoning: `low` — simple post meta increment with exclusion checks · (Spec: §Admin UX — Quote View Tracking)
  - Artifacts: `inc/modules/pricing/class-quote-view-tracking.php`, `single-quotes.php`, `inc/modules/pricing/class-quote-edit-screen.php`
  - Interfaces: On `single-quotes.php` load, increment `_tah_quote_view_count` and set `_tah_quote_last_viewed_at`. Skip if `is_user_logged_in()` or `?nt` parameter present.
  - Done When: Customer views increment counter. Admin/logged-in views are excluded. `?nt` parameter excluded. Header bar on edit screen shows "Viewed X times • Last viewed [date]".

- [x] **Implement admin notes field** · Reasoning: `low` — single textarea saving to post meta · (Spec: §Admin UX — Admin Notes)
  - Artifacts: `inc/modules/pricing/class-quote-edit-screen.php`
  - Interfaces: Text area in sidebar of quote edit screen. Saves to `_tah_quote_admin_notes` post meta.
  - Done When: Admin can write/edit notes. Notes persist on save. Never rendered on frontend.

- [ ] Verify Phase 2: create a quote with multiple groups and items, test formula inputs (rate and qty), verify auto-suggest, verify drag-and-drop, verify totals, test AJAX save, test duplicate quote, verify view tracking exclusion, delete a quote and confirm cascade (Manual step #3)

---

## Phase 3 — Frontend Rendering (Phase 1 — Static)

**Goals:** Render pricing module data on the customer-facing quote page, matching the visual style of existing Excel-pasted tables.

**Non-Goals:** No interactive JS (checkboxes/radio buttons). No customer-facing selections. Static only.

**Acceptance:** Quote page shows post content first, then pricing tables. Groups render as styled table sections. Subtotals and grand total display correctly. Quotes without pricing data render unchanged (backward compat).

### Tasks

- [x] **Implement frontend rendering function** · Reasoning: `high` — selection mode logic (all/multi/single), collapsible groups, subtotal rules, discount styling; defines the customer-facing output · (Spec: §Frontend Rendering — Phase 1)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-frontend.php`
  - Interfaces: `tah_render_quote_pricing($post_id)` global function, called from `single-quotes.php`
  - Depends On: Phase 0 (repository)
  - Done When: Each group renders as a styled table with heading and optional description text. Selection mode rules enforced: `all` groups include every item in totals (ignore `is_selected`); `multi` groups include only `is_selected = 1` items; `single` groups include exactly one selected item. Groups with `is_collapsed = true` render collapsed (heading + subtotal visible, "Show details" toggle to expand). Subtotals shown when `show_subtotal = true`. Grand total rendered. Description expandable detail per line item. Discount items render with distinct styling (negative values shown correctly).

- [x] **Update single-quotes.php template** · Reasoning: `low` — 3-line insertion with `function_exists` guards · (Spec: §Frontend Rendering — Render Order)
  - Artifacts: `single-quotes.php`
  - Interfaces: Template rendering order
  - Done When: Post content renders first (via `the_content()`), then `tah_render_quote_pricing(get_the_ID())` renders below, then `tah_render_quote_sections()` for info sections. Quotes with no pricing data show no extra output (backward compat).
  - Pattern — render function registration with backward-compat guard:
    ```php
    // In single-quotes.php:
    the_content();
    if (function_exists('tah_render_quote_pricing')) {
        tah_render_quote_pricing(get_the_ID());
    }
    if (function_exists('tah_render_quote_sections')) {
        tah_render_quote_sections(get_the_ID());
    }
    ```

- [x] **Add frontend CSS** · Reasoning: `medium` — must match existing Excel-pasted table styling + print-friendly `@media print` rules · (Spec: §Frontend Rendering)
  - Artifacts: `assets/css/_content.css`.
  - Done When: Tables match the visual style of existing Excel-pasted quote tables. Print-friendly via `@media print`.

- [ ] Verify Phase 3: view a quote with pricing data on the frontend, confirm layout matches spec, view a quote without pricing data confirms no change, test print preview (Manual step #4)

---

## Phase 4 — Trade Pricing Presets

**Goals:** Allow standard-format trades to define default groups and items that auto-populate when a trade is selected on a new quote.

**Non-Goals:** No cron. No email. No insurance format support (insurance has no groups). Presets include both groups and items.

**Acceptance:** Selecting a standard-format trade on a new quote populates default pricing groups and items. Missing SKUs are skipped with admin notice. Existing quotes are not affected.

### Tasks

- [x] **Add pricing preset field to trade taxonomy** · Reasoning: `medium` — JSON preset builder UI on taxonomy screen; must handle group + item structure · (Spec: §Admin UX — Trade Pricing Presets)
  - Artifacts: `inc/modules/pricing/class-pricing-trade-presets.php`
  - Interfaces: JSON preset builder on Trade add/edit screen. Term meta key: `_tah_trade_pricing_preset`
  - Depends On: Phase 2 (metabox)
  - Done When: Trade edit screen shows a preset builder for configuring default groups and items. Preset saved as JSON to term meta.

- [x] **Wire preset population on trade selection** · Reasoning: `medium` — AJAX handler with SKU lookup, missing-SKU fallback, and overwrite guard · (Spec: §Trade Pricing Presets — Behavior)
  - Artifacts: Quote pricing metabox JS + PHP handler
  - Interfaces: AJAX handler triggered when trade is selected on a new quote
  - Done When: Selecting a trade populates pricing groups and items from preset. Missing SKUs skipped with admin notice. Already-populated quotes are not overwritten.

- [ ] Verify Phase 4: create trade preset, create new quote with that trade, confirm groups/items populate, test with a missing SKU (Manual step #5)

---

## Phase 5 — Cron, Price Locking & Observability

**Goals:** Implement automated price updates, customer price locking, admin observability signals.

**Non-Goals:** No email sending (deferred to email module Phase 2/3). Cron logs the event but does not send mail.

**Acceptance:** Changing a catalog item price triggers recalculation on the next cron run. Price lock prevents recalculation. Admin sees cron status and pricing status column.

### Tasks

- [ ] **Implement price update cron job** · Reasoning: `high` — per-quote independent processing, stale detection, lock skipping, offer window timing, partial-failure isolation · (Spec: §Cron — Central Price Updates)
  - Artifacts: `inc/modules/pricing/class-pricing-cron.php`
  - Interfaces: WP-Cron scheduled event (`tah_pricing_update`). Hook: `tah_cron_frequency` setting. Per-quote independent processing.
  - Depends On: Phase 0 (repository), Phase 1 (formula engine)
  - Done When: Cron finds all quotes with stale `_tah_prices_resolved_at`, skips locked quotes, stores old `resolved_price` to `previous_resolved_price`, recalculates `resolved_price`, sets `_tah_lock_offer_expires_at` to `now + 3 days` on changed quotes (overwrites if existing window is still open — fresh 3 days from latest change), updates `_tah_prices_resolved_at`. Email sending deferred — cron only logs affected quote IDs. Observability options set: `_tah_cron_last_run`, `_tah_cron_last_status` (`success`/`partial`/`error`), `_tah_cron_quotes_updated`, `_tah_cron_last_errors` (array of quote IDs + error messages). Each quote processed independently — one failure doesn't block others.
  - Handoff Required: yes — document the staleness detection logic (`_tah_prices_resolved_at` vs catalog `updated_at`), the 3-day offer window overwrite semantics, and partial-failure isolation (how one quote's error is caught and logged without aborting the batch).
  - Pattern — cron scheduling + cleanup:
    ```php
    // In module boot or __construct:
    if (!wp_next_scheduled('tah_pricing_update')) {
        $freq = get_option('tah_cron_frequency', 'daily');
        wp_schedule_event(time(), $freq, 'tah_pricing_update');
    }
    add_action('tah_pricing_update', [$this, 'run_price_recalculation']);

    // Cleanup on theme switch:
    add_action('switch_theme', function() {
        wp_clear_scheduled_hook('tah_pricing_update');
    });
    ```

- [ ] **Implement price lock endpoint** · Reasoning: `high` — public-facing URL with token auth, price revert logic, expiry window semantics, and cron interaction · (Spec: §Cron — Price Locking)
  - Artifacts: Same cron file or separate handler
  - Interfaces: `?action=tah_lock_price&quote_id=X&token=Y` public URL. Token = `wp_hash(quote_id . post_date)`.
  - Done When: Checks `_tah_lock_offer_expires_at` — if expired, shows friendly "offer expired" message with link to current quote. If valid, **reverts** `resolved_price` to `previous_resolved_price` for all affected line items, sets `_tah_price_locked_until` to `_tah_lock_offer_expires_at` (remaining offer window). After lock expires, next cron run recalculates at current catalog prices. Invalid tokens show error. Admin metabox shows "Copy lock link" button only when `_tah_lock_offer_expires_at > now` and at least one line item has non-NULL `previous_resolved_price`.
  - Handoff Required: yes — document the revert semantics (`resolved_price` ← `previous_resolved_price`), how `_tah_price_locked_until` interacts with cron skip logic, and the token derivation formula (`wp_hash(quote_id . post_date)`).

- [ ] **Add Global Pricing Settings page** · Reasoning: `low` — standard WP settings API with 3 option fields + cron status display · (Spec: §Global Pricing Settings)
  - Artifacts: `inc/modules/pricing/class-pricing-settings.php`
  - Interfaces: WP admin settings page or section. Options: `tah_price_rounding`, `tah_price_rounding_direction`, `tah_cron_frequency`. Cron status display. Quote expiry settings deferred to Phase 2.
  - Done When: Settings page saves and loads all options. Cron status section shows last run time, status, and quote count.

- [ ] **Add pricing status column to quotes admin list** · Reasoning: `low` — single custom column with date/status display · (Spec: §Observability — Admin Quotes List)
  - Artifacts: `inc/cpt/quotes.php` or module file
  - Interfaces: Custom column in `manage_quotes_posts_columns`
  - Done When: Column shows "Current", "Updated [date]", or "Price locked until [date]" per quote. Column is sortable and filterable.

- [ ] Verify Phase 5: change a catalog price, run cron manually via WP-CLI (`wp cron event run tah_pricing_update`), confirm quotes updated, test price lock URL, check settings page and admin column (Manual step #6)

---

## Phase 6 — Insurance Quote Format

**Goals:** Implement the insurance quote format variant — Xactimate-style flat item list with price components, per-line SKU, tax, and F9 notes.

**Non-Goals:** No interactive frontend. No new CPT. Insurance uses the same `quotes` post type with `_tah_quote_format = 'insurance'`.

**Acceptance:** Admin can create an insurance quote with flat line items (no groups UI). Lines have material + labor components, SKU, tax, and optional F9 notes. Frontend renders Xactimate-style table with subtotal, tax total, and grand total.

### Tasks

- [x] **Add format selector to quote edit screen** · Reasoning: `medium` — dropdown that toggles between two metabox variants and filters trade selector by context · (Spec: §Quote Formats)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-metabox.php` (modification)
  - Interfaces: Dropdown/toggle at top of pricing metabox. Sets `_tah_quote_format` post meta. Switches between standard and insurance metabox variants. Trade selector filters trades by `_tah_trade_context` matching the selected format.
  - Done When: Format selector persists on save. Choosing "insurance" hides groups UI, shows flat item list + tax rate field, filters trade selector to insurance/both trades. Choosing "standard" shows normal groups UI, filters trades to standard/both.

- [x] **Add trade context field to trade taxonomy** · Reasoning: `low` — single dropdown on taxonomy edit screen saving to term meta · (Spec: §Quote Formats — Trade Context)
  - Artifacts: `inc/modules/pricing/class-pricing-trade-presets.php` or `inc/admin/class-trade-presets.php` (modification)
  - Interfaces: Dropdown on Trade add/edit screen for `_tah_trade_context` term meta: `standard`, `insurance`, `both` (default: `standard`)
  - Done When: Trade context saves and loads correctly. Trade selectors on quote edit screen filter by context. Catalog admin trade dropdown filters by context matching the active catalog type.

- [x] **Implement insurance metabox variant** · Reasoning: `high` — alternate UI mode with material/labor composition, per-line tax, F9 notes toggle, and insurance-specific auto-suggest filtering · (Spec: §Quote Formats, §Database Schema — insurance columns)
  - Artifacts: Same metabox file + `assets/js/quote-pricing.js` (additions)
  - Interfaces: Flat line item list with columns: line #, title, SKU, material cost, labor cost, unit price (computed), qty, tax rate, note (F9 toggle). Per-quote sales tax rate field (`_tah_quote_tax_rate`). Auto-suggest queries `catalog_type = 'insurance'` only.
  - Depends On: Phase 2 (base metabox)
  - Done When: Insurance items save `material_cost`, `labor_cost`, `line_sku`, `tax_rate`, `note` to line items table. Unit price auto-computed from material + labor. All items in one implicit group (`group_id` references a single auto-created group). Totals include subtotal + tax total + grand total.

- [ ] **Implement insurance frontend template variant** · Reasoning: `medium` — format-aware branch in existing renderer with Xactimate-style table and subtotal/tax/grand total · (Spec: §Frontend Rendering — Insurance Format)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-frontend.php` (modification)
  - Interfaces: Format-aware rendering in `tah_render_quote_pricing()`
  - Depends On: Phase 3 (base frontend rendering)
  - Done When: Insurance quotes render Xactimate-style table: #, Description, SKU, Qty, Unit Price, Tax, Total. F9 notes render as indented detail rows. Subtotal, tax total, grand total at bottom. Trade categories as optional visual section dividers.

- [ ] **Add insurance frontend CSS** · Reasoning: `low` — additive CSS for insurance table variant + print rules · (Spec: §Frontend Rendering — Insurance Format)
  - Artifacts: `assets/css/_content.css`
  - Done When: Insurance table styled distinctly from standard format. Print-friendly.

- [ ] Verify Phase 6: create insurance trades with `_tah_trade_context = 'insurance'`, add insurance catalog items, create an insurance quote, confirm trade selector shows only insurance trades, add items confirming no standard items appear, save, view frontend, confirm Xactimate-style rendering with correct totals (Manual step #7)

---

## Verification Plan

### Manual Verification Checklist

| # | Step | Covers |
|---|---|---|
| 1 | Activate theme → check DB for 3 tables + `tah_pricing_db_version` option | Phase 0: Schema + versioning |
| 2 | Admin → Pricing Catalog → Add items, edit prices, confirm history appends, deactivate item | Phase 1: Catalog CRUD + price history |
| 3 | Admin → Quote edit → Add groups + line items, test formulas (`$`, `$+150`, `$*1.1`, `1850`, `-200`), verify auto-suggest, reorder, save, confirm DB data | Phase 2: Metabox + formula engine |
| 4 | View quote on frontend → confirm post content renders first, then pricing tables, then info sections. Verify backward compat on old quotes. Print preview. | Phase 3: Frontend rendering |
| 5 | Admin → Trade edit → Create pricing preset → New quote → Select trade → Confirm groups populate | Phase 4: Trade presets |
| 6 | Change catalog price → Run `wp cron event run tah_pricing_update` → Confirm quote prices updated, `previous_resolved_price` set, cron status options set. Test lock URL. Check admin column. | Phase 5: Cron + observability |
| 7 | Admin → New Quote → Set format to "insurance" → Add items with material/labor/SKU/tax/notes → Save → View frontend → Confirm Xactimate-style table, F9 notes, subtotal + tax + grand total | Phase 6: Insurance format |

### Test Debt (Automated)

No automated test harness currently exists. The following should be covered by automated tests in a future phase:

- [ ] `TAH_Price_Formula::parse()` — unit tests for all formula variants
- [ ] `TAH_Price_Formula::resolve()` — unit tests for rounding modes
- [ ] `TAH_Pricing_Repository` — integration tests for CRUD operations
- [ ] Cron recalculation logic — integration test with mock data
- [ ] Cascade delete behavior — integration test

---

## Known Debt & Open Questions

- [ ] **No automated tests yet.** The project has no test harness. Suggest adding PHPUnit + WP test framework as a future foundational task.
- [ ] **Email sending deferred.** Cron logs price updates but does not send re-engagement emails. Requires email module (Phase 2/3 per spec).
- [ ] **Interactive frontend deferred.** Phase 2 of frontend (checkboxes/radio buttons + live totals JS) is not in this plan.
- [ ] **WP-Cron reliability.** For production, user should configure a real system crontab (`wp cron event run --due-now`) per spec recommendation.
- [ ] **Catalog bulk import.** No mechanism to import existing pricing from Excel/CSV. Likely needed for initial population.
- [ ] **Price components for standard quotes.** `material_cost` and `labor_cost` columns exist on all line items but are only used by insurance quotes. Standard quotes will adopt price components in a future phase.
- [ ] **Quote expiry behavior undefined.** `tah_quote_expiry_enabled` and `tah_quote_expiry_days` settings are deferred to Phase 2. Expiry behavior (frontend banner, prevent acceptance, status change) needs design.
- [ ] **Table prefix.** Implementation must use `$wpdb->prefix . 'tah_*'` — not hardcoded `wp_tah_*` — to support non-default WP prefixes.
- [ ] **Visual status pipeline.** Replace WP status dropdown with visual pipeline (Draft → Sent → Viewed → Accepted/Declined). Requires email/acceptance module.
- [ ] **Activity log sidebar.** Quote-level event log ("Price updated Feb 14", "Sent to customer Feb 15"). Requires email/acceptance module.
- [ ] **PDF export.** One-click "Download PDF" renders frontend template to PDF via Dompdf or similar.
- [ ] **Quote revision history.** Version diffing within a quote ("V1: $12,500 → V2: $13,000 — added hardwood upgrade").
- [ ] **Quick stats dashboard widget.** WP dashboard widget: quotes sent, total value, close rate, average per month. Aggregate queries on existing data.
- [ ] **Follow-up reminders.** Date field on edit screen + admin dashboard notice ("3 quotes need follow-up today"). Pairs with view tracking.
- [ ] **Job site photos per quote.** Gallery field (WP media library) on quote edit screen. Renders on frontend above/alongside pricing table.

---

## Delivery Checklist

The pricing module is "done" when all of the following hold:

- All 7 manual verification steps pass (see Verification Plan above).
- Standard quotes: catalog → groups → line items → formula → resolved price → frontend table pipeline works end-to-end.
- Insurance quotes: flat item list → material/labor composition → tax → Xactimate-style frontend pipeline works end-to-end.
- Cron recalculation updates stale quotes without affecting locked quotes.
- Price lock URL reverts prices within the offer window and expires gracefully.
- Backward compat: existing quotes without pricing data render unchanged on the frontend.
- No hardcoded `wp_` table prefix — all queries use `$wpdb->prefix`.
- Cascade deletes leave no orphaned rows in custom tables.
- Module can be disabled via `apply_filters('tah_module_pricing_enabled', false)` without errors.

---

## Plan Maintenance

- Checkboxes are the canonical execution tracker. Mark completed items `[x]` and preserve them verbatim — do not delete, merge, or reorder completed tasks.
- If `docs/pricing_module.md` changes behavior or contracts, add `[ ] Rebase plan to current spec` at the top of Phase 0 before adding new work.
- New work must be added explicitly as a task (with a spec section reference) or as Known Debt. Do not silently expand scope.
