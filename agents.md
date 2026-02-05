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
