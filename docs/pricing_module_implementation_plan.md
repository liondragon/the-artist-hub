# Pricing Module — Implementation Plan

**Spec Reference:** [`docs/pricing_module.md`](file:///home/zhenya/dev/the-artist-hub/docs/pricing_module.md) — the authoritative specification for this feature. The generic `Canonical_Spec_Template.md` format was not used; `pricing_module.md` serves as the single source of truth.

**Verification Baseline:** Manual-only Checklist. No existing test harness or test files. Test debt is logged under Known Debt.

> **Scope:** This plan implements the pricing module as defined in `pricing_module.md`. No new user-facing features beyond what the spec describes.

---

## Repo Map (Current State)

| File | Purpose |
|---|---|
| [`functions.php`](file:///home/zhenya/dev/the-artist-hub/functions.php) | Theme entry point. Loads `TAH_Module_Registry::boot()` |
| [`inc/modules/class-module-registry.php`](file:///home/zhenya/dev/the-artist-hub/inc/modules/class-module-registry.php) | Boots theme modules. Currently loads `info-sections` only |
| [`inc/admin/class-quote-sections.php`](file:///home/zhenya/dev/the-artist-hub/inc/admin/class-quote-sections.php) | **Architectural model.** 926-line class: metabox rendering, save hooks, trade presets, frontend rendering. This module is the pattern we follow. |
| [`inc/admin/class-trade-presets.php`](file:///home/zhenya/dev/the-artist-hub/inc/admin/class-trade-presets.php) | Trade taxonomy term meta fields for section presets |
| [`inc/cpt/quotes.php`](file:///home/zhenya/dev/the-artist-hub/inc/cpt/quotes.php) | Quote CPT registration, customer info metabox, admin columns |
| [`single-quotes.php`](file:///home/zhenya/dev/the-artist-hub/single-quotes.php) | Frontend quote template. Renders `the_content()` then `tah_render_quote_sections()` |
| [`assets/js/quote-sections.js`](file:///home/zhenya/dev/the-artist-hub/assets/js/quote-sections.js) | Admin JS for quote sections metabox (drag-and-drop, toggling, etc.) |
| [`assets/css/admin.css`](file:///home/zhenya/dev/the-artist-hub/assets/css/admin.css) | Admin styles for metaboxes |
| [`inc/migrations/`](file:///home/zhenya/dev/the-artist-hub/inc/migrations/) | Empty directory — ready for schema migrations |

**Key patterns to follow:**
- Module loading via `TAH_Module_Registry::boot()` → `require_once` + `::boot()`
- Admin metabox class with `__construct()` hooking into `add_meta_boxes`, `save_post`, `admin_enqueue_scripts`
- Frontend rendering via a global function called from `single-quotes.php`
- Trade presets stored as term meta with sortable UI on taxonomy screens

---

## Phase 0 — Database Schema & Module Scaffold

**Goals:** Create the three custom tables, the schema versioning system, and the module file structure so subsequent phases have a working foundation.

**Non-Goals:** No admin UI, no frontend rendering, no cron. Just tables + wiring.

**Acceptance:** Tables exist after theme activation. `tah_pricing_db_version` option is set. Module loads without errors.

### Tasks

- [ ] **Create schema migration file** (Spec: §Database Schema, §Quote Formats)
  - Artifacts: `inc/migrations/pricing-tables.php`
  - Interfaces: None — internal only
  - Done When: File contains `CREATE TABLE` for `wp_tah_pricing_items` (including `catalog_type` partition column), `wp_tah_quote_groups`, `wp_tah_quote_line_items` (including insurance-specific nullable columns: `material_cost`, `labor_cost`, `line_sku`, `tax_rate`, `note`), indexes, and charset matching spec schema. Uses `dbDelta()`.

- [ ] **Create pricing module bootstrap** (Spec: §Database Schema — Schema Versioning)
  - Artifacts: `inc/modules/pricing/class-pricing-module.php`
  - Interfaces: `TAH_Module_Registry::boot()` hook
  - Done When: `TAH_Pricing_Module::boot()` runs schema migration when `tah_pricing_db_version` is outdated. Module registered in `class-module-registry.php`.

- [ ] **Create pricing repository class** (Spec: §Database Schema, §Portability)
  - Artifacts: `inc/modules/pricing/class-pricing-repository.php`
  - Interfaces: `TAH_Pricing_Repository` wrapping `$wpdb` with typed methods
  - Done When: Repository has CRUD methods for all three tables: `get_catalog_items()`, `get_item_by_sku()`, `insert_item()`, `update_item()`, `deactivate_item()`, `get_quote_groups()`, `get_quote_line_items()`, `save_quote_groups()`, `save_quote_line_items()`, `delete_quote_pricing_data()`.

- [ ] Verify Phase 0: activate theme, confirm tables created and `tah_pricing_db_version` set (Manual step #1)

---

## Phase 1 — Admin Catalog & Price Formula Engine

**Goals:** Build the pricing catalog admin page and the price formula parser/resolver. These are the foundational pieces everything else depends on.

**Non-Goals:** No quote-level line items metabox yet. No frontend rendering. No cron.

**Acceptance:** Admin can add/edit/deactivate catalog items. Formula strings parse correctly into `price_mode` + `price_modifier`. Price history appends automatically on updates.

### Tasks

- [ ] **Implement pricing catalog admin page** (Spec: §Admin UX — Pricing Catalog Admin, §Quote Formats — Catalog Partitioning)
  - Artifacts: `inc/modules/pricing/class-pricing-catalog-admin.php`
  - Interfaces: WP admin menu page under "Quotes" menu. Tabs or filter to switch between standard and insurance catalog views. Add/edit/deactivate catalog items. Trade assignment dropdown (filtered by `_tah_trade_context`). Price history display.
  - Depends On: Phase 0 (repository + tables)
  - Done When: Admin can CRUD pricing items in both catalogs. Catalog view filters by `catalog_type`. Trade dropdown shows only trades matching the active catalog type. `is_active` toggle works. Editing `unit_price` appends to `price_history` JSON.

- [ ] **Implement price formula parser** (Spec: §Price Modification Model)
  - Artifacts: `inc/modules/pricing/class-price-formula.php`
  - Interfaces: `TAH_Price_Formula::parse($input) → {mode, modifier}` and `TAH_Price_Formula::resolve($mode, $modifier, $catalog_price, $rounding, $direction) → $resolved_price`
  - Done When: All formula syntaxes parse correctly (`$`, `$+150`, `$-100`, `$*1.1`, `$*0.9`, `1850`, `-200`). Resolver computes correct effective prices and applies MROUND rounding.

- [ ] **Implement impact preview count** (Spec: §Observability — Catalog Edit)
  - Artifacts: Same catalog admin file
  - Done When: When editing a pricing item, shows count of affected active quotes below the price field.

- [ ] Verify Phase 1: add several catalog items across trades, modify prices and confirm history appends, test all formula parsing variants (Manual step #2)

---

## Phase 2 — Quote Line Items Metabox

**Goals:** Build the admin-side metabox for managing groups and line items on individual quotes. This is the core editing experience.

**Non-Goals:** No frontend rendering. No cron. No trade presets for pricing (info section presets unaffected).

**Acceptance:** Admin can add groups with selection modes, add line items (via auto-suggest and custom), edit quantities and formulas, reorder items/groups, and save all data to the custom tables.

### Tasks

- [ ] **Implement quote line items metabox** (Spec: §Admin UX — Line Items Metabox)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-metabox.php`, `assets/js/quote-pricing.js`, `assets/css/admin.css` (additions)
  - Interfaces: WP metabox on quotes CPT edit screen. Groups panel with name/selection_mode. Line items within groups with inline formula fields.
  - Depends On: Phase 0 (repository), Phase 1 (formula parser)
  - Done When: Groups can be added/edited/removed with name, description, selection_mode, show_subtotal, is_collapsed. Line items can be added/removed within groups. Inline formula field shows resolved price unfocused, formula focused, with badges (DEFAULT/MODIFIED/CUSTOM). Drag-and-drop reorder for both groups and items. All data persists on save.

- [ ] **Implement auto-suggest endpoint** (Spec: §Admin UX — Auto-Suggest, §Quote Formats — Catalog Partitioning)
  - Artifacts: Same metabox file or separate AJAX handler in the module
  - Interfaces: AJAX/REST endpoint querying `wp_tah_pricing_items` by title, filtered by `catalog_type` matching quote format AND quote's trade, with universal items always shown
  - Done When: Typing in the "add item" field shows matching catalog items from the correct catalog type only. No cross-contamination between standard and insurance items. Selecting one populates title, description, unit_type, unit_price.

- [ ] **Implement resolved price computation on save** (Spec: §Price Modification Model)
  - Artifacts: Repository + metabox save handler
  - Interfaces: `_tah_prices_resolved_at` post meta
  - Done When: On save, `resolved_price` is computed for each line item using formula engine + rounding settings. `_tah_prices_resolved_at` is set. Totals are computed on-the-fly at render time (`SUM(qty × resolved_price)` per group) — not stored.

- [ ] **Wire cascade deletes** (Spec: §Maintenance — Cascade Deletes)
  - Artifacts: Module bootstrap file
  - Interfaces: `before_delete_post` hook
  - Done When: Permanently deleting a quote removes all its groups and line items from custom tables. Deleting a group removes its line items.

- [ ] Verify Phase 2: create a quote with multiple groups and items, test formula inputs, verify auto-suggest, verify drag-and-drop, verify totals, delete a quote and confirm cascade (Manual step #3)

---

## Phase 3 — Frontend Rendering (Phase 1 — Static)

**Goals:** Render pricing module data on the customer-facing quote page, matching the visual style of existing Excel-pasted tables.

**Non-Goals:** No interactive JS (checkboxes/radio buttons). No customer-facing selections. Static only.

**Acceptance:** Quote page shows post content first, then pricing tables. Groups render as styled table sections. Subtotals and grand total display correctly. Quotes without pricing data render unchanged (backward compat).

### Tasks

- [ ] **Implement frontend rendering function** (Spec: §Frontend Rendering — Phase 1)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-frontend.php`
  - Interfaces: `tah_render_quote_pricing($post_id)` global function, called from `single-quotes.php`
  - Depends On: Phase 0 (repository)
  - Done When: Each group renders as a styled table with heading and optional description text. Selection mode rules enforced: `all` groups include every item in totals (ignore `is_selected`); `multi` groups include only `is_selected = 1` items; `single` groups include exactly one selected item. Groups with `is_collapsed = true` render collapsed (heading + subtotal visible, "Show details" toggle to expand). Subtotals shown when `show_subtotal = true`. Grand total rendered. Description expandable detail per line item. Discount items render with distinct styling (negative values shown correctly).

- [ ] **Update single-quotes.php template** (Spec: §Frontend Rendering — Render Order)
  - Artifacts: `single-quotes.php`
  - Interfaces: Template rendering order
  - Done When: Post content renders first (via `the_content()`), then `tah_render_quote_pricing(get_the_ID())` renders below, then `tah_render_quote_sections()` for info sections. Quotes with no pricing data show no extra output (backward compat).

- [ ] **Add frontend CSS** (Spec: §Frontend Rendering)
  - Artifacts: `assets/css/_content.css` or new `pricing-frontend.css`
  - Done When: Tables match the visual style of existing Excel-pasted quote tables. Print-friendly via `@media print`.

- [ ] Verify Phase 3: view a quote with pricing data on the frontend, confirm layout matches spec, view a quote without pricing data confirms no change, test print preview (Manual step #4)

---

## Phase 4 — Trade Pricing Presets

**Goals:** Allow trades to define default groups and items that auto-populate when a trade is selected on a new quote.

**Non-Goals:** No cron. No email. Presets include both groups and items.

**Acceptance:** Selecting a trade on a new quote populates default pricing groups (and optionally items). Missing SKUs are skipped with admin notice. Existing quotes are not affected.

### Tasks

- [ ] **Add pricing preset field to trade taxonomy** (Spec: §Admin UX — Trade Pricing Presets)
  - Artifacts: `inc/modules/pricing/class-pricing-trade-presets.php`
  - Interfaces: JSON preset builder on Trade add/edit screen. Term meta key: `_tah_trade_pricing_preset`
  - Depends On: Phase 2 (metabox)
  - Done When: Trade edit screen shows a preset builder for configuring default groups and items. Preset saved as JSON to term meta.

- [ ] **Wire preset population on trade selection** (Spec: §Trade Pricing Presets — Behavior)
  - Artifacts: Quote pricing metabox JS + PHP handler
  - Interfaces: AJAX handler triggered when trade is selected on a new quote
  - Done When: Selecting a trade populates pricing groups and items from preset. Missing SKUs skipped with admin notice. Already-populated quotes are not overwritten.

- [ ] Verify Phase 4: create trade preset, create new quote with that trade, confirm groups/items populate, test with a missing SKU (Manual step #5)

---

## Phase 5 — Cron, Price Locking & Observability

**Goals:** Implement automated price updates, customer price locking, admin observability signals.

**Non-Goals:** No email sending (deferred to email module Phase 2/3). Cron logs the event but does not send mail.

**Acceptance:** Changing a catalog item price triggers recalculation on the next cron run. Price lock prevents recalculation. Admin sees cron status and pricing status column.

### Tasks

- [ ] **Implement price update cron job** (Spec: §Cron — Central Price Updates)
  - Artifacts: `inc/modules/pricing/class-pricing-cron.php`
  - Interfaces: WP-Cron scheduled event (`tah_pricing_update`). Hook: `tah_cron_frequency` setting. Per-quote independent processing.
  - Depends On: Phase 0 (repository), Phase 1 (formula engine)
  - Done When: Cron finds all quotes with stale `_tah_prices_resolved_at`, skips locked quotes, stores old `resolved_price` to `previous_resolved_price`, recalculates `resolved_price`, updates `_tah_prices_resolved_at`. Email sending deferred — cron only logs affected quote IDs. `_tah_cron_last_run`, `_tah_cron_last_status`, `_tah_cron_quotes_updated` options set.

- [ ] **Implement price lock endpoint** (Spec: §Cron — Price Locking)
  - Artifacts: Same cron file or separate handler
  - Interfaces: `?action=tah_lock_price&quote_id=X&token=Y` public URL. Token = `wp_hash(quote_id . post_date)`.
  - Done When: Valid token **reverts** `resolved_price` to `previous_resolved_price` for all affected line items, sets `_tah_price_locked_until` to `now + 3 days`. After lock expires, next cron run recalculates at current catalog prices. Expired/invalid tokens show friendly message with link to current quote.

- [ ] **Add Global Pricing Settings page** (Spec: §Global Pricing Settings)
  - Artifacts: `inc/modules/pricing/class-pricing-settings.php`
  - Interfaces: WP admin settings page or section. Options: `tah_price_rounding`, `tah_price_rounding_direction`, `tah_cron_frequency`. Cron status display. Quote expiry settings deferred to Phase 2.
  - Done When: Settings page saves and loads all options. Cron status section shows last run time, status, and quote count.

- [ ] **Add pricing status column to quotes admin list** (Spec: §Observability — Admin Quotes List)
  - Artifacts: `inc/cpt/quotes.php` or module file
  - Interfaces: Custom column in `manage_quotes_posts_columns`
  - Done When: Column shows "Current", "Updated [date]", or "Price locked until [date]" per quote. Column is sortable and filterable.

- [ ] Verify Phase 5: change a catalog price, run cron manually via WP-CLI (`wp cron event run tah_pricing_update`), confirm quotes updated, test price lock URL, check settings page and admin column (Manual step #6)

---

## Phase 6 — Insurance Quote Format

**Goals:** Implement the insurance quote format variant — Xactimate-style flat item list with price components, per-line SKU, tax, and F9 notes.

**Non-Goals:** No interactive frontend. No new CPT. Insurance uses the same `quotes` post type with `_tah_quote_format = 'insurance'`.

**Acceptance:** Admin can create an insurance quote with flat line items (no groups UI). Lines have material + labor components, SKU, tax, and optional F9 notes. Frontend renders Xactimate-style table with subtotal, tax total, and grand total.

### Tasks

- [ ] **Add format selector to quote edit screen** (Spec: §Quote Formats)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-metabox.php` (modification)
  - Interfaces: Dropdown/toggle at top of pricing metabox. Sets `_tah_quote_format` post meta. Switches between standard and insurance metabox variants. Trade selector filters trades by `_tah_trade_context` matching the selected format.
  - Done When: Format selector persists on save. Choosing "insurance" hides groups UI, shows flat item list + tax rate field, filters trade selector to insurance/both trades. Choosing "standard" shows normal groups UI, filters trades to standard/both.

- [ ] **Add trade context field to trade taxonomy** (Spec: §Quote Formats — Trade Context)
  - Artifacts: `inc/modules/pricing/class-pricing-trade-presets.php` or `inc/admin/class-trade-presets.php` (modification)
  - Interfaces: Dropdown on Trade add/edit screen for `_tah_trade_context` term meta: `standard`, `insurance`, `both` (default: `standard`)
  - Done When: Trade context saves and loads correctly. Trade selectors on quote edit screen filter by context. Catalog admin trade dropdown filters by context matching the active catalog type.

- [ ] **Implement insurance metabox variant** (Spec: §Quote Formats, §Database Schema — insurance columns)
  - Artifacts: Same metabox file + `assets/js/quote-pricing.js` (additions)
  - Interfaces: Flat line item list with columns: line #, title, SKU, material cost, labor cost, unit price (computed), qty, tax rate, note (F9 toggle). Per-quote sales tax rate field (`_tah_quote_tax_rate`). Auto-suggest queries `catalog_type = 'insurance'` only.
  - Depends On: Phase 2 (base metabox)
  - Done When: Insurance items save `material_cost`, `labor_cost`, `line_sku`, `tax_rate`, `note` to line items table. Unit price auto-computed from material + labor. All items in one implicit group (`group_id` references a single auto-created group). Totals include subtotal + tax total + grand total.

- [ ] **Implement insurance frontend template variant** (Spec: §Frontend Rendering — Insurance Format)
  - Artifacts: `inc/modules/pricing/class-quote-pricing-frontend.php` (modification)
  - Interfaces: Format-aware rendering in `tah_render_quote_pricing()`
  - Depends On: Phase 3 (base frontend rendering)
  - Done When: Insurance quotes render Xactimate-style table: #, Description, SKU, Qty, Unit Price, Tax, Total. F9 notes render as indented detail rows. Subtotal, tax total, grand total at bottom. Trade categories as optional visual section dividers.

- [ ] **Add insurance frontend CSS** (Spec: §Frontend Rendering — Insurance Format)
  - Artifacts: `assets/css/_content.css` or `pricing-frontend.css` (additions)
  - Done When: Insurance table styled distinctly from standard format. Print-friendly.

- [ ] Verify Phase 6: create insurance trades with `_tah_trade_context = 'insurance'`, add insurance catalog items, create an insurance quote, confirm trade selector shows only insurance trades, add items confirming no standard items appear, save, view frontend, confirm Xactimate-style rendering with correct totals (Manual step #7)

---

## Verification Plan

### Manual Verification Checklist

| # | Step | Covers |
|---|---|---|
| 1 | Activate theme → check DB for 3 tables + `tah_pricing_db_version` option | Phase 0: Schema + versioning |
| 2 | Admin → Pricing Catalog → Add items, edit prices, confirm history appends, deactivate item | Phase 1: Catalog CRUD + price history |
| 3 | Admin → Quote edit → Add groups + line items, test formulas (`$`, `$+150`, `$*1.1`, `1850`, `-200`), verify auto-suggest, reorder, save, confirm DB data | Phase 2: Metabox + formula engine |
| 4 | View quote on frontend → confirm post content renders first, then pricing tables, then info sections. Verify backward compat on old quotes. Print preview. | Phase 3: Frontend rendering |
| 5 | Admin → Trade edit → Create pricing preset → New quote → Select trade → Confirm groups populate | Phase 4: Trade presets |
| 6 | Change catalog price → Run `wp cron event run tah_pricing_update` → Confirm quote prices updated, `previous_resolved_price` set, cron status options set. Test lock URL. Check admin column. | Phase 5: Cron + observability |
| 7 | Admin → New Quote → Set format to "insurance" → Add items with material/labor/SKU/tax/notes → Save → View frontend → Confirm Xactimate-style table, F9 notes, subtotal + tax + grand total | Phase 6: Insurance format |

### Test Debt (Automated)

No automated test harness currently exists. The following should be covered by automated tests in a future phase:

- [ ] `TAH_Price_Formula::parse()` — unit tests for all formula variants
- [ ] `TAH_Price_Formula::resolve()` — unit tests for rounding modes
- [ ] `TAH_Pricing_Repository` — integration tests for CRUD operations
- [ ] Cron recalculation logic — integration test with mock data
- [ ] Cascade delete behavior — integration test

---

## Known Debt & Open Questions

- [ ] **No automated tests yet.** The project has no test harness. Suggest adding PHPUnit + WP test framework as a future foundational task.
- [ ] **Email sending deferred.** Cron logs price updates but does not send re-engagement emails. Requires email module (Phase 2/3 per spec).
- [ ] **Interactive frontend deferred.** Phase 2 of frontend (checkboxes/radio buttons + live totals JS) is not in this plan.
- [ ] **WP-Cron reliability.** For production, user should configure a real system crontab (`wp cron event run --due-now`) per spec recommendation.
- [ ] **Catalog bulk import.** No mechanism to import existing pricing from Excel/CSV. Likely needed for initial population.
- [ ] **Price components for standard quotes.** `material_cost` and `labor_cost` columns exist on all line items but are only used by insurance quotes. Standard quotes will adopt price components in a future phase.
- [ ] **Quote expiry behavior undefined.** `tah_quote_expiry_enabled` and `tah_quote_expiry_days` settings are deferred to Phase 2. Expiry behavior (frontend banner, prevent acceptance, status change) needs design.
- [ ] **Table prefix.** Implementation must use `$wpdb->prefix . 'tah_*'` — not hardcoded `wp_tah_*` — to support non-default WP prefixes.
