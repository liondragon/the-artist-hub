# Protocol: Metabox-Driven Content Templates (Refined)

## Overview
This protocol moves the informational "FAQ" sections of Quotes (e.g., "Dust Containment", "Finish Options") out of the main `post_content` editor and into a structured "Info Sections" metabox system.

It utilizes a **Recipe Model** based on the existing `trade` taxonomy:
- **Info Library:** Atomic `tah_template_part` posts (e.g., "Dust Containment", "LVP Warranty").
- **Trade Presets:** Each `trade` term (e.g., "Wood Refinishing") defines a default recipe (list of sections).
- **Quotes:** Initialize their sections based on their selected Trade.

---

## Architecture

### 1. Info Sections (The Library)
- **Content Source:** `tah_template_part` CPT.
- **Identification:** `_tah_section_key` (Canonical, Immutable, Unique).

### 2. Trade Presets (The Recipe)
Each `trade` taxonomy term stores configuration meta:
- `_tah_trade_default_sections`: Ordered array of keys (e.g., `['dust_containment', 'finish_options']`).
- **Validation Rule:** Only keys that exist in the current Info Library are allowed. Unknown keys are filtered out on save.
- **Management:** "Default Sections" meta box on the Trade Edit Screen.
  - **Security:** Requires `manage_categories` capability and a valid Nonce.
- **Protected Status:** Deletion of a Trade Term is **not blocked** by code.
  - **Policy:** Admins should manually reassign Quotes to a new Trade (or "General") before deleting a Trade term.
  - **Impact:** If a Trade is deleted key, Quotes retain their sections but lose "Active Recipe" context (disabling Sync/Reset tools).

### 3. Quote Data Storage (The Consumer)
Data is stored in `post_meta` for each Quote:
- `_tah_quote_sections_order`: **Single array** of section **Keys**.
  - **Hard Rule (Initialization):**
    - **Trigger:** First valid save.
    - **Guard:** `_tah_quote_sections_order` is EMPTY **AND** NOT Autosave **AND** NOT Revision **AND** User has edit capabilities.
    - **Logic:**
      1. Check selected `trade` term.
      2. **If Trade Selected:** Fetch `_tah_trade_default_sections`.
      3. **If Schema Found:** Copy that list to `_tah_quote_sections_order`.
      4. **New-Quote Fallback:** If no trade or no schema, check for a **"General"** trade term.
         - If "General" term exists: Use its preset.
         - If "General" term missing: Initialize as **EMPTY**.
    - **Note:** Programmatic creation requires manual init.
  - **Hard Rule (Legacy/Existing):** Missing Order Meta = Render Nothing. Do NOT auto-inject defaults.
  - **Hard Rule (No Mutation on Save):** Changing the Trade does not mutate sections/content/mode on save unless an explicit Sync/Reset action is submitted.
  - **Editor UX:** Selecting a Trade in the editor auto-populates the current list from that Trade preset to reduce manual setup.
  - **UX Expectation:** User must click **Sync** or **Reset** to apply the new Trade's preset.
  - **Tools:**
    1. **Sync from Trade (Safe):** Appends missing keys from the **current Trade Preset**.
       - **Edge Case:** If a section was removed from the Preset but exists in the Quote, **it is kept** (Additive).
       - Preserves existing orphaned keys.
    2. **Reset to Trade (Destructive):** Replaces entire order with **current Trade Preset**.
       - **Edge Case:** If a section was removed from the Preset but exists in the Quote, **it is removed** (Mirroring).
       - **Discards any orphaned keys.**
    - **Security:** `edit_post` capability + Nonce required.
- `_tah_section_{key}_enabled`: Boolean.
- `_tah_section_{key}_mode`: `default` or `custom`.
- `_tah_section_{key}_content`: Custom HTML.

### 4. The Backend UI (Quote Editor)
- **Trade Selection (Custom UI):** Replaces default taxonomy metabox with a **Radio Button List** (Single Select).
- **Info Sections Metabox:**
  - **Header:** Shows "Active Recipe: [Trade Name]" (or "None").
  - **Body:** Drag-and-drop interface with compact rows.
  - **Row Controls:**
    - Checkbox appears inline next to section title (no separate "Enabled" row).
    - Mode is shown as a badge (`Default` / `Custom`).
    - `Edit` toggles to `Collapse` while the editor is open.
    - `Revert to Default` is destructive (clears custom content and returns to default mode).
    - Custom mode is only considered active when custom content is non-empty.
  - **Empty State:** If order is empty, show: "No sections configured. Select a Trade above and click 'Sync' to populate." Disable Tools until Trade selected.
  - **Tools:** "Sync from [Trade]" and "Reset to [Trade]".

### 5. Frontend Rendering
1. Fetch `_tah_quote_sections_order`.
2. **Pre-fetch:** ID-based query for `tah_template_part` -> Map.
3. Loop through Order:
   - **Custom:** Render Meta HTML.
   - **Global:** Render CPT Content (if found in Map).
4. **Policy:** Output stored HTML fragments. No `wpautop`. No filters. Save-time sanitization.

---

## Implementation Details

### Key Immutability & Validation
- **Regex:** `^[a-z][a-z0-9_]*$`
- **Validation:** Unique across all `tah_template_part` posts.
- **Auto-generation:** If the key field is empty on first save, generate from title (e.g., `Dust Containment` → `dust_containment`) and then apply the same regex/uniqueness checks.
- **Failure:** Revert/Empty + Admin Notice (30s).

### Security
- **Global:** Trusted (Admin Only).
- **Custom Quote Meta:** `wp_kses_post()` at save time.
- **Tools:** Nonce verification.
