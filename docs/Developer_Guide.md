# Developer Guide (Theme Modules + Gotchas)

## Purpose
This repo has a few theme subsystems that are easy to break via load-order or WordPress admin quirks. This guide documents:
- where key features live,
- how the module system is wired, and
- the common gotchas when editing admin UI, metaboxes, and editor integration.

## Module System

### Entry Point
- Modules are bootstrapped once from `functions.php` via `TAH_Module_Registry::boot()`:
  - `inc/modules/class-module-registry.php`

### Module Contract (Conventions)
Each module should provide:
- `::boot()` idempotent include/bootstrap method
- `::is_enabled()` optional runtime enable flag (default ON), typically via a filter

### Adding A New Module (Checklist)
1. Create a module folder: `inc/modules/<module-name>/`
2. Add a bootstrap class file: `inc/modules/<module-name>/class-<module-name>-module.php`
3. Ensure `boot()` is safe to call multiple times (guard with a static boolean).
4. Add an enable toggle (default ON) via a filter:
   - Example: `apply_filters('tah_module_<module>_enabled', true)`
5. Register the module in `inc/modules/class-module-registry.php` (explicit order).
6. Update this document with the module purpose and entry points.

## Info Sections Module

### What It Is
The Info Sections system is a metabox-driven content system for Quotes:
- Global Info Sections (library) live as `tah_template_part` posts.
- Trades define preset recipeas (ordered key lists) via taxonomy term meta.
- Quotes store per-quote order and overrides in post meta.

### Bootstrapping + Toggle
- Module bootstrap: `inc/modules/info-sections/class-info-sections-module.php`
- Registry: `inc/modules/class-module-registry.php`
- Enable flag (default ON):
  - `add_filter('tah_module_info_sections_enabled', '__return_false');`

### Key Files
- Global library CPT + key metabox/validation:
  - `inc/cpt/template-parts.php`
- Trade presets UI/save:
  - `inc/admin/class-trade-presets.php`
- Quote editor metabox + persistence + frontend rendering:
  - `inc/admin/class-quote-sections.php`
- Admin UI interactions (sortable, controls, tools dropdown, etc.):
  - `assets/js/quote-sections.js`
- Admin styling (metabox UI, icons, etc.):
  - `assets/css/admin.css`
- Quote frontend template integration:
  - `single-quotes.php` (renders sections if `tah_render_quote_sections()` exists)

## Editor Template Button (TinyMCE)
- Button is added via `media_buttons` in `inc/editor-config.php`
- JS: `assets/js/custom-script.js` (dropdown creation/animation)
- Templates: `assets/templates/quotes/*.html`
- Template metadata parsed from HTML comment headers

## Admin Styling Gotchas
- Frontend `style.css` does not load in WP admin.
- Use `assets/css/admin.css` for admin/editor/login/admin-bar styling.
- `admin.css` is enqueued via `load_theme_admin_styles()` in `inc/admin.php`.

## Block Editor
- Gutenberg is disabled site-wide
- Classic Editor (TinyMCE) is used.

## CSS Variables
- Defined in `assets/css/variables.css`
- Imported into both `style.css` and `assets/css/admin.css`