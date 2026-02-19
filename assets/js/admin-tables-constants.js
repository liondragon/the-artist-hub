(function (window) {
    'use strict';

    var namespace = window.TAHAdminTables = window.TAHAdminTables || {};
    var runtime = window.TAHAdminTablesRuntimeConstants || {};
    var runtimeWidths = runtime.widths || {};
    var runtimeSaveBounds = runtimeWidths.saveBounds || {};
    var runtimeSavedSanity = runtimeWidths.savedSanity || {};
    var hasRuntimeWidths = Number.isFinite(Number(runtimeWidths.minPx))
        && Number.isFinite(Number(runtimeWidths.normalizeEpsilonPx))
        && Number.isFinite(Number(runtimeSaveBounds.maxFloorPx))
        && Number.isFinite(Number(runtimeSaveBounds.maxFactor))
        && Number.isFinite(Number(runtimeSaveBounds.fallbackMaxPx))
        && Number.isFinite(Number(runtimeSavedSanity.minFactor))
        && Number.isFinite(Number(runtimeSavedSanity.maxFactor));

    if (!hasRuntimeWidths) {
        console.error('TAH Admin Tables: Missing runtime constants for width bounds.');
        return;
    }

    namespace.Constants = Object.freeze({
        widths: {
            minPx: Number(runtimeWidths.minPx),
            normalizeEpsilonPx: Number(runtimeWidths.normalizeEpsilonPx),
            saveBounds: {
                maxFloorPx: Number(runtimeSaveBounds.maxFloorPx),
                maxFactor: Number(runtimeSaveBounds.maxFactor),
                fallbackMaxPx: Number(runtimeSaveBounds.fallbackMaxPx)
            },
            savedSanity: {
                minFactor: Number(runtimeSavedSanity.minFactor),
                maxFactor: Number(runtimeSavedSanity.maxFactor)
            }
        },
        store: {
            saveDebounceMs: 180
        },
        sort: {
            dragDistancePx: 5,
            dragOpacity: 0.9,
            helperZIndex: 9999
        }
    });
})(window);
