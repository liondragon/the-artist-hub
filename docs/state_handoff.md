# State Handoff Notes

## Quote Sections Sync/Reset (Phase 1)

- Clobber-avoidance rule: `TAH_Quote_Sections::save_quote_sections()` only updates meta fields when the corresponding request fields are present. Missing fields do not overwrite stored values.
- Sync/Reset isolation rule: when `tah_quote_sections_action` is `sync` or `reset`, save handling short-circuits after updating `_tah_quote_sections_order`. Per-section meta (`_tah_section_{key}_enabled`, `_tah_section_{key}_mode`, `_tah_section_{key}_content`) is not mutated or deleted by Sync/Reset.
- Sync behavior: additive only; missing preset keys are appended to current order.
- Reset behavior: destructive for order only; resulting order mirrors current trade preset exactly.

## Trade Preset Ordering (Phase 2)

- Ordering persistence strategy: Trade Presets UI is a sortable list (`.tah-trade-sections-sortable`) with checkbox rows. Dragging changes DOM order; checked items are submitted in that order.
- Serialization format: selected keys are saved in `_tah_trade_default_sections` as an indexed PHP array (`array_values(array_unique(...))`) preserving submitted order after filtering unknown keys.
- Determinism: saved order is rendered first on subsequent edits, with any remaining library keys appended afterward.
