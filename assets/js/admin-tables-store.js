(function ($, window) {
    'use strict';

    var namespace = window.TAHAdminTables = window.TAHAdminTables || {};
    var constants = namespace.Constants;
    if (!constants || !constants.store) {
        console.error('TAH Admin Tables: Missing constants module for store.');
        return;
    }
    var storeConstants = constants.store;

    namespace.Store = {
        normalizeKeyPart: function (value) {
            return String(value || '')
                .toLowerCase()
                .replace(/[^a-z0-9_-]/g, '');
        },

        getPrefContextKey: function (core, tableKey, variant) {
            var normalizedTableKey = this.normalizeKeyPart(tableKey);
            var normalizedVariant = this.normalizeKeyPart(variant);
            return String(core.config.screenId || '') + '|' + normalizedTableKey + '|' + normalizedVariant;
        },

        clearPendingSaveForContext: function (core, contextKey) {
            if (!contextKey) {
                return;
            }

            var saveState = core.saveState || {};
            if (saveState.timers && saveState.timers[contextKey]) {
                window.clearTimeout(saveState.timers[contextKey]);
                delete saveState.timers[contextKey];
            }
            if (saveState.pending && saveState.pending[contextKey]) {
                delete saveState.pending[contextKey];
            }
        },

        dispatchSaveRequest: function (core, contextKey, pending) {
            var onSuccess = pending && typeof pending.onSuccess === 'function' ? pending.onSuccess : null;
            var onError = pending && typeof pending.onError === 'function' ? pending.onError : null;
            var normalizedTableKey = this.normalizeKeyPart(pending.tableKey);
            var normalizedVariant = this.normalizeKeyPart(pending.variant);

            $.post(core.config.config.ajaxUrl || ajaxurl, {
                action: 'tah_save_table_prefs',
                nonce: core.config.nonce,
                screen_id: core.config.screenId,
                table_key: normalizedTableKey,
                variant: normalizedVariant,
                widths: pending.payload.widths,
                order: pending.payload.order
            }).done(function (response) {
                if (!response || response.success !== true) {
                    if (onError) {
                        onError(response);
                    }
                    return;
                }

                if (!core.config.config.prefs || typeof core.config.config.prefs !== 'object') {
                    core.config.config.prefs = {};
                }
                var prefKey = normalizedVariant ? normalizedTableKey + ':' + normalizedVariant : normalizedTableKey;
                var currentPref = core.config.config.prefs[prefKey] && typeof core.config.config.prefs[prefKey] === 'object'
                    ? core.config.config.prefs[prefKey]
                    : {};
                core.config.config.prefs[prefKey] = $.extend({}, currentPref, {
                    widths: pending.payload.widths,
                    order: pending.payload.order
                });

                core.saveState.payloadHashes[contextKey] = pending.payloadHash;
                if (onSuccess) {
                    onSuccess();
                }
            }).fail(function () {
                if (onError) {
                    onError();
                }
            });
        },

        flushPendingSaveForContext: function (core, contextKey) {
            if (!contextKey) {
                return;
            }

            var saveState = core.saveState || {};
            if (!saveState.pending || !saveState.pending[contextKey]) {
                this.clearPendingSaveForContext(core, contextKey);
                return;
            }

            var pending = saveState.pending[contextKey];
            this.clearPendingSaveForContext(core, contextKey);
            this.dispatchSaveRequest(core, contextKey, pending);
        },

        getAllowedColumnKeyMap: function ($table) {
            var allowedMap = {};
            $table.find('thead th[data-tah-col]').each(function () {
                var key = String($(this).attr('data-tah-col') || '');
                if (key !== '') {
                    allowedMap[key] = true;
                }
            });
            return allowedMap;
        },

        getEffectiveColumnWidth: function ($table, $col, key) {
            var interaction = namespace.Interaction;
            var $th = $table.find('thead th').filter(function () {
                return String($(this).attr('data-tah-col') || '') === String(key || '');
            }).first();
            var interactionWidth = interaction.getColumnWidth($table, $col, $th);
            if (Number.isFinite(interactionWidth) && interactionWidth > 0) {
                return interactionWidth;
            }

            var colEl = $col.get(0);
            if (colEl && typeof colEl.getBoundingClientRect === 'function') {
                var rectWidth = colEl.getBoundingClientRect().width;
                if (Number.isFinite(rectWidth) && rectWidth > 0) {
                    return rectWidth;
                }
            }

            var cssWidth = parseFloat($col.css('width'));
            if (Number.isFinite(cssWidth) && cssWidth > 0) {
                return cssWidth;
            }

            var inlineWidth = colEl && colEl.style && colEl.style.width ? String(colEl.style.width) : '';
            var parsedInlineWidth = parseFloat(inlineWidth);
            if (Number.isFinite(parsedInlineWidth) && parsedInlineWidth > 0) {
                return parsedInlineWidth;
            }

            return 0;
        },

        buildSafePrefsPayload: function ($table) {
            var self = this;
            var allowedMap = this.getAllowedColumnKeyMap($table);
            var tableConfig = $table.data('tah-table-config') || {};
            var interaction = namespace.Interaction;
            var widths = {};
            var order = [];
            var seenOrderKeys = {};

            $table.find('colgroup col').each(function () {
                var key = String($(this).attr('data-col-key') || '');
                if (!key || !allowedMap[key]) {
                    return;
                }
                if (interaction.isColumnNonResizable(tableConfig, key)) {
                    return;
                }
                if (!interaction.isColumnVisible(tableConfig, key)) {
                    return;
                }

                var $col = $(this);
                var width = self.getEffectiveColumnWidth($table, $col, key);
                if (!Number.isFinite(width)) {
                    return;
                }

                var minWidth = interaction.getColumnMinWidthPx(tableConfig, key);
                var maxWidth = interaction.getColumnMaxWidthPx($table, tableConfig, key, minWidth);
                if (maxWidth < minWidth) {
                    maxWidth = minWidth;
                }

                var rounded = Math.round(width);
                if (rounded < minWidth || rounded > maxWidth) {
                    return;
                }

                widths[key] = rounded;
            });

            $table.find('thead th').each(function () {
                var key = String($(this).attr('data-tah-col') || '');
                if (!key || !allowedMap[key] || seenOrderKeys[key]) {
                    return;
                }
                seenOrderKeys[key] = true;
                order.push(key);
            });

            return {
                widths: widths,
                order: order
            };
        },

        normalizePayloadOverrides: function (overrides) {
            if (!overrides || typeof overrides !== 'object') {
                return null;
            }

            var normalized = {};
            if (Object.prototype.hasOwnProperty.call(overrides, 'widths')) {
                normalized.widths = (overrides.widths && typeof overrides.widths === 'object' && !Array.isArray(overrides.widths))
                    ? overrides.widths
                    : {};
            }

            if (Object.prototype.hasOwnProperty.call(overrides, 'order')) {
                normalized.order = Array.isArray(overrides.order) ? overrides.order : [];
            }

            return normalized;
        },

        savePrefs: function (core, tableKey, variant, $table, payloadOverrides, callbacks) {
            var payload = this.buildSafePrefsPayload($table);
            var overrides = this.normalizePayloadOverrides(payloadOverrides);
            var normalizedTableKey = this.normalizeKeyPart(tableKey);
            var normalizedVariant = this.normalizeKeyPart(variant);
            if (overrides) {
                if (Object.prototype.hasOwnProperty.call(overrides, 'widths')) {
                    payload.widths = overrides.widths;
                }
                if (Object.prototype.hasOwnProperty.call(overrides, 'order')) {
                    payload.order = overrides.order;
                }
            }

            var onSuccess = callbacks && typeof callbacks.onSuccess === 'function' ? callbacks.onSuccess : null;
            var onError = callbacks && typeof callbacks.onError === 'function' ? callbacks.onError : null;
            var contextKey = $table.data('tah-pref-context-key') || this.getPrefContextKey(core, normalizedTableKey, normalizedVariant);
            var payloadHash = JSON.stringify(payload);

            if (core.saveState.payloadHashes[contextKey] === payloadHash) {
                if (onSuccess) {
                    onSuccess();
                }
                return;
            }

            this.clearPendingSaveForContext(core, contextKey);

            core.saveState.pending[contextKey] = {
                tableKey: normalizedTableKey,
                variant: normalizedVariant,
                payload: payload,
                payloadHash: payloadHash,
                onSuccess: onSuccess,
                onError: onError
            };

            core.saveState.timers[contextKey] = window.setTimeout(function () {
                var pending = core.saveState.pending[contextKey];
                if (!pending) {
                    delete core.saveState.timers[contextKey];
                    return;
                }
                delete core.saveState.timers[contextKey];
                delete core.saveState.pending[contextKey];
                namespace.Store.dispatchSaveRequest(core, contextKey, pending);
            }, storeConstants.saveDebounceMs);
        }
    };
})(jQuery, window);
