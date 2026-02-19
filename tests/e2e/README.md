# E2E Smoke Tests: Quote Pricing Columns

These tests provide minimal automated interaction coverage for the admin pricing table flow:

- load
- resize
- reorder
- refresh (persistence check)
- deterministic reset of stored column prefs before each variant flow

They run both quote variants:

- `standard`
- `insurance`

## Prerequisites

1. Local WordPress site running (default: `https://wphub.local`)
2. A quote post ID that can be edited in wp-admin
3. Admin credentials for the local site
4. Node + npm available (runner bootstraps Playwright under `tests/e2e/`)

## Required Environment Variables

- `TAH_E2E_BASE_URL` (optional, default `https://wphub.local`)
- `TAH_E2E_USERNAME`
- `TAH_E2E_PASSWORD`
- `TAH_E2E_QUOTE_ID`

If required env vars are missing, tests are skipped.

You can also put these in `tests/e2e/.env` (or point `TAH_E2E_ENV_FILE` to a different file); the runner script auto-loads it.

## Run

From repo root:

```bash
npm --prefix tests/e2e install
npx --prefix tests/e2e playwright test -c tests/e2e/playwright.config.js tests/e2e/quote-pricing-columns.spec.js
```

Wrapper script:

```bash
bash tests/e2e/run-columns-smoke.sh
```

Pass through Playwright args when needed:

```bash
bash tests/e2e/run-columns-smoke.sh --headed --grep "standard format"
```

Optional runtime controls:

- `TAH_E2E_RETRIES` (default `0` local, `2` in CI)
- `TAH_E2E_HEADED=1` (force headed mode)
- `TAH_E2E_SKIP_BROWSER_INSTALL=1` (skip browser install check)

To open the interactive report:

```bash
npx --prefix tests/e2e playwright show-report
```
