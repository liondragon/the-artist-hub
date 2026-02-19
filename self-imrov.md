# Self Improvement Log

## 2026-02-17

- When multiple mechanisms interact (state, layout, events, persistence), define ownership and a smoke-check matrix before patching.
- Use one-hypothesis-per-patch in unstable areas; bundled fixes hide causality and increase regression loops.
- After two consecutive regressions in the same area, stop and simplify the control flow instead of adding more guards.
- For UI layout systems, keep an explicit runtime state object (hidden widths, visible boundaries) and derive render classes from it; avoid storing transient layout state in scattered DOM data keys.
- For preference persistence, enforce key allow-lists and sane numeric bounds on both client and server; debounce writes to avoid noisy stop-event bursts.
- When extracting a reusable feature module, move context mapping and table registration behind explicit hooks and keep module-owned instances (avoid `$GLOBALS` cross-module coupling).
- For JS E2E in non-Node repos, keep an isolated `tests/e2e` package manifest and run tools with `--prefix` to avoid transient `npx` module-resolution failures.
- For large client-side refactors, do extraction-only first (delegate existing methods to new modules) and keep behavior guarded by existing smoke tests before any logic cleanup.
- Promote recurring execution lessons into reusable guides (coding + stack docs) once they repeat across tasks, but keep wording framework-agnostic unless the guide is stack-specific.
- When splitting client modules, use one shared namespace and a single boot-time dependency assertion; avoid scattered runtime no-op guards that hide wiring failures.
- In jQuery iterator callbacks, do not call module methods via `this`; capture `self` (or use explicit module refs) to avoid accidental context drift bugs.
- When adding new sizing constraints, centralize min/max helpers and reuse them in UI interaction, client persistence, and server sanitization so bounds cannot drift.
- Prefer one canonical config shape at module boundaries; mirroring the same data into alternate adapter objects creates drift and unnecessary branching in both JS and PHP.
- For refactor-heavy tasks, report both gross additions and gross deletions (including deleted files) so net simplification is visible and not mistaken for growth.
- When full legacy test runners have known unrelated failures, run and report a focused verification slice for touched surfaces in parallel with the legacy runner result.
- Eliminate legacy compatibility fallbacks once a markup/config contract is enforced (for example, no `id` fallback when `data-*` keys are required); dual paths silently reintroduce drift and patchwork.
- Avoid core pass-through wrappers that only forward calls to submodules; call module APIs directly at the callsite and keep only lifecycle/stateful helpers in core.
- When a table contract is consumed by PHP config, PHP markup, JS builders, and fixtures, keep one canonical source and derive all consumers from it; independent schema copies create high-friction drift and fragile patch seams.
- For WordPress AJAX flows, never treat HTTP 200 as success by itself; require explicit `response.success === true` before caching dedupe hashes or surfacing saved-state UX.
- If runtime disables an interaction due to temporary contract invalidity, add an explicit recovery path when the contract becomes valid again; disable-only flows create sticky degradation.
- For resize UX bugs, lock down behavior with focused E2E artifacts first (active-column-only, divider tracking, utility invariants) before changing math/layout code; this prevents speculative CSS/JS churn.
- When runtime reinitializes UI from localized config, update the in-memory prefs snapshot on successful saves (and flush pending debounced writes on destroy/reinit) or variant/layout toggles will silently revert recent edits.
- Use per-instance event namespaces for document/window bindings in multi-table screens; global namespace teardown causes cross-table interaction loss during reinit/destroy cycles.
- Favor behavioral JS tests (executing module code in a sandbox) over source-regex assertions for dependency guards and AJAX success/error handling; static checks miss runtime regressions.
- For hidden-on-init table UIs (for example collapsed WordPress postboxes), never finalize frame/column widths from a zero-width container; defer normalization until container width stabilizes across animation frames.
- Static/contract JS tests cannot catch pixel-level layout drift; add browser-level E2E assertions for width stability across visibility transitions (collapse/refresh/reopen).
- When multiple lifecycle events share table refresh work (`table_added`, `table_row_added`, `table_layout_changed`), keep one shared refresh pipeline and pass explicit options instead of duplicating scan/sync branches in each handler.
- Keep server-owned numeric safety limits (for example width caps used in sanitization) localized into JS at bootstrap to prevent PHP/JS constant drift on load-bearing paths.
- For load-bearing constants consumed by multiple runtime modules, prefer fail-closed bootstrap (explicit error + no module export) over silent fallback literals that hide wiring/config drift.
- When table markup contracts depend on ordered column keys, render both headers and row cells from the same column-key source to avoid drift between parallel hardcoded lists.
