# AGENTS_LOCAL.md

## Operator Overrides
- Communication: keep responses concise, factual, and explicit about what is verified vs inferred.

## Risk Callouts
- For load-bearing UI paths, separate structural refactors from behavior changes unless explicitly requested together.
- On unstable UI interaction work, use a fixed smoke matrix after each patch: load → resize → reorder → refresh.
- If two consecutive regressions appear in the same area, stop patching guards and simplify ownership boundaries first.

## Confirmation Gates
- Before long E2E runs, prefer quick syntax/list checks first and then run full smoke once env is ready.
- Preferred columns smoke command: `bash tests/e2e/run-columns-smoke.sh`.
- For E2E setup, use isolated harness bootstrap (`npm --prefix tests/e2e install`) and avoid root dependency changes unless requested.

## Quick Links
