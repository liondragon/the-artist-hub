# State Handoff Notes

## Pricing Module Phase Sequencing Note (2026-02-15)

- Phase 3 Task 1 ("frontend rendering function") was implemented ahead of Phase 2 by mistake.
- Added `inc/modules/pricing/class-quote-pricing-frontend.php` with `TAH_Quote_Pricing_Frontend` and global `tah_render_quote_pricing($post_id)`.
- Added frontend class loading in `TAH_Pricing_Module::load_module_classes()` (`inc/modules/pricing/class-pricing-module.php`) so the renderer is available on non-admin requests.
- `single-quotes.php` is not wired to call `tah_render_quote_pricing()` yet, so this early Phase 3 work has no customer-visible output at the moment.
- `docs/pricing_module_implementation_plan.md` currently marks Phase 3 Task 1 as complete; continue with remaining Phase 2 tasks next, then wire/integrate in Phase 3 Task 2.

## Pricing Module Phase 1 (Catalog + Formula)

- `TAH_Pricing_Module::load_module_classes()` now loads `class-price-formula.php` before the `is_admin()` guard, so formula parsing/resolution is available on non-admin paths too.
- `TAH_Price_Formula::parse()` is intentionally forgiving: empty/`$` maps to `default`, supports whitespace in `$+...`/`$-...`/`$*...`, and falls back to `default` for malformed formulas.
- `TAH_Price_Formula::resolve()` applies rounding after mode resolution; `tah_price_rounding <= 1` behaves as plain 2-decimal rounding, while larger multiples use nearest/up/down MROUND-style behavior.
- Pricing catalog impact preview is edit-only and uses `TAH_Pricing_Repository::count_active_quotes_by_pricing_item()` (distinct `quote_id` count from line items joined to `wp_posts`).
- "Active quotes" for impact preview currently excludes `post_status` values `trash`, `auto-draft`, and `inherit`; it does not attempt to infer business acceptance state yet.
- Catalog edit UX keeps catalog partition consistency by forcing the page filter to the edited row's `catalog_type` when `edit_item` points to an item from the other tab.

## Quote Sections Sync/Reset (Phase 1)

- Clobber-avoidance rule: `TAH_Quote_Sections::save_quote_sections()` only updates meta fields when the corresponding request fields are present. Missing fields do not overwrite stored values.
- Sync/Reset isolation rule: when `tah_quote_sections_action` is `sync` or `reset`, save handling short-circuits after updating `_tah_quote_sections_order`. Per-section meta (`_tah_section_{key}_enabled`, `_tah_section_{key}_mode`, `_tah_section_{key}_content`) is not mutated or deleted by Sync/Reset.
- Sync behavior: additive only; missing preset keys are appended to current order.
- Reset behavior: destructive for order only; resulting order mirrors current trade preset exactly.

## Trade Preset Ordering (Phase 2)

- Ordering persistence strategy: Trade Presets UI is a sortable list (`.tah-trade-sections-sortable`) with checkbox rows. Dragging changes DOM order; checked items are submitted in that order.
- Serialization format: selected keys are saved in `_tah_trade_default_sections` as an indexed PHP array (`array_values(array_unique(...))`) preserving submitted order after filtering unknown keys.
- Determinism: saved order is rendered first on subsequent edits, with any remaining library keys appended afterward.

## Pricing Table UI (Phase 2) (2026-02-15)

- Added `inc/modules/pricing/class-quote-pricing-metabox.php` (`TAH_Quote_Pricing_Metabox`) and relies on `TAH_Pricing_Module::load_module_classes()` requiring `class-quote-pricing-metabox.php` when present.
- Save path is JSON-only: `save_quote_pricing()` no-ops unless both hidden fields exist (`tah_quote_pricing_groups_json`, `tah_quote_pricing_items_json`) and nonce `_tah_quote_pricing_nonce` verifies.
- Custom quote shell now moves postbox `#tah_quote_pricing` into `#tah-quote-editor-main-pricing`; pricing JS binds `#post` submit to serialize both JSON payloads before WP save runs.
- Quantity arithmetic is UI-only; values like `12*15 + 8*10` resolve client-side to numeric `quantity`, but only normalized numeric `quantity` is persisted to the DB.
- Rate formula fallback behavior in JS: when catalog base price is zero/missing, `addition`/`percentage` formulas anchor off current resolved value to avoid collapsing to `$0.00` on blur.
- Line-item `sort_order` now respects the serialized per-group row index from JS (`sort_order` field) instead of relying on a global loop index in PHP.
- Empty line rows are intentionally skipped at save (`title === ''` short-circuit), so new blank placeholder rows never create DB records.
- `show_subtotal` and `is_collapsed` are persisted on quote groups; `show_subtotal` also controls subtotal visibility inside the admin table UI.

## Duplicate Quote Action (Phase 2) (2026-02-15)

- `TAH_Quote_Edit_Screen` now renders an active "Duplicate Quote" header button that points to a nonce-protected `admin-post.php?action=tah_duplicate_quote&quote_id={id}` URL.
- `TAH_Quote_Edit_Screen::handle_duplicate_quote()` creates a new `quotes` draft, copies quote taxonomies, copies most post meta, then clones pricing groups and line items through `TAH_Pricing_Repository` (IDs reset, group IDs remapped).
- Meta keys intentionally not copied to the duplicate: `_tah_prices_resolved_at`, view counters/timestamps, price-lock window keys, and edit-lock keys; this keeps the duplicate on a fresh pricing-resolve lifecycle.
- If pricing row duplication fails after draft creation, the new draft is deleted and the action exits with an error to avoid partial duplicates.

## Pricing Auto-Suggest Endpoint (Phase 2) (2026-02-15)

- Added `TAH_Pricing_Repository::search_catalog_items_for_quote($term, $catalog_type, $trade_id, $limit)` for active-item search with strict `catalog_type` filtering and trade prioritization.
- Added `wp_ajax_tah_search_pricing_items` in `TAH_Quote_Pricing_Metabox`:
  - Validates nonce + `edit_post` capability.
  - Resolves quote format from `_tah_quote_format` (defaults to `standard`) and uses it as catalog partition.
  - Resolves current quote trade from taxonomy and returns only matching-trade + universal (`trade_id IS NULL`) items.
- `quote-pricing.js` now attaches jQuery UI autocomplete to `.tah-line-title` fields (existing + dynamically added rows), calls the new endpoint, and on select populates:
  - `pricing_item_id`
  - title
  - description
  - `unit_type`
  - rate formula/resolved price (resets to catalog default `$`)
  - catalog price hidden field
- `class-quote-pricing-metabox.php` now enqueues `jquery-ui-autocomplete` and localizes `ajaxSearchAction`.

## Resolved Price Computation On Save (Phase 2) (2026-02-15)

- `TAH_Quote_Pricing_Metabox::persist_pricing_payload()` now recomputes each line item's `resolved_price` server-side via `TAH_Price_Formula::resolve(...)` using:
  - parsed line `rate_formula` (`price_mode` + `price_modifier`)
  - current catalog `unit_price` snapshot (when `pricing_item_id` is set)
  - global rounding options `tah_price_rounding` + `tah_price_rounding_direction`
- Client-supplied `resolved_price` is no longer trusted for persistence.
- `_tah_prices_resolved_at` is now updated on successful pricing payload persistence (WP save and AJAX draft save paths both call the same persistence method).

## Cascade Deletes (Phase 2) (2026-02-15)

- `TAH_Pricing_Module` now registers `before_delete_post` and routes `quotes` deletions through `TAH_Pricing_Repository::delete_quote_pricing_data()`.
- Delete order is explicitly preserved inside repository transaction: line items first, then groups, then quote post deletion proceeds in core.
- Group-level cascades during quote editing continue to flow through `save_quote_groups()` where stale group IDs delete their line items before group rows.

## Quote View Tracking (Phase 2) (2026-02-15)

- Added `inc/modules/pricing/class-quote-view-tracking.php` with global helper `tah_track_quote_view($post_id)` for customer-view counting.
- `single-quotes.php` now calls `tah_track_quote_view(get_the_ID())` inside the loop, so tracking only runs when the quote template is rendered.
- Tracking exclusions are enforced in the tracker class:
  - skip logged-in users
  - skip requests with `?nt`
- Successful track updates:
  - `_tah_quote_view_count` increment
  - `_tah_quote_last_viewed_at` set to current site time (`current_time('mysql')`)
- Quote edit header now displays summary when available: `"Viewed X times • Last viewed ..."` via `TAH_Quote_Edit_Screen::get_view_tracking_summary()`.

## Admin Notes Field (Phase 2) (2026-02-15)

- `TAH_Quote_Edit_Screen` now renders an inline "Admin Notes" card in the custom quote sidebar with textarea bound to `_tah_quote_admin_notes`.
- Added dedicated nonce guard (`_tah_quote_admin_notes_nonce`) and `save_post_quotes` handler `save_admin_notes()` to persist notes safely.
- Notes are saved as plain text via `sanitize_textarea_field`, remain admin-only, and are never rendered on `single-quotes.php`.

## Frontend Template Wiring (Phase 3 Task 2) (2026-02-15)

- `single-quotes.php` now renders quote layers in this order:
  1) `the_content()`
  2) `tah_render_quote_pricing(get_the_ID())` when available
  3) `tah_render_quote_sections(get_the_ID())` when available
- This finally wires the earlier `TAH_Quote_Pricing_Frontend` implementation into customer-facing quote pages.

## Auto-Suggest Glitch Follow-Up (2026-02-15)

- Reported symptom: suggestions rendered with blank left label and `$0.00` price despite matching items existing.
- Hardening applied on both response and client mapping:
  - Endpoint now always emits `label`/`value` with fallback order: `title` -> `sku` -> localized "Untitled item".
  - Endpoint `unit_price` now guards with `is_numeric()` before casting.
  - Client autocomplete now maps label with fallback order: `label` -> `title` -> `value` -> `sku`.
  - Client now parses prices through `normalizeCatalogPrice()` (accepts numeric and numeric-like strings) before rendering and before populating rate fields.
- Resulting behavior: suggestion rows should always show a non-empty item name when data exists, and unit price should no longer collapse to `$0.00` from response-shape or formatting mismatch.

## Trade Pricing Preset Field (Phase 4 Task 1) (2026-02-15)

- Added `inc/modules/pricing/class-pricing-trade-presets.php` (`TAH_Pricing_Trade_Presets`) and wired it via `TAH_Pricing_Module::load_module_classes()`.
- Trade taxonomy add/edit screens now include a "Pricing Preset (Standard Quotes)" JSON field backed by term meta `_tah_trade_pricing_preset`.
- Save path validates nonce + `manage_categories`, normalizes preset shape (`groups[]` with `name`, `selection_mode`, `show_subtotal`, `is_collapsed`, `items[]` of `pricing_item_sku` + `quantity`), and persists normalized JSON.
- Empty input persists `{"groups":[]}`; invalid JSON input is ignored (existing saved preset remains unchanged).

## Trade Preset Population On Trade Selection (Phase 4 Task 2) (2026-02-15)

- Added `wp_ajax_tah_apply_trade_pricing_preset` in `TAH_Quote_Pricing_Metabox`:
  - Validates nonce + `edit_post` capability.
  - Applies only to `_tah_quote_format = standard`.
  - Enforces overwrite guard: if quote already has persisted pricing groups/line items, returns `already_populated` and does not replace data.
  - Loads trade preset JSON from `_tah_trade_pricing_preset`, resolves each `pricing_item_sku` to active standard catalog items, and returns prebuilt group/item payload for UI rendering.
  - Missing SKUs are skipped and returned as `missing_count`/`missing_skus` for admin feedback.
- Added repository helper `TAH_Pricing_Repository::get_active_item_by_sku_and_catalog($sku, $catalog_type)` for strict SKU + catalog partition + active status lookup.
- `quote-pricing.js` now listens to `input[name=\"tah_trade_term_id\"]` changes:
  - Requests preset payload from the new AJAX action.
  - Client-side overwrite guard: if any pricing row already has a non-empty title, preset application is skipped.
  - Rebuilds pricing groups/items in the editor from the returned payload and recomputes totals.
  - Shows status feedback for success, no preset, unsupported format, already populated, and missing-SKU skip cases.

## Frontend Pricing CSS (Phase 3 Task 3) (2026-02-15)

- Added pricing frontend styles to `assets/css/_content.css` under `.pricing .tah-*` selectors to match structured quote tables:
  - Group card/header styling, collapsed subtotal block, details toggle.
  - Table layout for index/item/qty/rate/total columns.
  - Discount + not-selected visual states.
  - Subtotal and grand total emphasis rows.
- Added mobile adjustments (`@media (max-width: 780px)`) for tighter table spacing/column width behavior.
- Added print rules (`@media print`) to improve hardcopy output:
  - Avoid group page breaks.
  - Remove background fills.
  - Hide details summaries while keeping details content visible.
  - Keep excluded rows fully legible in print.

## Quote Format Selector + Trade Context Filtering (Phase 6 Task 1) (2026-02-15)

- `TAH_Quote_Pricing_Metabox` now renders a format selector at the top of the pricing metabox (`tah_quote_format`) plus an insurance tax-rate input (`tah_quote_tax_rate`).
- Save behavior now persists format/tax meta on both save paths:
  - Standard WP save (`save_post_quotes`) via `persist_quote_format_meta_from_request()`
  - AJAX draft save (`wp_ajax_tah_save_pricing`) via the same helper
- Auto-suggest and preset-apply endpoints now read requested format from the active request when present (fallback to persisted `_tah_quote_format`), so unsaved format toggles still query the correct catalog partition.
- `quote-pricing.js` now applies a format mode on load/change:
  - Standard mode: existing grouped UI behavior
  - Insurance mode: flattens to one implicit group client-side, hides group-management controls, shows tax-rate field/hint
- Trade selector filtering is now client-side and format-aware:
  - Uses localized `tradeContexts` map (`_tah_trade_context` term meta)
  - Shows only `standard`/`both` trades for standard format
  - Shows only `insurance`/`both` trades for insurance format
  - If currently selected trade becomes invalid after a format switch, selection falls back to `None`
- Trade preset auto-apply remains standard-only in JS and server responses; insurance mode no-ops by design.

## Trade Context Field On Trade Taxonomy (Phase 6 Task 2) (2026-02-15)

- `TAH_Pricing_Trade_Presets` now owns trade context meta alongside pricing preset meta.
- Trade add/edit forms now render a `Trade Context` dropdown (`tah_trade_context`) with values:
  - `standard` (default)
  - `insurance`
  - `both`
- Save flow now persists `_tah_trade_context` whenever the field is posted and normalizes legacy `all` to `both`.
- Preset JSON save behavior remains unchanged (same nonce/cap checks, same normalization); context save is independent so context updates are not blocked by preset JSON changes.

## Insurance Metabox Variant (Phase 6 Task 3) (2026-02-15)

- Important behavioral decision: in insurance mode, **F9 note is the same field as line `description`** (no separate note input). The F9 button only toggles visibility of that description input.
- `TAH_Quote_Pricing_Metabox` now supports insurance-mode table columns in both server-rendered and JS-added rows:
  - SKU
  - Material
  - Labor
  - Unit Price (computed)
  - Tax
  - F9 note via existing description field (with F9 toggle button)
- Insurance persistence semantics in `persist_pricing_payload()`:
  - Forces one implicit group (`Insurance Items`) regardless of incoming grouped payload.
  - Computes `resolved_price` as `material_cost + labor_cost`.
  - Forces `price_mode = override` and `price_modifier = resolved_price`.
  - Stores `note` from the same description value (per request to reuse description for F9).
- Insurance totals in `quote-pricing.js` now include three levels:
  - Subtotal: sum of `qty * unit_price`
  - Tax Total: sum of `qty * material_cost * tax_rate`
  - Grand Total: subtotal + tax total
- Tax behavior is intentionally material-only:
  - Line tax uses `material_cost` only (labor excluded).
  - Per-line `tax_rate` overrides quote-level rate; blank line tax rate falls back to quote-level `tah_quote_tax_rate`.
- Insurance mode UI constraints:
  - Group management controls remain hidden.
  - Margin column and rate badge hidden.
  - Line `Rate` input becomes read-only and displays computed unit price.
- Trade preset apply remains standard-only.

## Insurance Frontend Template Variant (Phase 6 Task 4) (2026-02-15)

- `TAH_Quote_Pricing_Frontend::render()` is now format-aware:
  - `_tah_quote_format = insurance` routes to a dedicated insurance renderer.
  - `standard` behavior is preserved in a separate standard renderer path.
- Insurance frontend output now renders as one Xactimate-style table with columns:
  - `#`, `Description`, `SKU`, `Qty`, `Unit Price`, `Tax`, `Total`
- Insurance totals semantics now match admin calculations:
  - Line subtotal = `qty * resolved_price`
  - Line tax = `qty * material_cost * effective_tax_rate`
  - Effective tax rate = line `tax_rate` when present, otherwise quote `_tah_quote_tax_rate`
  - Line total = line subtotal + line tax
  - Footer rows render `Subtotal`, `Tax Total`, and `Grand Total`
- F9 notes now render as dedicated detail rows directly below their parent line item.
- Optional trade category visual dividers are supported:
  - If a line item links to a catalog item with `category`, the renderer emits a category divider row when the category changes.

## Insurance Frontend CSS (Phase 6 Task 5) (2026-02-15)

- Added insurance-specific frontend styling in `assets/css/_content.css` under:
  - `.tah-quote-pricing-insurance`
  - `.tah-pricing-table-insurance`
  - `.tah-pricing-insurance-category-row`
  - `.tah-pricing-insurance-note-row`
  - `.tah-pricing-insurance-tax-total-row`
  - `.tah-pricing-insurance-grand-total-row`
- Insurance variant is now visually distinct from standard quote tables:
  - Light blue header treatment for insurance columns.
  - Dedicated category divider row styling.
  - F9 note rows rendered with subtle separated background + dashed divider.
  - Distinct tax/grand-total footer emphasis.
- Mobile adjustments include narrower insurance column constraints and note text sizing.
- Print rules now flatten insurance-specific backgrounds to white and keep note rows readable with solid separators.

## Pricing Payload Refactor (Behavior-Preserving Split) (2026-02-15)

- `TAH_Quote_Pricing_Metabox::persist_pricing_payload()` remains the orchestration entrypoint but no longer performs all row-building inline.
- Introduced helper classes under `inc/modules/pricing/`:
  - `TAH_Pricing_Group_Payload_Processor` (`class-pricing-group-payload-processor.php`)
    - Insurance group normalization behavior (`empty` -> implicit insurance group, non-empty -> first group only)
    - Group row sanitization + normalized values
    - Group client-key to persisted-ID map generation
  - `TAH_Pricing_Line_Item_Payload_Processor` (`class-pricing-line-item-payload-processor.php`)
    - Line item sanitization/normalization
    - Catalog price lookup cache for formula resolution
    - Standard formula resolution via `TAH_Price_Formula`
    - Insurance resolved-price semantics (`material_cost + labor_cost`, override mode)
    - Item type normalization (`standard` auto-switches to `discount` for negative resolved prices)
- Module bootstrap now requires both helper files before `class-quote-pricing-metabox.php` in `TAH_Pricing_Module::load_module_classes()`.
- No request/API contract changes:
  - Same metabox fields
  - Same AJAX/save endpoints
  - Same repository writes and `_tah_prices_resolved_at` update behavior
