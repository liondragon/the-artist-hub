# Developer Guide (Theme Modules + Gotchas)

Local site that has this theme activated: https://wphub.local/wp-admin/

## Purpose
This repo has a few theme subsystems that are easy to break via load-order or WordPress admin quirks. This guide documents:
- where key features live,
- how the module system is wired,
- the data model and data flow for major features,
- CSS class and JS conventions used in admin UI, and
- the common gotchas when editing admin UI, metaboxes, and editor integration.

---

## Theme Bootstrap

### Entry Point
`functions.php` loads everything in explicit order:

1. `inc/class-the-artist-hub.php` → singleton `The_Artist_Hub::get_instance()`
   - Theme setup (menus, image sizes, theme supports)
   - Frontend asset enqueueing (`style.css`, `functions.js`)
   - Performance (CSS preload, script defer)
2. `inc/widgets.php`, `inc/template-tags.php`, `inc/notes_function.php`
3. `inc/editor-config.php`, `inc/editor-filters.php`, `inc/search-filters.php`
4. `inc/modules/class-module-registry.php` → `TAH_Module_Registry::boot()`
5. `inc/admin.php` (admin bar cleanup, styles, login, security)
6. `inc/users.php`, `inc/comments.php`, `inc/custom_post_types.php`

### Key Class: `The_Artist_Hub`
- **Singleton** — `The_Artist_Hub::get_instance()`
- Hooks into `after_setup_theme`, `wp_enqueue_scripts`, `wp_head`, `script_loader_tag`
- Registers nav menus: `primary`, `footer-menu`, `res-menu`
- Image sizes: `large` (300×300), `medium` (150×150), `thumbnail` (65×65), `wide` (440×270 crop)

---

## Module System

### Registry
- `inc/modules/class-module-registry.php` — `TAH_Module_Registry::boot()`
- Idempotent (static `$booted` guard)
- Modules are loaded in **explicit, fixed order** — currently Info Sections + Pricing

### Module Contract (Conventions)
Each module must provide:
- `::boot()` — idempotent include/bootstrap method (guard with `$booted` flag)
- `::is_enabled()` — runtime enable flag (default ON), typically via `apply_filters()`

### Adding A New Module (Checklist)
1. Create a module folder: `inc/modules/<module-name>/`
2. Add a bootstrap class file: `inc/modules/<module-name>/class-<module-name>-module.php`
3. Ensure `boot()` is safe to call multiple times (guard with a static boolean).
4. Add an enable toggle (default ON) via a filter:
   - Example: `apply_filters('tah_module_<module>_enabled', true)`
5. Register the module in `inc/modules/class-module-registry.php` (explicit order).
6. Update this document with the module purpose and entry points.

---

## Naming Conventions

### Post Meta
- All post meta keys use `_tah_` prefix with underscored names
- Pattern: `_tah_{feature}_{field}` (e.g., `_tah_quote_format`, `_tah_prices_resolved_at`)
- Info sections use sub-pattern: `_tah_qs_{section_key}_{suffix}`

### Term Meta
- Trade term meta uses `_tah_trade_` prefix (e.g., `_tah_trade_default_sections`, `_tah_trade_context`, `_tah_trade_pricing_preset`)
- Term meta is the standard extensibility mechanism for adding module-specific data to trades

### WP Options
- Theme options use `tah_` prefix (e.g., `tah_pricing_db_version`, `tah_price_rounding`)

### CSS Classes
- All custom CSS classes use `tah-` prefix (e.g., `tah-group-card`, `tah-drag-handle`, `tah-icon-button`)
- Zero reliance on WP admin CSS classes for styling — only for structure/hooks

---

## Custom Post Types & Taxonomies

### Registration
CPT definitions live in `inc/cpt/`:

| File | CPT/Taxonomy | Slug | Purpose |
|------|-------------|------|---------|
| `quotes.php` | `quotes` | `quotes` | Customer quotes/estimates |
| `equipment.php` | `equipment` | `equipment` | Equipment catalog |
| `projects.php` | `projects` | `projects` | Portfolio projects |
| `vehicles.php` | `vehicles` | `vehicles` | Company vehicles |
| `template-parts.php` | `tah_template_part` | — | Global Info Section library |

### Taxonomy: `trade`
- Registered in `quotes.php` for the `quotes` CPT
- Represents a type of trade (e.g., "Hardwood Floors", "Tile")
- Trade terms use **term meta** as a general extensibility pattern:

| Term Meta Key | Module | Purpose |
|---|---|---|
| `_tah_trade_default_sections` | Info Sections | Ordered preset list of section keys |
| `_tah_trade_context` | Pricing | `standard`, `insurance`, or `both` — controls trade visibility per quote format |
| `_tah_trade_pricing_preset` | Pricing | JSON preset of default groups and line items |

### Loading Order
- CPTs are loaded via `inc/custom_post_types.php` (from `functions.php`)
- `template-parts.php` is additionally loaded by the Info Sections module bootstrap

---

## Custom Database Tables

### When to Use
Use custom tables (not post meta) when data is:
- Relational (parent-child: quote → groups → line items)
- Queried in aggregate (catalog search, price recalculation across all quotes)
- Expected to migrate to another framework (Laravel, Next.js)

### Pricing Module Tables
- `tah_pricing_items`: Catalog items (SKU, rate, history)
- `tah_quote_groups`: Sections within a quote
- `tah_quote_line_items`: Individual line items linked to groups and catalog items

### Schema Migration Pattern
- Migration files live in `inc/migrations/`
- Each module tracks its schema version via a WP option: `tah_{module}_db_version`
- On `admin_init` (or module boot), compare stored version against current. If outdated, run `dbDelta()`:

```php
$installed_version = get_option('tah_pricing_db_version', '0');
if (version_compare($installed_version, TAH_PRICING_DB_VERSION, '<')) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    require_once get_template_directory() . '/inc/migrations/pricing-tables.php';
    tah_pricing_create_tables();
    update_option('tah_pricing_db_version', TAH_PRICING_DB_VERSION);
}
```

### `$wpdb` Conventions
- Always use `$wpdb->prefix . 'tah_*'` — never hardcode `wp_tah_*`
- Use `$wpdb->prepare()` for all queries with user input
- Wrap multi-table operations in `$wpdb->query('START TRANSACTION')` / `COMMIT`

---

## Pricing Module (Current Map)

### Boot + Core
- Module bootstrap: `inc/modules/pricing/class-pricing-module.php`
- Repository (custom tables CRUD): `inc/modules/pricing/class-pricing-repository.php`
- Formula parser/resolver: `inc/modules/pricing/class-price-formula.php`

### Admin (Quote Edit + Save Path)
- Quote edit shell/layout: `inc/modules/pricing/class-quote-edit-screen.php`
- Pricing metabox UI + request handlers: `inc/modules/pricing/class-quote-pricing-metabox.php`
- Group payload normalization/mapping helper: `inc/modules/pricing/class-pricing-group-payload-processor.php`
- Line-item payload build/resolution helper: `inc/modules/pricing/class-pricing-line-item-payload-processor.php`

### Frontend
- Pricing renderer: `inc/modules/pricing/class-quote-pricing-frontend.php`
  - Format-aware branch: `standard` grouped tables vs `insurance` Xactimate-style table
- Frontend template wiring: `single-quotes.php` calls `tah_render_quote_pricing()` between `the_content()` and `tah_render_quote_sections()`

### Trade + Catalog
- Catalog admin: `inc/modules/pricing/class-pricing-catalog-admin.php`
- Trade pricing preset + trade context fields: `inc/modules/pricing/class-pricing-trade-presets.php`

### Maintenance
- View tracking: `inc/modules/pricing/class-quote-view-tracking.php`
- Cascade delete hook registration: `TAH_Pricing_Module::handle_before_delete_post()`


---

## Quote Editor & Metaboxes

### Purpose
To provide a consistent, premium CRM experience while leveraging the stability of WordPress core UI components.

### Strategy: "Visual Stacking, Behavioral Forking"
The Quote Editor (`class-quote-edit-screen.php`) standardizes all metaboxes (Quote Info, Pricing, Note to Customer) using a unified approach:

1.  **Visual Stacking (The "Look")**:
    -   All metaboxes use **standard WordPress markup**: `.postbox`, `.postbox-header`, `.hndle`, `.inside`, `.handlediv`.
    -   A **Scoped Skin** applies our custom design ("The Artist Hub" card style) to these standard elements.
    -   **Scope Selector**: `body.tah-quote-editor-enabled #tah-quote-editor` ensures styles never leak to other admin screens.
    -   **CSS File**: `assets/css/quote-editor.css` owns the layout and skin.

2.  **Behavioral Forking (The "Act")**:
    -   **Standard Behavior**: Most metaboxes (Quote Info, Pricing) rely 100% on native WordPress toggle and drag-and-drop scripts (`postbox.js`).
    -   **Custom Fork ("Note to a Customer")**: This specific metabox contains a complex TinyMCE editor that conflicts with `postbox.js`.
        -   **Opt-Out Class**: `.tah-postbox-custom-toggle` marks it for custom handling.
        -   **Event Blocking**: JS explicitly stops propagation on header clicks to prevent WP from interfering.
        -   **Custom Trigger**: A dedicated button `.tah-toggle-trigger` handles the collapse/expand logic.
        -   **Resize Hook**: Triggers a window resize event on toggle to fix TinyMCE layout glitches.

### Key Classes
| Class | Purpose |
|-------|---------|
| `.tah-quote-editor-enabled` | Body class added by `class-quote-edit-screen.php` to scope styles. |
| `.tah-postbox-custom-toggle` | Marker class on a `.postbox` to opt-out of WP `postbox.js` logic. |
| `.tah-toggle-trigger` | The custom toggle button used in "Note to a Customer". |

---

## Info Sections Module

### What It Is
The Info Sections system is a metabox-driven content system for Quotes:
- **Global Info Sections** (library) live as `tah_template_part` posts.
- **Trades** define preset recipes (ordered key lists) via taxonomy **term meta**.
- **Quotes** store per-quote order and overrides in **post meta**.

### Data Model

```
┌──────────────────────┐
│  tah_template_part   │  Global library of info sections
│  (CPT posts)         │  Each has: title, content, key (post_meta)
└────────┬─────────────┘
         │ key references
         ▼
┌──────────────────────┐
│  Trade (taxonomy)    │  term_meta: tah_trade_sections = ['key1','key2',...]
│  term meta "recipe"  │  Ordered list of which sections this trade uses
└────────┬─────────────┘
         │ initializes
         ▼
┌──────────────────────┐
│  Quote (post)        │  post_meta per section:
│  post meta overrides │  - _tah_section_order = ['key1','key2',...]
│                      │  - _tah_qs_{key}_enabled = '1'|'0'
│                      │  - _tah_qs_{key}_mode    = 'default'|'custom'
│                      │  - _tah_qs_{key}_content = '...'
│                      │  - _tah_qs_{key}_title   = '...'
└──────────────────────┘
```

### Bootstrapping + Toggle
- Module bootstrap: `inc/modules/info-sections/class-info-sections-module.php`
- Registry: `inc/modules/class-module-registry.php`
- Enable flag (default ON):
  - `add_filter('tah_module_info_sections_enabled', '__return_false');`
- Admin-only: `class-trade-presets.php` is loaded only when `is_admin()` is true

### Key Files
- Global library CPT + key metabox/validation:
  - `inc/cpt/template-parts.php`
- Trade presets UI/save:
  - `inc/admin/class-trade-presets.php` → `TAH_Trade_Presets`
- Quote editor metabox + persistence + frontend rendering:
  - `inc/admin/class-quote-sections.php` → `TAH_Quote_Sections`
- Admin UI interactions (sortable, controls, tools dropdown, etc.):
  - `assets/js/quote-sections.js`
- Admin styling (metabox UI, icons, etc.):
  - `assets/css/admin.css`
- Quote frontend template integration:
  - `single-quotes.php` (renders `the_content()`, then pricing via `tah_render_quote_pricing()`, then sections via `tah_render_quote_sections()`)

### Quote Sections Flow
1. When a new Quote is created and assigned a Trade, `maybe_initialize_quote_sections_order()` copies the Trade's recipe into the Quote's `_tah_section_order` meta.
2. The metabox (`render_quote_sections_metabox()`) renders each section as a sortable list item with inline editing capabilities.
3. Per-section overrides (enabled, mode, content, title) are stored as individual post_meta keys.
4. On the frontend, `render_sections_frontend()` reads the order and renders enabled sections.

### Trade Presets Flow
1. `render_field_add()` / `render_field_edit()` render the recipe editor on the Trade taxonomy add/edit screens.
2. `render_sortable_sections_list()` outputs a sortable `<ul>` with all global sections, marking which are in the recipe.
3. Each row has a hidden input (`tah_trade_sections[key] = 1|0`) that tracks inclusion.
4. `save_meta()` reads the submitted associative array, filters for `value === '1'`, and saves the ordered key list to term meta.


---

## Pricing Module

### What It Is
The Pricing Module provides a structured way to manage catalog items and build quotes with line-item pricing. It replaces free-form pricing with a data-driven approach.

### Key Concepts
- **Catalog Items**: Stored in `tah_pricing_items`. Have a SKU, base price, and trade association.
- **Quote Formats**: controlled by `_tah_quote_format` post meta.
  - `standard`: Grouped line items (e.g., "Hallway", "Kitchen").
  - `insurance`: Flat list with material/labor breakdown and tax calculations.
- **Price Formula**: Line items can use formulas to modify base prices:
  - `$` (Default): Use catalog price.
  - `$ +150`: Add $150 to catalog price.
  - `$ *1.2`: Add 20% to catalog price.
  - `500`: Override with flat $500.

### Data Flow
1. **Catalog Management**: Admin manages items in "Pricing Catalog".
2. **Quote Creation**:
   - User selects a Trade.
   - Initial groups populated from Trade Presets via `_tah_trade_pricing_preset` (JSON).
3. **Editing**:
   - `TAH_Quote_Pricing_Metabox` loads current data from custom tables.
   - User edits groups/items via AJAX (`tah_save_pricing`).
   - `TAH_Price_Formula` resolves final prices before saving.
4. **Display**:
   - `class-quote-pricing-frontend.php` renders the table on `single-quotes.php`.

---


## Admin CSS Architecture

### Files
| File | Scope | Loaded via |
|------|-------|-----------|
| `assets/css/variables.css` | CSS custom properties (colors, fonts) | `@import` in both `style.css` and `admin.css` |
| `assets/css/admin.css` | WP admin, login, admin bar — **reusable components** | `load_theme_admin_styles()` in `inc/admin.php` (global) |
| `assets/css/quote-editor.css` | Quote edit screen — **screen-specific layout** | Conditional enqueue (see below) |
| `assets/css/_content.css` | Shared content styles (TinyMCE + frontend) | `add_editor_style()` + `@import` in `style.css` |
| `style.css` | Frontend only | `The_Artist_Hub::enqueue_assets()` |

> **Gotcha:** Frontend `style.css` does NOT load in WP admin. Use `admin.css` for all admin/editor/login styling.

### CSS Split Strategy
- **`admin.css`** — reusable components shared across all modules: card styles, badge styles, `tah-icon-button` hover-reveal, `tah-drag-handle`, form field styling, state classes
- **Module-specific CSS** (e.g., `quote-editor.css`) — layout and structure unique to one screen. Loaded conditionally:

```php
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
    if (get_post_type() !== 'quotes') return;
    wp_enqueue_style('tah-quote-editor', get_template_directory_uri() . '/assets/css/quote-editor.css');
});
```

### CSS Class Conventions (Info Sections UI)

#### Layout Classes
| Class | Element | Purpose |
|-------|---------|---------|
| `.tah-quote-sections-list` | `<ul>` | Quote sections sortable container |
| `.tah-trade-sections-sortable` | `<ul>` | Trade recipe sortable container (has border, scroll, background) |
| `.tah-quote-section-item` | `<li>` | Individual section row (shared by both Quote and Trade) |
| `.tah-quote-section-title-row` | `<div>` | Flex row: handle + title + action buttons |
| `.tah-inline-enable` | `<label>` | Title wrapper with `margin-right: auto` to push buttons right |

#### Interactive Elements
| Class | Element | Purpose |
|-------|---------|---------|
| `.tah-drag-handle` | `<span>` | 6-dot SVG drag handle (cursor: grab) |
| `.tah-icon-button` | `<button>` | Generic icon button (hidden by default, shown on row hover) |
| `.tah-toggle-enabled` | `<button>` | Quote section visibility toggle (show/hide) |
| `.tah-trade-toggle-enabled` | `<button>` | Trade recipe inclusion toggle |
| `.tah-edit-section` | `<button>` | Expand/collapse section editor |
| `.tah-delete-section` | `<button>` | Delete section (red on hover) |
| `.tah-reset-section` | `<button>` | Revert to default (shown only on modified sections on hover) |

#### State Classes
| Class | Applied to | Trigger | Effect |
|-------|-----------|---------|--------|
| `.tah-section-disabled` | `.tah-quote-section-item` | JS toggle | `opacity: 0.5` — greyed out |
| `.tah-trade-section-disabled` | `.tah-quote-section-item` | JS toggle / PHP initial | `opacity: 0.5` — greyed out |
| `.tah-section-modified` | `.tah-quote-section-item` | PHP render | Shows `.tah-reset-section` on hover |

#### Visibility Pattern
Icon buttons (`.tah-icon-button`, `.tah-delete-section`) follow a **hover-reveal** pattern:
- Default: `opacity: 0` (hidden)
- On `.tah-quote-section-item:hover`: `opacity: 1` (visible)
- On `:focus-visible`: `opacity: 1` (accessible keyboard nav)

> **Important:** When adding new action buttons to section rows, use the `.tah-icon-button` class to inherit this hover-reveal behavior. If the button must always be visible, add a specific override: `.your-button { opacity: 1 !important; }`

---

## Admin JavaScript Architecture

### Files
| File | Purpose | Dependencies |
|------|---------|-------------|
| `assets/js/quote-sections.js` | Info Sections UI (sortable, CRUD, toggles) | jQuery, jQuery UI Sortable |
| `assets/js/custom-script.js` | TinyMCE template button/dropdown | jQuery |
| `assets/js/functions.js` | Frontend JS | None |

### `quote-sections.js` Structure
This file uses an IIFE wrapping jQuery and is organized into:

1. **Helper functions** — `escHtml()`, `setEnabledButtonState()`, `initializeEnabledStates()`
2. **Inline section creation** — Creating new custom sections with title input
3. **Sortable initialization** — jQuery UI Sortable for drag-and-drop reordering
4. **Event handlers** — Delegated via `$(document).on('click', selector, handler)`:
   - `.tah-toggle-enabled` → Toggle section enabled state (Quote)
   - `.tah-trade-toggle-enabled` → Toggle section in recipe (Trade)
   - `.tah-edit-section` → Expand/collapse inline editor
   - `.tah-delete-section` → Remove section from list
   - `.tah-actions-toggle` → Open/close actions dropdown menu
   - `.tah-reset-section` → Revert section to default content

### Key JS Function: `setEnabledButtonState($item, enabled, labels)`
Central function for toggling section state. It:
- Updates hidden input value (`1` / `0`)
- Swaps dashicon class (`dashicons-visibility` ↔ `dashicons-hidden`)
- Updates `aria-label` and `title`
- Toggles `.tah-section-disabled` class on the row

### Server-side Config
`TAH_Quote_Sections::enqueue_admin_assets()` passes config to JS via `wp_localize_script()`:
```php
wp_localize_script('tah-quote-sections', 'tahQuoteSectionsConfig', [
    'labels' => [...],
    'nonce'  => wp_create_nonce('...'),
    // ...
]);
```

### Adding New Interactive Behaviors
1. Add PHP markup in the render method with appropriate CSS classes
2. For Trade UI: use `class-trade-presets.php` `render_sortable_sections_list()`
3. For Quote UI: use `class-quote-sections.php` `render_quote_sections_metabox()`
4. Add delegated event handler in `quote-sections.js` using `$(document).on()`
5. Add CSS in `admin.css` — use existing classes where possible

### Conditional Asset Loading
Module-specific JS files should only load on relevant screens:

```php
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
    if (get_post_type() !== 'quotes') return;
    wp_enqueue_script('tah-quote-pricing', get_template_directory_uri() . '/assets/js/quote-pricing.js',
        ['jquery', 'jquery-ui-sortable'], null, true);
    wp_localize_script('tah-quote-pricing', 'tahPricingConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('tah_pricing_nonce'),
        'labels'  => [...],
    ]);
});
```

---

## AJAX Endpoints

### Convention
All AJAX actions use `tah_` prefix. Each endpoint:
1. Verifies nonce via `check_ajax_referer('tah_{action}_nonce', 'nonce')`
2. Checks capability via `current_user_can('edit_posts')` (or more specific cap)
3. Returns JSON via `wp_send_json_success($data)` / `wp_send_json_error($message)`

### Registration Pattern
```php
add_action('wp_ajax_tah_save_pricing', [$this, 'ajax_save_pricing']);
add_action('wp_ajax_tah_auto_suggest', [$this, 'ajax_auto_suggest']);
// No wp_ajax_nopriv_ — admin-only endpoints
```

> **Gotcha:** AJAX endpoints are admin-only unless you also register `wp_ajax_nopriv_` hooks. For the pricing module, all endpoints are admin-only.

---

## WP-Cron

### Scheduling Pattern
Register custom cron events on module boot. Unregister on module disable/theme deactivation.

```php
// Schedule
if (!wp_next_scheduled('tah_pricing_cron')) {
    wp_schedule_event(time(), 'daily', 'tah_pricing_cron');
}
add_action('tah_pricing_cron', [$this, 'run_price_recalculation']);

// Cleanup on theme switch
add_action('switch_theme', function() {
    wp_clear_scheduled_hook('tah_pricing_cron');
});
```

### Custom Intervals
If the module needs non-standard intervals (e.g., configurable frequency), use `cron_schedules` filter:

```php
add_filter('cron_schedules', function($schedules) {
    $schedules['tah_custom_interval'] = [
        'interval' => 3600, // seconds
        'display'  => 'TAH Custom Interval',
    ];
    return $schedules;
});
```

> **Production:** WP-Cron is request-triggered. For reliable scheduling, configure a real system crontab: `* * * * * cd /path/to/wp && wp cron event run --due-now`

---

## Custom Admin Pages

### Registration Pattern
For module-specific admin pages (not metaboxes), use `add_menu_page` / `add_submenu_page`:

```php
add_action('admin_menu', function() {
    add_menu_page(
        'Pricing Catalog',           // Page title
        'Pricing Catalog',           // Menu title
        'manage_options',            // Capability
        'tah-pricing-catalog',       // Menu slug
        [$this, 'render_catalog_page'], // Callback
        'dashicons-tag',             // Icon
        30                           // Position
    );
});
```

- Page slug uses `tah-` prefix
- Callback renders the full page HTML inside `<div class="wrap">` container
- Conditional asset loading: check `$_GET['page']` in `admin_enqueue_scripts`

---

## Editor Template Button (TinyMCE)
- Button is added via `media_buttons` in `inc/editor-config.php`
- JS: `assets/js/custom-script.js` (dropdown creation/animation)
- Templates: `assets/templates/quotes/*.html`
- Template metadata parsed from HTML comment headers

---

## Block Editor
- Gutenberg is **disabled site-wide**
- Classic Editor (TinyMCE) is used.
- Editor styles loaded: `variables.css` + `_content.css` via `add_editor_style()`

---

## Frontend Templates

| Template | CPT | Notes |
|----------|-----|-------|
| `single-quotes.php` | `quotes` | Renders `the_content()`, then pricing (`tah_render_quote_pricing()`), then info sections (`tah_render_quote_sections()`) |
| `single-projects.php` | `projects` | Project detail page |
| `archive-equipment.php` | `equipment` | Equipment listing |
| `front-page.php` | — | Homepage |

### Page Templates
- `page-templates/` directory contains specialized page layouts

### Adding New Render Functions to `single-quotes.php`
The quote template calls render functions in order. To add a new module's output:
1. Define a global render function in your module (e.g., `tah_render_quote_pricing($post_id)`)
2. Guard with `function_exists()` in the template for backward compatibility
3. Add the call in `single-quotes.php` in the correct position (pricing tables go after `the_content()`, before info sections)

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

---

## Common Gotchas

### Admin Styling
- Frontend `style.css` does NOT load in WP admin — use `assets/css/admin.css`
- `admin.css` is enqueued via `load_theme_admin_styles()` in `inc/admin.php`
- Module-specific CSS (e.g., `quote-editor.css`) must be conditionally enqueued with screen ID check
- Icon buttons are hidden by default (`opacity: 0`) — always-visible icons need explicit overrides

### Metabox Conflicts (Quote Editor)
- The "Note to a Customer" metabox uses `.tah-postbox-custom-toggle` and aggressive event blocking (`stopPropagation`) to prevent `postbox.js` from breaking the editor. Do not remove this class or the JS handler in `class-quote-edit-screen.php`.

### Info Sections
- `class-trade-presets.php` is loaded **only on admin** (`is_admin()` check in module boot)
- Trade recipe uses **term meta** (not post meta) — use `get_term_meta()` / `update_term_meta()`
- Quote sections use **post meta** with namespaced keys: `_tah_qs_{section_key}_{suffix}`
- Section keys come from `tah_template_part` post meta `_tah_section_key` — they are slug-like identifiers
- When adding/removing global library sections, existing Trade recipes and Quote orders will still reference old keys — there is no automatic cascade

### Custom Tables
- Always use `$wpdb->prefix` — never hardcode `wp_` prefix
- `dbDelta()` is finicky — column definitions must match exactly (no trailing commas, specific spacing)
- Test schema migrations on both fresh installs and upgrades

### AJAX
- Always verify nonce first, then check capability
- Use `wp_send_json_success()` / `wp_send_json_error()` — never `echo` + `die()`
- Admin-only endpoints: only register `wp_ajax_` hooks, not `wp_ajax_nopriv_`

### JavaScript
- All JS event handlers use **delegated events** (`$(document).on(...)`) since rows can be dynamically added
- jQuery UI Sortable requires `jquery-ui-sortable` as a script dependency
- Module JS is enqueued only on relevant screens — check `enqueue_admin_assets()` hooks with screen ID
- Pass server config to JS via `wp_localize_script()` — never hardcode URLs or nonces

### Load Order
- Modules boot via `TAH_Module_Registry::boot()` which runs during theme include
- Admin hooks fire later — do not assume admin context at include time
- Admin-only classes self-instantiate at file bottom: `new TAH_Class_Name();`
- Schema migrations run on `admin_init` or module boot — guard with version check
