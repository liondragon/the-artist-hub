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
