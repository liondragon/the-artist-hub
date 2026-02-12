# Implementation Plan: Metabox-Driven Content Templates

**Scope:** Implement the metabox-driven “Quote Sections” system described in `metabox_approach.md`.

**Spec Reference:** `metabox_approach.md` (no `Canonical_Spec.md` or `docs/Spec_Digest.md` present in this repo).

**Invariant-Only Plan:** This plan decomposes the current `metabox_approach.md` only. It does **not** add new user-facing behavior beyond the protocol described there.

---

**Repo Map (Current State)**
- `inc/cpt/template-parts.php`: Registers `tah_template_part` CPT, key metabox, validation, admin notice, and disables block editor for quotes/sections. Not currently included anywhere.
- `inc/admin/class-trade-presets.php`: Trade preset term UI + meta save with filtering of unknown keys. Checkbox UI (no ordering). Not currently included anywhere.
- `inc/cpt/quotes.php`: Quotes CPT + `trade` taxonomy + customer info metabox. No custom Trade radio UI or Quote Sections metabox.
- `single-quotes.php`: Renders `the_content()` only (no section rendering).
- Admin JS pattern exists in `inc/editor-config.php` + `assets/js/custom-script.js` (enqueue + localized data).
- `tests/`: Empty placeholder folder created; no harness yet.


## Phase 0 — Wire Existing Building Blocks

- **Goals:** Ensure existing Global Sections and Trade Preset code is loaded and reachable; establish file locations for new Quote Sections logic.
- **Non-Goals:** Full Quote Sections UI, sync/reset tools, frontend rendering.
- **Acceptance:** Global Sections CPT + key validation active; Trade Preset UI active; new Quote Sections controller file in place and wired.

### Contracts & Data Models
- [x] Wire `tah_template_part` CPT by including `inc/cpt/template-parts.php` (Target: `inc/custom_post_types.php` or `functions.php`) (Spec: §Architecture — Global Sections (The Library))
- [x] Wire Trade Presets by including `inc/admin/class-trade-presets.php` on admin load (Target: `inc/admin.php` or new admin include) (Spec: §Architecture — Trade Presets (The Recipe))
- [x] Add Quote Sections controller file (e.g., `inc/admin/class-quote-sections.php`) to own quote section meta keys and persistence (Spec: §Architecture — Quote Data Storage (The Consumer))
- [x] Define and centralize meta key constants for `_tah_quote_sections_order`, `_tah_section_{key}_enabled`, `_tah_section_{key}_mode`, `_tah_section_{key}_content` (Target: new constants file or within controller) (Spec: §Architecture — Quote Data Storage (The Consumer))
- [x] Verify/restrict Global Sections access to Admin only (capability mapping + menu access) (Target: `inc/cpt/template-parts.php`) (Spec: §Implementation Details — Security)

### Helper Contracts
- [x] Verify `_tah_section_key` regex + uniqueness logic matches spec; patch if needed (Target: `inc/cpt/template-parts.php`) (Spec: §Implementation Details — Key Immutability & Validation)
- [x] Ensure trade preset save filters unknown keys against current Global Library (Target: `inc/admin/class-trade-presets.php`) (Spec: §Architecture — Trade Presets (The Recipe))
- [x] Enforce capability + nonce checks for Trade Presets edit/save (Target: `inc/admin/class-trade-presets.php`) (Spec: §Architecture — Trade Presets (The Recipe); §Implementation Details — Security)

### Observability / Tooling
- [x] Confirm admin notice (30s) on invalid/duplicate `_tah_section_key` is wired and visible (Target: `inc/cpt/template-parts.php`) (Spec: §Implementation Details — Key Immutability & Validation)

---

## Phase 1 — Core Data & Orchestration

- **Goals:** Quote initialization + Sync/Reset logic + meta persistence; Trade selection behavior; enforce all hard rules.
- **Non-Goals:** UI polish beyond functional controls; advanced styling.
- **Acceptance:** First valid save initializes order; Sync/Reset behavior matches spec; Trade change never mutates sections unless user clicks tool; fail closed (nonce/cap failure ⇒ no persistent mutations; unknown keys are filtered; missing order/meta ⇒ render nothing).

### Pure Helpers (Deterministic)
- [x] Implement helper: resolve “Active Trade” deterministically from assigned terms (Spec: §Architecture — The Backend UI (Quote Editor); §Architecture — Quote Data Storage (The Consumer))
  - `Done When:` returns “no active term” when term lookup is empty or errors; given 0/1/N assigned terms, helper returns either (a) no active term or (b) the same deterministic chosen term (e.g., lowest `term_id`) every time
- [x] Implement helper: filter preset keys against current Global Library at time of use (init/sync/reset) (Spec: §Architecture — Trade Presets (The Recipe); §Architecture — Quote Data Storage (The Consumer))
  - `Done When:` preset application never yields unknown keys even if term meta drifted after Global Library changes; ordering is deterministic (prefer stored array order when present, otherwise apply a fixed sort rule)
- [x] Implement helpers: compute Sync order (additive) and Reset order (mirror) as pure functions (Spec: §Architecture — Quote Data Storage (The Consumer))
  - `Done When:` same inputs ⇒ same outputs; Sync never removes existing keys; Reset returns preset exactly
- [x] Define internal defaults for missing per-section meta (enabled/mode/content) and centralize read helpers (Target: controller) (Spec: §Architecture — Quote Data Storage (The Consumer); §Architecture — Frontend Rendering)
  - `Done When:` missing meta behaves deterministically without changing stored data; default is `enabled=true`, `mode=default`, `content=''`

### Orchestration (Persistence + Guards)
- [x] Implement quote initialization on first valid save (not autosave, not revision, user can edit) (Target: `save_post_quotes` hook in controller) (Spec: §Architecture — Quote Data Storage (The Consumer))
  - `Artifacts:` `inc/admin/class-quote-sections.php` (new), `inc/cpt/quotes.php` (modify)
  - `Interfaces:` `_tah_quote_sections_order` post meta
  - `Depends On:` Phase 0 — Wire `tah_template_part` CPT; Phase 0 — Wire Trade Presets
  - `Done When:` first valid save initializes order only when empty; canonical guards are applied (`wp_is_post_autosave($post_id)`, `wp_is_post_revision($post_id)`, `current_user_can('edit_post', $post_id)`); initialization runs only when the Quote Sections metabox nonce is present and valid (programmatic saves do not initialize per spec note); no legacy auto-injection
- [x] Implement “General” trade fallback on new quote when no trade/preset (Target: controller helper; use term lookup by slug/name) (Spec: §Architecture — Quote Data Storage (The Consumer))
- [x] Enforce no auto-mutation when Trade changes; require Sync/Reset tools (Target: controller save logic) (Spec: §Architecture — Quote Data Storage (The Consumer))
- [x] Implement Sync from Trade (additive) and Reset to Trade (destructive) server-side logic (Target: controller POST handler) (Spec: §Architecture — Quote Data Storage (The Consumer))
  - `Artifacts:` `inc/admin/class-quote-sections.php` (new)
  - `Interfaces:` Sync/Reset actions (nonce + POST)
  - `Depends On:` Phase 1 — Implement quote initialization on first valid save; Phase 1 — Implement helper: filter preset keys against current Global Library at time of use (init/sync/reset)
  - `Handoff Required:` yes — document clobber-avoidance rules (only update when fields present), and confirm Sync/Reset never delete per-section meta; write notes to `docs/state_handoff.md` (create if missing)
  - `Done When:` Sync appends missing preset keys only; Reset mirrors preset exactly; orphaned keys preserved on Sync and removed on Reset; Sync/Reset do not mutate per-section meta (`_tah_section_{key}_enabled|_mode|_content`) and never delete it

### Safety & Invariants
- [x] Enforce initialization guard: not autosave, not revision, user has edit capability (Target: `save_post_quotes` hook) (Spec: §Architecture — Quote Data Storage (The Consumer))
- [x] Enforce legacy rule: missing order meta renders nothing and never auto-injects defaults (Target: init guard + render helper) (Spec: §Architecture — Quote Data Storage (The Consumer))
- [x] Enforce save-time sanitization via `wp_kses_post()` for custom content (Target: controller save) (Spec: §Implementation Details — Security)

---

## Phase 2 — Admin UI & Frontend Rendering

- **Goals:** Quote Sections metabox UI (drag/drop, empty state, tools), Trade radio UI, and frontend rendering pipeline.
- **Non-Goals:** Additional features beyond spec.
- **Acceptance:** Admin UI matches spec; frontend renders stored sections in order with correct source and sanitization rules; fail closed (nonce/cap failure ⇒ no persistent mutations; missing order/key ⇒ render nothing; no filter-based fallbacks).

### Interfaces
- [x] Replace default Trade taxonomy metabox with custom single-select radio UI (Target: new admin UI in Quote Sections controller; remove default via `remove_meta_box`) (Spec: §Architecture — The Backend UI (Quote Editor))
- [x] Hard enforce single-select `trade` on save: if multiple terms are submitted, keep one and drop the rest (Target: `save_post_quotes` hook) (Spec: §Architecture — The Backend UI (Quote Editor))
- [x] Implement Quote Sections metabox with drag-and-drop ordering (Target: new metabox + `jquery-ui-sortable` script; admin CSS in `assets/css/admin.css`) (Spec: §Architecture — The Backend UI (Quote Editor))
  - `Artifacts:` `inc/admin/class-quote-sections.php` (new), `assets/js/quote-sections.js` (new), `assets/css/admin.css` (modify)
  - `Interfaces:` Quote Sections metabox UI; drag/drop ordering
  - `Depends On:` Phase 1 — Implement quote initialization on first valid save
  - `Done When:` metabox renders sections in order; drag/drop updates order field; tools appear but are gated by trade selection
- [x] Implement per-section metabox controls: enabled toggle, mode selector (`default|custom`), custom HTML editor (Spec: §Architecture — Quote Data Storage (The Consumer); §Architecture — The Backend UI (Quote Editor))
  - `Artifacts:` `inc/admin/class-quote-sections.php` (new), `assets/js/quote-sections.js` (new), `assets/css/admin.css` (modify)
  - `Interfaces:` `_tah_section_{key}_enabled`, `_tah_section_{key}_mode`, `_tah_section_{key}_content`
  - `Depends On:` Phase 1 — Define internal defaults for missing per-section meta (enabled/mode/content) and centralize read helpers
  - `Done When:` UI round-trips state across save/reload; reorder/sync/reset do not wipe per-section state
- [x] Persist per-section meta on Quote save; sanitize custom HTML with `wp_kses_post()` (Spec: §Implementation Details — Security)
  - `Done When:` per-section meta is only updated when the metabox nonce passes and fields are present in the request; missing POST fields do not clobber existing per-section meta
- [x] Implement Sync and Reset tool buttons wired to server-side handlers (Target: admin JS + nonce + POST handler in controller) (Spec: §Architecture — Quote Data Storage (The Consumer); §Architecture — The Backend UI (Quote Editor))
- [x] Compute “Active Recipe: [Trade Name]” from currently assigned trade; if none (or term deleted), show “None” and disable Sync/Reset (Spec: §Architecture — The Backend UI (Quote Editor); §Architecture — Trade Presets (The Recipe))
  - `Artifacts:` `inc/admin/class-quote-sections.php` (new), `assets/js/quote-sections.js` (new), `assets/css/admin.css` (modify)
  - `Interfaces:` Quote Sections metabox header (“Active Recipe: …”); tool enabled/disabled state
  - `Done When:` if multiple trade terms are assigned, pick a deterministic one for display (e.g., lowest `term_id`) and surface a non-blocking admin warning until the next save enforces single-select
- [x] Add empty-state message and tool gating when order is empty and/or no trade selected (Target: Quote Sections metabox render) (Spec: §Architecture — The Backend UI (Quote Editor))
- [x] Implement ordered Trade Presets UI so `_tah_trade_default_sections` is explicitly ordered (not checkbox-only) (Spec: §Architecture — Trade Presets (The Recipe))
  - `Artifacts:` `inc/admin/class-trade-presets.php` (modify), `assets/js/quote-sections.js` (modify or new admin JS), `assets/css/admin.css` (modify)
  - `Interfaces:` `_tah_trade_default_sections` term meta; Trade edit screen UI
  - `Depends On:` Phase 0 — Wire Trade Presets
  - `Handoff Required:` yes — document ordering persistence strategy + how order is serialized in term meta; write notes to `docs/state_handoff.md` (create if missing)
  - `Done When:` preset UI produces a deterministic ordered array in `_tah_trade_default_sections` and round-trips (save → edit screen reload shows the same order)

### Systems & Orchestration
- [x] Implement frontend rendering of section order with prefetch map and mode rules (Target: helper + integrate into `single-quotes.php` directly below `the_content()`) (Spec: §Architecture — Frontend Rendering)
  - `Artifacts:` `inc/admin/class-quote-sections.php` (new), `single-quotes.php` (modify)
  - `Interfaces:` frontend render output for quotes
  - `Depends On:` Phase 1 — Implement quote initialization on first valid save; Phase 0 — Wire `tah_template_part` CPT
  - `Done When:` output renders in order; skip rendering when enabled is false; when mode=custom, render stored content (empty content renders nothing, no fallback); when mode=default, render global content only if found in map; nothing renders when order meta missing; rendering is implemented in `single-quotes.php` (not a `the_content` filter) to avoid double-render
- [x] Add helper to fetch Global Sections map by key for rendering and validation (Target: Quote Sections controller) (Spec: §Architecture — Frontend Rendering; §Architecture — Trade Presets (The Recipe))

### Safety & Invariants
- [x] Enforce “no wpautop / no filters” for stored HTML fragments (custom and global) (Target: rendering helper) (Spec: §Architecture — Frontend Rendering)
- [x] Render Global Section content as raw stored `post_content` (no `the_content` filters) (Target: rendering helper) (Spec: §Architecture — Frontend Rendering)
- [x] Enforce capability + nonce checks for Sync and Reset tools (Target: controller) (Spec: §Architecture — Quote Data Storage (The Consumer); §Implementation Details — Security)

### Observability / Tooling
- [x] Add admin JS enqueue for Quote Sections tools + drag/drop behavior (Target: new JS file + enqueue in `inc/admin.php`) (Spec: §Architecture — The Backend UI (Quote Editor))

---

## File Skeletons (Proposed)

### `inc/admin/class-quote-sections.php`
- `class TAH_Quote_Sections`
- `const META_ORDER = '_tah_quote_sections_order'`
- `const META_ENABLED_PREFIX = '_tah_section_'`
- `const META_MODE_PREFIX = '_tah_section_'`
- `const META_CONTENT_PREFIX = '_tah_section_'`
- `__construct()`
- `register_metabox()`
- `render_metabox()`
- `enqueue_admin_assets()`
- `save_quote_sections($post_id, $post)`
- `handle_sync_reset()`
- `get_trade_preset_keys($term_id)`
- `get_global_sections_map()`
- `render_sections_frontend($post_id)`

### `assets/js/quote-sections.js`
- Handles drag/drop ordering
- Handles Sync/Reset actions (AJAX or POST submit)
- Updates hidden order field

### `assets/css/admin.css`
- Add styles for metabox header, tools, empty state, drag handles

---

## Test Plan

**Note:** Automated test harness is deferred for this project; rely on the Manual Verification checklist until a harness is explicitly adopted.

---

## Manual Verification (No Harness Yet)
1. Create/edit Trade “Wood”; select Global Sections; save; verify `_tah_trade_default_sections` stored.
2. Create a new Quote with Trade “Wood”; save; verify `_tah_quote_sections_order` initialized.
3. Change Trade; verify sections do not change until Sync/Reset is used.
4. Sync: verify missing preset keys are appended, existing/orphaned keys remain.
5. Reset: verify order matches current preset and orphaned keys are removed.
6. Delete a Trade term: verify deletion is allowed and affected Quotes retain sections but show no Active Recipe (Sync/Reset disabled).
7. Open a legacy Quote with no `_tah_quote_sections_order`; confirm frontend renders no sections and admin shows empty-state (no auto-init occurs).

---

## Minimal Integration Matrix (High-Signal Flows)

- Quote init → order stored → frontend render (Spec: §Architecture — Quote Data Storage; §Architecture — Frontend Rendering) — validated by Manual Verification steps 2 and 4
- Trade change → no auto-mutation → Sync/Reset behaviors (Spec: §Architecture — Quote Data Storage) — validated by Manual Verification steps 3–5
- Legacy behavior: missing order meta renders nothing (Spec: §Architecture — Quote Data Storage) — validated by Manual Verification step 7

---

## Delivery Checklist (Done Means)

- Phase 0/1/2 acceptance criteria satisfied.
- Sync/Reset behavior matches additive/destructive rules.
- Rendering shows Quote Sections directly below `the_content()` and never auto-injects defaults.
- Trade deletion remains allowed; Active Recipe context removed with tools disabled.
- Manual Verification checklist completed and recorded.

---

## Known Debt & Open Questions

- [ ] Add `Canonical_Spec.md` + `docs/Spec_Digest.md` if the project intends to use the standard spec pipeline (currently missing).
- [ ] If automated tests are desired, adopt a WordPress test harness and add unit/integration tasks (current plan is manual verification only).
