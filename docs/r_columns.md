# Implementation Plan: Hook-First Resizable and Draggable Admin Table Columns

## Objective

Make columns in `.tah-pricing-table-editor` and selected admin tables resizable and draggable without breaking existing styling, while using a universal, hook-driven integration model (not screen-specific JS hacks).

## Decision Summary

1. Resizing is supported for registered custom tables and explicitly opted-in core tables.
2. Reordering is supported only for registered custom tables (disabled for core list tables by default).
3. Column identity is contract-based (`data-tah-col`) and required on both header and body cells for reorder-safe behavior.
4. Preferences are namespaced by at least `{screen_id, table_key, variant}` to prevent cross-screen bleed.
5. Existing CSS remains authoritative for baseline layout; JS adds runtime widths via `colgroup` only.
6. UX additions are subtle and scoped: hover-only resize affordances, a small “Reset columns” action, and non-blocking “Saved” feedback on stop events.
7. Resizing supports “auto-fit” via divider double-click (content-based) and enforces sane min widths to prevent broken layouts.
8. If JS fails or config is missing, tables behave exactly as they do today.
9. Resizing is implemented via a small pointer-based handler (no `jquery-ui-resizable`), updating `<col>` widths only.

## Non-Goals

- No automatic reordering for WordPress core list tables.
- No removal/refactor of unrelated pricing UI behavior (for example `.tah-line-rate-badge` changes).
- No visual redesign of admin tables.
- No requirement to support keyboard-based column reordering in the first iteration (but do not break keyboard navigation inside table inputs).

## Architecture

## 1) PHP Registry + Hook Contracts (Source of Truth)

Create `inc/modules/admin-table-columns/class-admin-table-config.php` as a central registry and persistence layer.

### Hooks

- `tah_admin_table_registry` (filter): returns table configs keyed by `table_key`.
- `tah_admin_table_core_opt_in` (filter): explicit core screen opt-in, default empty.
- `tah_admin_table_can_reorder` (filter): final capability gate per table instance.
- `tah_admin_table_prefs_key_parts` (filter): optional extension of namespacing parts.

### Table Config Shape (per `table_key`)

- `screen_ids` (array): allowed `WP_Screen::id` values.
- `selector` (string): table selector within screen.
- `variant_attr` (string): attribute used as variant discriminator (default `data-tah-variant`).
- `columns` (map by column key):
- `locked` (bool): cannot resize or move when true.
- `min_width` (string|int): minimum width.
- `grow` (bool): elastic column behavior.
- `default_width` (string|int|null): optional preferred initial width.
- `allow_resize` (bool): default `true`.
- `allow_reorder` (bool): default `false` for core tables, `true` for custom tables.
- `requires_cell_map` (bool): default `true` when reorder is enabled.
- `show_reset` (bool): whether to show a “Reset columns” action for this table, default `true` for custom tables.
- `enable_autofit` (bool): whether divider double-click triggers auto-fit, default `true`.
- `save_feedback` (bool): whether to show a lightweight “Saved” status on stop events, default `true`.

### Localization Contract

Localize one payload to JS, including:

- active screen id
- resolved registry entries for current screen
- user prefs for matched tables only
- nonce + AJAX endpoint/action

No DOM auto-discovery for behavior decisions; behavior comes from the registry.

## 2) Persistence Model (User Meta)

- AJAX action: `wp_ajax_tah_save_table_prefs`
- User meta key: `_tah_table_prefs`
- Store shape:

`prefs[screen_id][table_key][variant] = { order: [], widths: {}, updated_at: ts }`

### Save Rules

- Debounced client save (for active drag/resize) and flush on stop.
- Persist only changed keys (widths/order diffs).
- Validate incoming columns against registry keys before write.
- Support reset by deleting `prefs[screen_id][table_key][variant]` (or clearing `order` and `widths`) and returning defaults on next load.

## 3) JS Table Columns Manager (`assets/js/admin-tables-core.js`)

### Dependencies

- Resizing: no jQuery UI widget; use pointer events and update `<colgroup>` widths only.
- Reordering: `jquery-ui-sortable` for custom tables only.

### Initialization

- Boot only for tables declared in localized registry.
- Resolve `table_key` + `variant` from config/attributes.
- Verify column contract:
- `th[data-tah-col]` required.
- If reorder enabled, each `td` in managed rows must also include `data-tah-col` (or a documented strict mapper).
- Add subtle affordances only (no layout changes): hover-only resize handles, `col-resize` cursor at the divider.
- Add a one-time, non-blocking hint (per table) when the user first uses resize/reorder (tooltip or title), scoped to that table instance.

### Width Model

- Inject/maintain one `colgroup` as runtime width source.
- Seed widths from computed layout on first run (before min enforcement).
- Apply min constraints after seeding.
- Apply saved widths next.
- Keep existing class-based styles intact; do not remove legacy width classes.
- Enforce min widths in a way that never forces columns below the minimum, even if the user drags smaller.

### Resize Behavior

- Handles on header cells only.
- Update target `col` width directly.
- Locked columns: no resize handle.
- Show a vertical guide line while dragging (within the table container) so the result is obvious.
- On stop: apply a small “settle” transition so the action feels committed without being flashy.
- Save widths on stop and on blur/unload fallback.
- Divider double-click triggers “auto-fit” (if enabled): compute a content-based width (header + a sample of body cells) and clamp to min width.

### Reorder Behavior (Custom Tables)

- Enable sortable headers only when `allow_reorder=true`.
- Locked columns cannot move.
- Prevent crossing locked boundaries.
- Reorder `thead`, `tbody` rows, and `colgroup` in one transaction.
- Persist resulting order.
- Provide clear visual feedback: placeholder highlight during drag and a small settle transition on drop.

### Group Sync

Tables with same `table_key` + `variant` group are synchronized (for views rendered in multiple blocks).

### Dynamic Rows

Listen for `tah:table_row_added`:

- ensure row cell order matches active column order
- apply `data-tah-col` validation
- rebind width/order if needed

### Reset and Status Feedback

- If `show_reset=true`, render a small “Reset columns” action near the table (for example adjacent to the table wrapper).
- Reset restores default widths/order for the current `{screen_id, table_key, variant}` only.
- If `save_feedback=true`, show “Saved” only on stop events (do not spam during drag).

### Resizable Implementation (Chosen Approach)

Implement a small resize handler that:

- Adds a thin divider/handle element inside each resizable header cell.
- On pointer down (mouse/touch/pen), tracks pointer movement and computes the new width.
- Applies the width only to the matching `<col>` in the table’s `<colgroup>`.

This avoids jQuery UI’s behavior of attaching widget markup/classes and inline styles to elements, which can cause subtle CSS/layout side effects.

What we lose by skipping jQuery UI:

- Less built-in behavior (for example helper overlays, some edge-case handling, and legacy browser quirks).
- We have to implement a few details ourselves (pointer capture, min-width clamping, and teardown on escape/unload).

What we gain:

- Tighter control over DOM/CSS (less chance of breaking existing styling).
- Smaller dependency surface and fewer “mystery” layout interactions.

## 4) Integration Plan by Surface

## Quote Pricing Editor

Files:

- `inc/modules/pricing/class-quote-pricing-metabox.php`
- `assets/js/quote-pricing.js`

Actions:

- Add table opt-in class (for example `.tah-resizable-table`) and table identifiers:
- `data-tah-table="pricing_editor"`
- `data-tah-variant="{standard|insurance}"`
- Add `data-tah-col` to every managed `th`.
- Add matching `data-tah-col` to every managed `td` in:
- PHP-rendered rows
- JS row template builder
- Fire `tah:table_row_added` after row insertion paths.

## Catalog Tables

- Register catalog table config with explicit selector and column map.
- Add `data-tah-col` where missing.
- Enable resize first; reorder only if catalog UX requires it and passes locked-boundary checks.

## 5) Styling Safety Rules

- Keep existing table CSS and width class rules as fallback defaults.
- Runtime widths must be additive (`colgroup`) and reversible.
- If JS fails or config missing, table renders exactly as today.
- No forced `table-layout` flips outside registered table definitions.
- Any new UI controls (Reset, hint) must use existing WP/admin button styles (`button-link` or equivalent) and be scoped to the table wrapper so styling does not leak.

## Execution Phases

## Phase 1: Infrastructure

- Add `class-admin-table-config.php` with registry filters, enqueue gate, prefs load/save, AJAX endpoint.
- Register `admin-tables-core.js` (+ `admin-tables-constants.js`, `admin-tables-store.js`, `admin-tables-interaction.js`) on matching screens only.

Acceptance:

- No new scripts loaded on unrelated admin screens.
- Registry can define at least one table without touching JS internals.

## Phase 2: Universal JS Manager

- Implement init, `colgroup` seeding, pointer-based resize, persistence, and validation.
- Add reorder support behind config gate.

Acceptance:

- Resize persists per user after refresh.
- Invalid or missing `data-tah-col` fails safe (no reorder enabled, no layout break).
- Hover-only resize affordances do not change baseline layout when not interacting.
- Reset restores defaults for current table scope only.
- Auto-fit never produces widths smaller than min width.

## Phase 3: Quote Editor Integration

- Add full `data-tah-col` mapping in both PHP and JS row templates.
- Wire row-added event.

Acceptance:

- New rows adopt current column order immediately.
- Standard and insurance variants persist independently.

## Phase 4: Catalog Integration

- Integrate catalog table config.

Acceptance:

- Catalog table resizes without styling regressions.

## Verification Checklist

- Quote editor (standard): resize + reorder + save/refresh.
- Quote editor (insurance): independent width/order from standard.
- New row insertion: ordering remains consistent.
- Catalog screen: resize works and styles unchanged.
- Reset columns restores defaults for the current variant only.
- Auto-fit works on a few representative columns and respects min widths.
- No console errors on non-opted screens.

## Future Plans

## Core WP Tables (Deferred)

- No auto-targeting.
- Only activate through `tah_admin_table_core_opt_in`.
- Default behavior: resize only, reorder disabled.
- Treat each opted-in core screen as a deliberate integration (verify checkbox/actions columns and any plugin-added columns on that screen).

Future execution:

- Add at least one explicit core screen opt-in as a reference implementation (resize-only).

## Rollback Strategy

- Disable by removing registry entries or returning empty registry from filter.
- Existing tables fall back to current static styling with no markup dependency failures.


## Notes
Registry model is better long-term (multi-screen, core opt-ins, feature flags, stricter contracts).
Resize: go back to the original pointer-based plan (no jquery-ui-resizable). Your current bugs are exactly where jQuery UI resizable collides with table layout + sortable.
Reorder: keep jquery-ui-sortable for now; it’s already working better and is less risky to replace immediately.
