#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${TAH_E2E_ENV_FILE:-tests/e2e/.env}"
PREFER_SHELL_ENV="${TAH_E2E_PREFER_SHELL_ENV:-0}"
if [[ -f "$ENV_FILE" ]]; then
  PRESET_TAH_E2E_BASE_URL="${TAH_E2E_BASE_URL:-}"
  PRESET_TAH_E2E_USERNAME="${TAH_E2E_USERNAME:-}"
  PRESET_TAH_E2E_PASSWORD="${TAH_E2E_PASSWORD:-}"
  PRESET_TAH_E2E_QUOTE_ID="${TAH_E2E_QUOTE_ID:-}"
  PRESET_TAH_E2E_RETRIES="${TAH_E2E_RETRIES:-}"
  PRESET_TAH_E2E_HEADED="${TAH_E2E_HEADED:-}"
  PRESET_TAH_E2E_SKIP_BROWSER_INSTALL="${TAH_E2E_SKIP_BROWSER_INSTALL:-}"

  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a

  if [[ "$PREFER_SHELL_ENV" == "1" ]]; then
    [[ -n "$PRESET_TAH_E2E_BASE_URL" ]] && export TAH_E2E_BASE_URL="$PRESET_TAH_E2E_BASE_URL"
    [[ -n "$PRESET_TAH_E2E_USERNAME" ]] && export TAH_E2E_USERNAME="$PRESET_TAH_E2E_USERNAME"
    [[ -n "$PRESET_TAH_E2E_PASSWORD" ]] && export TAH_E2E_PASSWORD="$PRESET_TAH_E2E_PASSWORD"
    [[ -n "$PRESET_TAH_E2E_QUOTE_ID" ]] && export TAH_E2E_QUOTE_ID="$PRESET_TAH_E2E_QUOTE_ID"
    [[ -n "$PRESET_TAH_E2E_RETRIES" ]] && export TAH_E2E_RETRIES="$PRESET_TAH_E2E_RETRIES"
    [[ -n "$PRESET_TAH_E2E_HEADED" ]] && export TAH_E2E_HEADED="$PRESET_TAH_E2E_HEADED"
    [[ -n "$PRESET_TAH_E2E_SKIP_BROWSER_INSTALL" ]] && export TAH_E2E_SKIP_BROWSER_INSTALL="$PRESET_TAH_E2E_SKIP_BROWSER_INSTALL"
  fi
fi

LIST_ONLY=0
for arg in "$@"; do
  if [[ "$arg" == "--list" ]]; then
    LIST_ONLY=1
    break
  fi
done

if [[ ! -d "tests/e2e/node_modules/@playwright/test" ]]; then
  echo "Installing Playwright test dependency in tests/e2e..."
  npm --prefix tests/e2e install --no-fund --no-audit
fi

if [[ "$LIST_ONLY" == "1" ]]; then
  echo "Listing columns smoke tests"
  npx --prefix tests/e2e playwright test \
    -c tests/e2e/playwright.config.js \
    tests/e2e/quote-pricing-columns.spec.js \
    "$@"
  exit 0
fi

if [[ -z "${TAH_E2E_BASE_URL:-}" ]]; then
  export TAH_E2E_BASE_URL="https://wphub.local"
fi

if [[ -z "${TAH_E2E_USERNAME:-}" ]]; then
  echo "Missing env var: TAH_E2E_USERNAME" >&2
  exit 1
fi

if [[ -z "${TAH_E2E_PASSWORD:-}" ]]; then
  echo "Missing env var: TAH_E2E_PASSWORD" >&2
  exit 1
fi

if [[ -z "${TAH_E2E_QUOTE_ID:-}" ]]; then
  echo "Missing env var: TAH_E2E_QUOTE_ID" >&2
  exit 1
fi

if [[ "${TAH_E2E_SKIP_BROWSER_INSTALL:-0}" != "1" ]]; then
  echo "Ensuring Chromium browser is installed..."
  npx --prefix tests/e2e playwright install chromium
fi

echo "Running columns smoke against ${TAH_E2E_BASE_URL} (quote ${TAH_E2E_QUOTE_ID}, user ${TAH_E2E_USERNAME})"

npx --prefix tests/e2e playwright test \
  -c tests/e2e/playwright.config.js \
  tests/e2e/quote-pricing-columns.spec.js \
  "$@"
