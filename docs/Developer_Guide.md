# Developer Guide (Theme Modules + Gotchas)

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
- Modules are loaded in **explicit, fixed order** — currently only Info Sections

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
- Each Trade term can store an **Info Sections Recipe** (ordered preset list) in term meta

### Loading Order
- CPTs are loaded via `inc/custom_post_types.php` (from `functions.php`)
- `template-parts.php` is additionally loaded by the Info Sections module bootstrap

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
  - `single-quotes.php` (renders sections if `tah_render_quote_sections()` exists)

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

## Admin CSS Architecture

### Files
| File | Scope | Loaded via |
|------|-------|-----------|
| `assets/css/variables.css` | CSS custom properties (colors, fonts) | `@import` in both `style.css` and `admin.css` |
| `assets/css/admin.css` | WP admin, login, admin bar | `load_theme_admin_styles()` in `inc/admin.php` |
| `assets/css/_content.css` | Shared content styles (TinyMCE + frontend) | `add_editor_style()` + `@import` in `style.css` |
| `style.css` | Frontend only | `The_Artist_Hub::enqueue_assets()` |

> **Gotcha:** Frontend `style.css` does NOT load in WP admin. Use `admin.css` for all admin/editor/login styling.

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
| `single-quotes.php` | `quotes` | Renders info sections via `tah_render_quote_sections()` |
| `single-projects.php` | `projects` | Project detail page |
| `archive-equipment.php` | `equipment` | Equipment listing |
| `front-page.php` | — | Homepage |

### Page Templates
- `page-templates/` directory contains specialized page layouts

---

## Common Gotchas

### Admin Styling
- Frontend `style.css` does NOT load in WP admin — use `assets/css/admin.css`
- `admin.css` is enqueued via `load_theme_admin_styles()` in `inc/admin.php`
- Icon buttons are hidden by default (`opacity: 0`) — always-visible icons need explicit overrides

### Info Sections
- `class-trade-presets.php` is loaded **only on admin** (`is_admin()` check in module boot)
- Trade recipe uses **term meta** (not post meta) — use `get_term_meta()` / `update_term_meta()`
- Quote sections use **post meta** with namespaced keys: `_tah_qs_{section_key}_{suffix}`
- Section keys come from `tah_template_part` post meta `_tah_section_key` — they are slug-like identifiers
- When adding/removing global library sections, existing Trade recipes and Quote orders will still reference old keys — there is no automatic cascade

### JavaScript
- All JS event handlers use **delegated events** (`$(document).on(...)`) since section rows can be dynamically added
- jQuery UI Sortable requires `jquery-ui-sortable` as a script dependency
- `quote-sections.js` is enqueued only on the Quote edit screen and Trade taxonomy screens — check `enqueue_admin_assets()` hooks

### Load Order
- Modules boot via `TAH_Module_Registry::boot()` which runs during theme include
- Admin hooks fire later — do not assume admin context at include time
- `class-trade-presets.php` self-instantiates at file bottom: `new TAH_Trade_Presets();`