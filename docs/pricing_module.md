# Pricing Module — Design Summary

> Agreed upon during discussion on 2026-02-13.

## Problem

Quotes currently rely on copy-pasting HTML tables from Excel into the Classic Editor content area for line items and pricing. The goal is to move all pricing data into WordPress with a structured, database-driven system that eliminates the Excel dependency while keeping the same clean frontend output.

## Current Architecture (Context)

The quotes CPT (`quotes`) currently has two content layers:

1. **Post content (Classic Editor)** — Raw HTML tables with line items and prices (the Excel-pasted part). Renders under `<h3>Base Quote</h3>` and `<h3>Optional Items</h3>` headings.
2. **Info Sections (`TAH_Quote_Sections`)** — Collapsible content sections (e.g., "Dust Containment", "Finish Options") managed via a metabox with drag-and-drop ordering, trade presets, and per-quote overrides. This layer is already well-structured and stays as-is.

Both existing layers stay fully functional. The pricing module is added as a **new third layer** — an optional, structured alternative to the Excel-pasted tables. Old quotes continue to work unchanged, and new quotes can still be created the old way if desired. When the pricing module has line items for a quote, they render alongside the post content box.

---

## Quote Formats

The same `quotes` CPT supports multiple **quote formats** via a `_tah_quote_format` post meta field. The format controls which metabox variant and template variant to render.

| Format | Use Case | Groups | Columns | Tax |
|---|---|---|---|---|
| `standard` (default) | Flooring/trade quotes | Yes — `all`, `multi`, `single` | Description, Qty, Unit, Price, Total | Built into unit prices |
| `insurance` | Xactimate-style insurance estimates | No — flat item list (one implicit group) | #, Description, SKU, Qty, Unit Price, Tax, Total | Per-quote sales tax rate |

**Design principle:** One CPT, one admin listing, one repository, one cron. The format determines:
- Which metabox variant renders in admin (groups + items vs. flat items + tax rate)
- Which frontend template variant renders (grouped tables vs. single Xactimate-style table)
- Which columns are populated on line items (insurance-specific columns are nullable, ignored by standard quotes)

### Catalog Partitioning

The pricing catalog (`wp_tah_pricing_items`) is **logically partitioned** by a `catalog_type` column (`standard` or `insurance`). Same item names (e.g., "Sand and Finish") can exist in both catalogs with different rates. Auto-suggest only shows items matching the quote's format — no cross-contamination. The admin catalog page shows tabs or a filter to switch between standard and insurance views.

### Trade Context

The `trade` taxonomy is shared between formats, but each term has a `_tah_trade_context` term meta indicating which format(s) it belongs to: `standard`, `insurance`, or `both`. This prevents insurance trades ("Water Damage", "Fire Restoration") from appearing in standard quote trade selectors and vice versa, while allowing truly shared trades if needed.

**Price components** (`material_cost` + `labor_cost`) are insurance-only for now. They will be added to standard quotes in a future phase.

---

## Database Schema

Three custom tables. Designed to be WP-agnostic for future portability to Laravel/Next.js.

> [!NOTE]
> Table names use `wp_tah_*` as shorthand throughout this doc. Implementation **must** use `$wpdb->prefix . 'tah_*'` to support non-default prefixes.

### `wp_tah_pricing_items` — Central Pricing Catalog

| Column | Type | Description |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `sku` | `VARCHAR(100) UNIQUE` | Identifier sku (e.g., `sand_finish_clear`). Renamed from `key` to avoid MySQL reserved word |
| `title` | `VARCHAR(255)` | Display name ("Sand and Finish (Clear)") |
| `description` | `TEXT` | Default detailed note for the line item |
| `unit_type` | `VARCHAR(50)` | `sqft`, `linear_ft`, `hour`, `flat`, `each`, etc. |
| `unit_price` | `DECIMAL(10,2) DEFAULT 0.00` | Current base price per unit |
| `trade_id` | `BIGINT UNSIGNED NULL` | FK to WP trade taxonomy term; `NULL` = universal item |
| `category` | `VARCHAR(100) NULL` | Sub-grouping within a trade (e.g., "Labor", "Materials", "Logistics") |
| `sort_order` | `INT UNSIGNED DEFAULT 0` | Display order in admin catalog and auto-suggest |
| `is_active` | `TINYINT(1) DEFAULT 1` | Soft-delete flag. Inactive items hidden from auto-suggest but preserved for existing quote references |
| `catalog_type` | `VARCHAR(20) DEFAULT 'standard'` | `standard` or `insurance` — partitions the catalog so items never cross between formats |
| `price_history` | `JSON` | Array of `{price, date}` objects (newest first) |
| `created_at` | `DATETIME` | When the item was first added |
| `updated_at` | `DATETIME` | Last modification timestamp |

**Indexes:**
- `UNIQUE` on `sku` — fast lookups by identifier, prevents duplicates
- Composite on `(catalog_type, trade_id, is_active)` — optimizes auto-suggest queries partitioned by format

**Trade term meta:**
- `_tah_trade_context` (`VARCHAR`) — `standard`, `insurance`, or `both`. Controls which format(s) a trade appears in for trade selectors and auto-suggest filtering.

**Price history format:**
```json
[
  {"price": 2200.00, "date": "2026-02-13"},
  {"price": 2100.00, "date": "2025-09-01"},
  {"price": 1950.00, "date": "2025-03-15"}
]
```
Appended automatically whenever `unit_price` changes. Useful for historical stats and honoring old prices for specific customers.

### `wp_tah_quote_groups` — Line Item Sections Within a Quote

| Column | Type | Description |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `quote_id` | `BIGINT UNSIGNED` | FK to WP post ID (quotes CPT) |
| `name` | `VARCHAR(255)` | Group heading ("Base Quote", "Finish Upgrades", etc.) |
| `description` | `TEXT NULL` | Helper text shown below heading (e.g., "Select one flooring option below") |
| `selection_mode` | `VARCHAR(20)` | `all`, `multi`, or `single` |
| `show_subtotal` | `TINYINT(1) DEFAULT 1` | Whether to render a subtotal row at the bottom of this group |
| `is_collapsed` | `TINYINT(1) DEFAULT 0` | Whether the group starts collapsed on the frontend |
| `sort_order` | `INT UNSIGNED` | Display position |

**Indexes:**
- On `quote_id` — all groups for a quote are always fetched together

**Selection mode rules for totals:**
- `all` — every item in the group is included in totals. `is_selected` is ignored.
- `multi` — only items with `is_selected = 1` are included in totals.
- `single` — exactly one item may have `is_selected = 1`. That item is included in totals; all others excluded.

**Selection modes:**

| Mode | Behavior | UI |
|---|---|---|
| `all` | All enabled items count toward total | No selection UI — all included |
| `multi` | Customer can check/uncheck items | Checkboxes |
| `single` | Customer picks exactly one | Radio buttons |

### `wp_tah_quote_line_items` — Individual Items Per Quote

| Column | Type | Description |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `quote_id` | `BIGINT UNSIGNED` | FK to quotes post ID |
| `group_id` | `BIGINT UNSIGNED` | FK to `wp_tah_quote_groups` |
| `pricing_item_id` | `BIGINT UNSIGNED NULL` | FK to catalog; `NULL` for custom/discount items |
| `item_type` | `VARCHAR(20) DEFAULT 'standard'` | `standard` or `discount` — enables distinct rendering and querying |
| `title` | `VARCHAR(255)` | Line item display name |
| `description` | `TEXT NULL` | Override of catalog description; `NULL` = use catalog default |
| `quantity` | `DECIMAL(10,2)` | Number of units |
| `unit_type` | `VARCHAR(50)` | Copied from catalog on creation, independently editable. Required for custom items (`pricing_item_id = NULL`) |
| `price_mode` | `VARCHAR(20)` | `default`, `percentage`, `addition`, `override` |
| `price_modifier` | `DECIMAL(10,2)` | Modifier value (interpretation depends on `price_mode`) |
| `resolved_price` | `DECIMAL(10,2)` | Computed effective unit price (snapshot) |
| `previous_resolved_price` | `DECIMAL(10,2) NULL` | Price before last cron update (for emails) |
| `is_selected` | `TINYINT(1) DEFAULT 1` | Whether item is selected (for `multi`/`single` groups) |
| `sort_order` | `INT UNSIGNED` | Display position within group |
| `material_cost` | `DECIMAL(10,2) NULL` | Material cost component (insurance format; NULL for standard) |
| `labor_cost` | `DECIMAL(10,2) NULL` | Labor cost component (insurance format; NULL for standard) |
| `line_sku` | `VARCHAR(100) NULL` | Display SKU on the line item (insurance format; distinct from `pricing_item_id`) |
| `tax_rate` | `DECIMAL(5,4) NULL` | Per-line tax override if different from quote-level rate (insurance format) |
| `note` | `TEXT NULL` | F9 note — renders as a detail row below the line item (insurance format) |

**Indexes:**
- On `quote_id` — fetching all items for a quote
- On `group_id` — fetching items within a group
- On `pricing_item_id` — cron lookups: "find all line items linked to this catalog item"

**Quote-level post meta:**
- `_tah_prices_resolved_at` (DATETIME) — stored on the quote post, not per line item. The cron checks if any linked pricing item's `updated_at` is newer than this timestamp to determine if recalculation is needed.
- `_tah_quote_format` (`VARCHAR`) — `standard` (default) or `insurance`. Controls metabox and template variant.
- `_tah_quote_tax_rate` (`DECIMAL(5,4) NULL`) — Sales tax percentage for insurance quotes.

---

## Price Modification Model

Each line item's price is entered via a **single inline formula field**. No menus or dropdowns — just type.

### Input Syntax

| Input | `price_mode` | Meaning | Example (catalog = $2,200) |
|---|---|---|---|
| `$` or empty | `default` | Use catalog price | → $2,200.00 |
| `$+150` | `addition` | Catalog + $150 | → $2,350.00 |
| `$-100` | `addition` | Catalog - $100 | → $2,100.00 |
| `$*1.1` | `percentage` | Catalog × 1.1 (10% up) | → $2,420.00 |
| `$*0.9` | `percentage` | Catalog × 0.9 (10% off) | → $1,980.00 |
| `1850` | `override` | Fixed price | → $1,850.00 |

### Field Behavior
- **Unfocused:** Shows the resolved price (e.g., `$2,350.00`) with a badge (DEFAULT / MODIFIED / CUSTOM)
- **Focused:** Reveals the formula (e.g., `$+150`) for editing
- **Placeholder hint:** `$ = catalog, $+50, $*1.1, or fixed amount`

### Storage Mapping
The formula is parsed into `price_mode` and `price_modifier` for the database:
- `$` → `price_mode = 'default'`, `price_modifier = 0`
- `$+150` → `price_mode = 'addition'`, `price_modifier = 150`
- `$*1.1` → `price_mode = 'percentage'`, `price_modifier = 1.1`
- `1850` → `price_mode = 'override'`, `price_modifier = 1850`

### Central Update Behavior

| `price_mode` | Central Updates? |
|---|---|
| `default` | ✅ Always current |
| `percentage` | ✅ Scales proportionally |
| `addition` | ✅ Additive adjustment preserved |
| `override` | ❌ Fixed, intentionally locked |

**Discount line items:** Supported via negative values — either `$-200` (addition-based discount) or `-200` (fixed discount).

---

## Cron: Central Price Updates & Re-engagement

A scheduled task (WP-Cron or real cron) runs periodically (configurable, **daily by default**) and:

1. Finds **all quotes** with non-overridden line items linked to catalog items whose `unit_price` has changed since `_tah_prices_resolved_at`
2. Skips quotes with an active price lock (`_tah_price_locked_until` > now)
3. Stores current `resolved_price` → `previous_resolved_price` on each affected line item
4. Recalculates `resolved_price` for each affected line item using current catalog pricing + the line item's `price_mode`/`price_modifier`
5. Sets `_tah_lock_offer_expires_at` to `now + 3 days` on quotes whose prices changed — this is the window within which the customer can click the lock link to revert to old prices
6. Updates `_tah_prices_resolved_at` on the quote
7. Logs the update (affected quote IDs, number of items recalculated) — **email sending is deferred** until the email module exists (Phase 2/3). The `previous_resolved_price` data is stored so emails can reference old vs. new pricing when ready.
8. Sets `_tah_cron_last_run`, `_tah_cron_last_status` (`success`, `partial`, or `error`), `_tah_cron_quotes_updated`, and `_tah_cron_last_errors` (array of quote IDs + error messages) as WP options

> [!NOTE]
> Totals (line totals, group subtotals, grand total) are **computed on-the-fly** at render time — `SUM(quantity × resolved_price)` per group. No stored totals to invalidate.

### Price Locking
- Link format: `?action=tah_lock_price&quote_id=123&token=xyz` (token = `wp_hash(quote_id . post_date)` — simple hash, not cryptographic; worst case is a free price lock)
- **Lock offer window:** The link is only valid while `_tah_lock_offer_expires_at > now`. This meta is set by the cron to `now + 3 days` when prices change. If the customer clicks after this window, they see the "offer expired" message.
- Clicking the link (within the window) **reverts** `resolved_price` to `previous_resolved_price` for all affected line items on the quote, honoring the pre-update prices
- Sets `_tah_price_locked_until` (post meta) to `_tah_lock_offer_expires_at` (the remaining time in the offer window)
- While locked, the cron skips this quote — prices stay at the reverted (old) values
- After lock expires and quote is not accepted, the next cron run recalculates all prices at whatever the current catalog prices are
- Formula relationships (`$+150`, `$*1.1`) are preserved throughout — no data is lost
- If clicked after `_tah_lock_offer_expires_at`: friendly message explaining the offer expired, with link to current quote

### WP-Cron Reliability
- WP-Cron is triggered by site visits and is unreliable on low-traffic sites. For production, configure a **real system crontab** (`wp cron event run --due-now`) to ensure consistent execution.
- The pricing cron should be idempotent — running it twice produces the same result (already satisfied by the `_tah_prices_resolved_at` check).

---

## Global Pricing Settings

Stored as WP options (e.g., under a "Pricing" settings page or a section in the theme settings).

| Setting | Default | Description |
|---|---|---|
| `tah_price_rounding` | `5` | Round resolved prices to nearest multiple (MROUND). Set to `1` for no rounding, `5` for nearest $5, `10` for nearest $10, etc. |
| `tah_price_rounding_direction` | `nearest` | `nearest`, `up`, or `down` |
| `tah_cron_frequency` | `daily` | How often the price update cron runs. Options: `hourly`, `daily`, `weekly` |

> [!NOTE]
> Quote expiry settings (`tah_quote_expiry_enabled`, `tah_quote_expiry_days`) are **deferred to Phase 2**. Expiry behavior (frontend banner, prevent acceptance, status change) has not been designed yet.

Rounding is applied to the **resolved price** after all formula computation. Example with `tah_price_rounding = 5`:
- `$2,200 * 1.03` = $2,266 → **$2,265** (nearest 5)
- `$2,200 + 147` = $2,347 → **$2,345** (nearest 5)

---

## Admin UX

### Line Items Metabox
- Similar in style to the existing `TAH_Quote_Sections` panel
- **Add groups** with a name and selection mode
- **Add line items** within each group via auto-suggest from the pricing catalog
- **Drag-and-drop reorder** items and groups
- **Inline formula price field** per item (`$`, `$+50`, `$*1.1`, or fixed amount)
- Auto-calculated totals per group and grand total (computed on-the-fly, not stored)
- Visual badges showing price mode (DEFAULT / MODIFIED / CUSTOM) consistent with existing section badges

### Pricing Catalog Admin
- Dedicated admin page for managing the central pricing table
- Add/edit/delete catalog items
- Trade assignment
- Price history visible per item

### Auto-Suggest
- When typing a new line item, the input queries the pricing catalog and suggests matches
- Filtered by the quote's assigned trade (with universal items always shown)
- Items from other trades can still be added — the trade filter is a convenience, not a restriction
- Selecting a suggestion populates title, description, unit_type, and unit_price

### Trade Pricing Presets

> [!NOTE]
> Trade pricing presets apply to **standard-format quotes only**. Insurance quotes use a flat item list with no groups, so presets are not applicable.

Stored as JSON on trade term meta (key: `_tah_trade_pricing_preset`), consistent with how `_tah_trade_default_sections` already works for info sections.

**Preset structure:**
```json
{
  "groups": [
    {
      "name": "Base Quote",
      "selection_mode": "all",
      "show_subtotal": true,
      "is_collapsed": false,
      "items": [
        {"pricing_item_sku": "demo_existing", "quantity": 1},
        {"pricing_item_sku": "prep_allowance", "quantity": 1},
        {"pricing_item_sku": "sand_finish_clear", "quantity": 1},
        {"pricing_item_sku": "waste_disposal", "quantity": 1}
      ]
    },
    {
      "name": "Optional Items and Finish Upgrades",
      "selection_mode": "multi",
      "show_subtotal": false,
      "is_collapsed": false,
      "items": [
        {"pricing_item_sku": "switch_to_stain", "quantity": 1},
        {"pricing_item_sku": "high_traffic_finish", "quantity": 1},
        {"pricing_item_sku": "four_coat_system", "quantity": 1}
      ]
    }
  ]
}
```

**Behavior:**
- Groups and line items populate **instantly when a trade is selected** (via JS, same as info sections)
- The preset is a starting point — after population, the quote owns its data and can be freely modified
- Preset updates do not affect existing quotes, only new trade selections
- Admin: Trade edit screen gets a preset builder UI for configuring default groups and items

---

## Frontend Rendering

The `single-quotes.php` template renders pricing data differently based on `_tah_quote_format`.

### Standard Format

#### Phase 1 (Static)
- Post content renders **first** (intro text, project notes, etc.), then pricing module tables below
- Each group renders as its own section with heading and table
- All groups render statically — salesperson configures `is_selected` in admin before sharing the link
- Groups with `show_subtotal = true` display a subtotal row
- Groups with `is_collapsed = true` render collapsed by default — only the group heading and subtotal are visible, with a "Show details" toggle to expand
- Base Quote group shows its own total; grand total at the bottom includes base + selected optional items
- `description` field rendered as expandable detail per line item if present
- No customer-facing interactivity — what you see is what was configured

#### Phase 2 (Interactive)
- `multi` groups: items with checkboxes, customer can toggle selections
- `single` groups: items with radio buttons, customer picks one
- Grand total updates live via JS as selections change

### Insurance Format

- Renders as a single Xactimate-style table — no group headings
- Columns: **#** (row number), **Description**, **SKU** (optional), **Qty**, **Unit Price**, **Tax**, **Total**
- Unit price optionally shows material + labor breakdown
- F9 notes render as indented detail rows below their parent line item
- Subtotal, tax total, and grand total rows at bottom
- Trade categories can be used as visual section dividers within the table

---

## Failure Handling

### Catalog Item Deletion
- **No hard delete.** The admin UI only offers deactivation via `is_active`. Deactivated items are hidden from auto-suggest but preserved for existing quote references.
- If a hard delete is ever needed (e.g., item created by mistake), it is only allowed when zero line items reference the `pricing_item_id`. Otherwise the operation is blocked.

### Cron Failure Mid-Run
- Each quote is processed **independently** with error handling. If one quote fails, the rest still complete.
- `_tah_prices_resolved_at` only updates on a successful per-quote recalculation, so failed quotes are automatically retried on the next cron run.
- Failures are logged to `_tah_cron_last_errors` (WP option) for admin visibility.

### Price Lock Link — Expired or Already Updated
- If a customer clicks the "lock price" link after the offer has expired or prices have already been updated, show a friendly message: *"This offer has expired. Your quote has been updated with current pricing."*
- Include a link to view the current quote with updated totals.

### Trade Preset — Missing SKU
- If a preset references a `pricing_item_sku` that no longer exists in the catalog, **skip the missing item** and populate the rest.
- Show an admin notice: *"2 preset items were skipped because they no longer exist in the catalog. Update the [Trade Name] preset to fix this."*

---

## Observability

### Cron Run Status
Stored as WP options, displayed on the Pricing settings page:

| Option | Description |
|---|---|
| `_tah_cron_last_run` | Timestamp of last cron execution |
| `_tah_cron_last_status` | `success`, `partial` (some quotes failed), or `error` |
| `_tah_cron_quotes_updated` | Number of quotes updated in last run |
| `_tah_cron_last_errors` | Array of quote IDs + error messages from last run (if any) |

Settings page shows at a glance: *"Last run: 2 hours ago • 12 quotes updated • Status: OK"*

### Admin Quotes List — Pricing Status Column
- Custom column in the WP quotes list table showing pricing status per quote:
  - **Current** — all prices match catalog
  - **Updated Feb 14** — prices were recalculated by cron on this date
  - **Price locked until Feb 17** — customer locked pricing
- Column is sortable and filterable

### Catalog Edit — Impact Preview (Phase 1)
- When editing a pricing item's `unit_price`, show a count: *"Changing this price will affect 23 active quotes."*
- Full impact report with old vs. new totals per quote deferred to Phase 2.

---

## Maintenance & Cleanup

### Cascade Deletes
- When a quote post is **permanently deleted** (not just trashed), a `before_delete_post` hook removes all associated rows from `wp_tah_quote_groups` and `wp_tah_quote_line_items`.
- When a group is deleted from a quote, all its line items are deleted too.

### Schema Versioning
- Store `tah_pricing_db_version` as a WP option.
- On theme activation / `admin_init`, compare against current version. If outdated, run upgrade SQL via `dbDelta()` (standard WP custom table pattern).
- Maps directly to Laravel migrations when porting.

### Boundary Conditions
- **No hard limits** on groups per quote or items per group. The UI self-regulates — practical quotes have 2–5 groups and 5–20 items each.
- **Price history JSON grows indefinitely.** Even with monthly price changes for 10 years, that's ~120 entries per item (a few KB). No pruning needed. Revisit only if an item somehow accumulates thousands of changes.

---

## Artifact Lifecycle

Quick reference for which operations create, read, update, and delete each entity.

| Artifact | Create | Read | Update | Delete |
|---|---|---|---|---|
| **Pricing Item** | Admin catalog page | Auto-suggest, cron, quote render | Admin catalog page (triggers `price_history` append) | Soft-delete only (`is_active = 0`); hard delete blocked if referenced |
| **Quote Group** | Trade preset trigger, manual add | Quote render, admin metabox | Admin metabox (name, mode, order) | Cascade on quote delete, manual remove |
| **Quote Line Item** | Trade preset trigger, manual add | Quote render, admin metabox, cron | Admin metabox (formula, qty, selection), cron (`resolved_price`) | Cascade on group/quote delete, manual remove |

---

## Portability to Laravel/Next.js

- All three tables are plain SQL with no WP-specific structures (no `post_meta`, no serialized arrays)
- Business logic lives in a thin service/repository class wrapping `$wpdb` — swap for Eloquent models in Laravel
- JSON `price_history` column works in MySQL, MariaDB, and Postgres
- Frontend rendering logic can be extracted to Blade/React templates with identical data contracts

---

## Resolved Decisions

1. **Same item, different trades** — One catalog row per trade. Users can also create custom universal rows as needed.

2. **Group and item defaults per trade** — Trade presets define default groups **and** default line items within those groups. The preset JSON includes SKU references and quantities per item.

3. **Quantity handling** — Most quotes use quantity × unit price across multiple line items, tallied in a total at the bottom. Totals = sum of (qty × resolved_price) per group.

4. **Re-engagement email template** — Editable HTML/CSS email templates with variables (customer name, quote link, old/new totals) are deferred to **Phase 2/3** after email module is set up. Price locking uses a quote-level `_tah_price_locked_until` post meta — no data is overwritten.

5. **Cron frequency** — Configurable via settings. **Daily by default.**

6. **Multi/single group interaction** — **Phase 1:** Salesperson configures selections (`is_selected`) in admin; frontend renders statically. **Phase 2:** Customer-facing JS interactivity with live total updates.

7. **Grand total display** — Base Quote shows its own subtotal. Grand total = base total + selected optional items. No tax handling needed (built into unit prices). **Phase 1** is static; **Phase 2** adds interactive total updates.

8. **Render order** — Post content first (as intro), then pricing module tables below.

---

## Known Debt / Future Phases

1. **Multi-trade quotes (Phase 2+)** — Currently quotes are single-trade. The pricing module allows adding items from any trade, but proper multi-trade quotes with merged info sections are deferred to the Laravel/Next.js rebuild.

2. **Interactive frontend selections (Phase 2)** — Customer-facing checkboxes/radio buttons with live grand total JS updates.

3. **Email templates module (Phase 2/3)** — Editable HTML/CSS email templates with variable substitution for re-engagement emails and other notifications.

4. **Quote duplication / templates (Phase 2)** — Duplicate existing quotes as starting points for new ones.

5. **Customer-facing accept/decline (Phase 2)** — Accept button that locks the quote, records acceptance timestamp, triggers notifications.

6. **Price components for standard quotes (Future)** — The `material_cost` and `labor_cost` columns exist on all line items but are only used by insurance quotes initially. Standard quotes will adopt price components in a future phase.
