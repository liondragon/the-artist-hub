# Protocol: Metabox-Driven Info Sections (Current Behavior)

## Overview
This protocol moves quote informational sections out of the main editor body and into a structured **Info Sections** metabox.

Model:
- **Info Library:** Global section templates (`tah_template_part` with immutable `_tah_section_key`).
- **Trade Presets:** Per-`trade` ordered defaults via `_tah_trade_default_sections`.
- **Quote Sections:** Per-quote ordered section composition with per-section overrides.

## Data Model

### Global Library
- CPT: `tah_template_part`
- Key meta: `_tah_section_key`
- Key rules: immutable, unique, validated, auto-generated from title if empty on first save.

### Trade Presets
- Term meta on `trade`: `_tah_trade_default_sections` (ordered key array)
- Unknown keys are filtered out against current global library.

### Quote Storage
- `_tah_quote_sections_order`: ordered section keys for a quote.
- `_tah_section_{key}_enabled`: `1|0`
- `_tah_section_{key}_mode`: `default|custom`
- `_tah_section_{key}_content`: HTML override content
- `_tah_section_{key}_title`: quote-local title (used for quote-local sections)

## Quote Editor UX

### Trade Control
- Single-select Trade radio metabox (custom UI).
- Active trade appears as: `Active Recipe: <Trade Name>`.

### Info Sections Metabox
- Drag-and-drop rows.
- Empty state shown when order is empty.
- Tools:
  1. `Sync from Trade`
  2. `Reset Order to Trade`
  3. `Reset to Trade Default`

### Row Controls
- Drag handle (reorder).
- Title:
  - Global section: static title.
  - Quote-local section: editable title input.
- Icon controls:
  - Visibility toggle icon:
    - Enabled: `dashicons-visibility`
    - Disabled: `dashicons-hidden`
  - Delete icon (`dashicons-trash`)
  - Expand/Collapse icon:
    - Closed: down arrow
    - Open: up arrow
- `Revert to Default` appears only when relevant:
  - For global sections with effective custom content.
  - Not shown for quote-local sections (no global default to revert to).
- Mode badge labels:
  - `DEFAULT`: global section using library content.
  - `MODIFIED`: global section with custom quote override.
  - `CUSTOM`: quote-local section.

### Add New Quote-Local Section
- Inline create row at the bottom of the list.
- Input placeholder: `Create a new info section for this quote`.
- Save controls:
  - `Enter` key
  - checkmark button
- Discard control:
  - red cross (appears when input has text).

## Save/Mutation Semantics

### Initialization
- On first valid quote save (non-autosave/revision, authorized), initialize `_tah_quote_sections_order` from active Trade preset, with `General` fallback when available.

### Trade Tool Actions
1. `Sync from Trade`:
   - Additive.
   - Appends missing preset keys.
   - Keeps existing/orphaned keys already on quote.
2. `Reset Order to Trade`:
   - Order-only reset to current trade preset.
   - Does not clear per-section override meta by itself.
3. `Reset to Trade Default`:
   - Order reset to current trade preset.
   - Also clears section overrides for relevant keys (enabled/mode/content/title), restoring trade defaults.

### Deletion Semantics
- Deleting a row in the metabox removes it from UI immediately.
- On quote save, deleted keys are treated as irreversible for that quote:
  - removed from stored order
  - per-section override meta cleared (`enabled`, `mode`, `content`, `title`)
- `Sync from Trade` can re-add missing trade-preset sections later.

## Frontend Rendering
1. Read `_tah_quote_sections_order`.
2. Resolve section state for each key (`enabled`, `mode`, `content`, title).
3. Render enabled sections in stored order:
   - `mode=custom`: render stored quote content.
   - `mode=default`: render global library content for that key.
4. HTML is rendered from stored fragments (save-time sanitized for quote custom content).

If no order meta exists, nothing is rendered (legacy-safe behavior).

## Notes
- Current implementation uses icon-driven controls for consistency.
- Badge text is uppercase by design (`DEFAULT`, `MODIFIED`, `CUSTOM`).
