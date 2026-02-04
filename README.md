# The Artist Hub Theme

**INTERNAL USE ONLY**

## Architectural Decisions & Constraints

**ATTENTION FUTURE AGENTS & DEVELOPERS:**

1.  **NO BLOCK EDITOR (GUTENBERG)**: This theme is designed to be lightweight and purely PHP-based. The WordPress Block Editor is intentionally disabled and unsupported.
    *   Do **NOT** add `theme.json`.
    *   Do **NOT** add block support.
    *   Keep the clean, classic editor experience.

2.  **NO BUILD PIPELINE**: There is no `package.json`, generic build scripts, or pre-processing. The theme serves raw CSS and JS for simplicity and ease of maintenance in this specific environment.

3.  **NO EXTERNAL DEPENDENCIES**: The theme should remain self-contained.

## Theme Structure
*   `css/variables.css`: Source of truth for design tokens.
*   `includes/`: Functional logic separated by concern.
*   `page-templates/`: Custom layouts.

## Maintenance
*   Strict types (`declare(strict_types=1);`) are enforced.
*   All output must be escaped (`esc_html`, `esc_attr`, etc.).
