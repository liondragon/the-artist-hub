# Column Resize Target Behavior Contract

## Purpose
Define the intended column resize UX and technical constraints for implementation work in the admin tables module.

## Scope
- Applies to managed admin tables using `TAHAdminTables` resize/reorder behavior.
- Applies across screen/table/variant contexts (for example `pricing_editor` standard/insurance).
- This is a behavior contract for implementation and QA.

## Target Behavior

### 1) Primary Interaction
- Dragging a column's right edge changes only that column's width in real time.
- Cursor is `col-resize` on the resize handle and remains consistent while dragging.
- Header/helper text must remain visible during drag operations.
- When a resize bound is reached, active divider shows bound feedback (`min`/`max`) without changing resize model.

### 2) Deterministic Rules
- Same pointer delta produces the same width delta.
- No implicit redistribution to unrelated columns during active resize.
- No width snap-back after mouseup.

### 3) Bounds and Eligibility
- Per-column bounds come from the resolved contract (`min_px_resolved`, `max_px_resolved`).
- Locked/non-resizable columns never expose resize handles.
- Hidden columns are excluded from resize math and width persistence.

### 4) Table Width Strategy
- Explicit column widths define total table width.
- Runtime table frame width is set from assigned visible column widths (column var sum), not from browser auto-distributed widths.
- If total width exceeds wrapper width, horizontal scroll is expected and acceptable.
- If total width is below wrapper width, apply filler policy:
  - If a single filler column is configured and eligible, it may absorb slack.
  - If filler is not configured or not eligible, slack remains visible at table end.
  - Slack-visible state (with no eligible filler) is expected behavior, not a defect.

### 4.1) Filler Column Policy
- Filler behavior is allowed only for one designated column (for example `description`).
- Filler expansion is allowed on initialization/load/reset only.
- Filler expansion is not allowed during active resize drag.
- Filler must be visible, resizable, and within max bound.
- Locked/non-resizable utility columns are never filler candidates.
- If filler reaches max bound, any remaining slack stays visible.

### 5) Persistence
- Persist only eligible column widths for current `{screen_id, table_key, variant}` context.
- Reload restores the same visible widths and order.
- Reset widths restores contract/default widths for current context only.

### 6) Reorder Interaction Contract
- Reorder changes order only; it does not mutate widths.
- Reorder placeholder and helper must show visible label text during drag.
- Reorder and resize handles must not conflict.

### 7) Dynamic Content Behavior
- Adding rows/groups does not alter existing column widths.
- Layout/format toggles may show/hide columns but keep remaining visible widths stable.
- Any re-clamp on visibility changes should be minimal and predictable.

### 8) Failure/Edge Behavior
- Missing/invalid config fails safely: table remains usable without managed enhancements.
- When resize hits bounds, drag stops at boundary (no jitter).
- While at boundary during drag, boundary indicator is visible; it clears on mouseup.
- Invalid saved widths are ignored server-side (fail closed).

## Acceptance Criteria
- User can widen `item` while preserving deterministic resize behavior (no artificial cap from contract max).
- Shrinking `description` does not cause unrelated left/right columns to auto-grow.
- After `Reset Widths`, active divider tracks pointer during drag (no detached cursor/divider feel).
- Width changes persist across refresh.
- Reset returns widths to baseline for current context.
- Utility columns (`handle`, `index`, `actions`) remain fixed and never absorb extra slack.
- `Reset Widths` immediate result matches post-refresh result (no transient oversize/undersize state).
- No disappearing header text during reorder drag.

## Implementation Phases

### Phase 1: Deterministic Base
- Enforce single-column-only resize behavior.
- Keep persistence stable with no snap-back.

### Phase 2: Variant + Dynamic Validation
- Validate behavior on `standard` and `insurance` variants.
- Validate dynamic row/group add flows preserve widths.

### Phase 3: UX Polish (Optional)
- Add minor feedback at resize bounds if needed.
- Keep behavior model unchanged.

## Detailed Implementation Plan

### A) Runtime Touchpoints
- `assets/js/admin-tables-interaction.js`
  - `normalizeVisibleColumnWidths(...)`: enforce deterministic width lock-in for visible columns.
  - `syncColumnVisibility(...)`: recompute effective table width after visibility changes.
  - `initResizable(...)` mousemove path: update only active column width and table effective width.
  - `setupColGroup(...)`: keep width variables bound per key.
- `assets/js/admin-tables-store.js`
  - `buildSafePrefsPayload(...)`: persist only eligible visible/resizable keys.
- `inc/modules/admin-table-columns/class-admin-table-config.php`
  - width sanitization remains authoritative (`sanitize_widths(...)`, bounds checks).
- `tests/e2e/quote-pricing-columns.spec.js`
  - resize/reorder/persistence behavior verification.

### B) Table Config Contract Extension (Filler)
- Add one optional table-level config field:
  - `filler_column_key` (string, optional)
- Validation/normalization rules:
  - sanitize via `sanitize_key`.
  - if key missing from `columns`, treat as not configured.
  - do not auto-select fallback filler.
- Default behavior:
  - no filler (`null`/empty) unless explicitly configured.

### C) Initialization / Load Algorithm
1. Build ordered visible column list from header + colgroup mapping.
2. Resolve each column width:
   - use saved width when valid.
   - otherwise use current measured width, then clamp to min/max.
3. Apply resolved widths to each visible column.
4. Compute container slack (`containerWidth - totalVisibleWidth`).
5. Apply filler policy only on init/load/reset:
   - if `slack > 0` and configured filler is eligible, grow filler up to max bound.
   - any remaining slack stays visible.
6. Set table pixel width/min-width to final visible sum.
7. Do not persist filler-induced widths unless a save event is triggered by user action.

### D) Active Resize Algorithm
1. On handle drag, resolve active column + bounds.
2. Compute next width from pointer delta and clamp to bounds.
3. Apply width only to active column.
4. Recompute table pixel width/min-width from visible columns.
5. Do not mutate neighboring columns during active drag.
6. On mouseup, persist via existing debounced save path.

### E) Reset Algorithm
1. Clear saved widths for current context.
2. Clear runtime column width vars for current table before reseeding (for example `--tah-col-*-width`) so stale drag widths are not reused.
3. Rebuild visible widths from contract/default measured state.
4. Run filler policy once (if configured and eligible).
5. Persist cleared override payload (`widths: {}`) as current behavior expects.

### F) Variant / Layout Change Algorithm
1. On `tah:table_layout_changed`, re-evaluate visible columns.
2. Preserve existing widths for still-visible keys where valid.
3. Clamp any out-of-bounds widths.
4. Recompute table width; optionally apply filler only if triggered path is init/load/reset.
5. Do not perform broad redistribution.

## Edge-Case Matrix
- Filler not configured:
  - keep slack visible; no implicit filler choice.
- Configured filler hidden:
  - skip filler growth; keep slack visible.
- Configured filler locked/non-resizable:
  - treat as ineligible; keep slack visible.
- Configured filler at max bound:
  - stop filler growth; keep remaining slack visible.
- All visible columns at min while user shrinks active column:
  - clamp active at min; drag has no further effect.
- Reordered columns:
  - width ownership remains by `data-tah-col` key, independent of position.
- Saved width violates new bounds:
  - ignore/clamp per existing sanitization rules.

## Automated Behavior Tests

### 1) Existing Test Harness (Recommended)
- Keep using:
  - `node tests/js/run-js-tests.js`
  - `bash tests/e2e/run-columns-smoke.sh`
- These already cover:
  - resize persistence
  - reorder persistence
  - no snap-back regression
  - drag helper/placeholder text regression

### 2) Add/Update Focused E2E Cases
- `resize-active-column-only`:
  - drag one column; assert immediate neighbors widths remain within tolerance.
- `horizontal-overflow-allowed`:
  - widen one column; assert wrapper overflow can increase without forcing neighbor growth.
- `divider-tracking-after-reset`:
  - reset widths, start drag, assert active column right edge stays near pointer position.
- `utility-columns-fixed`:
  - resize regular columns, assert `handle/index/actions` widths remain stable within tolerance.
- `bound-feedback-visible`:
  - drag past min/max and assert active header exposes boundary state during drag.
- `filler-policy-on-init`:
  - with configured filler, assert slack absorbed by filler on load/reset only.
- `no-filler-slack-visible`:
  - without filler config, assert slack remains (no auto-growth).
- `variant-toggle-stability`:
  - toggle format and assert preserved widths for common visible columns.

### 3) Minimal Unit/JS Regression Tests
- `tests/js/admin-table-core-regression.test.js`:
  - assert no shared config mutation seams reintroduced.
- `tests/js/admin-table-width-math.test.js`:
  - keep existing tests; width math may become less central if redistribution path is reduced.

### 4) Implementation Gate (Definition of Done)
- Focused new regressions pass.
- Existing JS suite passes.
- Columns smoke suite passes for standard + insurance.
- Manual sanity check in browser:
  - item can grow to max and stops.
  - description shrink does not grow unrelated neighbors.
  - refresh preserves widths.
  - reset returns baseline.

## Non-Goals
- No full grid-engine neighbor push/pull cascade behavior.
- No over-generalized layout solver for all edge cases.
- No breaking existing persistence contract shape.
- No implicit multi-column filler cascade when one filler is unavailable.
