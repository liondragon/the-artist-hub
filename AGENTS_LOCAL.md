# Agent Notes: The Artist Hub

## Project Structure
- **Theme CSS**: `style.css` (frontend only) 
- **Admin CSS**: `assets/css/admin.css` (WP admin area - login, admin bar, editor)
- **Admin PHP**: `inc/admin.php` (enqueues admin.css, admin bar cleanup, login customization)
- **Editor Config**: `inc/editor-config.php` (TinyMCE, templates button, default content)

## Key Gotchas

### WordPress Admin Styling
- Theme `style.css` does NOT load in WP admin - use `assets/css/admin.css`
- `admin.css` is enqueued via `load_theme_admin_styles()` in `inc/admin.php`

### Custom Editor Template Button
- Button added via `media_buttons` hook in `inc/editor-config.php`
- JS: `assets/js/custom-script.js` - handles dropdown creation/animation
- Templates loaded from `assets/templates/quotes/*.html`
- Template metadata parsed from HTML comment headers

### Quote Sections (Metabox System)
- **Protocol**: See `docs/metabox_approach.md`
- **Architecture**:
  - **Global Library**: `tah_template_part` CPT (managed in Admin > Global Sections).
  - **Trade Presets**: Taxonomy meta on `trade` terms (managed in Admin > Trades).
  - **Quote Storage**: `_tah_quote_sections_order` post meta on `quotes`.
- **Implementation**:
  - `inc/admin/class-trade-presets.php`: Handles Trade Taxonomy fields.
  - `inc/admin/class-quote-sections.php`: Handles Quote Metabox and Frontend rendering.
  - `assets/js/quote-sections.js`: Handles drag-and-drop and UI interactions.

### Block Editor
- Gutenberg is **disabled** (`use_block_editor_for_post` returns false)
- Classic Editor (TinyMCE) is the only editor

### CSS Variables
- Defined in `assets/css/variables.css`
- Imported via `@import` in both `style.css` and `admin.css`

## File Locations
| Purpose | Path |
|---------|------|
| Frontend styles | `style.css` |
| Admin styles | `assets/css/admin.css` |
| CSS variables | `assets/css/variables.css` |
| Admin scripts | `assets/js/custom-script.js` |
| Quote templates | `assets/templates/quotes/` |
| Quote Sections Logic | `inc/admin/class-quote-sections.php` |

## Known Issues / TODO
- **Typos in Content**:
  - "Prep Allwoance" -> "Allowance" (likely in `assets/templates/quotes/` or database content)
  - "Fateners/Adhesives" -> "Fasteners"
- **UX**:
  - Admin: Section keys (e.g. `(dust_collection)`) are visible to all users.
  - Frontend: "Base Quote" columns need better alignment.
